@extends('web.layout')

@section('title', $title.' — Zarf')

@section('body')
    <main class="page prose">
        <a class="brand" href="{{ url('/indir') }}">Zarf</a>
        <h1>{{ $title }}</h1>
        <p class="notice">[Hukukçu onayı bekliyor]</p>
        <p class="muted">Bu metin hukukçu onayından sonra yayımlanacaktır.</p>
        @include('web.partials.legal-links')
    </main>
@endsection
