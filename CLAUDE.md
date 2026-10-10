# Zarf (çalışma adı) — dijital davetiye

Davetiyeler telefon numarasına gönderilir, alıcının uygulamadaki posta kutusuna mühürlü bir zarf olarak düşer. Uygulaması olmayan misafir aynı zarfı web linkinde açar. Düğün salonları ücretsiz bir web paneliyle rezervasyonlarını yönetir ve çiftleri uygulamaya getirir.

## Repo yapısı

- `backend/` — Laravel: mobil API (`/api/v1`), Filament panelleri (`/admin`, `/salon`), web davetiye sayfası (`/z/{token}`)
- `mobile/` — Flutter uygulaması (iOS + Android)
- `docs/faz1-spec.md` — Faz 1 şartnamesi. İş kuralları için tek kaynak. Bir özelliğe başlamadan önce ilgili bölümünü oku.
- `docs/faz1-promptlar.md` — adım adım görev listesi (insan için; sen okumak zorunda değilsin)

## Teknoloji

- backend: PHP 8.3+, Laravel (güncel kararlı), PostgreSQL, Sanctum, Filament (güncel kararlı), `giggsey/libphonenumber-for-php`, Pest
- mobile: Flutter (stable), flutter_riverpod, go_router, dio, flutter_secure_storage, google_fonts, intl, flutter_contacts, url_launcher, share_plus, in_app_purchase, firebase_messaging, add_2_calendar
- local: `docker compose up -d` ile PostgreSQL (uygulama ve test veritabanı ayrı)

## Kurallar

- Kod, tablo, kolon, değişken adları İngilizce; kullanıcıya görünen tüm metinler Türkçe.
- Telefon numaraları her yerde E.164 (`+905321112233`). Normalizasyon yalnızca `PhoneNumber` servisinde yapılır.
- Para tutarları kuruş cinsinden integer saklanır.
- Zamanlar UTC saklanır, `Europe/Istanbul` gösterilir.
- Her yeni API ucu için Pest feature testi yaz; "başkasının verisine erişemez" testi zorunlu.
- Salon kullanıcıları yalnızca kendi salonlarının verisini görür. Bunu sorgu kapsamıyla uygula ve test et.
- Ücretsiz/ücretli sınırları (`config/zarf.php`) sunucuda zorlanır; mobil yalnızca gösterir.
- Gizli anahtarlar `.env`'de; `.env` commit edilmez, `.env.example` güncel tutulur.
- Üretimde OTP kodu loglanmaz; loglarda telefon maskelenir (`+90532***2233`).
- Yeni bir paket eklemeden önce neden gerektiğini söyle.
- Spec'teki "Faz 1'de yok" listesindeki hiçbir şeyi ekleme. Belirsiz bir noktada tahmin etme, sor.

## Komutlar

```bash
# backend
cd backend && composer install && php artisan migrate --seed
php artisan test
./vendor/bin/pint

# mobile (Android emülatöründen yerel API'ye 10.0.2.2 ile erişilir)
cd mobile && flutter pub get
flutter analyze && flutter test
flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000/api/v1
```

## Bitti tanımı

- `php artisan test`, `pint --test`, `flutter analyze`, `flutter test` temiz
- Spec'teki ilgili kabul kriterleri karşılanıyor
- Tek konulu commit, kısa ve açıklayıcı mesaj (`feat(api): ...`, `feat(mobile): ...`, `fix: ...`)
- Sonunda kısa özet: ne yaptın, spec'ten neyi bilerek atladın, açık kalan soru ne
