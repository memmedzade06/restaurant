<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WheelSpin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Admin: oyunçuların hədiyyə kodlarını yoxlayır və "istifadə olundu" kimi qeyd edir.
 * İstifadə olunmuş kod bir daha istifadə oluna bilməz.
 */
class PrizeController extends Controller
{
    public function index(Request $request): View
    {
        $status = in_array($request->query('status'), array_keys(WheelSpin::STATUS_LABELS), true) ? $request->query('status') : null;
        $q = trim((string) $request->query('q'));

        $prizes = WheelSpin::prizes($status)
            ->with(['user:id,name,phone', 'redeemer:id,name'])
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
            'counts' => collect(WheelSpin::STATUS_LABELS)->map(fn ($label, $key) => WheelSpin::prizes($key)->count()),
        ]);
    }

    /** Kodu "istifadə olundu" kimi qeyd edir */
    public function redeem(Request $request, WheelSpin $prize): RedirectResponse
    {
        $result = DB::transaction(function () use ($request, $prize) {
            $prize = WheelSpin::whereKey($prize->id)->lockForUpdate()->first(); // iki admin eyni anda bassa belə bir dəfə

            if (! $prize->code) {
                return ['error', 'Bu qeydin hədiyyə kodu yoxdur.'];
            }
            if ($prize->status() === WheelSpin::USED) {
                return ['error', "{$prize->code} kodu artıq " . $prize->redeemed_at->timezone('Asia/Baku')->format('d.m.Y H:i') . ' tarixində istifadə olunub.'];
            }
            if ($prize->status() === WheelSpin::EXPIRED) {
                return ['error', "{$prize->code} kodunun vaxtı keçib — istifadə etmək olmaz."];
            }

            $prize->forceFill(['redeemed_at' => now(), 'redeemed_by' => $request->user()->id])->save();

            return ['success', "{$prize->code} — «{$prize->prize_label}» istifadə olundu. Kod artıq qüvvədən düşüb."];
        });

        return back()->with($result[0], $result[1]);
    }

    private function normalizeCode(string $q): string
    {
        $code = strtoupper(preg_replace('/\s+/', '', $q));

        return str_starts_with($code, 'QP-') ? $code : 'QP-' . ltrim($code, '-');
    }
}
