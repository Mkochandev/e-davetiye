<?php

namespace App\Support;

use App\Services\PhoneNumber;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * Salon panelindeki "Çifti davet et" kutusu: indirme linki, QR ve WhatsApp mesajı (spec §7).
 */
class CoupleInvite
{
    public static function downloadUrl(): string
    {
        return url('/indir');
    }

    public static function message(string $couplePhone): string
    {
        $phone = app(PhoneNumber::class)->formatNational($couplePhone);

        return "Davetiyenizi hazırlamak için uygulamayı indirin ve {$phone} numarasıyla giriş yapın: ".self::downloadUrl();
    }

    public static function whatsappUrl(string $couplePhone): string
    {
        return PhoneNumber::whatsappUrl($couplePhone, self::message($couplePhone));
    }

    public static function qrSvg(int $size = 180): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle($size, 1), new SvgImageBackEnd));

        return $writer->writeString(self::downloadUrl());
    }
}
