<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\NardLeaderboard;
use Illuminate\Http\RedirectResponse;
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

    /**
     * İstifadəçini birdəfəlik silir. Onunla bağlı oyun nəticələri, hədiyyə kodları, nərd oyunları və
     * dəvətləri bazada avtomatik silinir; onun dəvət etdikləri isə "dəvət edən: yoxdur" olaraq qalır
     * (artıq yazılmış xallar geri alınmır). Admin özünü və başqa admini silə bilməz.
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->id === $request->user()->id) {
            return back()->with('error', 'Öz hesabınızı silə bilməzsiniz.');
        }
        if ($user->is_admin) {
            return back()->with('error', 'Admin hesabını buradan silmək olmaz.');
        }

        $label = trim($user->name . ' ' . (\App\Support\Phone::format($user->phone) ?: ''));
        $user->delete();
        logger()->info('Admin istifadəçini sildi', ['admin_id' => $request->user()->id, 'deleted_user_id' => $user->id]);

        return back()->with('success', "{$label} silindi.");
    }

    private function applyFilter($query, string $filter, $todayStart)
    {
        return match ($filter) {
            'verified' => $query->verified(),
            'unverified' => $query->whereNull('phone_verified_at')->whereNull('email_verified_at'),
            'online' => $query->where('last_seen_at', '>=', now()->subSeconds(User::ONLINE_SECONDS)),
            'today' => $query->where('created_at', '>=', $todayStart),
        };
    }
}
