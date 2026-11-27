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

            SupportBroadcast::channelQuery($b->audience, 'telegram')->select(['id', 'telegram_chat_id'])
                ->chunkById(200, function ($users) use ($b, $text, $buttons, &$fileId, &$sent) {
                    foreach ($users as $u) {
                        try {
                            if ($this->telegramOne($b, $u->telegram_chat_id, $text, $buttons, $fileId)) {
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

    private function telegramOne(SupportBroadcast $b, string $chatId, string $text, array $buttons, ?string &$fileId): bool
    {
        if (! $b->attachment_path) {
            $r = $this->bot->call('sendMessage', ['chat_id' => $chatId, 'text' => $text, 'parse_mode' => 'HTML'] + $buttons);

            return $r->ok() && $r->json('ok');
        }

        $kind = $this->telegramKind($b);
        $long = mb_strlen($text) > self::CAPTION_MAX;
        if ($long) { // yazı uzundursa — əvvəl mətn, sonra fayl (düymə faylın altında)
            $this->bot->call('sendMessage', ['chat_id' => $chatId, 'text' => $text, 'parse_mode' => 'HTML']);
        }
        $params = ['chat_id' => $chatId] + ($long || $text === '' ? [] : ['caption' => $text, 'parse_mode' => 'HTML']);
        if ($buttons) {
            $params['reply_markup'] = json_encode($buttons['reply_markup']);
        }

        if ($fileId) {
            $r = $this->bot->call($kind[0], $params + [$kind[1] => $fileId], 60);
        } else {
            $path = Storage::disk('local')->path($b->attachment_path);
            $r = $this->bot->upload($kind[0], $params, $kind[1], $path, $b->attachment_name, 300);
            if ($r->ok() && $r->json('ok')) {
                $fileId = $this->fileIdFrom($r->json('result'), $kind[1]);
            }
        }

        return $r->ok() && $r->json('ok');
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

    private function fileIdFrom(?array $result, string $field): ?string
    {
        $item = $result[$field] ?? null;
        if ($field === 'photo' && is_array($item)) {
            $item = end($item); // şəklin ən böyük ölçüsü
        }

        return is_array($item) ? ($item['file_id'] ?? null) : null;
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
