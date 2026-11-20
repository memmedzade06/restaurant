<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\TelegramVerification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class TelegramVerifyController extends Controller
{
    public function __construct(private readonly TelegramVerification $verification)
    {
    }

    /** "Telegram ilə təsdiqlə" səhifəsi */
    public function show(Request $request)
    {
        $user = $request->user();
        if ($user->isVerified()) {
            return redirect()->route('games.index');
        }
        if ($user->verificationRoute() !== 'verify.telegram') {
            return redirect()->route($user->verificationRoute()); // e-poçt ilə qeydiyyat
        }

        return view('frontend.auth.verify-telegram', [
            'user' => $user,
            'link' => $this->verification->startLink($user),
            'botReady' => (bool) config('services.telegram.bot_token') && (bool) config('services.telegram.bot_username'),
        ]);
    }

    /** Səhifə bu ünvanı yoxlayır — təsdiq olunanda avtomatik keçid edir */
    public function status(Request $request): JsonResponse
    {
        $verified = $request->user()->fresh()->isVerified();

        // Təsdiqdən sonra Oyunlar səhifəsi açılır; orada "qeydiyyat təsdiqləndi" pəncərəsi bir dəfə göstərilir
        if ($verified) {
            $request->session()->flash('registered', true);
        }

        // rezervasiya pəncərəsindən gəlibsə — ora qayıdır (yarımçıq rezervasiya avtomatik açılır)
        $redirect = $verified ? ($request->session()->pull('reservation.return') ?: route('games.index')) : route('games.index');

        return response()->json(['verified' => $verified, 'redirect' => $redirect]);
    }

    /** Telegram-ın webhook ünvanı (routes/api.php) */
    public function webhook(Request $request): Response
    {
        $secret = (string) config('services.telegram.webhook_secret');
        if ($secret === '' || ! hash_equals($secret, (string) $request->header('X-Telegram-Bot-Api-Secret-Token'))) {
            abort(403);
        }

        try {
            $this->verification->handleUpdate($request->all());
        } catch (\Throwable $e) {
            // Telegram eyni mesajı təkrar-təkrar göndərməsin deyə 200 qaytarırıq
            Log::error('Telegram webhook xətası: ' . $e->getMessage());
        }

        return response('ok');
    }
}
