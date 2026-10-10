{{-- Mağaza bağlantıları; adresler belli olana kadar yer tutucu (spec §9). --}}
<a class="btn" href="{{ config('zarf.store_urls.ios') ?: '#' }}" rel="noopener">App Store'dan indir</a>
<a class="btn btn--ghost" href="{{ config('zarf.store_urls.android') ?: '#' }}" rel="noopener">Google Play'den indir</a>
@unless (config('zarf.store_urls.ios') && config('zarf.store_urls.android'))
    <p class="small muted" style="margin: 10px 0 0; text-align: center;">Uygulama çok yakında mağazalarda.</p>
@endunless
