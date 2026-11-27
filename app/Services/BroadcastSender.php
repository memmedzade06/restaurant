<?php

namespace App\Services;

use App\Mail\BroadcastMail;
use App\Models\SupportBroadcast;
use App\Models\SupportMessage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Hamıya mesajın Telegram və e-poçt hissəsi. Cavab brauzerə gedəndən sonra arxa planda işləyir
 * (BroadcastController → dispatch()->afterResponse()), nəticə support_broadcasts.*_sent-ə yazılır.
 */
class BroadcastSender
{
    /** Telegram botlara saniyədə ~30 mesaja icazə verir — aralarında fasilə (mikrosaniyə) */
    private const TELEGRAM_PAUSE_US = 40_000;

    /** Gedişat neçə göndərişdən bir bazaya yazılır (admin tarixçədə "45 / 154" görür) */
    private const PROGRESS_EVERY = 10;

    /** Telegram-da şəkil/video/fayl altındakı yazı ən çox 1024 simvol ola bilər */
    private const CAPTION_MAX = 1024;

    public function __construct(private readonly TelegramBot $bot)
    {
    }

    public function run(SupportBroadcast $b): void
    {
        @set_time_limit(0);
        if ($b->hasChannel('telegram')) {
            $this->telegram($b);
        }
        if ($b->hasChannel('email')) {
            $this->email($b);
        }
    }

    public function telegram(SupportBroadcast $b): void
    {
        $sent = 0;
        $b->forceFill(['telegram_sent' => 0])->save(); // başladı — admin səhifəsində "0 / N", sonra artır
        if ($this->bot->configured()) {
            $text = e((string) $b->body);
            [$text, $buttons] = $this->telegramLink($b, $text);
            $fileId = null; // fayl bir dəfə yüklənir, sonra Telegram-ın verdiyi file_id ilə göndərilir
            $kind = $b->attachment_path ? $this->telegramKind($b) : null;
            $uploadFails = 0;

            SupportBroadcast::channelQuery($b->audience, 'telegram')->select(['id', 'telegram_chat_id'])
                ->chunkById(200, function ($users) use ($b, $text, $buttons, &$fileId, &$kind, &$uploadFails, &$sent) {
                    foreach ($users as $u) {
                        // fayl ardıcıl 3 dəfə yüklənmədisə — dayandırırıq (hər istifadəçiyə 17 MB-ı boş yerə yükləməyək)
                        if ($b->attachment_path && ! $fileId && $uploadFails >= 3) {
                            return false;
                        }
                        try {
                            if ($this->telegramOne($b, $u->telegram_chat_id, $text, $buttons, $fileId, $kind, $uploadFails)) {
                                $sent++;
                                if ($sent % self::PROGRESS_EVERY === 0) {
                                    $b->forceFill(['telegram_sent' => $sent])->save(); // gedişat admin səhifəsində görünsün
                                }
                            }
                        } catch (Throwable $e) {
                            report($e);
                        }
                        usleep(self::TELEGRAM_PAUSE_US);
                    }
                    $b->forceFill(['telegram_sent' => $sent])->save(); // gedişat admin səhifəsində görünsün
                });
        }
        $b->forceFill(['telegram_sent' => $sent])->save();
    }

    /** Link: https olanda mesajın altında düymə, olmayanda (lokal) mətnin sonunda ünvan */
    private function telegramLink(SupportBroadcast $b, string $text): array
    {
        if (! $b->link_url) {
            return [$text, []];
        }
        $url = str_starts_with($b->link_url, '/') ? url($b->link_url) : $b->link_url;
        $label = $b->link_label ?: SupportMessage::DEFAULT_LINK_LABEL;
        $button = TelegramBot::linkButton($label, $url);

        return $button ? [$text, $button] : [trim($text . "\n\n" . e($label) . ': ' . e($url)), []];
    }

    /**
     * Bir istifadəçiyə göndərir. Fayl ilk uğurlu yükləmədən sonra file_id ilə gedir.
     * Video sendVideo ilə alınmasa, sənəd (sendDocument) kimi yenidən cəhd olunur.
     */
    private function telegramOne(SupportBroadcast $b, string $chatId, string $text, array $buttons, ?string &$fileId, ?array &$kind, int &$uploadFails): bool
    {
        if (! $b->attachment_path) {
            $r = $this->bot->call('sendMessage', ['chat_id' => $chatId, 'text' => $text, 'parse_mode' => 'HTML'] + $buttons);

            return $this->ok($b, $r);
        }

        $long = mb_strlen($text) > self::CAPTION_MAX;
        if ($long) { // yazı uzundursa — əvvəl mətn, sonra fayl (düymə faylın altında)
            $this->bot->call('sendMessage', ['chat_id' => $chatId, 'text' => $text, 'parse_mode' => 'HTML']);
        }
        $params = ['chat_id' => $chatId] + ($long || $text === '' ? [] : ['caption' => $text, 'parse_mode' => 'HTML']);
        if ($buttons) {
            $params['reply_markup'] = json_encode($buttons['reply_markup']);
        }

        if ($fileId) {
            return $this->ok($b, $this->bot->call($kind[0], $params + [$kind[1] => $fileId], 60));
        }

        $path = Storage::disk('local')->path($b->attachment_path);
        $r = $this->bot->upload($kind[0], $params, $kind[1], $path, $b->attachment_name, 300);
        if (! ($r->ok() && $r->json('ok')) && $kind[0] !== 'sendDocument') {
            $this->ok($b, $r); // xətanı yadda saxla
            $kind = ['sendDocument', 'document']; // video/şəkil alınmadı — fayl kimi göndəririk
            $r = $this->bot->upload($kind[0], $params, $kind[1], $path, $b->attachment_name, 300);
        }
        if ($r->ok() && $r->json('ok')) {
            // sonrakılar üçün: Telegram faylı hansı növdə saxlayıbsa, o metodla göndəririk
            [$fileId, $field] = $this->fileIdFrom((array) $r->json('result')) + [null, null];
            $kind = match ($field) {
                'animation' => ['sendAnimation', 'animation'],
                'document' => ['sendDocument', 'document'],
                'photo' => ['sendPhoto', 'photo'],
                'video' => ['sendVideo', 'video'],
                default => $kind,
            };
            $uploadFails = 0;
        } else {
            $uploadFails++;
        }

        return $this->ok($b, $r);
    }

    /** Uğurlu cavabdır? Deyilsə, Telegram-ın səbəbini yadda saxlayır — admin tarixçədə görür (və log) */
    private function ok(SupportBroadcast $b, \Illuminate\Http\Client\Response $r): bool
    {
        if ($r->ok() && $r->json('ok')) {
            return true;
        }
        $why = (string) ($r->json('description') ?: ('HTTP ' . $r->status()));
        // bloklayan istifadəçilər normaldır — onları "son xəta" kimi göstərmirik
        if (! str_contains($why, 'blocked') && ! str_contains($why, 'chat not found') && ! str_contains($why, 'deactivated')) {
            \Illuminate\Support\Facades\Cache::put(self::errorKey($b->id), mb_substr($why, 0, 200), now()->addDays(3));
            logger()->warning('Hamıya mesaj: Telegram xətası', ['broadcast_id' => $b->id, 'error' => $why]);
        }

        return false;
    }

    public static function errorKey(int $broadcastId): string
    {
        return "broadcast_tg_error:{$broadcastId}";
    }

    /** [metod, sahə]: şəkil — sendPhoto, video — sendVideo, qalanı — sendDocument */
    private function telegramKind(SupportBroadcast $b): array
    {
        $m = (string) $b->attachment_mime;
        if (in_array($m, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            return ['sendPhoto', 'photo'];
        }
        if (in_array($m, SupportMessage::VIDEO_MIMES, true)) {
            return ['sendVideo', 'video'];
        }

        return ['sendDocument', 'document'];
    }

    /** Göndərilmiş faylın [file_id, növ]: Telegram videonu "animation", böyük faylı "document" kimi də qaytara bilər */
    private function fileIdFrom(array $result): array
    {
        foreach (['video', 'animation', 'document', 'photo'] as $field) {
            $item = $result[$field] ?? null;
            if ($field === 'photo' && is_array($item)) {
                $item = end($item); // şəklin ən böyük ölçüsü
            }
            if (is_array($item) && ! empty($item['file_id'])) {
                return [$item['file_id'], $field];
            }
        }

        return [];
    }

    public function email(SupportBroadcast $b): void
    {
        $sent = 0;
        $b->forceFill(['email_sent' => 0])->save();
        SupportBroadcast::channelQuery($b->audience, 'email')
            ->chunkById(100, function ($users) use ($b, &$sent) {
                foreach ($users as $u) {
                    try {
                        Mail::to($u->email)->send(new BroadcastMail($b, $u));
                        $sent++;
                        if ($sent % self::PROGRESS_EVERY === 0) {
                            $b->forceFill(['email_sent' => $sent])->save();
                        }
                    } catch (Throwable $e) {
                        report($e);
                    }
                }
                $b->forceFill(['email_sent' => $sent])->save();
            });
        $b->forceFill(['email_sent' => $sent])->save();
    }
}
