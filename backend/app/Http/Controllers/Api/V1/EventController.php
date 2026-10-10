<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\EventCreator;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\EventRequest;
use App\Http\Resources\EventResource;
use App\Models\Event;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class EventController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $events = $request->user()->events()
            ->with(['salon', 'invitation'])
            ->orderBy('starts_at')
            ->get();

        return EventResource::collection($events);
    }

    public function store(EventRequest $request): EventResource
    {
        $event = $request->user()->events()->create([
            ...$request->eventData(),
            'created_by' => EventCreator::User,
        ]);

        return new EventResource($event->load(['salon', 'invitation']));
    }

    public function show(Event $event): EventResource
    {
        return new EventResource($event->load(['salon', 'invitation']));
    }

    public function update(EventRequest $request, Event $event): EventResource
    {
        $data = $request->eventData();

        $this->ensureLockedFieldsUnchanged($event, $data);

        $event->update($data);

        return new EventResource($event->load(['salon', 'invitation']));
    }

    public function headcountSharing(Request $request, Event $event): EventResource
    {
        $data = $request->validate(['share' => ['required', 'boolean']]);

        $event->update(['share_headcount_with_salon' => $data['share']]);

        return new EventResource($event->load(['salon', 'invitation']));
    }

    /**
     * Salon etkinliğinde tarih, saat ve mekan çift tarafından değiştirilemez (spec §3.2).
     * Mevcut değerin aynısını göndermek hata değildir.
     *
     * @param  array<string, mixed>  $data
     */
    private function ensureLockedFieldsUnchanged(Event $event, array $data): void
    {
        $errors = [];

        foreach ($event->lockedFields() as $field) {
            if (array_key_exists($field, $data) && ! $this->sameValue($event->{$field}, $data[$field])) {
                $errors[$field] = 'Salonun eklediği etkinlikte bu alan değiştirilemez.';
            }
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function sameValue(mixed $current, mixed $new): bool
    {
        if ($current instanceof CarbonInterface || $new instanceof CarbonInterface) {
            return $current?->getTimestamp() === $new?->getTimestamp();
        }

        if (is_numeric($current) || is_numeric($new)) {
            return $current !== null && $new !== null && abs((float) $current - (float) $new) < 0.0000001;
        }

        return $current === $new;
    }
}
