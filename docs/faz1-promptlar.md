# Faz 1 — Claude Code adımları

Her adımı **yeni bir Claude Code oturumunda** repo kökünden başlat ve aşağıdaki promptu olduğu gibi yapıştır. Claude önce plan gösterecek; planı okuyup onayla, sonra uygulasın. Adım bitince testlerin geçtiğini ve commit'in atıldığını kontrol et, sonra bir sonrakine geç.

## Hazırlık (elle, bir kez)

1. GitHub'da boş, private bir repo aç ve bilgisayarına klonla.
2. `CLAUDE.md` dosyasını repo köküne, `faz1-spec.md` ve `faz1-promptlar.md` dosyalarını `docs/` klasörüne koy. Commit at.
3. Kurulu olsun: PHP 8.3+, Composer, Docker, Flutter (stable), Android Studio; iOS için Xcode.
4. Bundle kimliğini şimdi seç (örnek: `com.connectyazilim.zarf`) ve aşağıdaki promptlarda `[BUNDLE_ID]` yerine yaz. Sonradan değiştirmek zahmetli.
5. Repo kökünde `claude` komutunu çalıştır.

---

## Adım 0 — Proje iskeleti

```text
docs/faz1-spec.md dosyasını baştan sona oku. Henüz kod yazma.

1) Spec'te çelişkili, eksik ya da belirsiz gördüğün noktaları madde madde listele.
2) Bu adımın planını çıkar:
   - backend/ içinde güncel Laravel projesi (composer create-project)
   - mobile/ içinde Flutter projesi, bundle kimliği [BUNDLE_ID], yalnız iOS ve Android
   - kökte docker-compose.yml: PostgreSQL, uygulama ve test için iki ayrı veritabanı
   - backend/.env.example PostgreSQL ayarlarıyla, phpunit/pest test veritabanını kullansın
   - kök .gitignore ve kısa README (kurulum, çalıştırma, test komutları)

Planı ve soruları göster, onayımı bekle. Onaydan sonra uygula; `php artisan test` ve `flutter test` geçsin; "chore: proje iskeleti" commit'ini at.
```

## Adım 1 — Veri modeli ve telefonla giriş (backend)

```text
docs/faz1-spec.md §2, §3.7, §5 ve §6.1'i oku.

backend/ içinde:
- Sanctum, giggsey/libphonenumber-for-php ve Pest'i kur.
- §5'teki tüm tabloların migration, model, ilişki ve enum'larını yaz.
- PhoneNumber servisi: TR varsayılanıyla E.164'e çevirir, geçersizse doğrulama hatası; phone_hash (SHA-256) üretir.
- SmsSender arayüzü + LogSmsSender (local/testing) + NetgsmSmsSender iskeleti (gövdesi TODO, gerçek API çağrısı yok).
- config/zarf.php (§4 sınırları).
- §6.1 uçlarının hepsi; OTP kuralları ve hız sınırları §2'deki gibi; 123456 kodu yalnız local/testing'de.
- Doğrulamada §3.7 bağlamasını tek transaction içinde yap.
- Seeder: 1 admin, 1 demo salon + owner, §11'deki 6 şablon (config JSON'larıyla).
- Pest testleri: başarılı giriş, yanlış kod, süresi geçmiş kod, 5 yanlış deneme, hız sınırı, yeni kullanıcıda bekleyen zarf ve etkinlik bağlama, logout, PATCH /me.

Önce planı göster, onayımı bekle. Bitince testler ve pint temiz olsun; "feat(api): veri modeli ve telefonla giriş" commit'i.
```

## Adım 2 — Salon ve admin panelleri

```text
docs/faz1-spec.md §2, §3.1, §7 ve §8'i oku.

backend/ içinde Filament'i kur ve iki panel kur:
- /admin: admin guard; §8'deki kaynaklar. Salon oluştururken owner kullanıcısını da aç ve şifre belirleme e-postası gönder (local'de log mailer).
- /salon: salon guard; özel giriş sayfası (tek alan: e-posta veya telefon + şifre), şifre sıfırlama; §7'deki pano, takvim, rezervasyonlar, rezervasyon detayı (LCV özeti + "Çifti davet et" kutusu: QR, link, WhatsApp butonu), salon profili, kullanıcılar.
- Rezervasyon kaydı §3.1'deki gibi event oluşturur/günceller; iptal etkinliği iptal eder; çift kayıtlıysa owner bağlanır.
- Saat çakışması uyarısı.
- Her sorgu salon_id ile kapsansın.

Testler: salon kullanıcısı e-postayla ve telefonla girer; rezervasyon event oluşturur ve kayıtlı çifti bağlar; iptal; salon A, salon B'nin rezervasyonunu göremez/açamaz; staff kullanıcı profil ve kullanıcılar sayfasına giremez; LCV özeti paylaşım kapalıyken sayı göstermez.

Takvim için kullanacağın eklentiyi kurmadan önce bana söyle; uyumlu değilse basit aylık grid yaz. Önce planı göster, onayımı bekle. "feat(panel): salon ve admin panelleri" commit'i.
```

## Adım 3 — Etkinlik, şablon ve davetiye API'si

```text
docs/faz1-spec.md §3.2, §3.3, §4, §6.3, §6.4 (yalnız contacts/match ve recipients uçları) ve §11'i oku.

backend/ içinde:
- §6.3 uçlarının hepsi; salon etkinliğinde kilitli alanlar.
- Davetiye kaydında §4 kontrolleri: paketsiz premium şablon/renk → 402 pack_required.
- Yeni davetiyede etkinlik türüne göre §11 varsayılan metinleri.
- POST /contacts/match (hash'ler saklanmaz, en fazla 2.000).
- Davetli listeleme, ekleme (normalize, tekrar atlama, sınır kontrolü; aşarsa hiçbiri eklenmez), silme (açılmamışsa).
- API Resource sınıfları; rezervasyon ücretleri hiçbir yanıtta yok.

Testler: kilitli alan değiştirilemez; premium şablon paketsiz 402, paketli 200; 30 sınırı (29+2 → 402 ve hiç eklenmez); tekrar eden numara atlanır; başka kullanıcının etkinliği 404; contacts/match yalnız kayıtlıları döner.

Önce planı göster, onayımı bekle. "feat(api): etkinlik, şablon ve davetiye" commit'i.
```

## Adım 4 — Gönderim, posta kutusu, LCV ve web davetiye sayfası

```text
docs/faz1-spec.md §3.4, §3.5, §3.6, §6.2, §6.4 (send ve stats), §9, §11 ve §15'i oku.

backend/ içinde:
- POST /events/{event}/invitation/send: §3.4 kuralları; yanıtta wa.me linkleri; tekrar çağrılınca yalnız yeni alıcılar.
- Yeni zarf ve yeni LCV için bildirim olayları (şimdilik kuyruğa düşen ama FCM göndermeyen bir NotificationSender arayüzü; FCM Adım 9'da).
- §6.2 uçlarının hepsi; son tarih kontrolü; stats ucu.
- Web: /z/{token} (Blade, CSS zarf animasyonu, şablon config'ine göre kart, LCV formu, teşekkür ekranı), /indir, /aydinlatma-metni, /gizlilik, /kullanim-kosullari. Tasarım §15. noindex.
- Şablon çizimini PHP tarafında tek bir bileşende topla (Blade component), §11 çizim sırasıyla.

Testler: gönderimde kayıtlı/kayıtsız ayrımı; ikinci gönderim yalnız yenileri gönderir; inbox yalnız kendi zarfları; başkasının zarfı 404; open idempotent; LCV son tarihten sonra 422; web token ile LCV; geçersiz token 404; iptal edilmiş etkinlik mesajı; stats sayıları; web'den LCV veren kişi sonra kaydolunca LCV korunur.

Önce planı göster, onayımı bekle. "feat: gönderim, posta kutusu, LCV ve web davetiye" commit'i.
```

## Adım 5 — Flutter iskeleti ve giriş

```text
docs/faz1-spec.md §2, §10 (M1–M5), §14 ve §15'i oku.

mobile/ içinde:
- Paketler: flutter_riverpod, go_router, dio, flutter_secure_storage, google_fonts, intl. Başka paket gerekirse önce söyle.
- Klasör yapısı: lib/core (api, tema, router, hata), lib/features/<özellik>/{data,ui}.
- Tema §15 tokenlarıyla; Türkçe yerelleştirme ve tarih biçimi.
- API_BASE_URL --dart-define ile; dio interceptor token ekler, 401'de girişe döner; API hatalarını Türkçe mesajla gösteren ortak yapı.
- M1–M4 ekranları ve M5 sekme iskeleti (diğer sekmeler şimdilik boş durum).
- Token secure storage'da; uygulama açılışında oturum kontrolü.

Testler: telefon doğrulama (10 hane), OTP akışı için widget testi (API mock'lu), router yönlendirmesi.

Önce planı göster, onayımı bekle. Bitince flutter analyze ve flutter test temiz; "feat(mobile): iskelet ve giriş" commit'i.
```

## Adım 6 — Posta kutusu, zarf açma, davetiye ve LCV (Flutter)

```text
docs/faz1-spec.md §6.2, §10 (M6, M7, M8), §11 ve §15'i oku.

mobile/ içinde:
- EnvelopeView widget'ı: zarf rengi, kapak (zarf × 0,88), iç renk (× 0,62), mühür (renk + harfler); kapalı/açık animasyonu (kapak rotateX, kart yukarı kayar, mühür küçülüp kaybolur).
- InvitationCard widget'ı: §11 config'ini çizer (frame, ornament, fontlar, çizim sırası). Web'deki Blade bileşeniyle aynı görünmeli.
- M6 posta kutusu, M7 zarf açma (haptic + open çağrısı), M8 davetiye + LCV (takvime ekle: add_2_calendar; yol tarifi: url_launcher ile harita).
- Boş, yükleniyor ve hata durumları.

Testler: InvitationCard 6 şablonun hepsiyle golden ya da widget testi; LCV formu doğrulaması (kişi sayısı 1–10); son tarih geçince kilit.

Önce planı göster, onayımı bekle. "feat(mobile): posta kutusu, zarf ve LCV" commit'i.
```

## Adım 7 — Etkinlik, davetiye düzenleyici, davetliler ve gönderim (Flutter)

```text
docs/faz1-spec.md §3.2, §3.4, §4, §6.3, §6.4, §10 (M9–M13, M15, M16) ve §14'ü oku.

mobile/ içinde:
- M9 Etkinliklerim, M10 etkinlik oluştur, M11 etkinlik detayı (salonla paylaşım anahtarı).
- M12 davetiye düzenleyici: 3 adım ve canlı önizleme (Adım 6'daki widget'lar). Paketsiz premium seçimde M14'e gidecek bir yer tutucu (Adım 8'de gerçek paywall).
- M13 davetli seçimi: flutter_contacts ile rehber (izin öncesi açıklama ekranı), numaraları E.164'e çevir, SHA-256 hash'le, /contacts/match ile "Uygulamada" rozeti; elle numara ekleme; x/30 sayacı; 402 pack_required gelirse paywall yer tutucusu.
- M15 gönderim ve sonuç ekranı: WhatsApp (url_launcher ile wa.me) ve linki kopyala (share_plus/clipboard).
- M16 LCV takibi ve hatırlatma butonu.

Testler: hash'leme ve E.164 dönüşümü birim testi; sayaç ve sınır davranışı; gönderim sonuç ekranında link alıcıları listesi.

Önce planı göster, onayımı bekle. "feat(mobile): etkinlik, davetiye düzenleyici ve gönderim" commit'i.
```

## Adım 8 — Etkinlik Paketi satın alma

```text
docs/faz1-spec.md §3.3, §4, §6.5 ve §12'yi oku.

backend/:
- PurchaseVerifier arayüzü, FakePurchaseVerifier (yalnız local/testing), AppleVerifier ve GooglePlayVerifier.
- Gerçek doğrulayıcılar için gerekli .env anahtarlarını .env.example'a ekle; kimlik bilgisi yoksa açık hata versin. Hangi resmi API'yi nasıl kullandığını kod yorumunda kaynak bağlantısıyla belirt; emin olmadığın bir alanı uydurma, bana sor.
- POST /purchases: idempotent, paketi olan etkinliğe 409.

mobile/:
- in_app_purchase ile event_pack (consumable). M14 paywall: mağazadan fiyat, satın al, bekleme ve hata durumları.
- Satın alma akışını sunucuya doğrulat, onaydan sonra completePurchase. Uygulama açılışında bekleyen satın almaları işle.
- Adım 7'deki paywall yer tutucularını gerçek M14'e bağla.

Testler: backend'de sahte doğrulayıcıyla paket açılır; aynı transaction tekrar → paket bir kez; 409; doğrulama başarısız → paket açılmaz.

Önce planı göster, onayımı bekle. "feat: etkinlik paketi satın alma" commit'i.
```

## Adım 9 — Bildirimler, hesap silme ve uçtan uca kontrol

```text
docs/faz1-spec.md §6.1 (devices, DELETE /me), §10 (M17), §13, §14 ve §16'yı oku.

backend/:
- FCM ile gerçek NotificationSender (Firebase servis hesabı .env'den); kuyruk işi; geçersiz token temizliği.
- DELETE /me: §14'teki silme kuralları.

mobile/:
- firebase_messaging: izin isteme, token'ı /devices'a gönderme, bildirime dokununca M7'yi açma.
- M17 profil ve hesap silme.

Sonra §16'daki 7 senaryonun her biri için backend'de bir uçtan uca feature testi yaz ve çalıştır. Geçmeyen varsa düzelt.

Önce planı göster, onayımı bekle. "feat: bildirimler, hesap silme ve kabul testleri" commit'i.
```

---

## Mağazaya çıkmadan önce (elle)

- App Store Connect: uygulama kaydı ve **consumable** ürün `event_pack`; App Store Connect API anahtarı.
- Google Play Console: uygulama ve uygulama içi ürün `event_pack`; doğrulama için servis hesabı.
- Firebase projesi: iOS ve Android uygulamaları, `GoogleService-Info.plist` ve `google-services.json`, APNs anahtarı.
- SMS sağlayıcı hesabı ve `NetgsmSmsSender`'ın (ya da seçtiğin sağlayıcının) gerçek gövdesi.
- Alan adı ve sunucu; `/z` linkleri bu alan adını kullanır.
- KVKK aydınlatma metni, açık rıza metni, gizlilik politikası ve kullanım koşulları (hukukçu).
- Mağaza açıklaması, ekran görüntüleri ve gizlilik etiketleri (rehber erişimi, telefon numarası).
