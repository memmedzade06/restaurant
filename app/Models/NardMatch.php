<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * İki nəfərlik nərd oyunu. state — App\Services\NardRules-un vəziyyəti
 * (lövhə həmişə oyunçu 0-ın baxışından saxlanılır).
 */
class NardMatch extends Model
{
    public const SEEN_TIMEOUT = 90; // bu qədər saniyə səhifəsi açıq olmayan oyunçu oyundan çıxmış sayılır

    protected $fillable = ['player0_id', 'player1_id', 'status', 'state', 'version', 'winner', 'mars', 'end_reason', 'player0_seen_at', 'player1_seen_at'];

    protected $casts = [
        'state' => 'array',
        'mars' => 'boolean',
        'player0_seen_at' => 'datetime',
        'player1_seen_at' => 'datetime',
    ];

    public function player0(): BelongsTo
    {
        return $this->belongsTo(User::class, 'player0_id');
    }

    public function player1(): BelongsTo
    {
        return $this->belongsTo(User::class, 'player1_id');
    }

    /** İstifadəçinin bu oyundakı nömrəsi (0 / 1) və ya null — oyunçu deyil */
    public function seatOf(User $user): ?int
    {
        if ((int) $user->id === (int) $this->player0_id) {
            return 0;
        }

        return (int) $user->id === (int) $this->player1_id ? 1 : null;
    }

    public function isPlaying(): bool
    {
        return $this->status === 'playing';
    }

    /** İstifadəçinin hazırda davam edən oyunu */
    public static function activeFor(int $userId): ?self
    {
        return static::where('status', 'playing')
            ->where(fn ($q) => $q->where('player0_id', $userId)->orWhere('player1_id', $userId))
            ->latest('id')->first();
    }
}
