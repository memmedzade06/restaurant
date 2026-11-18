<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WheelSpin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Hədiyyə marketi: oyunçu balansındakı xalları hədiyyəyə çevirir.
 * Hədiyyə alınanda qiyməti balansdan çıxılır və kupon kodu yaranır (Hədiyyələrim səhifəsində görünür).
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

        $coupon = DB::transaction(function () use ($user, $item, $cost) {
            // xal yalnız balans kifayət edirsə çıxılır — eyni anda iki alış balansı mənfiyə sala bilməz
            $paid = User::whereKey($user->id)->where('points', '>=', $cost)->decrement('points', $cost);
            if (! $paid) {
                return null;
            }

            return WheelSpin::create([
                'user_id' => $user->id,
                'game' => 'market',
                'prize_key' => $item['key'],
                'prize_label' => $item['label'],
                'code' => WheelSpin::newCode(),
                'spun_on' => now('Asia/Baku')->toDateString(),
                'expires_at' => now()->addDays(max(1, (int) config('market.code_valid_days', 30))),
            ]);
        });

        if (! $coupon) {
            return redirect()->route('market')->with('market_error', 'Balansınızda kifayət qədər xal yoxdur.');
        }

        return redirect()->route('market')->with('market_bought', $coupon->id);
    }
}
