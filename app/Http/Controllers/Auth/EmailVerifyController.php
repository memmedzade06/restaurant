<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\EmailVerification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/** Qeydiyyatın e-poçtdakı "Təsdiqlə" düyməsi (imzalı link) ilə təsdiqi */
class EmailVerifyController extends Controller
{
    public function __construct(private readonly EmailVerification $verification)
    {
    }

    /** "E-poçtunuzu yoxlayın" səhifəsi */
    public function show(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        if ($user->isVerified()) {
            return redirect()->route('games.index');
        }
        if ($user->verificationRoute() !== 'verify.email') {
            return redirect()->route($user->verificationRoute());
        }

        $sendFailed = false;
        try {
            $this->verification->sendIfNeeded($user);
        } catch (\Throwable $e) {
            Log::error('Təsdiq məktubu göndərilmədi: ' . $e->getMessage());
            $sendFailed = true;
        }

        return view('frontend.auth.verify-email', [
            'user' => $user,
            'resendWait' => $this->verification->resendWait($user),
            'sendFailed' => $sendFailed,
        ]);
    }

    /** Məktubdakı düymə: link imzalıdır (signed middleware), başqa cihazda da açıla bilər */
    public function confirm(Request $request, User $user, string $hash): RedirectResponse
    {
        if (! $this->verification->confirm($user, $hash)) {
            abort(403, 'Təsdiq linki etibarsızdır.');
        }

        // Bu brauzerdə həmin hesabla daxil olubsa — birbaşa oyunlara, yoxsa girişə
        if ($request->user()?->is($user)) {
            $request->session()->flash('registered', true);

            return redirect()->route('games.index');
        }

        return redirect()->route($request->user() ? 'games.index' : 'login')
            ->with('status', 'E-poçtunuz təsdiqləndi! İndi hesabınıza daxil ola bilərsiniz.');
    }

    /** Gözləmə səhifəsi bunu yoxlayır — başqa cihazda təsdiqlənsə də özü keçid edir */
    public function status(Request $request): JsonResponse
    {
        $verified = $request->user()->fresh()->isVerified();
        if ($verified) {
            $request->session()->flash('registered', true);
        }

        // rezervasiya pəncərəsindən gəlibsə — ora qayıdır (yarımçıq rezervasiya avtomatik açılır)
        $redirect = $verified ? ($request->session()->pull('reservation.return') ?: route('games.index')) : route('games.index');

        return response()->json(['verified' => $verified, 'redirect' => $redirect]);
    }

    public function resend(Request $request): RedirectResponse
    {
        $user = $request->user();
        if ($user->isVerified() || ! $user->email) {
            return redirect()->route('games.index');
        }

        if ($this->verification->resendWait($user) > 0) {
            return back();
        }

        try {
            $this->verification->send($user);
        } catch (\Throwable $e) {
            Log::error('Təsdiq məktubu göndərilmədi: ' . $e->getMessage());

            return back()->with('error', 'Məktub göndərilmədi. Bir az sonra yenidən cəhd edin.');
        }

        return back()->with('status', 'Məktub yenidən göndərildi.');
    }
}
