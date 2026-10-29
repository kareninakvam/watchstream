<?php

namespace App\Models;

use App\Casts\UtcDateTime;
use Database\Factories\ChannelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['provider', 'provider_channel_id', 'name', 'access_token', 'refresh_token', 'token_expires_at', 'authorized_by', 'connection_status'])]
#[Hidden(['access_token', 'refresh_token'])]
class Channel extends Model
{
    /** @use HasFactory<ChannelFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'token_expires_at' => UtcDateTime::class,
        ];
    }

    public function authorizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'authorized_by');
    }

    public function liveSessions(): HasMany
    {
        return $this->hasMany(LiveSession::class);
    }

    public function receivedEvents(): HasMany
    {
        return $this->hasMany(ReceivedEvent::class);
    }
}
