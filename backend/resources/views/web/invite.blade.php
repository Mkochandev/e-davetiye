@extends('web.layout')

@php
    $event = $invitation->event;
    $attending = old('status', $recipient->rsvp_status->value) === 'attending';
    $guestCount = (int) old('guest_count', $recipient->guest_count ?? 1);
    $skipEnvelope = $errors->any();
@endphp

@section('title', $event->title.' — Davetiye')
@section('noindex', true)

@push('head')
    <script>document.documentElement.classList.add('js'@if ($skipEnvelope), 'is-revealed'@endif);</script>
@endpush

@section('body')
    <section class="stage" aria-label="Zarf">
        <p class="stage__from">{{ $event->title }}<br><span class="muted" style="color: rgba(255,255,255,.7); font-size: 16px;">sizi davet ediyor</span></p>
        <x-envelope :color="$invitation->envelope_color" :seal-color="$invitation->seal_color" :seal-text="$invitation->seal_text">
            {{ $invitation->texts['names'] ?? $event->title }}
        </x-envelope>
        <p class="stage__hint">Zarfı açmak için mühre dokunun</p>
    </section>

    <main class="page reveal">
        <x-invitation-card :invitation="$invitation" />

        @if ($event->latitude !== null && $event->longitude !== null)
            <a class="btn btn--ghost" style="margin-top: 16px;" target="_blank" rel="noopener noreferrer"
               href="https://www.google.com/maps/dir/?api=1&destination={{ $event->latitude }},{{ $event->longitude }}">Yol tarifi</a>
        @endif

        <section class="panel" id="lcv" aria-labelledby="lcv-title">
            <h2 id="lcv-title">Katılım durumunuz</h2>

            @if ($recipient->responded_at)
                <p class="small muted" style="margin: 0 0 12px;">
                    Yanıtınız: <strong style="color: var(--ink);">{{ $recipient->rsvp_status->value === 'attending' ? 'Katılıyorum · '.$recipient->guest_count.' kişi' : 'Katılamıyorum' }}</strong>
                </p>
            @endif

            @if ($invitation->rsvpClosed())
                <p class="notice" style="margin: 0;">LCV son tarihi geçti, yanıt değiştirilemez.</p>
            @else
                @if ($invitation->rsvp_deadline)
                    <p class="small muted" style="margin: 0 0 12px;">Son yanıt tarihi: {{ \App\Support\EventDate::date($invitation->rsvp_deadline->copy()->setTime(12, 0)) }}</p>
                @endif

                @if ($errors->any())
                    <p class="notice" role="alert" style="margin: 0 0 12px;">{{ $errors->first() }}</p>
                @endif

                <form method="post" action="{{ route('invite.rsvp', $recipient->token) }}" class="rsvp">
                    @csrf
                    <div class="rsvp__choices">
                        <label class="rsvp__choice">
                            <input type="radio" name="status" value="attending" @checked($attending) required>
                            <span>Katılıyorum</span>
                        </label>
                        <label class="rsvp__choice">
                            <input type="radio" name="status" value="not_attending" @checked(old('status', $recipient->rsvp_status->value) === 'not_attending')>
                            <span>Katılamıyorum</span>
                        </label>
                    </div>

                    <div class="rsvp__count">
                        <label for="guest_count" class="small muted">Kaç kişi geleceksiniz?</label>
                        <div class="stepper">
                            <button type="button" data-step="-1" aria-label="Azalt">−</button>
                            <input id="guest_count" name="guest_count" type="number" inputmode="numeric" min="1" max="{{ \App\Services\RsvpService::MAX_GUESTS }}" value="{{ max(1, $guestCount) }}">
                            <button type="button" data-step="1" aria-label="Artır">+</button>
                        </div>
                    </div>

                    <button type="submit" class="btn" style="margin-top: 16px;">{{ $recipient->responded_at ? 'Yanıtı güncelle' : 'Yanıtı gönder' }}</button>
                </form>
            @endif
        </section>
    </main>
@endsection

@push('head')
    <style>
        .rsvp__choices { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        .rsvp__choice { position: relative; }
        .rsvp__choice input { position: absolute; opacity: 0; }
        .rsvp__choice span { display: flex; align-items: center; justify-content: center; min-height: 52px; border: 1px solid var(--border); border-radius: 14px; font-weight: 600; cursor: pointer; }
        .rsvp__choice input:checked + span { border-color: var(--accent); background: var(--accent-soft); color: var(--accent); }
        .rsvp__choice input:focus-visible + span { outline: 2px solid var(--accent); outline-offset: 2px; }
        .rsvp__count { margin-top: 16px; }
        .rsvp:has(input[value="not_attending"]:checked) .rsvp__count { display: none; }
        .stepper { display: flex; align-items: center; gap: 10px; margin-top: 6px; }
        .stepper button { width: 48px; height: 48px; border: 1px solid var(--border); border-radius: 14px; background: var(--surface); color: var(--ink); font-size: 22px; cursor: pointer; }
        .stepper input { width: 72px; height: 48px; border: 1px solid var(--border); border-radius: 14px; text-align: center; font: 600 18px var(--font-body); color: var(--ink); -moz-appearance: textfield; }
        .stepper input::-webkit-inner-spin-button, .stepper input::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }
    </style>
@endpush

@push('scripts')
    <script>
        (function () {
            var root = document.documentElement;
            var env = document.querySelector('.env');
            var token = document.querySelector('meta[name="csrf-token"]').content;

            document.querySelector('[data-open]').addEventListener('click', function () {
                if (navigator.vibrate) navigator.vibrate(30);
                env.classList.add('is-open');
                fetch(@json(route('invite.open', $recipient->token)), {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
                    credentials: 'same-origin'
                });
                setTimeout(function () { root.classList.add('is-revealed'); window.scrollTo(0, 0); }, 1500);
            });

            var input = document.getElementById('guest_count');
            document.querySelectorAll('[data-step]').forEach(function (button) {
                button.addEventListener('click', function () {
                    var next = (parseInt(input.value, 10) || 1) + parseInt(button.dataset.step, 10);
                    input.value = Math.min(parseInt(input.max, 10), Math.max(1, next));
                });
            });
        })();
    </script>
@endpush
