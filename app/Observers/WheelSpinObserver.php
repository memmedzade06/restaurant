<?php

namespace App\Observers;

use App\Models\WheelSpin;
use App\Services\PrizeNotifier;

/**
 * Yeni hədiyyə kodu yarananda (istənilən oyunda, marketdə və ya ad günü) qalibin chat-inə mesaj yazılır
 * və Telegram bildirişi göndərilir.
 * Bildiriş istifadəçiyə cavab göndəriləndən SONRA gedir — oyun Telegram-ı gözləmir.
 */
class WheelSpinObserver
{
    /** Tranzaksiya uğurla bitəndən sonra (geri qaytarılan alış/hədiyyə üçün bildiriş getməsin) */
    public bool $afterCommit = true;

    public function created(WheelSpin $spin): void
    {
        if (! $spin->code) {
            return;
        }

        // istifadəçinin chat-inə: nə qazandı, kod, müddət, minimal masa hesabı (profildə "Mesajlar"da da görünür)
        \App\Services\PrizeChatMessage::send($spin);

        $id = $spin->id;
        dispatch(function () use ($id) {
            if ($spin = WheelSpin::with('user')->find($id)) {
                app(PrizeNotifier::class)->notify($spin);
            }
        })->afterResponse();
    }
}
