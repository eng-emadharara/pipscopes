<?php

namespace App\Mail;

use App\Models\User;
use App\Support\EmailVerification;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VerificationCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $user, public string $code)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __(':code رمز تأكيد بريدك في :app', ['code' => $this->code, 'app' => config('app.name')]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.verification-code',
            text: 'emails.verification-code-text',
            with: ['minutes' => EmailVerification::CODE_TTL_MINUTES],
        );
    }
}
