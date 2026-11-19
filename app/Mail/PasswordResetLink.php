<?php

namespace App\Mail;

use App\Models\User;
use App\Services\EmailPasswordReset;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PasswordResetLink extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $user, public string $url)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: '50 Qəpik — şifrənin bərpası');
    }

    public function content(): Content
    {
        return new Content(
            view: 'frontend.emails.password-reset',
            with: ['ttl' => EmailPasswordReset::TTL_MINUTES],
        );
    }
}
