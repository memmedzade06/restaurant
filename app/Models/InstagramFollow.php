<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Instagram səhifəmizi izləmə sorğusu: istifadəçi adını yazır, admin yoxlayıb təsdiqləyir */
class InstagramFollow extends Model
{
    public const PENDING = 'pending';
    public const APPROVED = 'approved';
    public const REJECTED = 'rejected';

    protected $fillable = ['user_id', 'username', 'status', 'points', 'reviewed_by', 'reviewed_at'];

    protected $casts = [
        'points' => 'float',
        'reviewed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** "@ad", "instagram.com/ad/" və s. → "ad"; yararsızdırsa null */
    public static function normalize(?string $input): ?string
    {
        $s = mb_strtolower(trim((string) $input));
        $s = preg_replace('~^(https?://)?(www\.)?(instagram\.com|instagr\.am)/~', '', $s);
        $s = trim(explode('?', $s)[0], " /@\t");

        return preg_match('/^(?!.*\.\.)(?!\.)[a-z0-9._]{1,30}(?<!\.)$/', $s) ? $s : null;
    }

    public function profileUrl(): string
    {
        return 'https://www.instagram.com/' . $this->username . '/';
    }

    /** İzləməyə görə veriləcək xal (config/social.php) */
    public static function reward(): float
    {
        return max(0, (float) config('social.instagram.points', 1));
    }
}
