# Faz 1 Şartnamesi — Zarf (çalışma adı)

Sürüm 1 · 10 Ekim 2026

## 1. Amaç ve kapsam

Faz 1'in amacı Gaziantep'te 10–20 düğün salonuyla pilot yapmak. Akış: çift salonda kaparo verir, salon rezervasyonu panele girer, çift uygulamaya telefonuyla girince etkinliği hazır bulur, ücretsiz ya da ücretli davetiyesini oluşturup gönderir. Misafirler zarfı posta kutusunda ya da web linkinde açıp LCV verir.

**Faz 1'de var**

- Salon web paneli: giriş, rezervasyon takvimi, rezervasyon/kaparo takibi, salon profili, personel, LCV özeti
- Admin web paneli: salon hesabı açma, şablon yönetimi, kayıtları görme
- Mobil uygulama: telefon + SMS koduyla giriş, posta kutusu, zarf açma, davetiye görüntüleme ve LCV, etkinlik ve davetiye oluşturma, davetli seçimi, gönderim, LCV takibi
- Ücretsiz sürüm ve uygulama içi satın almayla "Etkinlik Paketi"
- Uygulaması olmayan misafir için web davetiye sayfası (`/z/{token}`)
- 4 ücretsiz + 2 premium şablon (bizim yaptığımız)
- Push bildirim (yeni zarf, yeni LCV)

**Faz 1'de yok (ekleme)**

Kartvizit · tasarımcı pazaryeri · dijital takı/hediye · sesli davet araması · SMS ile davetiye gönderimi (yalnızca giriş kodu SMS'i var) · çok dil · iki ailenin ortak liste yönetimi · teşekkür kartı · anı albümü · salon panelinin mobil sürümü · salona gelir payı · halka açık "boş tarih ara" özelliği.

## 2. Roller ve giriş

| Rol | Nerede | Giriş | Hesabı kim açar |
| --- | --- | --- | --- |
| Admin (biz) | Web `/admin` | E-posta + şifre | Seeder / komut |
| Salon kullanıcısı (`owner`, `staff`) | Web `/salon` | E-posta **veya** telefon + şifre | Admin (owner'ı); owner personeli ekler |
| Uygulama kullanıcısı | Mobil | Telefon + 6 haneli SMS kodu | Kullanıcı kendisi |

- Çift ve misafir aynı hesap türüdür. Herkes hem davetiye alabilir hem oluşturabilir.
- Salon kullanıcısı ilk girişte şifresini e-postadaki bağlantıyla belirler. Şifre sıfırlama e-postayla.
- Ayrı auth guard'lar: `admin` (admins tablosu), `salon` (salon_users tablosu), `sanctum` (users, mobil token).

**OTP kuralları**

- Kod 6 haneli, 5 dakika geçerli, veritabanında hash'li saklanır.
- Aynı numaraya 60 saniyede 1, saatte 5 kod. Aynı IP'den saatte 20 istek.
- Bir koda en fazla 5 yanlış deneme; sonra kod geçersiz.
- Yanıtlar numaranın kayıtlı olup olmadığını belli etmez.
- `local` ve `testing` ortamında SMS gönderilmez, kod loga yazılır ve `123456` her zaman kabul edilir. Bu davranış başka hiçbir ortamda açılamaz.
- Faz 1 yalnızca Türkiye numaralarını kabul eder ama E.164 saklar (sonra yurt dışı açılacak).

## 3. Ana akışlar

### 3.1 Salon rezervasyonu → etkinlik

1. Salon kullanıcısı panelde rezervasyon oluşturur: iletişim adı, çiftin telefonu, etkinlik türü, başlık ("Elif & Emre"), tarih, saat aralığı, ücretler, durum.
2. Kayıtla birlikte bir `event` oluşur ya da güncellenir: `salon_id` dolu, `created_by = salon`, mekan adı/adresi/konumu salondan kopyalanır, `couple_phone` = çiftin telefonu.
3. O telefonla kayıtlı kullanıcı varsa `events.owner_user_id` hemen bağlanır ve kullanıcıya push gider ("Salonun etkinliğini ekledi"). Yoksa bağlama ilk girişte yapılır (§3.7).
4. Rezervasyon `cancelled` olursa etkinlik iptal işaretlenir; gönderilmiş davetiye alıcılara "iptal edildi" olarak görünür.

### 3.2 Çift davetiye oluşturur

1. Etkinliklerim → etkinlik → "Davetiye oluştur".
2. Şablon seçer (premium olanlar paketsiz kilitli), metinleri düzenler, zarf rengi / mühür rengi / mühür harflerini seçer, canlı önizlemeyi görür, kaydeder.
3. Salonun eklediği etkinlikte tarih, saat ve mekan çift tarafından değiştirilemez (salon değiştirir). Kullanıcının kendi oluşturduğu etkinlikte değiştirilebilir.

### 3.3 Etkinlik Paketi satın alma

Paywall şu durumlarda açılır: premium şablon/renk seçimi, davetli sayısının ücretsiz sınırı aşması. Satın alma mağaza üzerinden yapılır, sunucu doğrular, `events.pack_purchased_at` dolar. Paket o etkinliğe aittir. Ayrıntı §12.

### 3.4 Gönderim

1. Çift davetlileri rehberden seçer ya da numara girer. Uygulama, rehberdeki numaraların hash'lerini sunucuya gönderip hangilerinin kayıtlı olduğunu öğrenir ve rozet gösterir.
2. "Gönder" ile henüz gönderilmemiş tüm alıcılar gönderilir:
   - Kayıtlı kullanıcı → `channel = in_app`, posta kutusuna düşer, push gider.
   - Kayıtlı değil → `channel = link`, kişiye özel `/z/{token}` linki üretilir.
3. Sonuç ekranında link alıcıları listelenir; her birinde "WhatsApp'ta gönder" (`https://wa.me/<numara-başında-+-olmadan>?text=<mesaj>`) ve "Linki kopyala" vardır. Mesaj: `"{başlık} sizi davet ediyor. Davetiyenizi açmak için: {url}"`.
4. Sonradan eklenen davetliler için "Gönder" tekrar çağrılabilir; zaten gönderilmiş olanlara tekrar gönderilmez.

### 3.5 Misafir uygulamada

Push'a ya da posta kutusundaki zarfa dokunur → zarf açma ekranı → mühre dokunur (titreşim + animasyon) → davetiye → LCV (katılıyorum + kişi sayısı / katılamıyorum). LCV son tarihe kadar değiştirilebilir.

### 3.6 Misafir web linkinde

`/z/{token}` açılır → aynı zarf animasyonu (CSS) → davetiye → LCV formu → teşekkür ekranı ve uygulamayı indirme bağlantıları. İlk açılışta `opened_at` dolar.

### 3.7 Girişte bağlama

Kullanıcı OTP'yi doğruladığı anda, aynı transaction içinde:

- `recipients.phone` = kullanıcının numarası olan ve `user_id` boş tüm kayıtlar kullanıcıya bağlanır. Bunlar posta kutusunda görünür; daha önce web'den verdiği LCV korunur. `channel` değişmez.
- `events.couple_phone` = kullanıcının numarası olan ve `owner_user_id` boş etkinlikler kullanıcıya bağlanır.

## 4. Ücretsiz ve ücretli

| | Ücretsiz | Etkinlik Paketi |
| --- | --- | --- |
| Şablonlar | Ücretsiz 4 şablon | Tüm şablonlar |
| Davetli sayısı | 30 | Sınırsız (kötüye kullanım için teknik üst sınır 1.000) |
| Zarf rengi | Ada çayı, Lacivert | 4 renk |
| Mühür rengi | Bordo | 4 renk |
| LCV takibi, web linki, push | Var | Var |
| Fiyat | 0 | Mağazada belirlenir (varsayım 250 TL); uygulama fiyatı mağazadan okur |

- Ürün kimliği her iki mağazada `event_pack`, türü **consumable** (her etkinlik için ayrı alınır).
- Sınırlar `config/zarf.php` içinde: `free_recipient_limit` (30), `max_recipient_limit` (1000), `free_envelope_colors` (`sage`, `navy`), `free_seal_colors` (`bordo`). Şablonun premium olup olmadığı `templates.is_premium` alanından gelir.
- Sunucu, paketsiz etkinlikte premium şablon/renk kaydını ve 30'u aşan davetli eklemeyi `402` + `{"code": "pack_required"}` ile reddeder.

## 5. Veri modeli

Tüm tablolarda `id`, `created_at`, `updated_at` var. Enum'lar PHP backed enum.

**users** — `phone` (E.164, unique) · `phone_hash` (SHA-256 hex, unique) · `name` · `kvkk_accepted_at` · `kvkk_version`

**otp_requests** — `phone` · `code_hash` · `expires_at` · `attempts` · `consumed_at` · `ip`

**devices** — `user_id` · `platform` (`ios`, `android`) · `fcm_token` (unique) · `last_seen_at`

**admins** — `name` · `email` (unique) · `password`

**salons** — `name` · `phone` · `email` · `address` · `city` · `district` · `latitude` · `longitude` · `logo_path` · `capacity` · `is_active`

**salon_users** — `salon_id` · `name` · `email` (unique) · `phone` (E.164, unique, nullable) · `password` · `role` (`owner`, `staff`) · `last_login_at`

**events** — `owner_user_id` (nullable) · `salon_id` (nullable) · `created_by` (`user`, `salon`) · `type` (`wedding`, `engagement`, `henna`, `circumcision`, `birthday`, `other`) · `title` · `starts_at` · `ends_at` (nullable) · `venue_name` · `venue_address` · `latitude` · `longitude` · `couple_phone` (nullable) · `share_headcount_with_salon` (bool, varsayılan true) · `pack_purchased_at` (nullable) · `cancelled_at` (nullable)

**reservations** — `salon_id` · `event_id` (unique) · `contact_name` · `contact_phone` · `status` (`option`, `confirmed`, `cancelled`) · `total_amount` · `deposit_amount` · `paid_amount` (kuruş) · `notes`
Rezervasyon salonun iç kaydıdır; ücret bilgileri asla mobil API'den dönmez.

**templates** — `name` · `slug` (unique) · `event_types` (json dizi) · `is_premium` · `config` (json, §11) · `sort_order` · `is_active`

**invitations** — `event_id` (unique; Faz 1'de etkinlik başına bir davetiye) · `template_id` · `texts` (json: `label`, `intro`, `names`, `message`) · `envelope_color` · `seal_color` · `seal_text` (en fazla 4 karakter) · `rsvp_deadline` (date, nullable) · `first_sent_at`

**recipients** — `invitation_id` · `phone` · `phone_hash` · `name` · `user_id` (nullable) · `token` (40 karakter, unique) · `channel` (`in_app`, `link`, gönderimde belirlenir) · `sent_at` · `opened_at` · `rsvp_status` (`pending`, `attending`, `not_attending`) · `guest_count` (1–10, nullable) · `responded_at` · unique(`invitation_id`, `phone`)

**purchases** — `user_id` · `event_id` · `platform` · `product_id` · `transaction_id` (unique) · `status` (`verified`, `failed`, `refunded`) · `payload` (json) · `verified_at`

## 6. Mobil API (`/api/v1`)

JSON; hata mesajları Türkçe `message` alanında; doğrulama hataları `422`; yetkisiz erişim `404` (varlığı belli etme). Kimlik: Sanctum bearer token.

### 6.1 Kimlik

| Uç | Gövde | Sonuç |
| --- | --- | --- |
| `POST /auth/otp` | `phone` | `204` |
| `POST /auth/verify` | `phone`, `code`, `device_name` | `{token, user, is_new}`; §3.7 bağlaması burada |
| `GET /me` | — | kullanıcı |
| `PATCH /me` | `name`, `kvkk_accepted` (true) | kullanıcı |
| `POST /auth/logout` | — | `204`, mevcut token silinir |
| `DELETE /me` | — | `204`; §14 hesap silme |
| `POST /devices` | `platform`, `fcm_token` | `204`, upsert |

### 6.2 Posta kutusu ve LCV

| Uç | Açıklama |
| --- | --- |
| `GET /inbox` | Kullanıcıya bağlı ve gönderilmiş zarflar. Alanlar: `id` (recipient id), `title`, `event_type`, `starts_at`, `sent_at`, `opened_at`, `rsvp_status`, `envelope_color`, `seal_color`, `seal_text`, `cancelled`. Sıra: açılmamışlar önce, sonra etkinlik tarihi. |
| `GET /inbox/{recipient}` | Davetiyeyi çizmek için her şey: şablon `config`, `texts`, etkinlik (tarih, saat, mekan, koordinat), zarf seçenekleri, `rsvp_status`, `guest_count`, `rsvp_deadline`. |
| `POST /inbox/{recipient}/open` | `opened_at` boşsa doldurur. |
| `POST /inbox/{recipient}/rsvp` | `status` (`attending`/`not_attending`), `guest_count` (attending ise 1–10). Son tarih geçtiyse `422`. Çifte push gider. |

### 6.3 Etkinlik, şablon, davetiye

| Uç | Açıklama |
| --- | --- |
| `GET /events` | Kullanıcının sahibi olduğu etkinlikler; salondan gelenlerde `salon: {name}` ve `locked_fields`. |
| `POST /events` | `type`, `title`, `starts_at`, `ends_at`, `venue_name`, `venue_address`, `latitude`, `longitude` |
| `GET /events/{event}` · `PATCH /events/{event}` | Salonun eklediği etkinlikte `starts_at`, `ends_at`, `venue_*` alanları değiştirilemez (`422`). |
| `PATCH /events/{event}/headcount-sharing` | `share` (bool) |
| `GET /templates?event_type=` | Aktif şablonlar; her birinde `is_premium`. |
| `GET /events/{event}/invitation` | Davetiye ya da `404`. |
| `PUT /events/{event}/invitation` | `template_id`, `texts`, `envelope_color`, `seal_color`, `seal_text`, `rsvp_deadline`. Paketsiz premium seçim → `402 pack_required`. |

### 6.4 Davetliler ve gönderim

| Uç | Açıklama |
| --- | --- |
| `POST /contacts/match` | `hashes` (en fazla 2.000 SHA-256). Yanıt: kayıtlı olanların hash'leri. Hash'ler saklanmaz. |
| `GET /events/{event}/invitation/recipients` | Tüm davetliler ve durumları. |
| `POST /events/{event}/invitation/recipients` | `recipients: [{phone, name}]`. Normalize eder, tekrarları atlar. Toplam sınırı aşarsa `402 pack_required`, hiçbiri eklenmez. |
| `DELETE /events/{event}/invitation/recipients/{recipient}` | Açılmamış davetli silinebilir; açılmışsa `422`. |
| `POST /events/{event}/invitation/send` | §3.4. Yanıt: `{in_app_count, link_recipients: [{id, name, phone, url, whatsapp_url}]}` |
| `GET /events/{event}/invitation/stats` | `sent`, `opened`, `attending` (hane), `attending_guests` (kişi), `not_attending`, `pending`, `unopened` |

### 6.5 Satın alma

| Uç | Açıklama |
| --- | --- |
| `POST /purchases` | `platform`, `product_id`, `event_id`, `verification_data` (mağazanın verdiği doğrulama verisi). Doğrulanırsa `events.pack_purchased_at` dolar. Aynı `transaction_id` ikinci kez gelirse aynı başarılı yanıt döner (idempotent). Paketi zaten olan etkinlik → `409`. |

## 7. Salon paneli (Filament, `/salon`)

Panelin işi salonu uygulamaya bağlı tutmak: rezervasyonlarını burada yönetirlerse veri kendiliğinden oluşur.

- **Giriş:** e-posta veya telefon + şifre (tek alan; `@` içeriyorsa e-posta, değilse telefon olarak normalize edilir). "Şifremi unuttum" e-postayla.
- **Pano:** bugünkü ve bu haftaki etkinlikler, bu ayın rezervasyon sayısı, yaklaşan 5 etkinlik (davetiye gönderildi mi rozetiyle).
- **Takvim:** aylık görünüm; her gün `kesin`, `opsiyon` ya da boş. Güne tıklayınca o günün rezervasyonları ve "Rezervasyon ekle". Uygun bir Filament takvim eklentisi yoksa basit aylık grid olarak kendi bileşenini yaz.
- **Rezervasyonlar:** liste (tarih, başlık, iletişim, durum, kalan alacak, çift bağlandı mı, davetiye gönderildi mi) ve form (§3.1 alanları). Kalan alacak = toplam − ödenen. Aynı salonda saatleri çakışan `confirmed` rezervasyon varsa kaydetmeden önce uyarı ve onay.
- **Rezervasyon detayı:**
  - LCV özeti: çift paylaşımı açtıysa katılıyor (kişi), katılamıyor, bekleyen, açılmadı. Kapalıysa "Çift kişi sayısını paylaşmıyor".
  - "Çifti davet et" kutusu: uygulama indirme sayfasının QR kodu ve kısa linki, çiftin numarasına WhatsApp mesajı butonu ("Davetiyenizi hazırlamak için uygulamayı indirin ve {telefon} numarasıyla giriş yapın: {link}").
- **Salon profili** (yalnız owner): ad, telefon, e-posta, adres, il, ilçe, konum (enlem/boylam), logo, kapasite.
- **Kullanıcılar** (yalnız owner): personel ekle, devre dışı bırak.
- Her sorgu `salon_id` ile kapsanır. Salon A'nın kullanıcısı salon B'nin hiçbir kaydına URL ile bile erişemez (test).

## 8. Admin paneli (Filament, `/admin`)

- **Salonlar:** oluştur/düzenle/pasifleştir; oluştururken owner kullanıcısını da aç ve şifre belirleme e-postası gönder.
- **Şablonlar:** liste, oluştur/düzenle (config JSON editörü + önizleme), aktif/pasif, sıralama.
- **Kullanıcılar, Etkinlikler, Satın almalar:** salt okunur listeler ve filtreler.
- **Pano:** toplam kullanıcı, bu hafta gönderilen zarf, açılma oranı, satılan paket.

## 9. Web davetiye sayfası (`/z/{token}`)

- Laravel Blade, harici JS çatısı yok. Mobil öncelikli, `noindex, nofollow`.
- Zarf animasyonu CSS ile (kapak `rotateX`, kart yukarı kayar, mühür kaybolur). Tasarım §15'teki tokenlarla.
- Davetiye kartı §11'deki şablon config'iyle uygulamadakiyle aynı görünür.
- LCV formu: Katılıyorum (kişi sayısı 1–10) / Katılamıyorum. Gönderince teşekkür ekranı ve App Store / Google Play bağlantıları (şimdilik yer tutucu).
- Geçersiz token → 404 sayfası. İptal edilmiş etkinlik → "Bu etkinlik iptal edildi".
- Ayrıca statik sayfalar: `/aydinlatma-metni`, `/gizlilik`, `/kullanim-kosullari` (metinler "[Hukukçu onayı bekliyor]" yer tutucusuyla).
- Uygulama indirme sayfası `/indir` (salon QR'ı buraya gider): mağaza bağlantıları.

## 10. Mobil ekranlar

| Kod | Ekran | Kabul kriterleri |
| --- | --- | --- |
| M1 | Tanıtım | Tek ekran, zarf görseli, "Başla". |
| M2 | Telefon girişi | `+90` sabit, 10 hane doğrulaması, "Kod gönder". |
| M3 | Kod doğrulama | 6 kutucuk, 60 sn sonra "Tekrar gönder", hatalı kodda açıklayıcı mesaj. |
| M4 | Profil tamamlama | Yalnız ilk girişte: ad soyad + KVKK açık rıza onay kutusu + aydınlatma metni bağlantısı. |
| M5 | Sekmeler | Posta kutusu · Etkinliklerim · Profil |
| M6 | Posta kutusu | "Açılmamış" ve "Açılmış" bölümleri; zarf kartı (zarf rengi, mühür, gönderen başlığı, tarih); boş durum; çekerek yenileme. |
| M7 | Zarf açma | Koyu tam ekran; mühre dokununca `HapticFeedback.mediumImpact`, kapak açılır, kart çıkar, "Davetiyeyi oku". `open` çağrılır. |
| M8 | Davetiye + LCV | Şablon çizimi; "Takvime ekle", "Yol tarifi" (harita uygulaması); LCV seçimi, kişi sayacı, gönder; yanıt sonrası onay; son tarih geçtiyse kilitli. |
| M9 | Etkinliklerim | Salondan gelenler "Salon tarafından eklendi" rozetiyle; kendi etkinlikleri; "Yeni etkinlik". |
| M10 | Etkinlik oluştur | Tür, başlık, tarih-saat, mekan adı, adres. |
| M11 | Etkinlik detayı | Durum kartı (davetiye yok / taslak / gönderildi), "Davetiyeyi düzenle", LCV özeti, salonla kişi sayısı paylaşımı anahtarı (salon etkinliğinde). |
| M12 | Davetiye düzenleyici | 3 adım: şablon (premium kilit rozeti) → metinler (etiket, giriş, isimler, mesaj, LCV son tarihi) → zarf rengi, mühür rengi, mühür harfleri; her adımda canlı önizleme. |
| M13 | Davetli seçimi | Rehber izni ve açıklaması, çoklu seçim, elle numara ekleme, "Uygulamada" rozeti, `x/30` sayacı; sınır aşılınca M14. |
| M14 | Paywall | Paket içeriği (§4), mağazadan gelen fiyat, "Satın al"; satın alma sürerken bekleme durumu; hata mesajı. |
| M15 | Gönder | Özet (x uygulamaya, y linkle) → Gönder → sonuç: link alıcıları listesi, her birinde "WhatsApp'ta gönder" ve "Linki kopyala". |
| M16 | LCV takibi | Sayaçlar (§6.4 stats) + liste; filtre: tümü / katılıyor / katılamıyor / bekliyor / açılmadı; link alıcısına WhatsApp hatırlatma butonu. |
| M17 | Profil | Ad, telefon, bildirim izni, aydınlatma metni, çıkış, "Hesabımı sil" (onay diyaloğu). |

- Push'a dokunmak M7'yi açar (`data.recipient_id`).
- Tarih biçimi Türkçe (`intl`, `tr_TR`): "12 Haziran 2027, Cumartesi · 19:00".
- Ortak widget'lar: `EnvelopeView` (renk, mühür, açık/kapalı animasyonu), `InvitationCard` (§11 config'ten çizim). M6, M7, M8, M12 bunları kullanır.

## 11. Şablon sistemi

Faz 1 şablonları görsel dosya gerektirmez; kart, config'teki renk, font ve süslemeyle kodla çizilir. Mobil (`InvitationCard`) ve web (`/z`) aynı config'i aynı kurallarla çizer.

**Config şeması (v1)**

```json
{
  "v": 1,
  "paper": "#FFFFFF",
  "ink": "#17182A",
  "muted": "#5F6273",
  "accent": "#8A1F3D",
  "frame": "double",
  "frame_color": "#ECE4E8",
  "display_font": "Fraunces",
  "body_font": "Plus Jakarta Sans",
  "ornament": "dot-rule"
}
```

- `frame`: `none` | `single` | `double` · `ornament`: `none` | `dot-rule` (iki çizgi arasında nokta) | `top-band` (üstte kısa kalın şerit)
- Çizim sırası sabit, ortalanmış: `label` (küçük, harf aralıklı, accent) → `intro` (muted) → `names` (display font, büyük) → `message` (muted) → süsleme → tarih (display font) → gün ve saat → mekan adı → mekan adresi.
- `texts` davetiyeden; tarih, saat ve mekan etkinlikten gelir.

**Seed şablonları**

| slug | Ad | Premium | paper | ink / muted | accent | frame | display_font | ornament |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `klasik` | Klasik | Hayır | `#FFFFFF` | `#17182A` / `#5F6273` | `#8A1F3D` | double `#ECE4E8` | Fraunces | dot-rule |
| `modern` | Modern | Hayır | `#FFFFFF` | `#17182A` / `#5F6273` | `#17182A` | none | Plus Jakarta Sans | top-band |
| `bahar` | Bahar | Hayır | `#F3F6F1` | `#17182A` / `#56614F` | `#5E7C55` | single `#D5DFD0` | Fraunces | top-band |
| `minimal` | Minimal | Hayır | `#FAFAFA` | `#17182A` / `#5F6273` | `#5F6273` | single `#E3E4EA` | Plus Jakarta Sans | none |
| `altin-varak` | Altın Varak | Evet | `#FFFFFF` | `#17182A` / `#5F6273` | `#A8833A` | double `#E6D9BC` | Fraunces | dot-rule |
| `gece-mavisi` | Gece Mavisi | Evet | `#1D2340` | `#FFFFFF` / `#C9CBDA` | `#D9B46A` | single `#3A4266` | Fraunces | dot-rule |

Tüm şablonlar tüm etkinlik türlerinde kullanılabilir.

**Etkinlik türüne göre varsayılan metinler**

| type | label | message |
| --- | --- | --- |
| wedding | DÜĞÜN DAVETİ | düğünümüzde sizi de aramızda görmekten mutluluk duyarız. |
| engagement | NİŞAN DAVETİ | nişanımızda sizi de aramızda görmekten mutluluk duyarız. |
| henna | KINA GECESİ DAVETİ | kına gecemizde sizi de aramızda görmekten mutluluk duyarız. |
| circumcision | SÜNNET DÜĞÜNÜ DAVETİ | oğlumuzun sünnet düğününde sizi de aramızda görmekten mutluluk duyarız. |
| birthday | DOĞUM GÜNÜ DAVETİ | doğum günümüzde sizi de aramızda görmek isteriz. |
| other | DAVET | sizi de aramızda görmekten mutluluk duyarız. |

`intro` varsayılanı boş ("Yılmaz ve Demir aileleri olarak" gibi kullanıcı yazar); `names` varsayılanı etkinlik başlığı.

## 12. Satın alma doğrulama

- Mobil: `in_app_purchase` paketi; ürün `event_pack`, `buyConsumable` ile alınır. Satın alma akışından gelen doğrulama verisi sunucuya `POST /purchases` ile gönderilir; sunucu onaylayınca `completePurchase` çağrılır. Uygulama açılışında bekleyen (tamamlanmamış) satın almalar da sunucuya gönderilip tamamlanır.
- Sunucu: `PurchaseVerifier` arayüzü.
  - `FakePurchaseVerifier`: yalnız `local`/`testing`; `verification_data = "fake-ok"` kabul eder.
  - `AppleVerifier`: App Store Server API ile işlem doğrulama.
  - `GooglePlayVerifier`: Google Play Developer API ile satın alma doğrulama.
  - Gerçek doğrulayıcıların kimlik bilgileri (App Store Connect API anahtarı, Google servis hesabı) `.env`'den gelir; yoksa açık bir hata verir, sessizce kabul etmez.
- `transaction_id` unique; aynı işlem iki kez paket açmaz.
- Faz 1'de iade bildirimleri (App Store Server Notifications / Play RTDN) yok; `refunded` durumu elle işaretlenebilir.

## 13. Bildirimler

- Firebase Cloud Messaging (iOS dahil). Sunucuda kuyruk işi (`database` kuyruğu) ile gönderilir.
- Olaylar: yeni zarf (alıcıya), yeni LCV (davetiye sahibine), salon etkinlik ekledi (çifte).
- Metinler: "📬" gibi emoji yok. Örnek: "Posta kutunda yeni bir zarf var — Elif & Emre".
- Geçersiz FCM token'ı silinir.

## 14. Güvenlik ve KVKK

- Yetki: kullanıcı yalnız kendine bağlı `recipients` kaydına, sahibi olduğu `events`'e erişir; salon kullanıcısı yalnız kendi salonuna. Her biri için test.
- `recipients.token`: `Str::random(40)`; `/z` sayfaları listelenmez, `noindex`.
- Rehber: eşleştirme için yalnız SHA-256 hash'ler gönderilir ve saklanmaz. Ham numara yalnız kullanıcının seçtiği davetliler için gönderilir. Telefon hash'i de kişisel veri sayılır.
- Açık rıza: `kvkk_accepted_at` ve `kvkk_version` saklanır.
- Hesap silme (`DELETE /me`): kullanıcı, cihazları, token'ları, sahibi olduğu etkinlikler/davetiyeler/davetliler silinir; aldığı zarflarda `user_id` boşaltılır (çiftin listesi bozulmaz). Salonun eklediği etkinlik silinmez, yalnız `owner_user_id` boşaltılır.
- Loglarda OTP kodu (üretimde) ve tam telefon numarası yok.
- Tüm uçlarda hız sınırı (varsayılan API limiti + OTP'ye özel limitler).

## 15. Tasarım tokenları

| Token | Değer |
| --- | --- |
| Zemin | `#F2F3F5` |
| Yüzey | `#FFFFFF` |
| Mürekkep (ana metin) | `#17182A` |
| İkincil metin | `#5F6273` |
| Kenarlık | `#DDDFE6` |
| Vurgu (bordo) | `#8A1F3D` |
| Vurgu açık | `#F6E7EC` |
| Başarı | `#23603F` / zemin `#E3F0E8` |
| Gövde fontu | Plus Jakarta Sans (400, 500, 600, 700) |
| Başlık / davetiye fontu | Fraunces (500, 600) |
| Köşe yarıçapı | kart 20–24, buton 16, giriş alanı 14 |
| Dokunma alanı | en az 44×44 |

**Zarf renkleri** (`envelope_color` anahtarları): `sage` Ada çayı `#B7C4B1` · `navy` Lacivert `#2E3B5E` · `blush` Pudra `#E8CBC6` · `sand` Kum `#DCCFB8`. Kapak rengi = zarf × 0,88; iç renk = zarf × 0,62.

**Mühür renkleri** (`seal_color`): `bordo` `#8A1F3D` · `gold` Altın `#A8833A` · `ink` Mürekkep `#1D2340` · `forest` Orman `#2F5A45`. Mühür: daire, içinde %40 beyaz ince halka, beyaz Fraunces harfler.

Zarf açma ekranı zemini `#17182A`. Durum çubuğu taklidi yok, emoji yok.

## 16. Uçtan uca kabul senaryoları

1. Salon kullanıcısı `+905321112233` numaralı çift için rezervasyon açar. Çift bu numarayla giriş yapınca Etkinliklerim'de salonun etkinliğini görür; tarih ve mekanı değiştiremez.
2. Çift ücretsiz şablonla davetiye oluşturur, 3 kişi ekler (1'i kayıtlı) ve gönderir. Kayıtlı olan posta kutusunda zarfı görür ve push alır; diğer 2'si için WhatsApp linki döner.
3. Link alan misafir web'de zarfı açıp 2 kişilik katılım bildirir. Çiftin istatistiğinde ve (paylaşım açıksa) salon panelinde 2 kişi görünür; paylaşım kapatılınca salon sayıyı göremez.
4. Linkle davet edilen kişi sonradan aynı numarayla uygulamaya kaydolur. Zarf posta kutusunda görünür, web'den verdiği LCV korunur.
5. Çift 31. davetliyi eklemeye çalışınca `402` ve paywall. Sahte doğrulayıcıyla satın alınca sınır kalkar, premium şablon ve renkler açılır. Aynı işlem tekrar gönderilince ikinci kez paket açılmaz.
6. Salon A kullanıcısı salon B'nin rezervasyonunu URL'yi tahmin ederek bile açamaz.
7. Kullanıcı hesabını silince aldığı zarflar çiftin listesinde kalır ama kullanıcıya bağlı değildir.

## 17. Açık sorular (koda başlamadan önce ya da ilgili adımda karar verilecek)

- Uygulamanın kalıcı adı ve bundle kimliği (sonradan değiştirmek zahmetli)
- SMS sağlayıcısı (giriş kodu için)
- Alan adı (`/z` linkleri ve panel için)
- Etkinlik Paketi'nin mağaza fiyatı
- KVKK metinleri (hukukçu)
- Sunucu ve barındırma
