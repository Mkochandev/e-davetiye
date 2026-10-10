<?php

namespace App\Models;

use App\Enums\EventCreator;
use App\Enums\EventType;
use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'owner_user_id', 'salon_id', 'created_by', 'type', 'title', 'starts_at', 'ends_at',
    'venue_name', 'venue_address', 'latitude', 'longitude', 'couple_phone',
    'share_headcount_with_salon', 'pack_purchased_at', 'cancelled_at',
])]
class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'created_by' => EventCreator::class,
            'type' => EventType::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'latitude' => 'float',
            'longitude' => 'float',
            'share_headcount_with_salon' => 'boolean',
            'pack_purchased_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function salon(): BelongsTo
    {
        return $this->belongsTo(Salon::class);
    }

    public function reservation(): HasOne
    {
        return $this->hasOne(Reservation::class);
    }

    public function invitation(): HasOne
    {
        return $this->hasOne(Invitation::class);
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    public function hasPack(): bool
    {
        return $this->pack_purchased_at !== null;
    }

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }
}
