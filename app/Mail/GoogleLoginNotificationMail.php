<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class GoogleLoginNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $name,
        public string $email,
        public string $signedInAt,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'New sign-in to your TaskFlow account');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.google-login-notification');
    }
}
