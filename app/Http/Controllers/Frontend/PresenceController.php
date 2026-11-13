<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * "İndi saytda" — açıq səhifələr hər bir neçə saniyədən bir siqnal göndərir,
 * siqnalı kəsilən istifadəçi siyahıdan düşür.
 */
class PresenceController extends Controller
{
    /** Səhifə açıqdır — istifadəçi aktivdir */
    public function ping(Request $request): Response
    {
        $request->user()->forceFill(['last_seen_at' => now()])->saveQuietly();

        return response()->noContent();
    }

    /**
     * Səhifə bağlanır. Dərhal silmirik: istifadəçi saytın başqa səhifəsinə keçirsə,
     * yeni səhifə bir an sonra yenə siqnal göndərəcək. Keçmirsə, ~10 saniyəyə siyahıdan düşür.
     */
    public function leave(Request $request): Response
    {
        $request->user()->forceFill([
            'last_seen_at' => now()->subSeconds(User::ONLINE_SECONDS - 10),
        ])->saveQuietly();

        return response()->noContent();
    }

    /** Oyunlar səhifəsindəki siyahının canlı yenilənməsi üçün */
    public function online(Request $request): JsonResponse
    {
        $users = User::online()->orderByDesc('last_seen_at')->limit(30)->get(['id', 'name']);

        return response()->json([
            'count' => User::online()->count(),
            'users' => $users->map(fn (User $u) => [
                'name' => $u->short_name,
                'me' => $request->user()?->id === $u->id,
            ]),
        ]);
    }
}
