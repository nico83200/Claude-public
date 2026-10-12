<?php

namespace App\Services;

use App\Models\RidingSession;
use Illuminate\Support\Str;

/**
 * Bilan de séance en questions à choix (au lieu de textes libres), adaptées à la séance :
 * objectif saisi, exercices prévus, catégories de ces exercices, type de séance et discipline.
 * Les réponses sont enregistrées en texte dans les champs habituels (progrès, difficultés, à retravailler,
 * anomalies, objectifs), donc l'historique, le suivi et les alertes fonctionnent comme avant.
 */
class DebriefQuestions
{
    public const ANOMALIES = ['Boiterie', 'Raideur', 'Membre chaud ou gonflé', 'Plaie ou blessure', 'Toux ou gêne respiratoire', 'Comportement inhabituel', 'Fer perdu', 'Problème de matériel'];

    public const NEXT = ['Refaire la même séance', 'Plus de détente', 'Séance plus courte', 'Augmenter la difficulté', 'Revoir les bases', 'Travail en extérieur', 'Travail à pied ou en longe'];

    private const BY_TYPE = [
        'jumping' => ['Abords', 'Cadence avant l\'obstacle', 'Équilibre à la réception', 'Franchise'],
        'poles' => ['Régularité de la foulée', 'Équilibre', 'Attention aux barres'],
        'dressage' => ['Contact', 'Incurvation', 'Rectitude', 'Transitions'],
        'lunging' => ['Écoute à la voix', 'Détente', 'Régularité sur le cercle'],
        'groundwork' => ['Écoute', 'Respect de l\'espace', 'Détente'],
        'liberty' => ['Écoute', 'Lien avec le cheval', 'Détente'],
        'ride' => ['Calme en extérieur', 'Franchise', 'Allures'],
        'outdoor' => ['Calme en extérieur', 'Franchise', 'Allures'],
        'private_lesson' => ['Position', 'Utilisation des aides', 'Équilibre du cavalier'],
        'group_lesson' => ['Position', 'Utilisation des aides', 'Équilibre du cavalier'],
        'recovery' => ['Décontraction', 'Allures régulières'],
        'active_rest' => ['Décontraction', 'Calme'],
    ];

    private const GENERIC = ['Rythme', 'Décontraction', 'Contact', 'Transitions', 'Impulsion', 'Équilibre'];

    /** @return array{objective: ?string, exercises: string[], aspects: string[]} */
    public function for(RidingSession $session): array
    {
        $session->loadMissing('exercises.exercise.category');
        $work = $session->exercises->where('is_break', false);
        $exercises = $work->pluck('name')->filter()->unique()->take(10)->values()->all();
        $aspects = collect(self::BY_TYPE[$session->session_type] ?? [])
            ->merge(self::BY_TYPE[$session->discipline] ?? [])
            ->merge($work->map(fn ($i) => $i->exercise?->category?->name)->filter())
            ->merge(self::GENERIC)
            ->reject(fn ($a) => in_array(mb_strtolower($a), ['échauffement', 'retour au calme'], true))
            ->unique(fn ($a) => mb_strtolower($a))->take(9)->values()->all();

        return ['objective' => $session->objective ?: null, 'exercises' => $exercises, 'aspects' => $aspects];
    }

    /** Réponses du questionnaire → champs texte du bilan. */
    public function compose(array $in, RidingSession $session): array
    {
        $list = fn ($key) => array_values(array_filter(array_map(fn ($v) => Str::limit(trim((string) $v), 150, ''), (array) ($in[$key] ?? []))));
        $join = fn (array $items) => $items ? implode(', ', $items) : null;
        $good = $list('q_good');
        $objective = $in['q_objective'] ?? null;
        $progress = collect([
            $session->objective && in_array($objective, ['atteint', 'en partie', 'pas encore'], true) ? 'Objectif « '.$session->objective.' » : '.$objective.'.' : null,
            $good ? 'Bien réussi : '.$join($good).'.' : null,
            ! empty($in['q_comment']) ? 'Remarque : '.Str::limit(trim($in['q_comment']), 500, '').'' : null,
        ])->filter()->implode(' ');
        $anomalies = array_values(array_diff($list('q_anomalies'), ['Rien à signaler']));

        return [
            'progress' => $progress ?: null,
            'difficulties' => $join(array_values(array_diff($list('q_hard'), ['Rien de particulier']))),
            'to_rework' => $join($list('q_rework')),
            'anomalies' => $join($anomalies),
            'next_objectives' => $join($list('q_next')),
        ];
    }
}
