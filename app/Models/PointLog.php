<?php

namespace App\Models;

use App\Http\Controllers\Frontend\QuizController;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Xal tarixçəsinin bir sətri: balans nə qədər, nəyə görə dəyişib.
 * Mətn göstəriləndə qurulur — admin tam ad və kupon kodunu, istifadəçi qısa adı görür.
 */
class PointLog extends Model
{
    public const UPDATED_AT = null;

    public const SOURCES = [
        'referral' => ['Dəvət', 'fa-user-plus'],
        'quiz' => ['Bilik Yarışı', 'fa-question'],
        'stack' => ['Stəkan qülləsi', 'fa-layer-group'],
        'sufre' => ['Süfrəni doldur', 'fa-mug-hot'],
        'market' => ['Hədiyyə marketi', 'fa-gift'],
        'instagram' => ['Instagram izləmə', 'fab fa-instagram'],
        'birthday' => ['Ad günü', 'fa-birthday-cake'],
        'admin' => ['Admin düzəlişi', 'fa-user-shield'],
    ];

    protected $fillable = ['user_id', 'amount', 'source', 'related_user_id', 'admin_id', 'meta', 'note', 'restored', 'created_at'];

    protected $casts = [
        'amount' => 'float',
        'meta' => 'array',
        'restored' => 'boolean',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function related(): BelongsTo
    {
        return $this->belongsTo(User::class, 'related_user_id');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function sourceLabel(): string
    {
        return self::SOURCES[$this->source][0] ?? $this->source;
    }

    public function icon(): string
    {
        return self::iconClass($this->source);
    }

    /** İkonun tam sinfi: brend ikonları (fab ...) olduğu kimi, qalanları "fa ..." */
    public static function iconClass(string $source): string
    {
        $icon = self::SOURCES[$source][1] ?? 'fa-star';

        return str_starts_with($icon, 'fab ') ? $icon : 'fa ' . $icon;
    }

    /** Sətrin izahı. $forAdmin — tam adlar, kupon kodu, adminin adı */
    public function describe(bool $forAdmin = false): string
    {
        $m = $this->meta ?? [];

        return match ($this->source) {
            'referral' => ($this->related
                    ? ($forAdmin ? $this->related->name : $this->related->short_name)
                    : 'silinmiş istifadəçi')
                . (isset($m['level']) ? ' · ' . QuizController::ordinal((int) $m['level']) . ' səviyyə' : ''),
            'quiz' => isset($m['stage']) ? QuizController::ordinal((int) $m['stage']) . ' mərhələ keçildi' : 'Mərhələ keçildi',
            'stack' => isset($m['tier']) ? $m['tier'] . ' stəkan mərhələsi — bonus' : (isset($m['score']) ? $m['score'] . ' stəkan — bonus' : 'Bonus'),
            'sufre' => isset($m['tier']) ? $m['tier'] . ' xal mərhələsi — bonus' : 'Bonus',
            'instagram' => isset($m['username']) ? '@' . $m['username'] . ' — izləmə təsdiqləndi' : 'İzləmə təsdiqləndi',
            'birthday' => 'Doğum tarixi əlavə olundu',
            'market' => ($m['label'] ?? 'Hədiyyə') . ($forAdmin && ! empty($m['code']) ? ' · ' . $m['code'] : ''),
            'admin' => trim(($forAdmin && $this->admin ? $this->admin->name : '') . ($this->note ? ($forAdmin && $this->admin ? ' — ' : '') . $this->note : '')) ?: 'Balans düzəldildi',
            default => '',
        };
    }
}
