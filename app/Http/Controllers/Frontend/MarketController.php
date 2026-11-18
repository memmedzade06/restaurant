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
    /** Marketdəki hədiyyələr (config/market.php) */
    public static function items(): array
    {
        return collect(config('market.items', []))
            ->map(fn ($i) => $i + ['cost' => 0, 'icon' => 'fa-gift', 'desc' => ''])
            ->filter(fn ($i) => (float) $i['cost'] > 0)
            ->keyBy('key')
            ->all();
    }

    public static function perDay(): int
    {
        return max(1, (int) config('market.per_day', 2));
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $verified = $user && $user->hasVerifiedPhone();

        // yenicə alınmış kupon (bir dəfə göstərilir)
        $bought = $verified && session('market_bought')
            ? WheelSpin::where('user_id', $user->id)->whereKey(session('market_bought'))->first()
            : null;

        return view('frontend.market.index', [
            'items' => self::items(),
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

            return $coupon;
        });

        return match (true) {
            $result === 'limit' => redirect()->route('market')->with('market_error', 'Bu gün üçün ' . self::perDay() . ' hədiyyə limitiniz bitib. Sabah yenə gəlin!'),
            $result === 'points' => redirect()->route('market')->with('market_error', 'Balansınızda kifayət qədər xal yoxdur.'),
            default => redirect()->route('market')->with('market_bought', $result->id),
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
