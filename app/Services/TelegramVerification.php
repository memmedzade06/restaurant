<?php

namespace App\Services;

use App\Models\User;
use App\Support\Phone;
use Illuminate\Support\Str;

/**
 * Qeydiyyatın Telegram ilə təsdiqi:
 *  1) sayt bir dəfəlik kod yaradır, istifadəçi t.me/<bot>?start=<kod> linkini açır;
 *  2) bot "/start <kod>" alır, hesabı bu Telegram söhbətinə bağlayır və nömrə istəyir;
 *  3) istifadəçi öz kontaktını paylaşır — nömrə formadakı nömrə ilə eynidirsə, təsdiqlənir.
 * "/start r_<kod>" — şifrə bərpasıdır, TelegramPasswordReset-ə ötürülür.
 */
class TelegramVerification
{
    public const TOKEN_TTL_MINUTES = 30;

    public function __construct(private readonly TelegramBot $bot, private readonly TelegramPasswordReset $reset)
    {
    }

    /** Lazım olsa yeni kod yaradır və Telegram linkini qaytarır */
    public function startLink(User $user): string
    {
        if (! $user->telegram_token || ! $user->telegram_token_expires_at || $user->telegram_token_expires_at->isPast()) {
            $user->forceFill([
                'telegram_token' => Str::random(40),
                'telegram_token_expires_at' => now()->addMinutes(self::TOKEN_TTL_MINUTES),
            ])->save();
        }

        return 'https://t.me/' . config('services.telegram.bot_username') . '?start=' . $user->telegram_token;
    }

    /** Telegram-dan gələn bir "update"-i emal edir (webhook və ya polling) */
    public function handleUpdate(array $update): void
    {
        $message = $update['message'] ?? null;
        if (! $message || ($message['chat']['type'] ?? '') !== 'private') {
            return;
        }

        $chatId = (string) $message['chat']['id'];

        if (isset($message['contact'])) {
            // əvvəlcə bu söhbətdə gözləyən şifrə bərpası varmı
            if (! $this->reset->handleContact($chatId, $message)) {
                $this->handleContact($chatId, $message);
            }
            return;
        }

        $text = trim($message['text'] ?? '');
        if (str_starts_with($text, '/start')) {
            $arg = trim(substr($text, 6));
            if (str_starts_with($arg, TelegramPasswordReset::PREFIX)) {
                $this->reset->handleStart($chatId, substr($arg, strlen(TelegramPasswordReset::PREFIX)));
            } else {
                $this->handleStart($chatId, $arg);
            }
            return;
        }

        $this->bot->sendMessage($chatId, 'Qeydiyyatı təsdiqləmək üçün saytdakı <b>Telegram ilə təsdiqlə</b> düyməsini basın.');
    }

    private function handleStart(string $chatId, string $token): void
    {
        $user = $token !== ''
            ? User::where('telegram_token', $token)->where('telegram_token_expires_at', '>', now())->first()
            : null;

        if (! $user) {
            // Kod artıq istifadə olunub: bu Telegram ilə təsdiqlənmiş hesab varsa, bunu bildiririk
            if (User::where('telegram_chat_id', $chatId)->whereNotNull('phone_verified_at')->exists()) {
                $this->sendWithSiteLink($chatId, "✅ Qeydiyyatınız artıq təsdiqlənib.\nSayta qayıdıb davam edə bilərsiniz.");
                return;
            }

            $this->bot->sendMessage($chatId, "Salam! 👋\nBu link etibarsızdır və ya vaxtı keçib. Saytda yenidən <b>Telegram ilə təsdiqlə</b> düyməsini basın.");
            return;
        }

        if ($user->hasVerifiedPhone()) {
            $this->sendWithSiteLink($chatId, '✅ Bu hesab artıq təsdiqlənib.');
            return;
        }

        $user->forceFill(['telegram_chat_id' => $chatId])->save();

        $this->bot->sendMessage(
            $chatId,
            'Salam, <b>' . e($user->name) . "</b>! 👋\n50 Qəpik saytında qeydiyyatı təsdiqləmək üçün aşağıdakı <b>📱 Nömrəmi paylaş</b> düyməsini basın.\n\nQeydiyyat nömrəsi: " . Phone::format($user->phone),
            TelegramBot::contactKeyboard()
        );
    }

    private function handleContact(string $chatId, array $message): void
    {
        $contact = $message['contact'];

        // Başqasının kontaktını göndərməyin qarşısını alır
        if (($contact['user_id'] ?? null) !== ($message['from']['id'] ?? false)) {
            $this->bot->sendMessage($chatId, 'Zəhmət olmasa, başqasının yox, <b>öz</b> nömrənizi düymə ilə paylaşın.', TelegramBot::contactKeyboard());
            return;
        }

        $user = User::where('telegram_chat_id', $chatId)
            ->whereNull('phone_verified_at')
            ->where('telegram_token_expires_at', '>', now())
            ->latest('id')
            ->first();

        if (! $user) {
            $this->bot->sendMessage($chatId, 'Təsdiq gözləyən qeydiyyat tapılmadı. Saytda yenidən <b>Telegram ilə təsdiqlə</b> düyməsini basın.', TelegramBot::removeKeyboard());
            return;
        }

        if (Phone::normalize($contact['phone_number'] ?? '') !== $user->phone) {
            $this->bot->sendMessage(
                $chatId,
                "❌ Telegram nömrəniz qeydiyyatdakı nömrə ilə eyni deyil.\nQeydiyyat nömrəsi: " . Phone::format($user->phone) . "\n\nSaytda nömrəni düzəldin və yenidən cəhd edin.",
                TelegramBot::removeKeyboard()
            );
            return;
        }

        $user->forceFill([
            'phone_verified_at' => now(),
            'telegram_token' => null,
            'telegram_token_expires_at' => null,
        ])->save();
        Referrals::reward($user); // dəvət linki ilə gəlibsə — dəvət edənə xal

        $this->sendWithSiteLink($chatId, "✅ Təşəkkürlər! Qeydiyyatınız təsdiqləndi.\nSayta qayıda bilərsiniz.");
    }

    /**
     * Mesaj + sayta keçid: https-də "🌐 Sayta daxil ol" düyməsi, yoxsa mətndə link.
     * Nömrə düyməsi bir dəfəlikdir (one_time_keyboard) — kontakt paylaşılanda özü gizlənir.
     */
    private function sendWithSiteLink(string $chatId, string $text): void
    {
        $url = route('games.index'); // təsdiqdən sonra birbaşa oyunlara
        $button = TelegramBot::linkButton('🌐 Sayta daxil ol', $url);

        $button
            ? $this->bot->sendMessage($chatId, $text, $button)
            : $this->bot->sendMessage($chatId, $text . "\n\n🌐 Sayta daxil olun: " . $url, TelegramBot::removeKeyboard());
    }
}
