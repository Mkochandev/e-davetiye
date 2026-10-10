<?php

use App\Enums\Platform;
use App\Services\Purchases\AppleVerifier;
use App\Services\Purchases\FakePurchaseVerifier;
use App\Services\Purchases\GooglePlayVerifier;
use App\Services\Purchases\Jwt;
use App\Services\Purchases\PurchaseRejected;
use App\Services\Purchases\PurchaseVerifiers;
use App\Services\Purchases\StoreUnavailable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/** Yalnız test için üretilmiş anahtarlar (tests/Fixtures/keys). */
function testKey(string $name): array
{
    $dir = base_path('tests/Fixtures/keys');

    return [file_get_contents("{$dir}/{$name}.pem"), file_get_contents("{$dir}/{$name}.pub")];
}

/** Apple'ın döndürdüğü biçimde (imzası bu testte önemsiz) bir JWS. */
function fakeJws(array $payload): string
{
    return Jwt::b64('{"alg":"ES256"}').'.'.Jwt::b64(json_encode($payload)).'.'.Jwt::b64('sig');
}

function appleTransaction(array $override = []): array
{
    return [
        'transactionId' => '2000000123456789',
        'bundleId' => 'com.connectyazilim.zarf',
        'productId' => 'event_pack',
        'type' => 'Consumable',
        'environment' => 'Sandbox',
        ...$override,
    ];
}

function apple(?string $pem): AppleVerifier
{
    return new AppleVerifier([
        'key_id' => '2X9R4HXF34',
        'issuer_id' => '57246542-96fe-1a63-e053-0824d011072a',
        'bundle_id' => 'com.connectyazilim.zarf',
        'private_key' => $pem,
    ]);
}

describe('AppleVerifier', function () {
    beforeEach(fn () => [$this->pem, $this->public] = testKey('ec-test'));

    it('asks Apple about the transaction with an ES256 token and falls back to sandbox', function () {
        Http::fake([
            'api.storekit.apple.com/*' => Http::response(['errorCode' => 4040010, 'errorMessage' => 'Transaction id not found.'], 404),
            'api.storekit-sandbox.apple.com/*' => Http::response(['signedTransactionInfo' => fakeJws(appleTransaction())]),
        ]);

        $result = apple($this->pem)->verify('event_pack', fakeJws(['transactionId' => '2000000123456789']));

        expect($result->transactionId)->toBe('2000000123456789');

        Http::assertSent(function (Request $request) {
            if (! str_starts_with($request->url(), 'https://api.storekit-sandbox.apple.com/inApps/v1/transactions/2000000123456789')) {
                return false;
            }
            [$h, $p, $s] = explode('.', substr($request->header('Authorization')[0], 7));
            $header = json_decode(base64_decode(strtr($h, '-_', '+/')), true);
            $claims = json_decode(base64_decode(strtr($p, '-_', '+/')), true);

            // r||s ham imza, 64 bayt; DER'e çevirip açık anahtarla doğrula.
            $raw = base64_decode(strtr($s, '-_', '+/'));
            $int = fn (string $b) => "\x02".chr(strlen($b = (ord($b[0]) & 0x80 ? "\x00" : '').ltrim($b, "\x00") ?: "\x00")).$b;
            $seq = $int(substr($raw, 0, 32)).$int(substr($raw, 32));
            $der = "\x30".chr(strlen($seq)).$seq;

            return $header === ['alg' => 'ES256', 'kid' => '2X9R4HXF34', 'typ' => 'JWT']
                && $claims['aud'] === 'appstoreconnect-v1'
                && $claims['bid'] === 'com.connectyazilim.zarf'
                && $claims['iss'] === '57246542-96fe-1a63-e053-0824d011072a'
                && $claims['exp'] - $claims['iat'] <= 3600
                && strlen($raw) === 64
                && openssl_verify("{$h}.{$p}", $der, $this->public, OPENSSL_ALGO_SHA256) === 1;
        });
    });

    it('rejects revoked, wrong product and wrong app transactions', function (array $override) {
        Http::fake(['api.storekit.apple.com/*' => Http::response(['signedTransactionInfo' => fakeJws(appleTransaction($override))])]);

        expect(fn () => apple($this->pem)->verify('event_pack', '2000000123456789'))
            ->toThrow(PurchaseRejected::class);
    })->with([
        'revoked' => [['revocationDate' => 1700000000000]],
        'other product' => [['productId' => 'other']],
        'other app' => [['bundleId' => 'com.example.other']],
        'not consumable' => [['type' => 'Non-Consumable']],
    ]);

    it('rejects an unknown transaction in both environments', function () {
        Http::fake(['*' => Http::response(['errorCode' => 4040010], 404)]);

        expect(fn () => apple($this->pem)->verify('event_pack', '2000000123456789'))->toThrow(PurchaseRejected::class);
    });

    it('fails loudly without credentials and when Apple is down', function () {
        Http::fake(['*' => Http::response([], 500)]);

        expect(fn () => apple(null)->verify('event_pack', '2000000123456789'))->toThrow(StoreUnavailable::class, 'APPLE_IAP_PRIVATE_KEY_PATH')
            ->and(fn () => apple($this->pem)->verify('event_pack', '2000000123456789'))->toThrow(StoreUnavailable::class);
    });
});

describe('GooglePlayVerifier', function () {
    beforeEach(function () {
        [$pem, $this->public] = testKey('rsa-test');
        $this->google = new GooglePlayVerifier([
            'package_name' => 'com.connectyazilim.zarf',
            'service_account' => ['client_email' => 'iap@zarf.iam.gserviceaccount.com', 'private_key' => $pem, 'token_uri' => 'https://oauth2.googleapis.com/token'],
        ]);
    });

    it('gets an access token with a signed assertion and checks the purchase', function () {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'ya29.test', 'expires_in' => 3599]),
            'androidpublisher.googleapis.com/*' => Http::response(['purchaseState' => 0, 'consumptionState' => 0, 'orderId' => 'GPA.1234-5678-9012-34567']),
        ]);

        $result = $this->google->verify('event_pack', 'purchase-token-abc');

        expect($result->transactionId)->toBe('GPA.1234-5678-9012-34567');

        Http::assertSent(function (Request $request) {
            if ($request->url() !== 'https://oauth2.googleapis.com/token') {
                return false;
            }
            [$h, $p, $s] = explode('.', $request['assertion']);
            $claims = json_decode(base64_decode(strtr($p, '-_', '+/')), true);

            return $request['grant_type'] === 'urn:ietf:params:oauth:grant-type:jwt-bearer'
                && $claims['scope'] === 'https://www.googleapis.com/auth/androidpublisher'
                && $claims['aud'] === 'https://oauth2.googleapis.com/token'
                && openssl_verify("{$h}.{$p}", base64_decode(strtr($s, '-_', '+/')), $this->public, OPENSSL_ALGO_SHA256) === 1;
        });
        Http::assertSent(fn (Request $r) => $r->url() === 'https://androidpublisher.googleapis.com/androidpublisher/v3/applications/com.connectyazilim.zarf/purchases/products/event_pack/tokens/purchase-token-abc'
            && $r->header('Authorization')[0] === 'Bearer ya29.test');
    });

    it('rejects canceled and pending purchases', function (int $state) {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'ya29.test']),
            'androidpublisher.googleapis.com/*' => Http::response(['purchaseState' => $state, 'orderId' => 'GPA.1']),
        ]);

        expect(fn () => $this->google->verify('event_pack', 'token'))->toThrow(PurchaseRejected::class);
    })->with([1, 2]);

    it('fails loudly without credentials', function () {
        $missing = new GooglePlayVerifier(['package_name' => 'com.connectyazilim.zarf', 'service_account' => null]);

        expect(fn () => $missing->verify('event_pack', 'token'))->toThrow(StoreUnavailable::class, 'GOOGLE_PLAY_SERVICE_ACCOUNT_PATH');
    });
});

it('uses the fake verifier only in local and testing', function () {
    $verifiers = app(PurchaseVerifiers::class);
    expect($verifiers->for(Platform::Ios))->toBeInstanceOf(FakePurchaseVerifier::class);

    app()->detectEnvironment(fn () => 'production');
    try {
        expect($verifiers->for(Platform::Ios))->toBeInstanceOf(AppleVerifier::class)
            ->and($verifiers->for(Platform::Android))->toBeInstanceOf(GooglePlayVerifier::class);
    } finally {
        app()->detectEnvironment(fn () => 'testing');
    }
});
