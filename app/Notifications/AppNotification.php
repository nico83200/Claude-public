<?php

namespace App\Notifications;

use App\Models\FeatureFlag;
use App\Models\PushSubscription;
use App\Models\User;
use App\Notifications\Channels\WebPushChannel;
use App\Services\MailDesign;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification générique : application, notification sur le téléphone (appareils abonnés)
 * et email si configuré et accepté.
 *
 * Le contenu doit être construit par l'appelant en ne reprenant que des
 * informations auxquelles le destinataire a accès.
 *
 * Envoi en file d'attente (traitée chaque minute par le cron) : un serveur d'e-mails ou de notifications
 * lent ou en panne ne ralentit ni ne fait échouer l'action de l'utilisateur. Pour un envoi immédiat
 * (alerte technique, e-mail demandé par l'utilisateur), utiliser notifyNow().
 */
class AppNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /** Nouvel essai après une panne passagère du serveur d'e-mails ou de notifications. */
    public int $tries = 3;

    public array $backoff = [60, 300];

    public const TYPES = [
        'appointment' => 'Rendez-vous à venir',
        'care_due' => 'Échéance de soin',
        'treatment_end' => 'Fin de traitement',
        'weight_due' => 'Pesée à faire',
        'announcement' => 'Message de Jack&Cie',
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
        'access_changed' => 'Partage suspendu, repris ou quitté par la personne invitée',
        'session_debrief' => 'Bilan de séance à compléter',
        'session_report' => 'Bilan fait par un cavalier',
        'daily_reminder' => 'Rappel de saisie',
        'weekly_digest' => 'Récapitulatif de la semaine',
        'daily_nudge' => 'Suggestion : activer le rappel quotidien',
        'push_test' => 'Essai de notification',
        'payment_due' => 'Paiement à effectuer',
        'inactivity' => 'Rappel après une absence',
        'account_security' => 'Sécurité du compte',
        'new_user' => 'Nouvelle inscription (administration)',
        'system_alert' => 'Alerte technique (administration)',
        'vet_summary' => 'Résumé de santé envoyé à un professionnel',
        'referral' => 'Parrainage : jours offerts',
    ];

    public function __construct(
        public string $kind,
        public string $title,
        public string $body,
        public ?string $url = null,
        public bool $mail = true,
        // Texte plus détaillé pour l'e-mail (le téléphone garde le résumé court).
        public ?string $mailBody = null,
    ) {
        // Mise en file seulement une fois l'enregistrement validé en base (jamais de notification fantôme).
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        // Alerte technique : toujours par e-mail, même si les e-mails de notification sont coupés.
        // Envoi demandé expressément (alerte technique, résumé envoyé à un vétérinaire) : toujours par e-mail.
        $mailEnabled = $this->mail && (in_array($this->kind, ['system_alert', 'vet_summary'], true) || (FeatureFlag::enabled('email_notifications') && app(MailDesign::class)->enabled($this->kind)));
        if (! $notifiable instanceof User) {
            return $mailEnabled ? ['mail'] : [];
        }
        $channels = $this->kind === 'push_test' ? [] : ['database'];
        if (PushSubscription::where('user_id', $notifiable->id)->exists()) {
            $channels[] = WebPushChannel::class;
        }
        if ($mailEnabled && ($this->kind === 'system_alert' || ($notifiable->loadMissing('profile')->profile?->email_notifications ?? true))) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        // Mise en forme réglée par le super-administrateur (Administration › E-mails).
        return app(MailDesign::class)->mailMessage($this->kind, ['name' => $notifiable->name ?? null], $this->title, $this->mailBody ?? $this->body, $this->url);
    }

    /** @return array{title: string, body: string, url: string|null, tag: string} */
    public function toWebPush(object $notifiable): array
    {
        return ['title' => $this->title, 'body' => $this->body, 'url' => $this->url, 'tag' => $this->kind];
    }

    public function toArray(object $notifiable): array
    {
        return ['kind' => $this->kind, 'title' => $this->title, 'body' => $this->body, 'url' => $this->url];
    }
}
