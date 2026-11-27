<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Adminlə yazışma mesajı: user_id — söhbətin sahibi, from_admin — cavabı admin yazıb */
class SupportMessage extends Model
{
    public const MAX_LENGTH = 2000;

    /** Düymənin yazısı verilməyəndə */
    public const DEFAULT_LINK_LABEL = 'Keçid et';

    protected $fillable = ['user_id', 'sender_id', 'from_admin', 'broadcast_id', 'body', 'link_url', 'link_label',
        'attachment_path', 'attachment_name', 'attachment_mime', 'attachment_size', 'read_at'];

    /** Admin mesaja fayl əlavə edə bilər: şəkil, PDF, Word, Excel, mətn — ən çox 10 MB; video (mp4, mov, webm) — ən çox 50 MB */
    public const FILE_MAX_KB = 10240;
    public const VIDEO_MAX_KB = 51200;
    public const VIDEO_TYPES = 'mp4,mov,webm';
    public const FILE_TYPES = 'jpg,jpeg,png,webp,gif,pdf,doc,docx,xls,xlsx,txt,' . self::VIDEO_TYPES;
    public const VIDEO_MIMES = ['video/mp4', 'video/quicktime', 'video/webm'];

    /** Faylın yoxlanması (admin cavabı və hamıya mesaj üçün eyni): video 50 MB-a, qalan fayllar 10 MB-a qədər */
    public static function fileRules(): array
    {
        return ['nullable', 'file', 'max:' . self::VIDEO_MAX_KB, 'mimes:' . self::FILE_TYPES,
            function (string $attribute, $value, \Closure $fail) {
                $video = in_array($value->getMimeType(), self::VIDEO_MIMES, true);
                if (! $video && $value->getSize() > self::FILE_MAX_KB * 1024) {
                    $fail('Fayl ən çox ' . (self::FILE_MAX_KB / 1024) . ' MB ola bilər (video — ' . (self::VIDEO_MAX_KB / 1024) . ' MB).');
                }
            }];
    }

    public static function fileMessages(): array
    {
        return [
            'file.max' => 'Video ən çox ' . (self::VIDEO_MAX_KB / 1024) . ' MB, digər fayllar ' . (self::FILE_MAX_KB / 1024) . ' MB ola bilər.',
            'file.mimes' => 'Bu növ fayl olmaz. Olar: ' . str_replace(',', ', ', self::FILE_TYPES) . '.',
            'file.uploaded' => 'Fayl yüklənmədi (çox böyük ola bilər).',
        ];
    }

    /** Yüklənmiş faylı saxlayır (storage/app/support/İL/AY, açıq deyil) və mesajın sahələrini qaytarır */
    public static function storeUpload(\Illuminate\Http\UploadedFile $file): array
    {
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
        $path = $file->storeAs('support/' . now()->format('Y/m'), \Illuminate\Support\Str::random(32) . '.' . $ext, 'local');
        $name = trim(preg_replace('~[\\/:*?"<>|\x00-\x1F]+~u', '_', $file->getClientOriginalName())) ?: ('fayl.' . $ext);

        return [
            'attachment_path' => $path,
            'attachment_name' => mb_substr($name, 0, 200),
            'attachment_mime' => $file->getMimeType(),
            'attachment_size' => $file->getSize(),
        ];
    }

    public function isImageAttachment(): bool
    {
        return in_array($this->attachment_mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true);
    }

    public function isVideoAttachment(): bool
    {
        return in_array($this->attachment_mime, self::VIDEO_MIMES, true);
    }

    /** 1,2 MB / 340 KB */
    public static function humanSize(int $bytes): string
    {
        return $bytes >= 1048576
            ? str_replace('.', ',', (string) round($bytes / 1048576, 1)) . ' MB'
            : max(1, (int) round($bytes / 1024)) . ' KB';
    }

    protected $casts = [
        'from_admin' => 'boolean',
        'read_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /** İstifadəçinin hələ oxumadığı admin cavabları */
    public function scopeUnreadForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId)->where('from_admin', true)->whereNull('read_at');
    }

    /** Adminin hələ oxumadığı istifadəçi mesajları */
    public function scopeUnreadForAdmin(Builder $query): Builder
    {
        return $query->where('from_admin', false)->whereNull('read_at');
    }

    /** Brauzerə göndərilən forma. $viewerIsAdmin — kim baxır (öz mesajı sağda görünür) */
    public function toChat(bool $viewerIsAdmin): array
    {
        $local = $this->created_at->timezone('Asia/Baku');

        return [
            'id' => $this->id,
            'body' => $this->body,
            'mine' => $this->from_admin === $viewerIsAdmin,
            'author' => $this->from_admin
                ? ($viewerIsAdmin
                    ? ($this->broadcast_id ? 'Hamıya · ' . ($this->sender?->short_name ?? 'Admin') : ($this->sender_id ? $this->sender?->short_name ?? 'Admin' : 'Avtomatik'))
                    : '50 Qəpik')
                : null,
            'time' => $local->isToday() ? $local->format('H:i') : $local->format('d.m.Y H:i'),
            'read' => $this->read_at !== null, // öz mesajında: ✓ göndərilib, ✓✓ qarşı tərəf oxuyub
            // əlavə olunmuş fayl: şəkil söhbətdə görünür, qalanı yüklənir
            'file' => $this->attachment_path ? [
                'url' => route('support.file', $this),
                'name' => $this->attachment_name,
                'size' => self::humanSize((int) $this->attachment_size),
                'image' => $this->isImageAttachment(),
                'video' => $this->isVideoAttachment(), // söhbətdə oynadılır
            ] : null,
            // mesajın altındakı düymə (admin hamıya mesajda link qoyubsa)
            'link' => $this->link_url ? [
                'url' => $this->link_url,
                'label' => $this->link_label ?: self::DEFAULT_LINK_LABEL,
                'external' => self::isExternal($this->link_url),
            ] : null,
        ];
    }

    /** Link başqa sayta aparır? (sayt daxilindəki link eyni pəncərədə, kənar link yeni pəncərədə açılır) */
    public static function isExternal(string $url): bool
    {
        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return false;
        }

        return parse_url($url, PHP_URL_HOST) !== parse_url(config('app.url'), PHP_URL_HOST)
            && parse_url($url, PHP_URL_HOST) !== request()->getHost();
    }

    /**
     * Mesaj mətni: mətndəki http(s):// ünvanları basıla bilən link olur, qalan hər şey ekranlanır (HTML işləmir).
     * Brauzerdəki eyni iş: public/js/support-chat.js → linkify.
     */
    public static function linkify(string $text): \Illuminate\Support\HtmlString
    {
        $out = '';
        $pos = 0;
        preg_match_all('~https?://[^\s<>"\']+~u', $text, $m, PREG_OFFSET_CAPTURE);
        foreach ($m[0] as [$url, $at]) {
            $url = rtrim($url, '.,!?:;)'); // cümlənin sonundakı nöqtə linkə düşməsin
            $out .= e(substr($text, $pos, $at - $pos));
            $out .= '<a href="' . e($url) . '" target="_blank" rel="noopener nofollow">' . e($url) . '</a>';
            $pos = $at + strlen($url);
        }

        return new \Illuminate\Support\HtmlString($out . e(substr($text, $pos)));
    }

    /** Hazırda saytda olan admin varmı ("indi saytda" siqnalı son User::ONLINE_SECONDS saniyədə gəlib) */
    public static function adminOnline(): bool
    {
        return User::where('is_admin', true)
            ->where('last_seen_at', '>=', now()->subSeconds(User::ONLINE_SECONDS))
            ->exists();
    }

    /**
     * Baxanın öz mesajlarından qarşı tərəfin oxuduğu ən son mesajın id-si (yoxdursa 0).
     * Oxunma ardıcıl olur (açılanda hamısı oxunur), ona görə bu id-yə qədər olanların hamısı oxunub.
     */
    public static function readUntil(int $userId, bool $viewerIsAdmin): int
    {
        return (int) static::where('user_id', $userId)->where('from_admin', $viewerIsAdmin)
            ->whereNotNull('read_at')->max('id');
    }
}
