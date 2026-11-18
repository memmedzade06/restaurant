<?php

namespace App\Observers;

use App\Models\WheelSpin;
use App\Services\PrizeNotifier;

/**
 * Yeni hədiyyə kodu yarananda (istənilən oyunda və ya marketdə) qalibə Telegram bildirişi göndərilir.
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

        $id = $spin->id;
        dispatch(function () use ($id) {
            if ($spin = WheelSpin::with('user')->find($id)) {
                app(PrizeNotifier::class)->notify($spin);
            }
        })->afterResponse();
    }
}
