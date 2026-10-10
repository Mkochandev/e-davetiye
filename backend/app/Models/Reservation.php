<?php

namespace App\Models;

use App\Enums\ReservationStatus;
use Database\Factories\ReservationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Salonun iç kaydı. Ücret alanları (kuruş) mobil API'den asla dönmez.
 */
#[Fillable(['salon_id', 'event_id', 'contact_name', 'contact_phone', 'status', 'total_amount', 'deposit_amount', 'paid_amount', 'notes'])]
class Reservation extends Model
{
    /** @use HasFactory<ReservationFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => ReservationStatus::class,
            'total_amount' => 'integer',
            'deposit_amount' => 'integer',
            'paid_amount' => 'integer',
        ];
    }

    public function salon(): BelongsTo
    {
        return $this->belongsTo(Salon::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function remainingAmount(): int
    {
        return $this->total_amount - $this->paid_amount;
    }
}
