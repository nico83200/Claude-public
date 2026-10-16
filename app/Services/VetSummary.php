<?php

namespace App\Services;

use App\Models\CareRecord;
use App\Models\HealthObservation;
use App\Models\Horse;
use App\Models\HorseMeasurement;
use App\Models\RidingSession;
use App\Models\Treatment;

/**
 * Résumé de santé d'un cheval sur les derniers mois, pour le vétérinaire :
 * soins, traitements, poids, observations et anomalies signalées en séance.
 * Données brutes uniquement (aucune interprétation médicale).
 */
class VetSummary
{
    public function build(Horse $horse, int $months = 6): array
    {
        $horse->loadMissing(['breed', 'identifiers']);
        $from = now()->subMonths($months)->startOfDay();
        $weights = HorseMeasurement::where('horse_id', $horse->id)->where('measured_on', '>=', $from->toDateString())->orderBy('measured_on')->get();
        $withWeight = $weights->whereNotNull('weight_kg');

        return [
            'horse' => $horse,
            'from' => $from,
            'months' => $months,
            'identity' => array_filter([
                'Sexe' => $horse->sex !== 'unknown' ? Horse::SEXES[$horse->sex] ?? null : null,
                'Âge' => $horse->age() ? $horse->age().' ans' : null,
                'Race' => $horse->breed?->name,
                'Robe' => $horse->coat,
                'Discipline' => Horse::DISCIPLINES[$horse->main_discipline] ?? $horse->main_discipline,
                'Transpondeur' => $horse->identifier('transponder'),
                'SIRE' => $horse->identifier('sire'),
            ]),
            'precautions' => $horse->precautions,
            // Notes de la fiche d'urgence (allergies, réactions connues…) : utiles au vétérinaire.
            'emergency_notes' => $horse->emergency_notes,
            'treatments' => Treatment::where('horse_id', $horse->id)
                ->where(fn ($q) => $q->where('status', 'active')->orWhere('starts_on', '>=', $from->toDateString())->orWhere('ends_on', '>=', $from->toDateString()))
                ->orderByDesc('starts_on')->get(),
            'care' => CareRecord::with(['category', 'professional'])->where('horse_id', $horse->id)->where('performed_at', '>=', $from)->orderByDesc('performed_at')->get(),
            'upcoming' => CareRecord::with('category')->where('horse_id', $horse->id)->whereNotNull('next_check_on')->whereNull('next_check_done_at')
                ->where('next_check_on', '>=', today()->toDateString())->orderBy('next_check_on')->get(),
            'weights' => $weights,
            'weight_change' => $withWeight->count() >= 2 ? round($withWeight->last()->weight_kg - $withWeight->first()->weight_kg, 1) : null,
            'observations' => HealthObservation::where('horse_id', $horse->id)->where('observed_at', '>=', $from)->orderByDesc('observed_at')->get(),
            'anomalies' => RidingSession::where('horse_id', $horse->id)->where('scheduled_at', '>=', $from)->whereNotNull('anomalies')->where('anomalies', '!=', '')
                ->orderByDesc('scheduled_at')->get(['id', 'scheduled_at', 'anomalies', 'session_type']),
        ];
    }

    /** Texte brut (pour la synthèse rédigée par Claude). */
    public function text(array $s): string
    {
        $h = $s['horse'];
        $lines = ['Cheval : '.$h->shortName().($h->official_name !== $h->shortName() ? ' ('.$h->official_name.')' : '').' · '.collect($s['identity'])->map(fn ($v, $k) => $k.' '.$v)->implode(', ')];
        $lines[] = 'Période : depuis le '.$s['from']->format('d/m/Y');
        if ($s['precautions']) {
            $lines[] = 'Précautions : '.$s['precautions'];
        }
        if ($s['emergency_notes'] ?? null) {
            $lines[] = 'Notes d\'urgence (allergies…) : '.$s['emergency_notes'];
        }
        $lines[] = "\nTraitements :";
        foreach ($s['treatments'] as $t) {
            $lines[] = '- '.$t->product.($t->dosage ? ' ('.$t->dosage.')' : '').' du '.$t->starts_on->format('d/m/Y').($t->ends_on ? ' au '.$t->ends_on->format('d/m/Y') : '').' · '.$t->status;
        }
        $lines[] = "\nSoins :";
        foreach ($s['care'] as $c) {
            $lines[] = '- '.$c->performed_at->format('d/m/Y').' '.$c->category?->name.($c->professional ? ' ('.$c->professional->fullName().')' : '').($c->reason ? ' – motif : '.$c->reason : '').($c->diagnosis ? ' – diagnostic : '.$c->diagnosis : '').($c->instructions ? ' – consignes : '.$c->instructions : '');
        }
        $lines[] = "\nPoids :";
        foreach ($s['weights'] as $w) {
            $lines[] = '- '.$w->measured_on->format('d/m/Y').($w->weight_kg ? ' '.$w->weight_kg.' kg' : '').($w->body_condition ? ' · état '.$w->body_condition.'/9' : '');
        }
        $lines[] = "\nObservations :";
        foreach ($s['observations'] as $o) {
            $lines[] = '- '.$o->observed_at->format('d/m/Y').' ['.(HealthObservation::SEVERITIES[$o->severity] ?? $o->severity).'] '.mb_substr((string) $o->body, 0, 300);
        }
        $lines[] = "\nAnomalies signalées en séance :";
        foreach ($s['anomalies'] as $a) {
            $lines[] = '- '.$a->scheduled_at->format('d/m/Y').' '.mb_substr((string) $a->anomalies, 0, 300);
        }

        return implode("\n", $lines);
    }
}
