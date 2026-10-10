@extends('web.layout')

@section('title', 'Uygulamayı indir — Zarf')

@section('body')
    <main class="page" style="padding-top: 48px;">
        <a class="brand" href="{{ url('/indir') }}">Zarf</a>
        <section class="panel">
            <h2>Davetiyeniz zarfında gelsin</h2>
            <p class="muted" style="margin: 0 0 16px;">Telefon numaranızla giriş yapın; salonunuzun eklediği etkinliğiniz ve size gönderilen davetiyeler hazır olsun.</p>
            @include('web.partials.store-links')
        </section>
        @include('web.partials.legal-links')
    </main>
@endsection
