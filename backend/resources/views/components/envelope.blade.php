{{-- Kapalı zarf + mühür (spec §15). Kapak = zarf × 0,88; iç = zarf × 0,62. `.is-open` ile açılır. --}}
@props(['color', 'sealColor', 'sealText' => null])
@php
    $body = \App\Support\Palette::envelope($color);
    $flap = \App\Support\Palette::scale($body, \App\Support\Palette::FLAP_FACTOR);
    $inner = \App\Support\Palette::scale($body, \App\Support\Palette::INNER_FACTOR);
    $seal = \App\Support\Palette::seal($sealColor);
@endphp
<div {{ $attributes->class('env') }} style="--env: {{ $body }}; --env-flap: {{ $flap }}; --env-inner: {{ $inner }}; --seal: {{ $seal }};">
    <div class="env__back"></div>
    <div class="env__letter">{{ $slot }}</div>
    <div class="env__pocket"></div>
    <div class="env__flap"></div>
    <button type="button" class="env__seal" data-open aria-label="Zarfı aç">
        <span>{{ $sealText }}</span>
    </button>
</div>
