<?php

namespace App\Models;

use App\Enums\SalonUserRole;
use App\Notifications\SalonPasswordSetup;
use Database\Factories\SalonUserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Password;

#[Fillable(['salon_id', 'name', 'email', 'phone', 'password', 'role', 'is_active', 'last_login_at'])]
#[Hidden(['password', 'remember_token'])]
class SalonUser extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<SalonUserFactory> */
    use HasFactory, Notifiable;

    public const PASSWORD_BROKER = 'salon_users';

    protected $attributes = [
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'role' => SalonUserRole::class,
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function salon(): BelongsTo
    {
        return $this->belongsTo(Salon::class);
    }

    public function isOwner(): bool
    {
        return $this->role === SalonUserRole::Owner;
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'salon'
            && $this->is_active
            && $this->salon?->is_active;
    }

    /**
     * İlk şifre belirleme bağlantısını e-postayla gönderir (spec §2).
     */
    public function sendPasswordSetupLink(): void
    {
        $token = Password::broker(self::PASSWORD_BROKER)->createToken($this);

        $this->notify(new SalonPasswordSetup($token));
    }
}
