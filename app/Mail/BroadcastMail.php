<?php

namespace App\Mail;

use App\Models\SupportBroadcast;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Str;

/** Hamıya mesaj — e-poçt variantı: mətn, link düyməsi və (kiçikdirsə) fayl əlavə kimi */
class BroadcastMail extends Mailable
{
    /** Bundan böyük fayl məktuba əlavə olunmur — məktubda saytdakı "Mesajlar"a link verilir */
    public const ATTACH_MAX_BYTES = 15 * 1024 * 1024;

    /** Böyük faylın linki neçə gün açılır */
    public const LINK_DAYS = 30;

    public function __construct(public SupportBroadcast $broadcast, public User $user)
    {
    }

    public function envelope(): Envelope
    {
        $first = trim(Str::before((string) $this->broadcast->body, "\n"));

        return new Envelope(subject: '50 Qəpik' . ($first !== '' ? ' — ' . Str::limit($first, 60) : ': yeni mesaj'));
    }

    public function attachesFile(): bool
    {
        return $this->broadcast->attachment_path && (int) $this->broadcast->attachment_size <= self::ATTACH_MAX_BYTES;
    }

    public function content(): Content
    {
        $b = $this->broadcast;

        return new Content(view: 'frontend.emails.broadcast', with: [
            'linkUrl' => $b->link_url ? (str_starts_with($b->link_url, '/') ? url($b->link_url) : $b->link_url) : null,
            'linkLabel' => $b->link_label ?: \App\Models\SupportMessage::DEFAULT_LINK_LABEL,
            'fileAttached' => $this->attachesFile(),
            'messagesUrl' => route('support'),
            'inChat' => $b->hasChannel('chat'), // mesaj saytdakı söhbətə də gedibmi
            // məktuba sığmayan fayl üçün imzalı link (30 gün): giriş tələb olunmur
            'fileUrl' => $b->attachment_path && ! $this->attachesFile()
                ? \Illuminate\Support\Facades\URL::temporarySignedRoute('support.broadcast.file', now()->addDays(self::LINK_DAYS), ['broadcast' => $b->id])
                : null,
            'fileIsVideo' => in_array($b->attachment_mime, \App\Models\SupportMessage::VIDEO_MIMES, true),
        ]);
    }

    public function attachments(): array
    {
        if (! $this->attachesFile()) {
            return [];
        }

        return [Attachment::fromStorageDisk('local', $this->broadcast->attachment_path)
            ->as($this->broadcast->attachment_name)
            ->withMime($this->broadcast->attachment_mime ?: 'application/octet-stream')];
    }
}
