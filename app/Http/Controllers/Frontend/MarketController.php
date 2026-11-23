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
 * Gündə ən çox config('market.per_day') hədiyyə almaq olar.
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
        return max(1, (int) config('market.per_day', 2));
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $verified = $user && $user->isVerified();

        // yenicə alınmış kupon (bir dəfə göstərilir)
        $bought = $verified && session('market_bought')
            ? WheelSpin::where('user_id', $user->id)->whereKey(session('market_bought'))->first()
            : null;

        // əvvəl kateqoriyalar görünür; ?kat=... ilə daxil olanda həmin kateqoriyanın məhsulları
        $categories = self::categories();
        $cat = $categories[$request->query('kat')] ?? null;
        $items = $cat ? array_filter(self::items(), fn ($i) => $i['cat'] === $cat['key']) : [];
        uasort($items, fn ($a, $b) => (float) $a['cost'] <=> (float) $b['cost']); // ucuzdan bahaya

        // qaydalar pəncərəsi yalnız marketə ilk girəndə açılır (sessiya ərzində bir dəfə) —
        // kateqoriyalar arasında gəzəndə və alışdan sonra təkrar açılmır
        $showRules = ! $bought && ! $request->session()->get('market_rules_seen');
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
            'left' => $verified ? $this->leftToday($user->id) : null,
            'perDay' => self::perDay(),
            'bought' => $bought,
        ]);
    }

    /** Hədiyyəni al: xal balansdan çıxılır, kupon yaranır */
    public function buy(Request $request, string $key): RedirectResponse
    {
        $item = self::items()[$key] ?? null;
        abort_unless($item, 404);
        $cost = (float) $item['cost'];
        $user = $request->user();

        $result = DB::transaction(function () use ($user, $item, $cost) {
            // eyni istifadəçinin eyni anda gələn sorğuları növbəyə düzülür — gündəlik limiti keçmək olmur
            User::whereKey($user->id)->lockForUpdate()->first();

            if ($this->leftToday($user->id) <= 0) {
                return 'limit';
            }
            // xal yalnız balans kifayət edirsə çıxılır — balans mənfiyə düşə bilməz
            if (! User::whereKey($user->id)->where('points', '>=', $cost)->decrement('points', $cost)) {
                return 'points';
            }

            $coupon = WheelSpin::create([
                'user_id' => $user->id,
                'game' => 'market',
                'prize_key' => $item['key'],
                'prize_label' => $item['label'],
                'code' => WheelSpin::newCode(),
                'spun_on' => $this->today(),
                'expires_at' => now()->addDays(max(1, (int) config('market.code_valid_days', 30))),
            ]);
            $this->countToday($user->id);
            \App\Services\Points::log($user->id, -$cost, 'market', ['label' => $item['label'], 'code' => $coupon->code, 'key' => $item['key']]); // tarixçə

            return $coupon;
        });

        return match (true) {
            $result === 'limit' => redirect()->route('market', ['kat' => $item['cat']])->with('market_error', 'Bu gün üçün ' . self::perDay() . ' hədiyyə limitiniz bitib. Sabah yenə gəlin!'),
            $result === 'points' => redirect()->route('market', ['kat' => $item['cat']])->with('market_error', 'Balansınızda kifayət qədər xal yoxdur.'),
            default => redirect()->route('market', ['kat' => $item['cat']])->with('market_bought', $result->id),
        };
    }

    /** Bu gün neçə hədiyyə də almaq olar */
    private function leftToday(int $userId): int
    {
        $inDb = WheelSpin::where('game', 'market')->where('user_id', $userId)->whereDate('spun_on', $this->today())->count();
        // istifadə olunan kupon bazadan silinir — bu günün sayı keşdə də saxlanılır ki, limit sıfırlanmasın
        $taken = max($inDb, (int) Cache::get($this->dayKey($userId), 0));

        return max(0, self::perDay() - $taken);
    }

    private function countToday(int $userId): void
    {
        $key = $this->dayKey($userId);
        Cache::put($key, (int) Cache::get($key, 0) + 1, now('Asia/Baku')->endOfDay());
    }

    private function dayKey(int $userId): string
    {
        return "market_taken:{$userId}:" . $this->today();
    }

    private function today(): string
    {
        return now('Asia/Baku')->toDateString();
    }
}
