<?php

namespace App\Models;

use App\Casts\UtcDateTime;
use Database\Factories\LiveSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['channel_id', 'provider_stream_id', 'started_at', 'ended_at', 'status', 'scoring_rule_version', 'scoring_rule'])]
class LiveSession extends Model
{
    /** @use HasFactory<LiveSessionFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'started_at' => UtcDateTime::class,
            'ended_at' => UtcDateTime::class,
            'scoring_rule' => 'array',
        ];
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    public function receivedEvents(): HasMany
    {
        return $this->hasMany(ReceivedEvent::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }
}
