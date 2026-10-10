<?php

namespace App\Models;

use App\Enums\Platform;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'platform', 'fcm_token', 'last_seen_at'])]
class Device extends Model
{
    protected function casts(): array
    {
        return [
            'platform' => Platform::class,
            'last_seen_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
