<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Email de vérification des réglages d'envoi (Administration › Intégrations). */
class TestMail extends Mailable
{
    public function __construct(public string $appName) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->appName.' — email de test');
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.test');
    }
}
