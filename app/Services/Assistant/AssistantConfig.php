<?php

namespace App\Services\Assistant;

use App\Models\ApplicationSetting;

/** Réglages de l'assistant modifiables par le super-administrateur. */
class AssistantConfig
{
    public const MODELS = [
        'claude-opus-5-5' => 'Opus 5.5 — le plus fiable (recommandé)',
        'claude-sonnet-5-5' => 'Sonnet 5.5 — plus rapide, 2 × moins cher',
        'claude-haiku-5-5' => 'Haiku 5.5 — très rapide, économique',
    ];

    public const EFFORTS = [
        'low' => 'Rapide (réponses en quelques secondes)',
        'medium' => 'Équilibré (recommandé)',
        'high' => 'Approfondi (plus lent)',
    ];

    public function model(): string
    {
        $m = ApplicationSetting::get('assistant.model');

        return array_key_exists((string) $m, self::MODELS) ? $m : 'claude-opus-5-5';
    }

    public function effort(): string
    {
        $e = ApplicationSetting::get('assistant.effort');

        return array_key_exists((string) $e, self::EFFORTS) ? $e : 'medium';
    }

    /** Texte libre d'indications ajouté aux consignes de l'IA. */
    public function instructions(): string
    {
        return trim((string) ApplicationSetting::get('assistant.instructions', ''));
    }

    /** Durée d'une séance sans précision contraire (minutes). */
    public function sessionMinutes(): array
    {
        $min = (int) (ApplicationSetting::get('assistant.session_min') ?: 50);
        $max = (int) (ApplicationSetting::get('assistant.session_max') ?: 60);

        return [min($min, $max), max($min, $max)];
    }

    public function enabled(): bool
    {
        return (bool) (ApplicationSetting::get('assistant.enabled') ?? true);
    }
}
