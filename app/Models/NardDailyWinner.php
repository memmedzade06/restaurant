<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Nərd: günün qalibi (yer, xal, verilən endirim kodu) */
class NardDailyWinner extends Model
{
    protected $fillable = ['day', 'place', 'user_id', 'points', 'wins', 'wheel_spin_id'];

    protected $casts = ['day' => 'date'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function prize(): BelongsTo
    {
        return $this->belongsTo(WheelSpin::class, 'wheel_spin_id');
    }
}
