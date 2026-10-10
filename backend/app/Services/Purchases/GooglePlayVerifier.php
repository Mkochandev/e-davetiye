<?php

namespace App\Services\Purchases;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Google Play Developer API ile satın alma doğrulama. İstemci (in_app_purchase) satın alma token'ını gönderir.
 *
 * - purchases.products.get:
 *   https://developers.google.com/android-publisher/api-ref/rest/v3/purchases.products/get
 *   `GET https://androidpublisher.googleapis.com/androidpublisher/v3/applications/{packageName}/purchases/products/{productId}/tokens/{token}`
 *   kapsam `https://www.googleapis.com/auth/androidpublisher`.
 * - ProductPurchase alanları: https://developers.google.com/android-publisher/api-ref/rest/v3/purchases.products
 *   `purchaseState` 0 satın alındı / 1 iptal / 2 bekliyor; `orderId`; `consumptionState`; `purchaseType` (0 test).
 * - Servis hesabıyla erişim token'ı (JWT bearer, RS256):
 *   https://developers.google.com/identity/protocols/oauth2/service-account#httprest
 */
class GooglePlayVerifier implements PurchaseVerifier
{
    public const API_URL = 'https://androidpublisher.googleapis.com/androidpublisher/v3';

    public const SCOPE = 'https://www.googleapis.com/auth/androidpublisher';

    /**
     * @param  array{package_name: ?string, service_account: ?array<string, mixed>}  $config
     */
    public function __construct(private readonly array $config) {}

    public function verify(string $productId, string $verificationData): VerifiedPurchase
    {
        if (empty($this->config['package_name'])) {
            throw new StoreUnavailable('Google Play doğrulaması yapılandırılmamış: GOOGLE_PLAY_PACKAGE_NAME eksik.');
        }

        $url = sprintf(
            '%s/applications/%s/purchases/products/%s/tokens/%s',
            self::API_URL,
            rawurlencode($this->config['package_name']),
            rawurlencode($productId),
            rawurlencode($verificationData),
        );

        try {
            $response = Http::withToken($this->accessToken())->acceptJson()->timeout(15)->get($url);
        } catch (ConnectionException $e) {
            throw new StoreUnavailable('Google Play Developer API\'ye ulaşılamadı.', previous: $e);
        }

        if (in_array($response->status(), [400, 404, 410], true)) {
            throw new PurchaseRejected('Google Play satın alması bulunamadı.', payload: ['status' => $response->status()]);
        }

        if (! $response->successful()) {
            throw new StoreUnavailable("Google Play Developer API yanıtı: {$response->status()}");
        }

        $purchase = $response->json();
        // Sipariş kimliği işlem kimliğidir; yoksa token'dan türetilir (token'ın kendisi saklanmaz).
        $transactionId = $purchase['orderId'] ?? 'gp-'.hash('sha256', $verificationData);

        $reason = match ($purchase['purchaseState'] ?? null) {
            0 => null,
            2 => 'Ödeme henüz tamamlanmadı.',
            default => 'Satın alma iptal edilmiş.',
        };

        if ($reason !== null) {
            throw new PurchaseRejected($reason, $transactionId, $purchase);
        }

        return new VerifiedPurchase($transactionId, $productId, $purchase);
    }

    private function accessToken(): string
    {
        $account = $this->config['service_account'] ?? null;
        if (empty($account['client_email']) || empty($account['private_key'])) {
            throw new StoreUnavailable('Google Play doğrulaması yapılandırılmamış: GOOGLE_PLAY_SERVICE_ACCOUNT_PATH eksik ya da geçersiz.');
        }

        return Cache::remember('google-play-access-token:'.$account['client_email'], now()->addMinutes(50), function () use ($account) {
            $tokenUri = $account['token_uri'] ?? 'https://oauth2.googleapis.com/token';
            $now = time();
            $assertion = Jwt::sign(
                ['alg' => 'RS256', 'typ' => 'JWT'],
                ['iss' => $account['client_email'], 'scope' => self::SCOPE, 'aud' => $tokenUri, 'iat' => $now, 'exp' => $now + 3600],
                $account['private_key'],
            );

            try {
                $response = Http::asForm()->timeout(15)->post($tokenUri, [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $assertion,
                ]);
            } catch (ConnectionException $e) {
                throw new StoreUnavailable('Google OAuth sunucusuna ulaşılamadı.', previous: $e);
            }

            return $response->json('access_token')
                ?? throw new StoreUnavailable("Google erişim token'ı alınamadı: {$response->status()}");
        });
    }
}
