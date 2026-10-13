<?php

namespace App\Services;

use App\Models\ApplicationSetting;
use App\Notifications\AppNotification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Storage;

/**
 * Mise en forme des e-mails, entièrement réglable par le super-administrateur
 * (Administration › E-mails) : logo, couleurs, textes avant et après le motif,
 * objet, bouton, signature, pied de page, et surcharge type par type.
 *
 * Un champ propre à un type laissé vide reprend le texte général ;
 * « - » seul l'efface pour ce type.
 */
class MailDesign
{
    public const KEY = 'mail_design';

    public const LOGO_DIR = 'mail';

    public const LOGOS = [
        'logo' => 'Logo complet',
        'wordmark' => 'Nom Jack&Cie',
        'signature' => 'Signature',
        'horse' => 'Cheval seul',
        'custom' => 'Image envoyée',
        'none' => 'Aucun logo',
    ];

    public const PLACEHOLDERS = [
        '{prenom}' => 'Prénom du destinataire',
        '{nom}' => 'Nom complet du destinataire',
        '{titre}' => 'Titre de la notification',
        '{message}' => 'Motif (texte du rappel)',
        '{lien}' => 'Adresse ouverte par le bouton',
        '{app}' => "Nom de l'application",
        '{date}' => 'Date du jour',
    ];

    /** E-mails de compte, hors notifications : modifiables mais jamais désactivables. */
    public const ACCOUNT_KINDS = [
        'verify_email' => 'Confirmation de l\'adresse e-mail',
        'reset_password' => 'Réinitialisation du mot de passe',
    ];

    /** Textes propres à un type tant que le super-administrateur ne les a pas changés. */
    public const KIND_DEFAULTS = [
        'verify_email' => ['button_label' => 'Confirmer mon adresse'],
        'reset_password' => ['button_label' => 'Choisir un nouveau mot de passe'],
    ];

    /** Champs texte réglables globalement et par type. */
    public const TEXT_FIELDS = ['subject', 'greeting', 'intro', 'message', 'outro', 'button_label'];

    public const COLOR_FIELDS = ['background_color', 'card_color', 'text_color', 'button_color', 'button_text_color', 'accent_color'];

    /** Exemples réalistes pour l'aperçu de chaque type. */
    private const SAMPLES = [
        'appointment' => ['Rendez-vous demain', 'Séance monte libre – Jack le 14/10/2026 à 17:30.'],
        'care_due' => ['Vermifuge à prévoir', 'Le vermifuge de Jack est prévu le 20/10/2026.'],
        'weight_due' => ['Pesée à faire', 'Pensez à mesurer le poids de Jack : dernière pesée il y a 4 semaines.'],
        'daily_reminder' => ['Rien de noté aujourd\'hui ?', 'Une séance, un soin, une observation ? Notez-le en quelques secondes.'],
        'verify_email' => ['Confirmez votre adresse e-mail', 'Cliquez sur le bouton ci-dessous pour confirmer votre adresse e-mail.'],
        'reset_password' => ['Réinitialisation du mot de passe', 'Vous avez demandé à réinitialiser votre mot de passe. Ce lien est valable 60 minutes.'],
    ];

    public static function defaults(): array
    {
        return [
            'logo' => 'logo',
            'logo_width' => 140,
            'logo_link' => true,
            'align' => 'center',
            'background_color' => '#f4f1ea',
            'card_color' => '#ffffff',
            'text_color' => '#1e2a23',
            'button_color' => '#174d37',
            'button_text_color' => '#ffffff',
            'accent_color' => '#a8875e',
            'subject_prefix' => '',
            'subject' => '{titre}',
            'greeting' => 'Bonjour {prenom},',
            'intro' => '',
            'message' => '{message}',
            'outro' => '',
            'button_label' => 'Ouvrir',
            'signature' => "À bientôt,\nL'équipe {app}",
            'show_fallback_link' => true,
            'fallback_text' => 'Si le bouton ne fonctionne pas, copiez ce lien dans votre navigateur :',
            'footer' => "Vous recevez cet e-mail car les notifications par e-mail sont activées dans votre compte {app}.\nVous pouvez les désactiver à tout moment dans Paramètres › Notifications.",
            'kinds' => [],
        ];
    }

    /** Réglages enregistrés complétés par les valeurs par défaut. */
    public function settings(): array
    {
        $stored = (array) ApplicationSetting::get(self::KEY, []);
        $settings = array_replace(self::defaults(), array_intersect_key($stored, self::defaults()));
        $settings['kinds'] = (array) ($stored['kinds'] ?? []);

        return $settings;
    }

    /** @return array<string, string> tous les types d'e-mails réglables */
    public static function kinds(): array
    {
        return AppNotification::TYPES + self::ACCOUNT_KINDS;
    }

    public function enabled(string $kind): bool
    {
        if (isset(self::ACCOUNT_KINDS[$kind])) {
            return true;
        }

        return (bool) ($this->settings()['kinds'][$kind]['enabled'] ?? true);
    }

    /** Valeur effective d'un champ pour un type (surcharge du type, sinon texte général). */
    public function field(array $settings, string $kind, string $field): string
    {
        $own = trim((string) ($settings['kinds'][$kind][$field] ?? self::KIND_DEFAULTS[$kind][$field] ?? ''));
        if ($own === '-') {
            return '';
        }

        return $own !== '' ? $own : (string) ($settings[$field] ?? '');
    }

    /**
     * Contenu final d'un e-mail, variables remplacées.
     *
     * @param  array{name?: string|null}  $recipient
     */
    public function compose(string $kind, array $recipient, string $title, string $body, ?string $url, ?array $settings = null): array
    {
        $s = $settings ?? $this->settings();
        $name = trim((string) ($recipient['name'] ?? ''));
        $vars = [
            '{prenom}' => $name !== '' ? strtok($name, ' ') : '',
            '{nom}' => $name,
            '{titre}' => $title,
            '{message}' => $body,
            '{lien}' => (string) $url,
            '{app}' => (string) config('app.name'),
            '{date}' => now()->format('d/m/Y'),
        ];
        $fill = function (string $text) use ($vars): string {
            $text = strtr($text, $vars);
            // « Bonjour ," quand le prénom est inconnu → « Bonjour, »
            return trim(preg_replace('/ +([,.])/u', '$1', preg_replace('/[ \t]{2,}/', ' ', $text)));
        };
        $subject = $fill($this->field($s, $kind, 'subject')) ?: $title;
        if (($prefix = trim((string) $s['subject_prefix'])) !== '') {
            $subject = $fill($prefix).' '.$subject;
        }

        return [
            'subject' => $subject,
            'greeting' => $fill($this->field($s, $kind, 'greeting')),
            'intro' => $fill($this->field($s, $kind, 'intro')),
            'message' => $fill($this->field($s, $kind, 'message')),
            'outro' => $fill($this->field($s, $kind, 'outro')),
            'button' => $url ? ($fill($this->field($s, $kind, 'button_label')) ?: 'Ouvrir') : null,
            'url' => $url,
            'signature' => $fill((string) $s['signature']),
            'fallback' => $url && $s['show_fallback_link'] ? $fill((string) $s['fallback_text']) : null,
            'footer' => $fill((string) $s['footer']),
            'logo' => $this->logoUrl($s),
            'logo_width' => (int) $s['logo_width'],
            'logo_link' => (bool) $s['logo_link'],
            'align' => $s['align'] === 'left' ? 'left' : 'center',
            'colors' => array_intersect_key($s, array_flip(self::COLOR_FIELDS)),
            'app' => (string) config('app.name'),
        ];
    }

    public function mailMessage(string $kind, array $recipient, string $title, string $body, ?string $url): MailMessage
    {
        $mail = $this->compose($kind, $recipient, $title, $body, $url);

        $message = (new MailMessage)->subject($mail['subject'])
            ->view(['emails.notification', 'emails.notification-text'], ['mail' => $mail]);
        if ($url) {
            // Bouton exposé comme une action Laravel classique (lu par les tests et les journaux).
            $message->actionText = $mail['button'];
            $message->actionUrl = $url;
        }

        return $message;
    }

    /** Aperçu d'un type avec un exemple réaliste (ou les réglages en cours d'édition). */
    public function preview(string $kind, ?array $settings = null, ?string $name = 'Nicolas Martin'): array
    {
        [$title, $body] = self::SAMPLES[$kind] ?? [self::kinds()[$kind] ?? 'Notification', 'Voici le motif de la notification, tel qu\'il est envoyé par l\'application.'];

        return $this->compose($kind, ['name' => $name], $title, $body, route('dashboard'), $settings);
    }

    public function logoUrl(?array $settings = null): ?string
    {
        $s = $settings ?? $this->settings();
        $choice = $s['logo'] ?? 'logo';
        if ($choice === 'none') {
            return null;
        }
        if ($choice === 'custom') {
            $file = $this->customLogoPath();

            return $file ? route('mail.logo', ['v' => substr(md5((string) Storage::disk('local')->lastModified($file)), 0, 8)]) : url('/brand/jackcie-logo.png');
        }
        $files = ['logo' => 'jackcie-logo.png', 'wordmark' => 'jackcie-wordmark.png', 'signature' => 'jackcie-signature.png', 'horse' => 'jackcie-cheval.png'];

        return url('/brand/'.($files[$choice] ?? $files['logo']));
    }

    public function customLogoPath(): ?string
    {
        $files = Storage::disk('local')->files(self::LOGO_DIR);

        return collect($files)->first(fn ($f) => str_starts_with(basename($f), 'logo.'));
    }

    public function save(array $settings): void
    {
        ApplicationSetting::put(self::KEY, $settings);
    }
}
