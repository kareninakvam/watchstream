<?php

namespace App\Models;

use App\Casts\UtcDateTime;
use Database\Factories\ReceivedEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['provider', 'channel_id', 'live_session_id', 'user_id', 'provider_event_id', 'provider_user_id', 'type', 'occurred_at', 'received_at', 'status', 'attempts', 'claimed_at', 'processed_at', 'error_code'])]
class ReceivedEvent extends Model
{
    /** @use HasFactory<ReceivedEventFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'occurred_at' => UtcDateTime::class,
            'received_at' => UtcDateTime::class,
            'claimed_at' => UtcDateTime::class,
            'processed_at' => UtcDateTime::class,
            'attempts' => 'integer',
        ];
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    public function liveSession(): BelongsTo
    {
        return $this->belongsTo(LiveSession::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
