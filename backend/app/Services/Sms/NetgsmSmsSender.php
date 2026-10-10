<?php

namespace App\Services\Sms;

use RuntimeException;

/**
 * Netgsm iskeleti. Sağlayıcı kesinleşince gerçek API çağrısı yazılacak (spec §17).
 */
class NetgsmSmsSender implements SmsSender
{
    public function __construct(
        private readonly ?string $usercode,
        private readonly ?string $password,
        private readonly ?string $header,
    ) {}

    public function send(string $phone, string $message): void
    {
        if (blank($this->usercode) || blank($this->password) || blank($this->header)) {
            throw new RuntimeException('Netgsm kimlik bilgileri eksik (NETGSM_USERCODE, NETGSM_PASSWORD, NETGSM_HEADER).');
        }

        // TODO: Netgsm SMS gönderim API'sini çağır. Mesaj içeriği (OTP kodu) loglanmamalı.
        throw new RuntimeException('NetgsmSmsSender henüz uygulanmadı.');
    }
}
