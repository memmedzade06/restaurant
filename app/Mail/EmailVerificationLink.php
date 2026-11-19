<?php

namespace App\Mail;

use App\Models\User;
use App\Services\EmailVerification;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EmailVerificationLink extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $user, public string $url)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: '50 Qəpik — qeydiyyatı təsdiqləyin');
    }

    public function content(): Content
    {
        return new Content(
            view: 'frontend.emails.verify-link',
            with: ['ttl' => EmailVerification::LINK_TTL_MINUTES],
        );
    }
}
