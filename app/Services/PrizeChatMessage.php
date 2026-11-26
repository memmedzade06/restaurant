<?php

namespace App\Services;

use App\Models\SupportMessage;
use App\Models\WheelSpin;
use Illuminate\Support\Facades\Log;

/**
 * Hədiyyə kodu yarananda (oyun, Hədiyyə marketi, ad günü) istifadəçinin chat-inə avtomatik mesaj:
 * nə qazandı, kod, nə vaxta qədər etibarlıdır və minimal masa hesabı.
 * sender_id boşdur — admin söhbətdə onu "Avtomatik" kimi görür, istifadəçi isə "50 Qəpik"dən.
 */
class PrizeChatMessage
{
    /** Hədiyyə haradan gəlib — "… hədiyyə qazandınız" */
    private const WHERE = [
        'wheel' => 'Çarx oyununda',
        'stack' => 'Stəkan qülləsində',
        'sufre' => 'Süfrəni doldur oyununda',
        'nard' => 'Nərddə günün qalibi oldunuz və',
        'claw' => 'Hədiyyə Kranında',
    ];

    public static function send(WheelSpin $spin): ?SupportMessage
    {
        $user = $spin->user;
        if (! $spin->code || ! $user || $user->is_admin) {
            return null; // admin chat-dən istifadə etmir
        }

        try {
            return SupportMessage::create([
                'user_id' => $user->id,
                'sender_id' => null,
                'from_admin' => true,
                'body' => self::text($spin),
            ]);
        } catch (\Throwable $e) {
            // mesaj getməsə də hədiyyə qüvvədədir (kod "Hədiyyələrim"dədir)
            Log::warning('Hədiyyə barədə chat mesajı yazılmadı: ' . $e->getMessage(), ['spin_id' => $spin->id]);

            return null;
        }
    }

    public static function text(WheelSpin $spin): string
    {
        $label = $spin->prize_label;
        $lines = match ($spin->game) {
            'market' => ['🎁 Hədiyyəniz hazırdır!', "Hədiyyə marketindən aldınız: {$label}"],
            'birthday' => ['🎂 Ad gününüz mübarək!', "Ad günü hədiyyəniz: {$label}"],
            default => ['🎉 Təbriklər!', (self::WHERE[$spin->game] ?? 'Oyunda') . " hədiyyə qazandınız: {$label}"],
        };

        $lines[] = '';
        $lines[] = "🎟 Kod: {$spin->code}";
        if ($spin->expires_at) {
            $lines[] = '⏰ ' . $spin->expires_at->timezone('Asia/Baku')->format('d.m.Y H:i') . ' tarixinədək etibarlıdır.';
        }
        if ($min = WheelSpin::minBill($spin->game)) {
            $lines[] = "💳 Hədiyyədən istifadə üçün minimal masa hesabı {$min} AZN olmalıdır.";
        }
        $lines[] = '';
        $lines[] = 'Kodu restoranda ofisianta göstərin. Kod «Hədiyyələrim» bölməsində də saxlanılır.';

        return implode("\n", $lines);
    }
}
