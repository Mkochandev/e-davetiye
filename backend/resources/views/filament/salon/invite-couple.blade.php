@php
    use App\Support\CoupleInvite;

    $phone = $getRecord()->contact_phone;
@endphp

<div style="display:flex; flex-wrap:wrap; gap:1.5rem; align-items:center;">
    <div style="background:#fff; padding:.5rem; border-radius:12px; line-height:0;" aria-label="Uygulama indirme QR kodu">
        {!! preg_replace('/^<\?xml.*?\?>\s*/', '', CoupleInvite::qrSvg()) !!}
    </div>

    <div style="display:flex; flex-direction:column; gap:.75rem; min-width:0;">
        <div>
            <div style="font-size:.875rem; opacity:.7;">İndirme bağlantısı</div>
            <x-filament::link :href="CoupleInvite::downloadUrl()" target="_blank">
                {{ CoupleInvite::downloadUrl() }}
            </x-filament::link>
        </div>

        <div>
            <x-filament::button
                tag="a"
                :href="CoupleInvite::whatsappUrl($phone)"
                target="_blank"
                icon="heroicon-o-chat-bubble-left-right"
                color="success"
            >
                Çifte WhatsApp mesajı gönder
            </x-filament::button>
        </div>
    </div>
</div>
