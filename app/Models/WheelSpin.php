<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WheelSpin extends Model
{
    protected $fillable = ['user_id', 'prize_key', 'prize_label', 'code', 'spun_on', 'expires_at', 'redeemed_at'];

    protected $casts = [
        'spun_on' => 'date',
        'expires_at' => 'datetime',
        'redeemed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Kod hələ istifadə oluna bilərmi */
    public function isActive(): bool
    {
        return $this->code && ! $this->redeemed_at && (! $this->expires_at || $this->expires_at->isFuture());
    }
}
