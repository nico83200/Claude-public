<?php

namespace App\Services;

use App\Models\ApplicationSetting;

/**
 * Identité de l'éditeur et sous-traitants, pour les pages Confidentialité et Conditions.
 * Les réglages de l'administration priment ; à défaut, config/equine.php (legal) et, pour les
 * sous-traitants, la liste des services réellement branchés (Stripe, Claude, ElevenLabs…).
 */
class LegalInfo
{
    public function entity(): string
    {
        return $this->setting('legal_entity') ?? config('equine.legal.entity');
    }

    public function address(): string
    {
        return $this->setting('legal_address') ?? config('equine.legal.address');
    }

    public function siret(): ?string
    {
        return config('equine.legal.siret');
    }

    public function hosting(): string
    {
        return $this->setting('hosting_provider') ?? config('equine.legal.hosting');
    }

    public function contact(): string
    {
        return $this->setting('dpo_email') ?? $this->support();
    }

    public function support(): string
    {
        return $this->setting('support_email') ?? config('equine.support_email') ?? config('mail.from.address');
    }

    /** Sous-traitants : texte de l'administration s'il est complet, sinon la liste des services configurés. */
    public function subprocessors(): array
    {
        $custom = $this->setting('subprocessors');
        if ($custom !== null && ! str_contains($custom, 'à compléter')) {
            return array_values(array_filter(array_map('trim', preg_split('/\R/', $custom))));
        }

        return $this->detectedSubprocessors();
    }

    /** Services réellement branchés sur cette installation. */
    public function detectedSubprocessors(): array
    {
        $lines = ['Hébergement de l\'application et des données : '.$this->hosting()];
        if (filled(config('services.stripe.secret'))) {
            $lines[] = 'Paiement des abonnements : Stripe Payments Europe Ltd. (Irlande)';
        }
        if (filled(config('services.anthropic.key'))) {
            $lines[] = 'Assistant (consignes, questions, factures et ordonnances photographiées) : Anthropic PBC (États-Unis)';
        }
        if (filled(config('services.elevenlabs.key'))) {
            $lines[] = 'Voix (transcription des consignes dictées, réponses lues) : ElevenLabs (États-Unis)';
        }
        $social = array_filter([
            filled(config('services.google.client_id')) ? 'Google' : null,
            filled(config('services.apple.client_id')) ? 'Apple' : null,
        ]);
        if ($social) {
            $lines[] = 'Connexion avec un compte '.implode(' ou ', $social).' (si vous la choisissez) : '.implode(', ', array_map(fn ($s) => $s === 'Google' ? 'Google LLC' : 'Apple Inc.', $social));
        }
        $host = (string) config('mail.mailers.smtp.host');
        if (config('mail.default') === 'smtp' && $host !== '' && ! in_array($host, ['127.0.0.1', 'localhost', 'mailpit'], true)) {
            $lines[] = 'Envoi des e-mails : serveur '.$host;
        }

        return $lines;
    }

    private function setting(string $key): ?string
    {
        $value = ApplicationSetting::get($key);

        return is_string($value) && trim($value) !== '' && ! str_contains($value, 'à compléter') ? trim($value) : null;
    }
}
