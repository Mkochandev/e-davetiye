@extends('web.layout')

@section('title', 'Teşekkürler — '.$recipient->invitation->event->title)
@section('noindex', true)

@section('body')
    <main class="page">
        <section class="panel" style="text-align: center;">
            <h2 style="font-size: 26px;">Teşekkürler</h2>
            <p class="notice notice--ok" style="margin: 12px 0 0;">
                @if ($recipient->rsvp_status->value === 'attending')
                    Katılımınız iletildi · {{ $recipient->guest_count }} kişi
                @else
                    Katılamayacağınız iletildi
                @endif
            </p>
            <p class="muted" style="margin: 16px 0 0;">{{ $recipient->invitation->event->title }} yanıtınızı görebilir.</p>
            <a class="small" style="display: inline-block; margin-top: 12px;" href="{{ route('invite.show', $recipient->token) }}">Davetiyeye dön</a>
        </section>

        <section class="panel">
            <h2>Davetiyeleriniz bir arada</h2>
            <p class="muted" style="margin: 0 0 16px;">Uygulamayı indirip bu numarayla giriş yaptığınızda davetiyeleriniz posta kutunuzda olur.</p>
            @include('web.partials.store-links')
        </section>
    </main>
@endsection
