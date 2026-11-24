<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InstagramFollow;
use App\Models\User;
use App\Services\Points;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Admin: Instagram izləmə sorğuları — izləyicilərdə yoxlayıb təsdiqləmək (xal yazılır) və ya rədd etmək */
class InstagramFollowController extends Controller
{
    public function index(Request $request): View
    {
        $status = in_array($request->query('status'), [InstagramFollow::APPROVED, InstagramFollow::REJECTED], true)
            ? $request->query('status') : InstagramFollow::PENDING;

        return view('frontend.admin.instagram', [
            'follows' => InstagramFollow::with(['user:id,name,phone,email,points', 'reviewer:id,name'])
                ->where('status', $status)
                ->orderBy($status === InstagramFollow::PENDING ? 'created_at' : 'reviewed_at', $status === InstagramFollow::PENDING ? 'asc' : 'desc')
                ->paginate(50)->withQueryString(),
            'status' => $status,
            'counts' => InstagramFollow::selectRaw('status, COUNT(*) c')->groupBy('status')->pluck('c', 'status'),
            'reward' => InstagramFollow::reward(),
        ]);
    }

    public function approve(Request $request, InstagramFollow $follow): RedirectResponse
    {
        $admin = $request->user();

        $given = DB::transaction(function () use ($follow, $admin) {
            $f = InstagramFollow::whereKey($follow->id)->lockForUpdate()->first();
            if (! $f || $f->status !== InstagramFollow::PENDING) {
                return null; // artıq baxılıb (iki admin eyni anda basa bilər)
            }
            $user = User::whereKey($f->user_id)->lockForUpdate()->first();
            $points = $user && $user->earnsGamePoints() ? InstagramFollow::reward() : 0;
            $f->update(['status' => InstagramFollow::APPROVED, 'points' => $points, 'reviewed_by' => $admin->id, 'reviewed_at' => now()]);
            if ($points > 0) {
                $user->increment('points', $points);
                Points::log($user->id, $points, 'instagram', ['username' => $f->username], null, $admin->id);
            }

            return $points;
        });

        return back()->with($given === null ? 'error' : 'success', $given === null
            ? 'Bu sorğuya artıq baxılıb.'
            : "@{$follow->username} təsdiqləndi" . ($given > 0 ? ' — ' . \App\Services\Referrals::format($given) . ' xal yazıldı.' : ' (bu hesab xal almır).'));
    }

    public function reject(Request $request, InstagramFollow $follow): RedirectResponse
    {
        $done = InstagramFollow::whereKey($follow->id)->where('status', InstagramFollow::PENDING)
            ->update(['status' => InstagramFollow::REJECTED, 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);

        return back()->with($done ? 'success' : 'error', $done ? "@{$follow->username} rədd edildi." : 'Bu sorğuya artıq baxılıb.');
    }
}
