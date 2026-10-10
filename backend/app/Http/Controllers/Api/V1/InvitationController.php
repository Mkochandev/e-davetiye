<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\PackRequiredException;
use App\Http\Controllers\Controller;
use App\Http\Resources\InvitationResource;
use App\Models\Event;
use App\Models\Invitation;
use App\Models\Template;
use App\Support\InvitationDefaults;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InvitationController extends Controller
{
    public function show(Event $event): InvitationResource
    {
        $invitation = $event->invitation ?? abort(404);

        return new InvitationResource($invitation->load('template'));
    }

    /**
     * Davetiyeyi oluşturur ya da günceller. Gönderilmeyen metin alanları yeni davetiyede §11 varsayılanlarıyla,
     * mevcut davetiyede eski değerleriyle doldurulur.
     */
    public function upsert(Request $request, Event $event): InvitationResource
    {
        $deadlineLimit = $event->starts_at->timezone('Europe/Istanbul')->toDateString();

        $data = $request->validate([
            'template_id' => ['required', 'integer', Rule::exists('templates', 'id')->where('is_active', true)],
            'texts' => ['sometimes', 'array:label,intro,names,message'],
            'texts.label' => ['sometimes', 'nullable', 'string', 'max:60'],
            'texts.intro' => ['sometimes', 'nullable', 'string', 'max:200'],
            'texts.names' => ['sometimes', 'nullable', 'string', 'max:120'],
            'texts.message' => ['sometimes', 'nullable', 'string', 'max:500'],
            'envelope_color' => ['sometimes', 'string', Rule::in(array_keys(config('zarf.envelope_colors')))],
            'seal_color' => ['sometimes', 'string', Rule::in(array_keys(config('zarf.seal_colors')))],
            'seal_text' => ['sometimes', 'nullable', 'string', 'max:4'],
            'rsvp_deadline' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'before_or_equal:'.$deadlineLimit],
        ], [
            'rsvp_deadline.before_or_equal' => 'LCV son tarihi etkinlik gününden sonra olamaz.',
        ], [
            'template_id' => 'şablon',
            'envelope_color' => 'zarf rengi',
            'seal_color' => 'mühür rengi',
            'seal_text' => 'mühür harfleri',
            'rsvp_deadline' => 'LCV son tarihi',
        ]);

        $invitation = $event->invitation ?? new Invitation([
            'event_id' => $event->id,
            'texts' => InvitationDefaults::texts($event),
            'envelope_color' => InvitationDefaults::ENVELOPE_COLOR,
            'seal_color' => InvitationDefaults::SEAL_COLOR,
        ]);

        $texts = $invitation->texts;
        foreach ($data['texts'] ?? [] as $key => $value) {
            $texts[$key] = (string) $value;
        }

        $invitation->fill([
            'template_id' => $data['template_id'],
            'texts' => $texts,
            ...collect($data)->only(['envelope_color', 'seal_color', 'seal_text', 'rsvp_deadline'])->all(),
        ]);

        if (! $event->hasPack()) {
            $this->ensureFreeOptions($invitation);
        }

        $invitation->save();

        return new InvitationResource($invitation->load('template'));
    }

    /**
     * @throws PackRequiredException
     */
    private function ensureFreeOptions(Invitation $invitation): void
    {
        if (Template::whereKey($invitation->template_id)->value('is_premium')) {
            throw new PackRequiredException('Premium şablonlar için Etkinlik Paketi gerekiyor.');
        }

        if (! in_array($invitation->envelope_color, config('zarf.free_envelope_colors'), true)
            || ! in_array($invitation->seal_color, config('zarf.free_seal_colors'), true)) {
            throw new PackRequiredException('Bu renk için Etkinlik Paketi gerekiyor.');
        }
    }
}
