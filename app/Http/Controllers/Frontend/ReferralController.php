<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Referrals;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReferralController extends Controller
{
    /** Dəvət linki / QR kod: kodu yadda saxlayıb qeydiyyat səhifəsinə aparır */
    public function visit(Request $request, string $code): RedirectResponse
    {
        if ($request->user()) {
            return redirect()->route('home'); // artıq qeydiyyatı var
        }

        $code = strtoupper($code);
        if (User::where('referral_code', $code)->whereNotNull('phone_verified_at')->exists()) {
            Referrals::remember($code);
        }

        return redirect()->route('register');
    }
}
