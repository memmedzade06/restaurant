<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\PointLog;
use App\Models\User;
use App\Services\Points;
use App\Support\Birthday;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Hesabım: doğum tarixini yazmaq (bir dəfə) və ad günü kuponunu götürmək */
class BirthdayController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $back = redirect(route('account') . '#ad-gunu');

        if ($user->birth_date) {
            return $back->withErrors(['birthday' => 'Doğum tarixi artıq yazılıb. Səhv yazılıbsa, adminə yazın.']);
        }

        $data = $request->validate([
            'birth_date' => ['required', 'date_format:Y-m-d', 'after:1920-01-01', 'before:' . now('Asia/Baku')->subYears(5)->toDateString()],
        ], [
            'birth_date.required' => 'Doğum tarixini seçin.',
            'birth_date.date_format' => 'Doğum tarixini düzgün seçin.',
            'birth_date.after' => 'Doğum tarixini düzgün seçin.',
            'birth_date.before' => 'Doğum tarixini düzgün seçin.',
        ]);

        // tarix yazılır və bir dəfəlik bonus xal verilir (iki dəfə basılsa belə bir dəfə)
        $bonus = DB::transaction(function () use ($user, $data) {
            $fresh = User::whereKey($user->id)->lockForUpdate()->first();
            if ($fresh->birth_date) {
                return null; // paralel sorğu artıq yazıb
            }
            $fresh->forceFill(['birth_date' => $data['birth_date'], 'birth_date_set_at' => now()])->save();

            $points = Birthday::setPoints();
            $already = PointLog::where('user_id', $fresh->id)->where('source', 'birthday')->exists(); // admin silib, yenidən yazılıbsa — yox
            if ($points <= 0 || $already || ! $fresh->earnsGamePoints()) {
                return 0.0;
            }
            $fresh->increment('points', $points);
            Points::log($fresh->id, $points, 'birthday');

            return $points;
        });

        return $back->with('birthday_saved', $bonus ?? 0.0);
    }

    public function claim(Request $request): RedirectResponse
    {
        $user = $request->user();
        $spin = Birthday::claim($user);

        if (! $spin) {
            return redirect(route('account') . '#ad-gunu')
                ->withErrors(['birthday' => 'Ad günü kuponunu indi götürmək mümkün deyil.']);
        }

        return redirect(route('prizes'))->with('birthday_coupon', $spin->code);
    }
}
