@php
    $stats = $getState();
@endphp

@if ($stats === null)
    <p style="opacity:.7;">Çift kişi sayısını paylaşmıyor.</p>
@else
    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(140px, 1fr)); gap:1rem;">
        @foreach ([
            ['Katılıyor', $stats['attending_guests'].' kişi', $stats['attending'].' hane'],
            ['Katılamıyor', $stats['not_attending'], null],
            ['Bekleyen', $stats['pending'], null],
            ['Açılmadı', $stats['unopened'], null],
        ] as [$label, $value, $hint])
            <div>
                <div style="font-size:.875rem; opacity:.7;">{{ $label }}</div>
                <div style="font-size:1.5rem; font-weight:600;">{{ $value }}</div>
                @if ($hint)
                    <div style="font-size:.75rem; opacity:.6;">{{ $hint }}</div>
                @endif
            </div>
        @endforeach
    </div>
@endif
