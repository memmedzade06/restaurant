<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\NardLeaderboard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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
        // ?sort=points — xalı çox olan birinci (çoxdan aza); yoxsa ən yeni qeydiyyat birinci
        $sort = $request->query('sort') === 'points' ? 'points' : null;
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
            ->when($sort === 'points', fn ($query) => $query->orderByDesc('points'))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('frontend.admin.users', [
            'users' => $users,
            'q' => $q,
            'filter' => $filter,
            'sort' => $sort,
            'counts' => collect(self::FILTERS)->map(fn ($label, $key) => $this->applyFilter(User::query(), $key, $todayStart)->count()),
            'total' => User::count(),
        ]);
    }

    /** İstifadəçinin tipini dəyişmək üçün səhifə */
    public function edit(User $user): View
    {
        return view('frontend.admin.user-edit', [
            'user' => $user,
            // dəvət piramidası: bu istifadəçinin link/QR ilə gətirdiyi təsdiqlənmiş profillər (3 səviyyə)
            'tree' => \App\Services\Referrals::tree($user, 500, true),
            // xal tarixçəsi: balans necə yığılıb (dəvət, oyunlar, market, admin)
            'history' => \App\Services\Points::history($user, 200),
        ]);
    }

    /**
     * İstifadəçinin tipini dəyişir: qonaq, ofisiant və ya admin.
     * Admin öz tipini dəyişə bilməz — təsadüfən adminlikdən çıxıb paneldən kənarda qalmasın.
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(User::TYPES))],
        ], [
            'type.required' => 'İstifadəçi tipini seçin.',
            'type.in' => 'Belə istifadəçi tipi yoxdur.',
        ]);

        if ($user->id === $request->user()->id && $data['type'] !== $user->type) {
            return back()->with('error', 'Öz tipinizi dəyişə bilməzsiniz.');
        }

        $old = $user->type;
        $user->forceFill(['type' => $data['type']])->save();
        if ($old !== $user->type) {
            logger()->info('Admin istifadəçinin tipini dəyişdi', [
                'admin_id' => $request->user()->id, 'user_id' => $user->id, 'from' => $old, 'to' => $user->type,
            ]);
        }

        return redirect()->route('admin.users')->with('success', "{$user->name} — tip: {$user->type_label}.");
    }

    /**
     * İstifadəçinin xal balansını dəyişir: yeni balans yazmaq ("set"), əlavə etmək ("add") və ya çıxmaq ("sub").
     * Balans mənfiyə düşə bilməz. Hər dəyişiklik loga yazılır (kim, kimə, nə qədər, səbəb).
     */
    public function updatePoints(Request $request, User $user): RedirectResponse
    {
        // "2,5" də yazmaq olsun — onluq vergül nöqtəyə çevrilir
        if (is_string($request->input('amount'))) {
            $request->merge(['amount' => str_replace(',', '.', trim($request->input('amount')))]);
        }
        $data = $request->validate([
            'mode' => ['required', Rule::in(['set', 'add', 'sub'])],
            'amount' => ['required', 'numeric', 'min:0', 'max:100000', 'regex:/^\d+(\.\d{1,3})?$/'],
            'reason' => ['nullable', 'string', 'max:200'],
        ], [
            'amount.required' => 'Xal miqdarını yazın.',
            'amount.numeric' => 'Xal rəqəm olmalıdır.',
            'amount.min' => 'Xal mənfi ola bilməz.',
            'amount.max' => 'Xal çox böyükdür.',
            'amount.regex' => 'Xal ən çox 3 onluq rəqəmlə yazılır (məs. 2.5 və ya 0.125).',
        ]);
        $amount = round((float) $data['amount'], 3);

        $result = \Illuminate\Support\Facades\DB::transaction(function () use ($user, $data, $amount, $request) {
            // eyni anda oyundan xal yazılsa da, hesab düz qalsın
            $old = (float) User::whereKey($user->id)->lockForUpdate()->value('points');
            $new = match ($data['mode']) {
                'set' => $amount,
                'add' => $old + $amount,
                'sub' => $old - $amount,
            };
            $new = round($new, 3);
            if ($new < 0) {
                return ['error' => 'Balans mənfiyə düşə bilməz — istifadəçinin cəmi ' . \App\Services\Referrals::format($old) . ' xalı var.'];
            }
            User::whereKey($user->id)->update(['points' => $new]);
            \App\Services\Points::log($user->id, $new - $old, 'admin', ['mode' => $data['mode'], 'from' => $old, 'to' => $new],
                null, $request->user()->id, $data['reason'] ?? null); // tarixçə

            return ['old' => $old, 'new' => $new];
        });

        if (isset($result['error'])) {
            return back()->withInput()->with('points_error', $result['error']);
        }

        logger()->info('Admin istifadəçinin xalını dəyişdi', [
            'admin_id' => $request->user()->id, 'user_id' => $user->id, 'mode' => $data['mode'],
            'amount' => $amount, 'from' => $result['old'], 'to' => $result['new'], 'reason' => $data['reason'] ?? null,
        ]);

        $fmt = fn ($p) => \App\Services\Referrals::format((float) $p);

        return redirect()->route('admin.users.edit', $user)
            ->with('success', "Xal dəyişdi: {$fmt($result['old'])} → {$fmt($result['new'])} xal.");
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
