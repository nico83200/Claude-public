<?php

namespace App\Notifications;

use App\Models\FeatureFlag;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification générique (application + email si configuré et accepté).
 *
 * Le contenu doit être construit par l'appelant en ne reprenant que des
 * informations auxquelles le destinataire a accès.
 */
class AppNotification extends Notification
{
    use Queueable;

    public const TYPES = [
        'appointment' => 'Rendez-vous à venir',
        'care_due' => 'Échéance de soin',
        'treatment_end' => 'Fin de traitement',
        'invitation' => 'Invitation',
        'invitation_accepted' => 'Invitation acceptée',
        'observation' => 'Nouvelle observation',
        'session_changed' => 'Modification de séance',
        'payment_succeeded' => 'Paiement confirmé',
        'payment_failed' => 'Paiement échoué',
        'renewal' => 'Renouvellement',
        'cancellation' => 'Résiliation',
        'sync_action' => 'Synchronisation à vérifier',
        'access_revoked' => 'Accès révoqué',
    ];

    public function __construct(
        public string $kind,
        public string $title,
        public string $body,
        public ?string $url = null,
        public bool $mail = true,
    ) {}

    public function via(object $notifiable): array
    {
        $mailEnabled = $this->mail && FeatureFlag::enabled('email_notifications');
        if (! $notifiable instanceof User) {
            return $mailEnabled ? ['mail'] : [];
        }
        $channels = ['database'];
        if ($mailEnabled && ($notifiable->profile?->email_notifications ?? true)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject($this->title)->greeting('Bonjour,')->line($this->body);
        if ($this->url) {
            $mail->action('Ouvrir', $this->url);
        }

        return $mail->salutation('— '.config('app.name'));
    }

    public function toArray(object $notifiable): array
    {
        return ['kind' => $this->kind, 'title' => $this->title, 'body' => $this->body, 'url' => $this->url];
    }
}
