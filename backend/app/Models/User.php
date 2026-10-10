<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Mobil uygulama kullanıcısı (çift ya da misafir). Şifresi yok; telefon + SMS koduyla girer.
 */
#[Fillable(['phone', 'phone_hash', 'name', 'kvkk_accepted_at', 'kvkk_version'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'kvkk_accepted_at' => 'datetime',
        ];
    }

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class, 'owner_user_id');
    }

    public function receivedRecipients(): HasMany
    {
        return $this->hasMany(Recipient::class);
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }
}
