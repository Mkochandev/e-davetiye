# Zarf (çalışma adı)

Dijital davetiye: Laravel API + Filament panelleri (`backend/`) ve Flutter uygulaması (`mobile/`). İş kuralları: `docs/faz1-spec.md`.

## Gereksinimler

- PHP 8.3+ (`pdo_pgsql`, `pgsql`, `intl`, `mbstring`, `openssl`, `zip` eklentileriyle), Composer
- Docker (PostgreSQL için)
- Flutter (stable)

## Kurulum

```bash
# PostgreSQL: "zarf" (uygulama) ve "zarf_test" (testler) veritabanları
docker compose up -d

# backend
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed

# mobile
cd mobile
flutter pub get
```

## Çalıştırma

```bash
# API ve paneller: http://localhost:8000
cd backend && php artisan serve

# Uygulama (Android emülatörü yerel API'ye 10.0.2.2 ile erişir)
cd mobile && flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000/api/v1
```

## Paneller

- Admin: http://localhost:8000/admin — seeder: `admin@zarf.test` / `password` (`SEED_ADMIN_*` ile değiştirilir)
- Salon: http://localhost:8000/salon — demo owner: `salon@zarf.test` ya da `0533 000 00 01` / `password`

Salon kullanıcıları ilk şifrelerini e-postadaki bağlantıyla belirler; local'de e-postalar `storage/logs/laravel.log`'a yazılır.

## Test

```bash
cd backend && php artisan test && ./vendor/bin/pint --test
cd mobile && flutter analyze && flutter test
```

Backend testleri `zarf_test` veritabanını kullanır (`backend/phpunit.xml`).
