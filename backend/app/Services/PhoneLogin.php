<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Recipient;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * OTP doğrulama + kullanıcı oluşturma + girişte bağlama (spec §3.7) tek transaction'da.
 */
class PhoneLogin
{
    public function __construct(
        private readonly OtpService $otp,
        private readonly PhoneNumber $phones,
    ) {}

    /**
     * @param  string  $phone  E.164
     * @return array{user: User, token: string, is_new: bool}
     *
     * @throws ValidationException
     */
    public function login(string $phone, string $code, string $deviceName): array
    {
        $result = DB::transaction(function () use ($phone, $code, $deviceName) {
            if ($error = $this->otp->consume($phone, $code)) {
                // Hata dönülür ki deneme sayacı commit edilsin.
                return $error;
            }

            $user = User::firstOrCreate(
                ['phone' => $phone],
                ['phone_hash' => $this->phones->hash($phone)],
            );

            $this->linkPendingRecords($user);

            return [
                'user' => $user,
                'token' => $user->createToken($deviceName)->plainTextToken,
                'is_new' => $user->wasRecentlyCreated,
            ];
        });

        if (is_string($result)) {
            throw ValidationException::withMessages(['code' => $result]);
        }

        return $result;
    }

    /**
     * Numaraya gönderilmiş zarfları ve salonun çift için açtığı etkinlikleri kullanıcıya bağlar.
     * Web'den verilmiş LCV ve `channel` olduğu gibi kalır.
     */
    private function linkPendingRecords(User $user): void
    {
        Recipient::query()
            ->where('phone', $user->phone)
            ->whereNull('user_id')
            ->update(['user_id' => $user->id]);

        Event::query()
            ->where('couple_phone', $user->phone)
            ->whereNull('owner_user_id')
            ->update(['owner_user_id' => $user->id]);
    }
}
