<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\PackRequiredException;
use App\Http\Controllers\Controller;
use App\Http\Resources\RecipientResource;
use App\Models\Event;
use App\Models\Invitation;
use App\Models\Recipient;
use App\Services\PhoneNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RecipientController extends Controller
{
    public function index(Event $event): AnonymousResourceCollection
    {
        $invitation = $event->invitation ?? abort(404);

        $invitation->setRelation('event', $event);
        $recipients = $invitation->recipients()->with('appUser:id,phone_hash')->orderBy('id')->get();
        $recipients->each->setRelation('invitation', $invitation);

        return RecipientResource::collection($recipients);
    }

    /**
     * Numaraları normalize eder, tekrarları atlar. Sınır aşılırsa hiçbiri eklenmez (spec §6.4).
     */
    public function store(Request $request, Event $event, PhoneNumber $phones): JsonResponse
    {
        $invitation = $event->invitation ?? abort(404);

        $data = $request->validate([
            'recipients' => ['required', 'array', 'min:1', 'max:'.config('zarf.max_recipient_limit')],
            'recipients.*.phone' => ['required', 'string', 'max:30'],
            'recipients.*.name' => ['nullable', 'string', 'max:100'],
        ], [], [
            'recipients' => 'davetliler',
            'recipients.*.phone' => 'telefon numarası',
            'recipients.*.name' => 'ad',
        ]);

        // Geçersiz numaralar satır bazında raporlanır.
        $incoming = [];
        $errors = [];
        foreach ($data['recipients'] as $i => $row) {
            $phone = $phones->tryNormalize($row['phone']);

            if ($phone === null) {
                $errors["recipients.{$i}.phone"] = PhoneNumber::INVALID_MESSAGE;

                continue;
            }

            $incoming[$phone] ??= trim((string) ($row['name'] ?? '')) ?: null;
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        $added = DB::transaction(function () use ($invitation, $event, $incoming, $phones) {
            // Aynı anda gelen iki istek sınırı birlikte aşamasın.
            Invitation::whereKey($invitation->id)->lockForUpdate()->first();

            $existing = $invitation->recipients()->whereIn('phone', array_keys($incoming))->pluck('phone')->all();
            $new = array_diff_key($incoming, array_flip($existing));
            $total = $invitation->recipients()->count() + count($new);

            if ($total > $event->recipientLimit()) {
                if (! $event->hasPack()) {
                    throw new PackRequiredException(
                        'Ücretsiz sürümde en fazla '.config('zarf.free_recipient_limit').' davetli eklenebilir.',
                    );
                }

                throw ValidationException::withMessages([
                    'recipients' => 'Bir davetiyeye en fazla '.config('zarf.max_recipient_limit').' davetli eklenebilir.',
                ]);
            }

            return $invitation->recipients()->createMany(collect($new)->map(fn (?string $name, string $phone) => [
                'phone' => $phone,
                'phone_hash' => $phones->hash($phone),
                'name' => $name,
                'token' => Str::random(40),
            ])->values()->all());
        });

        $added->load('appUser:id,phone_hash');

        return response()->json([
            'added' => RecipientResource::collection($added),
            'skipped_count' => count($data['recipients']) - $added->count(),
            'total' => $invitation->recipients()->count(),
            'limit' => $event->recipientLimit(),
        ], 201);
    }

    public function destroy(Event $event, Recipient $recipient): Response
    {
        if ($recipient->opened_at !== null) {
            throw ValidationException::withMessages([
                'recipient' => 'Zarfını açmış bir davetli silinemez.',
            ]);
        }

        $recipient->delete();

        return response()->noContent();
    }
}
