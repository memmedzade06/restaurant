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

        // forma: gün → ay → il (üç seçim)
        [$date, $error] = Birthday::fromParts(
            $request->input('birth_day'), $request->input('birth_month'), $request->input('birth_year'),
            \Carbon\CarbonImmutable::create(1920, 1, 2, 0, 0, 0, 'Asia/Baku'),
            \Carbon\CarbonImmutable::now('Asia/Baku')->subYears(5),
        );
        if (! $date) {
            return $back->withInput()->withErrors(['birthday' => $error ?? 'Doğum tarixini seçin.']);
        }
        $data = ['birth_date' => $date->toDateString()];

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
