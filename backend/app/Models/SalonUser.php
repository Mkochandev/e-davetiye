<?php

namespace App\Models;

use App\Enums\SalonUserRole;
use Database\Factories\SalonUserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['salon_id', 'name', 'email', 'phone', 'password', 'role', 'last_login_at'])]
#[Hidden(['password', 'remember_token'])]
class SalonUser extends Authenticatable
{
    /** @use HasFactory<SalonUserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'role' => SalonUserRole::class,
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
}
