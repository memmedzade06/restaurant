<?php

namespace App\Services;

use App\Models\User;
use App\Support\Phone;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * "Şifrəmi unutdum" — Telegram ilə təsdiq:
 *  1) istifadəçi saytda nömrəsini yazır, sayt bir dəfəlik kod yaradır (keşdə, brauzerin sessiyasına bağlı);
 *  2) t.me/<bot>?start=r_<kod> linkini açır;
 *  3) bot həmin Telegram hesabının bu istifadəçiyə məxsus olduğunu yoxlayır:
 *     - qeydiyyatda bağlanmış Telegram söhbətidirsə — dərhal təsdiqlənir;
 *     - yoxsa nömrəni paylaşmağı istəyir və hesabdakı nömrə ilə müqayisə edir;
 *  4) təsdiqdən sonra yalnız həmin brauzer yeni şifrə təyin edə bilər.
 */
class TelegramPasswordReset
{
    public const TTL_MINUTES = 15;
    public const PREFIX = 'r_';   // /start parametrində qeydiyyat kodundan fərqləndirir

    public function __construct(private readonly TelegramBot $bot)
    {
    }

    /** Yeni bərpa kodu yaradır */
    public function start(User $user): string
    {
        $token = Str::random(32);
        Cache::put($this->key($token), ['user_id' => $user->id, 'confirmed' => false], now()->addMinutes(self::TTL_MINUTES));

        return $token;
    }

    public function link(string $token): string
    {
        return 'https://t.me/' . config('services.telegram.bot_username') . '?start=' . self::PREFIX . $token;
    }

    /** ['user_id', 'confirmed'] və ya null (kod yoxdur / vaxtı keçib) */
    public function get(?string $token): ?array
    {
        return $token ? Cache::get($this->key($token)) : null;
    }

    /** Telegram-da təsdiqlənibsə istifadəçini qaytarır */
    public function confirmedUser(?string $token): ?User
    {
        $data = $this->get($token);

        return $data && $data['confirmed'] ? User::find($data['user_id']) : null;
    }

    /** Yeni şifrə təyin olundu: kod silinir, Telegram-a bildiriş gedir */
    public function finish(string $token, User $user): void
    {
        Cache::forget($this->key($token));

        if ($user->telegram_chat_id) {
            $this->bot->sendMessage($user->telegram_chat_id, "🔐 50 Qəpik saytında hesabınızın şifrəsi dəyişdirildi.\nBunu siz etməmisinizsə, bizimlə əlaqə saxlayın.");
        }
    }

    /** Bot "/start r_<kod>" aldı */
    public function handleStart(string $chatId, string $token): void
    {
        $data = $this->get($token);
        $user = $data ? User::find($data['user_id']) : null;

        if (! $user) {
            $this->bot->sendMessage($chatId, "Bu link etibarsızdır və ya vaxtı keçib.\nSaytda yenidən <b>Şifrəmi unutdum</b> bölməsinə keçin.", TelegramBot::removeKeyboard());
            return;
        }

        if ($data['confirmed']) {
            $this->bot->sendMessage($chatId, "✅ Artıq təsdiqlənib.\nSayta qayıdıb yeni şifrə təyin edin.", TelegramBot::removeKeyboard());
            return;
        }

        // Qeydiyyatda bu Telegram söhbəti hesaba bağlanıb — kimliyi artıq bilirik
        if ($user->telegram_chat_id && hash_equals((string) $user->telegram_chat_id, $chatId)) {
            $this->confirm($token, $data, $chatId);
            return;
        }

        // Başqa Telegram söhbəti — nömrəni paylaşıb sübut etməlidir
        Cache::put($this->chatKey($chatId), $token, now()->addMinutes(self::TTL_MINUTES));
        $this->bot->sendMessage(
            $chatId,
            "Şifrəni bərpa etmək üçün aşağıdakı <b>📱 Nömrəmi paylaş</b> düyməsini basın.\n\nHesabın nömrəsi: " . Phone::format($user->phone),
            TelegramBot::contactKeyboard()
        );
    }

    /**
     * Kontakt mesajı bu söhbətdə gözləyən şifrə bərpasına aiddirsə emal edir.
     * Qaytarır: true — mesaj burada emal olundu.
     */
    public function handleContact(string $chatId, array $message): bool
    {
        $token = Cache::get($this->chatKey($chatId));
        if (! $token) {
            return false;
        }

        $contact = $message['contact'];
        if (($contact['user_id'] ?? null) !== ($message['from']['id'] ?? false)) {
            $this->bot->sendMessage($chatId, 'Zəhmət olmasa, başqasının yox, <b>öz</b> nömrənizi düymə ilə paylaşın.', TelegramBot::contactKeyboard());
            return true;
        }

        $data = $this->get($token);
        $user = $data ? User::find($data['user_id']) : null;
        if (! $user) {
            Cache::forget($this->chatKey($chatId));
            $this->bot->sendMessage($chatId, "Bərpa linkinin vaxtı keçib.\nSaytda yenidən <b>Şifrəmi unutdum</b> bölməsinə keçin.", TelegramBot::removeKeyboard());
            return true;
        }

        if (Phone::normalize($contact['phone_number'] ?? '') !== $user->phone) {
            Cache::forget($this->chatKey($chatId));
            $this->bot->sendMessage(
                $chatId,
                "❌ Telegram nömrəniz hesabdakı nömrə ilə eyni deyil.\nHesabın nömrəsi: " . Phone::format($user->phone),
                TelegramBot::removeKeyboard()
            );
            return true;
        }

        $user->forceFill(['telegram_chat_id' => $chatId])->save();
        $this->confirm($token, $data, $chatId);

        return true;
    }

    private function confirm(string $token, array $data, string $chatId): void
    {
        Cache::put($this->key($token), ['confirmed' => true] + $data, now()->addMinutes(self::TTL_MINUTES));
        Cache::forget($this->chatKey($chatId));

        $this->bot->sendMessage($chatId, "✅ Təsdiqləndi!\nSayta qayıdın və yeni şifrənizi təyin edin.", TelegramBot::removeKeyboard());
    }

    private function key(string $token): string
    {
        return 'pwreset:' . $token;
    }

    private function chatKey(string $chatId): string
    {
        return 'pwreset_chat:' . $chatId;
    }
}
