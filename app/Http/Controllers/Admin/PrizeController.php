<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WheelSpin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Admin və ofisiant: oyunçuların hədiyyə kodlarını yoxlayır və istifadə edir.
 * Ofisiant hər təsdiq etdiyi kupona config('games.waiter_redeem_points') xal alır (balansına).
 * İstifadə olunan kod silinmir — "istifadə olunub" kimi qeyd olunur (kim, nə vaxt) və
 * ayrıca "İstifadə olunub" bölməsində görünür; bir daha istifadə oluna bilməz.
 */
class PrizeController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q'));
        // bölmə: aktiv (susmaya görə), istifadə olunub, vaxtı keçib və ya hamısı ("all").
        // Axtarışda susmaya görə hamısı — istifadə olunmuş kod da tapılsın.
        $requested = (string) $request->query('status', $q !== '' ? 'all' : WheelSpin::ACTIVE);
        $status = in_array($requested, array_keys(WheelSpin::STATUS_LABELS), true) ? $requested : null;
        // ?user=ID — yalnız bir istifadəçinin kuponları (İstifadəçilər siyahısından "N kod" / "N aktiv" linki)
        $filterUser = $request->filled('user') ? \App\Models\User::find((int) $request->query('user')) : null;
        $byUser = fn ($query) => $query->when($filterUser, fn ($w) => $w->where('user_id', $filterUser->id));

        $prizes = $byUser(WheelSpin::prizes($status))
            ->with(['user:id,name,phone', 'redeemer:id,name'])
            // istifadə olunmuşlar — ən son istifadə olunan yuxarıda
            ->when($status === WheelSpin::USED, fn ($query) => $query->orderByDesc('redeemed_at'))
            ->when($q !== '', function ($query) use ($q) {
                $code = strtoupper($q);
                $digits = preg_replace('/\D+/', '', $q);

                $query->where(function ($w) use ($code, $digits) {
                    $w->where('code', 'like', "%{$code}%");
                    if (strlen($digits) >= 4) {
                        $w->orWhereHas('user', fn ($u) => $u->where('phone', 'like', "%{$digits}%"));
                    }
                });
            })
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        // Kod tam yazılıbsa, onu ayrıca kartda göstəririk
        $exact = $q !== '' ? WheelSpin::prizes()->with('user:id,name,phone')->where('code', $this->normalizeCode($q))->first() : null;

        return view('frontend.admin.prizes', [
            'prizes' => $prizes,
            'exact' => $exact,
            'q' => $q,
            'status' => $status,
            'counts' => collect(WheelSpin::STATUS_LABELS)->map(fn ($label, $key) => $byUser(WheelSpin::prizes($key))->count()),
            'filterUser' => $filterUser,
        ]);
    }

    /**
     * Kodu istifadə edir: "istifadə olunub" kimi qeyd olunur (vaxt və admin) — silinmir,
     * "İstifadə olunub" bölməsində qalır. İkinci dəfə istifadə etmək olmur.
     * $id — model bağlaması yoxdur ki, silinmiş (vaxtı keçmiş) kod 404 yox, aydın mesaj versin.
     */
    public function redeem(Request $request, string $prize): RedirectResponse
    {
        $result = $this->redeemPrize($prize, $request->user());

        return back()->with($result[0], $result[1]);
    }

    /**
     * Hədiyyənin QR kodu oxudulanda: QR-da bu ünvan var (/admin/hediyyeler/qr/QP-XXXXXX).
     * Admin onu telefonla oxudub açanda kod dərhal istifadə olunur və nəticə hədiyyə kodları
     * səhifəsində göstərilir. Daxil olmayıbsa — əvvəl giriş, sonra yenə bu ünvan; admin deyilsə — 403.
     */
    public function qr(string $code): RedirectResponse
    {
        $code = $this->normalizeCode($code);
        $id = WheelSpin::where('code', $code)->value('id');
        $result = $id
            ? $this->redeemPrize((string) $id, request()->user())
            : ['error', "{$code} kodu tapılmadı — vaxtı keçib silinib və ya kod səhvdir."];

        return redirect()->route('admin.prizes', ['q' => $code])->with($result[0], $result[1]);
    }

    /** Kodu istifadə edir. Qaytarır: ['success'|'error', mesaj] */
    private function redeemPrize(string $prize, \App\Models\User $by): array
    {
        return DB::transaction(function () use ($prize, $by) {
            $prize = WheelSpin::whereKey($prize)->lockForUpdate()->first(); // iki admin eyni anda bassa belə bir dəfə

            if (! $prize) {
                return ['error', 'Kod tapılmadı — artıq istifadə olunub və ya vaxtı keçib.'];
            }
            if (! $prize->code) {
                return ['error', 'Bu qeydin hədiyyə kodu yoxdur.'];
            }
            if ($prize->status() === WheelSpin::USED) {
                return ['error', "{$prize->code} kodu artıq " . $prize->redeemed_at->timezone('Asia/Baku')->format('d.m.Y H:i') . ' tarixində istifadə olunub.'];
            }
            if ($prize->status() === WheelSpin::EXPIRED) {
                return ['error', "{$prize->code} kodunun vaxtı keçib — istifadə etmək olmaz."];
            }

            $prize->forceFill(['redeemed_at' => now(), 'redeemed_by' => $by->id])->save();
            $msg = "{$prize->code} — «{$prize->prize_label}» istifadə olundu. Kod qüvvədən düşdü və «İstifadə olunub» bölməsinə keçdi.";

            // ofisiant (admin yox) təsdiqləyibsə — balansına xal; kod bir dəfə istifadə olunduğu üçün xal da bir dəfə
            $reward = (float) config('games.waiter_redeem_points', 0.5);
            if ($reward > 0 && ! $by->is_admin && $by->type === \App\Models\User::TYPE_WAITER) {
                \App\Models\User::whereKey($by->id)->increment('points', $reward);
                \App\Services\Points::log($by->id, $reward, 'waiter', ['code' => $prize->code, 'label' => $prize->prize_label, 'prize_id' => $prize->id]);
                $msg .= ' Balansınıza +' . \App\Services\Referrals::format($reward) . ' xal yazıldı.';
            }

            return ['success', $msg];
        });
    }

    private function normalizeCode(string $q): string
    {
        $code = strtoupper(preg_replace('/\s+/', '', $q));

        return str_starts_with($code, 'QP-') ? $code : 'QP-' . ltrim($code, '-');
    }
}
