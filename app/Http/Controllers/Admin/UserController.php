<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\NardLeaderboard;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin: qeydiyyatdan keçmiş istifadəçilər — axtarış, süzgəc (təsdiqlənmiş, saytda olan),
 * hər biri üçün qısa oyun göstəriciləri (hədiyyə kodları, Stəkan rekordu, Nərdin bu günkü xalı).
 */
class UserController extends Controller
{
    public const FILTERS = [
        'verified' => 'Təsdiqlənmiş',
        'unverified' => 'Təsdiqlənməmiş',
        'online' => 'İndi saytda',
        'today' => 'Bu gün qeydiyyat',
    ];

    public function index(Request $request): View
    {
        $filter = array_key_exists($request->query('filter'), self::FILTERS) ? $request->query('filter') : null;
        $q = trim((string) $request->query('q'));
        $todayStart = now('Asia/Baku')->startOfDay()->setTimezone(config('app.timezone'));
        $nardDay = NardLeaderboard::dayStart();

        $users = User::query()
            ->when($filter, fn ($query) => $this->applyFilter($query, $filter, $todayStart))
            ->when($q !== '', function ($query) use ($q) {
                $digits = preg_replace('/\D+/', '', $q);
                $query->where(function ($w) use ($q, $digits) {
                    $w->where('name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%");
                    if (strlen($digits) >= 3) {
                        $w->orWhere('phone', 'like', "%{$digits}%");
                    }
                });
            })
            ->withCount([
                'prizes',
                'prizes as active_prizes_count' => fn ($p) => $p->whereNull('redeemed_at')
                    ->where(fn ($e) => $e->whereNull('expires_at')->orWhere('expires_at', '>', now())),
            ])
            ->withMax(['gameResults as stack_best' => fn ($g) => $g->where('game', 'stack')], 'points')
            ->withSum(['gameResults as nard_today' => fn ($g) => $g->where('game', NardLeaderboard::GAME)->where('created_at', '>=', $nardDay)], 'points')
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('frontend.admin.users', [
            'users' => $users,
            'q' => $q,
            'filter' => $filter,
            'counts' => collect(self::FILTERS)->map(fn ($label, $key) => $this->applyFilter(User::query(), $key, $todayStart)->count()),
            'total' => User::count(),
        ]);
    }

    private function applyFilter($query, string $filter, $todayStart)
    {
        return match ($filter) {
            'verified' => $query->whereNotNull('phone_verified_at'),
            'unverified' => $query->whereNull('phone_verified_at'),
            'online' => $query->where('last_seen_at', '>=', now()->subSeconds(User::ONLINE_SECONDS)),
            'today' => $query->where('created_at', '>=', $todayStart),
        };
    }
}
