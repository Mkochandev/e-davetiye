<?php

namespace App\Models;

use App\Enums\RecipientChannel;
use App\Enums\RsvpStatus;
use Database\Factories\RecipientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'invitation_id', 'phone', 'phone_hash', 'name', 'user_id', 'token', 'channel',
    'sent_at', 'opened_at', 'rsvp_status', 'guest_count', 'responded_at',
])]
#[Hidden(['token'])]
class Recipient extends Model
{
    /** @use HasFactory<RecipientFactory> */
    use HasFactory;

    protected $attributes = [
        'rsvp_status' => 'pending',
    ];

    protected function casts(): array
    {
        return [
            'channel' => RecipientChannel::class,
            'rsvp_status' => RsvpStatus::class,
            'sent_at' => 'datetime',
            'opened_at' => 'datetime',
            'guest_count' => 'integer',
            'responded_at' => 'datetime',
        ];
    }

    public function invitation(): BelongsTo
    {
        return $this->belongsTo(Invitation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
