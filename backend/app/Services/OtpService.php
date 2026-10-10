<?php

namespace App\Services;

use App\Models\OtpRequest;
use App\Services\Sms\SmsSender;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

/**
 * SMS giriş kodu kuralları (spec §2).
 */
class OtpService
{
    /** Yalnız local/testing'de her zaman kabul edilen kod. Başka ortamda açılamaz. */
    public const MASTER_CODE = '123456';

    public function __construct(private readonly SmsSender $sms) {}

    /**
     * Numaraya yeni kod üretip gönderir. Numaranın kayıtlı olup olmadığından bağımsız davranır.
     *
     * @param  string  $phone  E.164
     *
     * @throws TooManyRequestsHttpException
     */
    public function send(string $phone, ?string $ip): void
    {
        $this->ensureWithinPhoneLimits($phone);

        $code = $this->generateCode();

        OtpRequest::create([
            'phone' => $phone,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(config('zarf.otp.ttl_minutes')),
            'ip' => $ip,
        ]);

        $this->sms->send($phone, "Zarf giriş kodunuz: {$code}. Kod 5 dakika geçerlidir, kimseyle paylaşmayın.");
    }

    /**
     * Numaranın son kodunu doğrular ve tüketir. Başarısızsa kullanıcıya gösterilecek hata mesajını döner.
     * Çağıran transaction içinde çalıştırmalı; yanlış denemede sayaç yine de kalıcı olmalı (hata dönülür, fırlatılmaz).
     *
     * @param  string  $phone  E.164
     */
    public function consume(string $phone, string $code): ?string
    {
        $otp = OtpRequest::query()
            ->where('phone', $phone)
            ->latest('id')
            ->lockForUpdate()
            ->first();

        if (self::masterCodeEnabled() && $code === self::MASTER_CODE) {
            if ($otp && $otp->consumed_at === null) {
                $otp->update(['consumed_at' => now()]);
            }

            return null;
        }

        if (! $otp || $otp->consumed_at !== null) {
            return 'Geçerli bir kod bulunamadı. Lütfen yeni kod isteyin.';
        }

        if ($otp->attempts >= config('zarf.otp.max_attempts')) {
            return 'Çok fazla hatalı deneme yapıldı. Lütfen yeni kod isteyin.';
        }

        if ($otp->expires_at->isPast()) {
            return 'Kodun süresi doldu. Lütfen yeni kod isteyin.';
        }

        if (! Hash::check($code, $otp->code_hash)) {
            $otp->increment('attempts');

            return 'Kod hatalı. Lütfen tekrar deneyin.';
        }

        $otp->update(['consumed_at' => now()]);

        return null;
    }

    public static function masterCodeEnabled(): bool
    {
        return App::environment(['local', 'testing']);
    }

    private function ensureWithinPhoneLimits(string $phone): void
    {
        $last = OtpRequest::query()->where('phone', $phone)->latest('id')->first();
        $cooldown = config('zarf.otp.per_phone_cooldown_seconds');

        if ($last && $last->created_at->gt(now()->subSeconds($cooldown))) {
            $retryAfter = max(1, $cooldown - (int) $last->created_at->diffInSeconds(now()));

            throw new TooManyRequestsHttpException($retryAfter, "Yeni kod istemeden önce {$retryAfter} saniye bekleyin.");
        }

        $lastHour = OtpRequest::query()
            ->where('phone', $phone)
            ->where('created_at', '>', now()->subHour())
            ->count();

        if ($lastHour >= config('zarf.otp.per_phone_per_hour')) {
            throw new TooManyRequestsHttpException(3600, 'Bu numara için çok fazla kod istendi. Lütfen daha sonra tekrar deneyin.');
        }
    }

    private function generateCode(): string
    {
        do {
            $code = str_pad((string) random_int(0, 999_999), config('zarf.otp.length'), '0', STR_PAD_LEFT);
        } while ($code === self::MASTER_CODE);

        return $code;
    }
}
