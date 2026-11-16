<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Nərdə dəvət: bir oyunçu digərini oynamağa çağırır. Qısa müddət etibarlıdır. */
class NardInvite extends Model
{
    public const TTL = 120; // saniyə

    protected $fillable = ['from_user_id', 'to_user_id', 'status', 'match_id', 'expires_at'];

    protected $casts = ['expires_at' => 'datetime'];

    public function from(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function to(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending')->where('expires_at', '>', now());
    }
}
