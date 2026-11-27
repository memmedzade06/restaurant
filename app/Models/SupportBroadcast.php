<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Hamıya mesaj: admin bir mesajı seçilmiş istifadəçilərə göndərib (tarixçə).
 * Hara: sayt söhbəti (chat), Telegram (Telegram ilə təsdiqlənənlər), e-poçt (təsdiqlənmiş e-poçtu olanlar).
 */
class SupportBroadcast extends Model
{
    /** Kimə göndərmək olar: açar => [ad, izah] */
    public const AUDIENCES = [
        'all' => ['Bütün təsdiqlənmiş qonaqlar', 'Hesabını təsdiqləmiş bütün istifadəçilər'],
        'active30' => ['Son 30 gündə saytda olanlar', 'Son 30 gün ərzində sayta daxil olmuş təsdiqlənmiş qonaqlar'],
    ];

    /** Hara göndərmək olar: açar => [ad, izah, ikon] */
    public const CHANNELS = [
        'chat' => ['Sayt söhbəti', 'Profildəki "Mesajlar" və küncdəki söhbət', 'fa fa-comments'],
        'telegram' => ['Telegram', 'Telegram ilə təsdiqlənənlərə bot yazır', 'fab fa-telegram-plane'],
        'email' => ['E-poçt', 'E-poçtu təsdiqlənmiş olanlara məktub', 'fa fa-envelope'],
    ];

    protected $fillable = ['admin_id', 'body', 'link_url', 'link_label', 'attachment_path', 'attachment_name', 'attachment_mime',
        'attachment_size', 'audience', 'channels', 'recipients', 'telegram_total', 'telegram_sent', 'email_total', 'email_sent'];

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function audienceLabel(): string
    {
        return self::AUDIENCES[$this->audience][0] ?? $this->audience;
    }

    /** @return string[] */
    public function channelList(): array
    {
        return array_values(array_filter(explode(',', (string) $this->channels)));
    }

    public function hasChannel(string $channel): bool
    {
        return in_array($channel, $this->channelList(), true);
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

    /** Kanal üzrə alıcılar: Telegram — bota yazmış (chat id-si olan), e-poçt — təsdiqlənmiş e-poçtu olan */
    public static function channelQuery(string $audience, string $channel): Builder
    {
        $q = self::recipientsQuery($audience);

        return match ($channel) {
            'telegram' => $q->whereNotNull('telegram_chat_id'),
            'email' => $q->whereNotNull('email')->whereNotNull('email_verified_at'),
            default => $q,
        };
    }
}
