<?php

namespace App\Models;

use App\Enums\Platform;
use App\Enums\PurchaseStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'event_id', 'platform', 'product_id', 'transaction_id', 'status', 'payload', 'verified_at'])]
class Purchase extends Model
{
    protected function casts(): array
    {
        return [
            'platform' => Platform::class,
            'status' => PurchaseStatus::class,
            'payload' => 'array',
            'verified_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
