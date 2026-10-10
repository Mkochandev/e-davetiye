{{-- Şablon çizimi (spec §11). Ölçüler mobil InvitationCard ile aynı tutulur. --}}
@php
    $c = $config;
    $frame = $c['frame'] ?? 'none';
    $ornament = $c['ornament'] ?? 'none';
@endphp
<article {{ $attributes->class(['icard', 'icard--frame-'.$frame]) }}
    style="--paper: {{ $c['paper'] }}; --ink: {{ $c['ink'] }}; --muted: {{ $c['muted'] }}; --accent: {{ $c['accent'] }}; --frame: {{ $c['frame_color'] ?? 'transparent' }}; --display: {{ $font('display_font') }}; --body: {{ $font('body_font') }};">
    @if ($ornament === 'top-band')
        <div class="icard__band" aria-hidden="true"></div>
    @endif
    <div class="icard__inner">
        @if ($texts['label'] !== '')
            <p class="icard__label">{{ $texts['label'] }}</p>
        @endif
        @if ($texts['intro'] !== '')
            <p class="icard__intro">{{ $texts['intro'] }}</p>
        @endif
        <h1 class="icard__names">{{ $texts['names'] }}</h1>
        @if ($texts['message'] !== '')
            <p class="icard__message">{{ $texts['message'] }}</p>
        @endif
        @if ($ornament === 'dot-rule')
            <div class="icard__dot-rule" aria-hidden="true"><span></span><i></i><span></span></div>
        @else
            <div class="icard__spacer" aria-hidden="true"></div>
        @endif
        <p class="icard__date">{{ $date }}</p>
        <p class="icard__time">{{ $dayAndTime }}</p>
        @if ($venueName)
            <p class="icard__venue">{{ $venueName }}</p>
        @endif
        @if ($venueAddress)
            <p class="icard__address">{{ $venueAddress }}</p>
        @endif
    </div>
</article>
