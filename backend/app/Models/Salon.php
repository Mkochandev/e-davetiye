<?php

namespace App\Models;

use Database\Factories\SalonFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'phone', 'email', 'address', 'city', 'district', 'latitude', 'longitude', 'logo_path', 'capacity', 'is_active'])]
class Salon extends Model
{
    /** @use HasFactory<SalonFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'capacity' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(SalonUser::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }
}
