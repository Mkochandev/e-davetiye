<?php

namespace App\Services\Purchases;

use RuntimeException;

/**
 * Mağaza API'leri için asgari JWT: ES256 (App Store Server API) ve RS256 (Google servis hesabı).
 * Yalnız imzalama ve doğrulanmamış payload okuma; ayrı bir paket gerektirmez (ext-openssl).
 */
class Jwt
{
    /**
     * @param  array<string, mixed>  $header
     * @param  array<string, mixed>  $payload
     */
    public static function sign(array $header, array $payload, string $privateKeyPem): string
    {
        $alg = $header['alg'] ?? throw new RuntimeException('JWT alg eksik.');
        $input = self::b64(json_encode($header, JSON_UNESCAPED_SLASHES)).'.'.self::b64(json_encode($payload, JSON_UNESCAPED_SLASHES));

        $key = openssl_pkey_get_private($privateKeyPem) ?: throw new RuntimeException('Özel anahtar okunamadı.');
        if (! openssl_sign($input, $signature, $key, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('JWT imzalanamadı.');
        }

        if ($alg === 'ES256') {
            $signature = self::derToRaw($signature, 32);
        } elseif ($alg !== 'RS256') {
            throw new RuntimeException("Desteklenmeyen JWT algoritması: {$alg}");
        }

        return $input.'.'.self::b64($signature);
    }

    /**
     * JWS'in payload'ını imzayı doğrulamadan okur. Yalnız Apple'dan TLS ile doğrudan alınan ya da
     * istemciden gelen ve sonra Apple'a sorulacak kimliği bulmak için kullanılır.
     *
     * @return array<string, mixed>|null
     */
    public static function unverifiedPayload(string $jws): ?array
    {
        $parts = explode('.', $jws);
        if (count($parts) !== 3) {
            return null;
        }

        $json = base64_decode(strtr($parts[1], '-_', '+/'), true);
        $data = $json === false ? null : json_decode($json, true);

        return is_array($data) ? $data : null;
    }

    public static function b64(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * OpenSSL ECDSA imzası DER (SEQUENCE{INTEGER r, INTEGER s}); JWS ise r||s ister (RFC 7518 §3.4).
     */
    private static function derToRaw(string $der, int $partLength): string
    {
        $offset = 2;
        if ((ord($der[1]) & 0x80) !== 0) {
            $offset += ord($der[1]) & 0x7F;
        }

        $raw = '';
        for ($i = 0; $i < 2; $i++) {
            $length = ord($der[$offset + 1]);
            $int = substr($der, $offset + 2, $length);
            $offset += 2 + $length;
            $raw .= str_pad(ltrim($int, "\x00"), $partLength, "\x00", STR_PAD_LEFT);
        }

        return $raw;
    }
}
