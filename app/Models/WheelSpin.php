<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WheelSpin extends Model
{
    public const ACTIVE = 'active';
    public const USED = 'used';
    public const EXPIRED = 'expired';

    /** Statusların göstərilən adları */
    public const STATUS_LABELS = [
        self::ACTIVE => 'Aktiv',
        self::USED => 'İstifadə olunub',
        self::EXPIRED => 'Vaxtı keçib',
    ];

    protected $fillable = ['user_id', 'game', 'prize_key', 'prize_label', 'code', 'spun_on', 'expires_at', 'redeemed_at', 'redeemed_by'];

    protected $casts = [
        'spun_on' => 'date',
        'expires_at' => 'datetime',
        'redeemed_at' => 'datetime',
    ];

    /** Hədiyyənin gəldiyi oyunun adı */
    public const GAME_LABELS = [
        'wheel' => 'Bəxt çarxı',
        'stack' => 'Stəkan qülləsi',
        'nard' => 'Nərd — günün qalibi',
        'claw' => 'Hədiyyə Kranı',
    ];

    /**
     * Bazada yalnız aktiv kuponlar qalsın: müddəti bitmiş və istifadə olunmuş kuponları silir.
     * (İstifadə olunan kod admin paneldə dərhal silinir; bu, köhnə qeydlər üçün ehtiyatdır.)
     * Nərd qaliblərinin kupona bağlantısı özü boşalır (nullOnDelete). Qaytarır: silinən kuponların sayı.
     */
    public static function pruneExpired(): int
    {
        return static::query()
            ->whereNotNull('code')
            ->where(fn ($q) => $q->whereNotNull('redeemed_at')
                ->orWhere(fn ($q) => $q->whereNotNull('expires_at')->where('expires_at', '<=', now())))
            ->delete();
    }

    /** Unikal hədiyyə kodu: QP-XXXXXX */
    public static function newCode(): string
    {
        do {
            $code = 'QP-' . strtoupper(\Illuminate\Support\Str::random(6));
        } while (static::where('code', $code)->exists());

        return $code;
    }

    public function gameLabel(): string
    {
        return self::GAME_LABELS[$this->game] ?? $this->game;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Kodu "istifadə olundu" kimi qeyd edən admin */
    public function redeemer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'redeemed_by');
    }

    /** Kod hələ istifadə oluna bilərmi */
    public function isActive(): bool
    {
        return $this->status() === self::ACTIVE;
    }

    public function status(): string
    {
        if ($this->redeemed_at) {
            return self::USED;
        }

        return ! $this->expires_at || $this->expires_at->isFuture() ? self::ACTIVE : self::EXPIRED;
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status()];
    }

    /** Yalnız kodu olan (götürülmüş) hədiyyələr, statusa görə süzgəclə */
    public function scopePrizes(Builder $query, ?string $status = null): Builder
    {
        $query->whereNotNull('code');

        return match ($status) {
            self::ACTIVE => $query->whereNull('redeemed_at')->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now())),
            self::USED => $query->whereNotNull('redeemed_at'),
            self::EXPIRED => $query->whereNull('redeemed_at')->where('expires_at', '<=', now()),
            default => $query,
        };
    }
}
