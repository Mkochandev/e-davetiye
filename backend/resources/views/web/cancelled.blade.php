@extends('web.layout')

@section('title', $event->title.' — Davetiye')
@section('noindex', true)

@section('body')
    <main class="page" style="text-align: center; padding-top: 72px;">
        <section class="panel">
            <h2>{{ $event->title }}</h2>
            <p class="notice" style="margin: 12px 0 0;">Bu etkinlik iptal edildi</p>
        </section>
    </main>
@endsection
