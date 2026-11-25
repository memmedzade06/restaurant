<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Hamıya mesaj: admin bir mesajı seçilmiş istifadəçilərin söhbətinə göndərib (tarixçə) */
class SupportBroadcast extends Model
{
    /** Kimə göndərmək olar: açar => [ad, izah] */
    public const AUDIENCES = [
        'all' => ['Bütün təsdiqlənmiş qonaqlar', 'Hesabını təsdiqləmiş bütün istifadəçilər'],
        'active30' => ['Son 30 gündə saytda olanlar', 'Son 30 gün ərzində sayta daxil olmuş təsdiqlənmiş qonaqlar'],
    ];

    protected $fillable = ['admin_id', 'body', 'audience', 'recipients'];

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function audienceLabel(): string
    {
        return self::AUDIENCES[$this->audience][0] ?? $this->audience;
    }

    /**
     * Mesajı alacaq istifadəçilər: yalnız təsdiqlənmiş qonaqlar (ofisiant və admin yox).
     * active30 — son 30 gündə saytda olanlar.
     */
    public static function recipientsQuery(string $audience): Builder
    {
        return User::query()->verified()
            ->where('is_admin', false)
            ->where(fn ($q) => $q->whereNull('type')->orWhere('type', User::TYPE_GUEST))
            ->when($audience === 'active30', fn ($q) => $q->where('last_seen_at', '>=', now()->subDays(30)));
    }
}
