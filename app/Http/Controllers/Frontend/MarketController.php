<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WheelSpin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Hədiyyə marketi: oyunçu balansındakı xalları hədiyyəyə çevirir.
 * Hədiyyə alınanda qiyməti balansdan çıxılır və kupon kodu yaranır (Hədiyyələrim səhifəsində görünür).
 * Gündə ən çox config('market.per_day') hədiyyə almaq olar. config('market.small_max') xala qədər olan hədiyyə
 * yarım hədiyyə sayılır: birinin yerinə 2 dənə seçmək olar (rəfdə klikləməklə ikisi birlikdə alınır).
 */
class MarketController extends Controller
{
    /** Seçilmiş hədiyyələrin kateqoriyası (config/market.php) */
    public const FEATURED = 'secilmis';

    /**
     * Marketdəki bütün hədiyyələr: seçilmişlər (config/market.php) + bütün menyu (config/market_menu.php,
     * php artisan market:sync-menu ilə yenilənir). Hər birinin "cat" sahəsi kateqoriyasını göstərir.
     */
    public static function items(): array
    {
        $featured = collect(config('market.items', []))->map(fn ($i) => $i + ['cat' => self::FEATURED]);
        $cats = collect(self::menuCategories())->keyBy('key');
        $skip = collect(config('market.menu_item_exclude', []))->map(fn ($n) => mb_strtolower(trim($n)))->all();
        $menu = collect(config('market_menu.items', []))->filter(function ($i) use ($cats, $skip) {
            $c = $cats->get($i['cat'] ?? null);

            return $c && ! in_array(mb_strtolower(trim($i['label'])), $skip, true)
                && (empty($c['only']) || preg_match($c['only'], $i['label']));
        });

        return $featured->concat($menu)
            ->map(fn ($i) => $i + ['cost' => 0, 'icon' => 'fa-utensils', 'desc' => '', 'image' => null])
            ->filter(fn ($i) => (float) $i['cost'] > 0)
            ->keyBy('key')
            ->all();
    }

    /**
     * Menyu kateqoriyaları: config('market.menu_exclude')-dəkilər (məs. Kabablar) çıxılır,
     * config('market.menu_filter') ilə adı dəyişdirilir və məhsulları süzülür (məs. Qəlyan Seti → Qəlyan).
     */
    private static function menuCategories(): array
    {
        $norm = fn ($n) => mb_strtolower(trim($n));
        $exclude = collect(config('market.menu_exclude', []))->map($norm)->all();
        $filters = collect(config('market.menu_filter', []))->mapWithKeys(fn ($f, $name) => [$norm($name) => $f]);

        return collect(config('market_menu.categories', []))
            ->reject(fn ($c) => in_array($norm($c['label']), $exclude, true))
            ->map(fn ($c) => array_merge($c, $filters->get($norm($c['label']), [])))
            ->values()->all();
    }

    /** Kateqoriyalar: [key => [label, image, count, min]] — boş olanlar göstərilmir */
    public static function categories(): array
    {
        $first = collect(config('market.items', []))->first(fn ($i) => ! empty($i['image']));
        $cats = collect([['key' => self::FEATURED, 'label' => 'Seçilmiş hədiyyələr', 'image' => $first['image'] ?? null, 'featured' => true]])
            ->concat(self::menuCategories());
        $items = collect(self::items())->groupBy('cat');

        return $cats->filter(fn ($c) => $items->has($c['key']))
            ->mapWithKeys(fn ($c) => [$c['key'] => $c + [
                'count' => $items[$c['key']]->count(),
                'min' => $items[$c['key']]->min(fn ($i) => (float) $i['cost']),
                'featured' => false,
            ]])
            ->all();
    }

    public static function perDay(): int
    {
        return max(1, (int) config('market.per_day', 1));
    }

    /** Bu qiymətə (xal) qədər olan hədiyyələr "kiçik"dir — yarım hədiyyə sayılır */
    public static function smallMax(): float
    {
        return (float) config('market.small_max', 3);
    }

    public static function isSmall(array $item): bool
    {
        return (float) $item['cost'] <= self::smallMax();
    }

    /** Gündəlik limit paylarla sayılır: adi hədiyyə 2 pay, kiçik hədiyyə 1 pay */
    private const SHARES = 2;

    private static function shares(array $item): int
    {
        return self::isSmall($item) ? 1 : self::SHARES;
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $verified = $user && $user->isVerified();

        // yenicə alınmış kupon(lar) — bir dəfə göstərilir; 2 kiçik hədiyyə birlikdə alınanda iki kupon olur
        $bought = $verified && session('market_bought')
            ? WheelSpin::where('user_id', $user->id)->whereKey((array) session('market_bought'))->orderBy('id')->get()
            : collect();
        $bought = $bought->isNotEmpty() ? $bought : null;

        // əvvəl kateqoriyalar görünür; ?kat=... ilə daxil olanda həmin kateqoriyanın məhsulları
        $categories = self::categories();
        $cat = $categories[$request->query('kat')] ?? null;
        $items = $cat ? array_filter(self::items(), fn ($i) => $i['cat'] === $cat['key']) : [];
        uasort($items, fn ($a, $b) => (float) $a['cost'] <=> (float) $b['cost']); // ucuzdan bahaya

        // qaydalar pəncərəsi yalnız marketə ilk girəndə açılır (sessiya ərzində bir dəfə) —
        // kateqoriyalar arasında gəzəndə və alışdan sonra təkrar açılmır
        $showRules = ! $bought && ! $request->session()->get('market_rules_seen');
        $shares = $verified ? $this->sharesLeft($user->id) : 0;
        if ($showRules) {
            $request->session()->put('market_rules_seen', true);
        }

        return view('frontend.market.index', [
            'showRules' => $showRules,
            'categories' => $categories,
            'cat' => $cat,
            'items' => $items,
            'verified' => $verified,
            'balance' => $verified ? (float) $user->points : null,
            'left' => $verified ? intdiv($shares, self::SHARES) : null, // bu gün neçə adi hədiyyə qalıb
            'smallLeft' => $verified ? $shares : null,                  // neçə kiçik hədiyyə qalıb
            'perDay' => self::perDay(),
            'smallMax' => self::smallMax(),
            'bought' => $bought,
        ]);
    }

    /**
     * Hədiyyəni al: xal balansdan çıxılır, kupon yaranır. "also" — eyni anda seçilmiş ikinci hədiyyə:
     * yalnız ikisi də kiçik olanda (config('market.small_max') xala qədər) olur; eyni hədiyyə də ola bilər (2 ədəd).
     * İki hədiyyəyə bir kupon verilir: adı "2 x Çay" və ya "Çay + Limon", açarı "a+b" (gündəlik limit üçün).
     */
    public function buy(Request $request, string $key): RedirectResponse
    {
        $all = self::items();
        $item = $all[$key] ?? null;
        abort_unless($item, 404);
        $back = redirect()->route('market', ['kat' => $item['cat']]);
        $small = \App\Services\Referrals::format(self::smallMax());

        $picked = [$item];
        $also = (string) $request->input('also', '');
        if ($also !== '') {
            $other = $all[$also] ?? null;
            abort_unless($other, 404);
            if (! self::isSmall($item) || ! self::isSmall($other)) {
                return $back->with('market_error', "Birlikdə yalnız {$small} xala qədər olan 2 hədiyyə seçmək olar.");
            }
            $picked[] = $other;
        }
        $cost = array_sum(array_map(fn ($i) => (float) $i['cost'], $picked));
        $need = array_sum(array_map(fn ($i) => self::shares($i), $picked));
        $user = $request->user();

        $result = DB::transaction(function () use ($user, $picked, $cost, $need) {
            // eyni istifadəçinin eyni anda gələn sorğuları növbəyə düzülür — gündəlik limiti keçmək olmur
            User::whereKey($user->id)->lockForUpdate()->first();

            if ($this->sharesLeft($user->id) < $need) {
                return 'limit';
            }
            // xal yalnız balans kifayət edirsə çıxılır — balans mənfiyə düşə bilməz
            if (! User::whereKey($user->id)->where('points', '>=', $cost)->decrement('points', $cost)) {
                return 'points';
            }

            $keys = array_column($picked, 'key');
            $label = count($picked) === 1 ? $picked[0]['label']
                : ($keys[0] === $keys[1] ? "2 x {$picked[0]['label']}" : $picked[0]['label'] . ' + ' . $picked[1]['label']);
            $label = mb_substr($label, 0, 100);
            $coupon = WheelSpin::create([
                'user_id' => $user->id,
                'game' => 'market',
                'prize_key' => implode('+', $keys),
                'prize_label' => $label,
                'code' => WheelSpin::newCode(),
                'spun_on' => $this->today(),
                'expires_at' => now()->addDays(max(1, (int) config('market.code_valid_days', 30))),
            ]);
            \App\Services\Points::log($user->id, -$cost, 'market', ['label' => $label, 'code' => $coupon->code, 'key' => $coupon->prize_key]); // tarixçə
            $this->countToday($user->id, $need);

            return [$coupon->id];
        });

        if ($result === 'limit') {
            return $back->with('market_error', $this->sharesLeft($user->id) > 0
                ? "Bu gün yalnız {$small} xala qədər olan 1 hədiyyə də seçə bilərsiniz."
                : 'Bu gün üçün ' . self::perDay() . ' hədiyyə limitiniz bitib. Sabah yenə gəlin!');
        }
        if ($result === 'points') {
            return $back->with('market_error', 'Balansınızda kifayət qədər xal yoxdur.');
        }

        return $back->with('market_bought', $result);
    }

    /** Bu gün neçə pay qalıb (adi hədiyyə 2 pay, kiçik hədiyyə 1 pay) */
    private function sharesLeft(int $userId): int
    {
        $all = self::items();
        $inDb = WheelSpin::where('game', 'market')->where('user_id', $userId)->whereDate('spun_on', $this->today())
            ->pluck('prize_key')
            // "a+b" — bir kuponda iki kiçik hədiyyə
            ->sum(fn ($k) => collect(explode('+', $k))->sum(fn ($p) => isset($all[$p]) ? self::shares($all[$p]) : self::SHARES));
        // istifadə olunan kupon bazadan silinir — bu günün payları keşdə də saxlanılır ki, limit sıfırlanmasın
        $taken = max($inDb, (int) Cache::get($this->dayKey($userId), 0));

        return max(0, self::perDay() * self::SHARES - $taken);
    }

    private function countToday(int $userId, int $shares): void
    {
        $key = $this->dayKey($userId);
        Cache::put($key, (int) Cache::get($key, 0) + $shares, now('Asia/Baku')->endOfDay());
    }

    private function dayKey(int $userId): string
    {
        return "market_shares:{$userId}:" . $this->today(); // pay sayı
    }

    private function today(): string
    {
        return now('Asia/Baku')->toDateString();
    }
}
