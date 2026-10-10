<?php

namespace App\Models;

use App\Enums\RecipientChannel;
use App\Enums\RsvpStatus;
use App\Services\PhoneNumber;
use Database\Factories\RecipientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
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

    /**
     * Numarası uygulamada kayıtlı kullanıcı (henüz bağlanmamış olsa da).
     */
    public function appUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'phone_hash', 'phone_hash');
    }

    #[Scope]
    protected function sent(Builder $query): void
    {
        $query->whereNotNull('sent_at');
    }

    /**
     * Kişiye özel web davetiye linki (spec §3.4).
     */
    public function webUrl(): string
    {
        return url("/z/{$this->token}");
    }

    public function whatsappUrl(): string
    {
        $title = $this->invitation->event->title;

        return PhoneNumber::whatsappUrl($this->phone, "{$title} sizi davet ediyor. Davetiyenizi açmak için: {$this->webUrl()}");
    }
}
