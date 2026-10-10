<?php

namespace App\Services\Purchases;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * App Store Server API ile işlem doğrulama.
 *
 * İstemci (in_app_purchase / StoreKit 2) işlemin JWS'ini gönderir. Ondan yalnız `transactionId` okunur;
 * asıl bilgi Apple'a "Get Transaction Info" ile sorulur ve Apple'ın TLS üzerinden döndürdüğü
 * `signedTransactionInfo` kullanılır. Böylece istemcinin gönderdiği içeriğe güvenilmez.
 *
 * - Get Transaction Info: https://developer.apple.com/documentation/appstoreserverapi/get-transaction-info
 *   Üretim `https://api.storekit.apple.com/inApps/v1/transactions/{transactionId}`,
 *   sandbox `https://api.storekit-sandbox.apple.com/...`; bulunamazsa 404.
 * - JWT: https://developer.apple.com/documentation/appstoreserverapi/generating-json-web-tokens-for-api-requests
 *   ES256; header `alg`, `kid`, `typ`; payload `iss`, `iat`, `exp` (en çok 60 dk), `aud=appstoreconnect-v1`, `bid`.
 * - Alanlar: https://developer.apple.com/documentation/appstoreserverapi/jwstransactiondecodedpayload
 *   (`transactionId`, `bundleId`, `productId`, `type` = `Consumable`, `revocationDate`, `environment`).
 */
class AppleVerifier implements PurchaseVerifier
{
    public const PRODUCTION_URL = 'https://api.storekit.apple.com';

    public const SANDBOX_URL = 'https://api.storekit-sandbox.apple.com';

    /**
     * @param  array{key_id: ?string, issuer_id: ?string, bundle_id: ?string, private_key: ?string}  $config
     */
    public function __construct(private readonly array $config) {}

    public function verify(string $productId, string $verificationData): VerifiedPurchase
    {
        $transactionId = $this->transactionIdFrom($verificationData)
            ?? throw new PurchaseRejected('App Store işlem kimliği okunamadı.');

        $token = $this->token();

        // Apple'ın önerisi: önce üretim; işlem orada yoksa sandbox (TestFlight/sandbox alımları yalnız orada).
        $response = $this->fetch(self::PRODUCTION_URL, $transactionId, $token);
        if ($response->status() === 404) {
            $response = $this->fetch(self::SANDBOX_URL, $transactionId, $token);
        }

        if ($response->status() === 404 || $response->status() === 400) {
            throw new PurchaseRejected('App Store işlemi bulunamadı.', $transactionId, ['status' => $response->status()]);
        }

        if (! $response->successful()) {
            throw new StoreUnavailable("App Store Server API yanıtı: {$response->status()}");
        }

        $info = Jwt::unverifiedPayload((string) $response->json('signedTransactionInfo'))
            ?? throw new StoreUnavailable('App Store yanıtında signedTransactionInfo yok.');

        $reason = match (true) {
            ($info['transactionId'] ?? null) !== $transactionId => 'İşlem kimliği eşleşmiyor.',
            ($info['bundleId'] ?? null) !== $this->config['bundle_id'] => 'Uygulama kimliği eşleşmiyor.',
            ($info['productId'] ?? null) !== $productId => 'Ürün eşleşmiyor.',
            ($info['type'] ?? null) !== 'Consumable' => 'Ürün türü beklenen değil.',
            isset($info['revocationDate']) => 'İşlem iade edilmiş.',
            default => null,
        };

        if ($reason !== null) {
            throw new PurchaseRejected($reason, $transactionId, $info);
        }

        return new VerifiedPurchase($transactionId, $productId, $info);
    }

    /**
     * StoreKit 2 JWS'inden `transactionId`; sadece rakamlardan oluşan bir kimlik de kabul edilir.
     */
    private function transactionIdFrom(string $data): ?string
    {
        if (ctype_digit($data)) {
            return $data;
        }

        $id = Jwt::unverifiedPayload($data)['transactionId'] ?? null;

        return is_string($id) && ctype_digit($id) ? $id : null;
    }

    private function fetch(string $baseUrl, string $transactionId, string $token): Response
    {
        try {
            return Http::withToken($token)
                ->acceptJson()
                ->timeout(15)
                ->get("{$baseUrl}/inApps/v1/transactions/{$transactionId}");
        } catch (ConnectionException $e) {
            throw new StoreUnavailable('App Store Server API\'ye ulaşılamadı.', previous: $e);
        }
    }

    private function token(): string
    {
        foreach (['key_id' => 'APPLE_IAP_KEY_ID', 'issuer_id' => 'APPLE_IAP_ISSUER_ID', 'bundle_id' => 'APPLE_BUNDLE_ID', 'private_key' => 'APPLE_IAP_PRIVATE_KEY_PATH'] as $key => $env) {
            if (empty($this->config[$key])) {
                throw new StoreUnavailable("App Store doğrulaması yapılandırılmamış: {$env} eksik.");
            }
        }

        $now = time();

        return Jwt::sign(
            ['alg' => 'ES256', 'kid' => $this->config['key_id'], 'typ' => 'JWT'],
            [
                'iss' => $this->config['issuer_id'],
                'iat' => $now,
                'exp' => $now + 600,
                'aud' => 'appstoreconnect-v1',
                'bid' => $this->config['bundle_id'],
            ],
            $this->config['private_key'],
        );
    }
}
