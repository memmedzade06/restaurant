<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\InstagramFollow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Hesabım: Instagram səhifəmizi izləyən istifadəçi adını göndərir — admin təsdiqləyəndə xal alır */
class InstagramFollowController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $back = redirect(route('account') . '#instagram');

        if (! $user->earnsGamePoints()) {
            return $back->withErrors(['instagram' => 'Ofisiant və admin hesabları bu xalı almır.']);
        }

        $current = InstagramFollow::where('user_id', $user->id)->first();
        if ($current && $current->status !== InstagramFollow::REJECTED) {
            return $back->withErrors(['instagram' => $current->status === InstagramFollow::APPROVED
                ? 'Bu xalı artıq almısınız.'
                : 'Sorğunuz artıq göndərilib — admin yoxlayır.']);
        }

        $username = InstagramFollow::normalize($request->input('username'));
        if (! $username) {
            return $back->withInput()->withErrors(['instagram' => 'Instagram adını düzgün yazın (məs. @adiniz). Yalnız hərf, rəqəm, nöqtə və alt xətt.']);
        }
        $taken = InstagramFollow::where('username', $username)->where('user_id', '!=', $user->id)->exists();
        if ($taken) {
            return $back->withInput()->withErrors(['instagram' => 'Bu Instagram adı başqa hesabda artıq istifadə olunub.']);
        }

        InstagramFollow::updateOrCreate(['user_id' => $user->id], [
            'username' => $username,
            'status' => InstagramFollow::PENDING,
            'points' => null, 'reviewed_by' => null, 'reviewed_at' => null,
        ]);

        return $back->with('instagram_sent', true);
    }
}
