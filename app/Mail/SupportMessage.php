<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Message envoyé depuis Assistance : réponse directe à l'utilisateur (Reply-To). */
class SupportMessage extends Mailable
{
    /** @param array<string, string> $context */
    public function __construct(public User $sender, public string $topic, public string $body, public array $context, public ?array $file = null) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[Assistance Jack&Cie] '.$this->topic.' — '.$this->sender->name,
            replyTo: [new Address($this->sender->email, $this->sender->name)],
        );
    }

    public function content(): Content
    {
        return new Content(text: 'emails.support');
    }

    public function attachments(): array
    {
        return $this->file ? [Attachment::fromData(fn () => $this->file['data'], $this->file['name'])->withMime($this->file['mime'])] : [];
    }
}
