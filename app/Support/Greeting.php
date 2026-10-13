<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * Salutation du tableau de bord selon le moment de la journée et le jour de la semaine.
 * La formule change d'un créneau à l'autre mais reste stable pendant un même créneau
 * (pas de texte différent à chaque rechargement).
 */
class Greeting
{
    /** @return array<int, string> formules possibles, {prenom} remplacé ensuite */
    public static function options(CarbonInterface $now): array
    {
        $h = $now->hour;
        $day = $now->dayOfWeekIso; // 1 = lundi … 7 = dimanche
        $weekend = $day >= 6;

        if ($h >= 22 || $h < 5) {
            return ['Bonsoir {prenom}', 'Encore debout, {prenom} ?', 'Une dernière ronde, {prenom} ?', 'Douce nuit, {prenom}'];
        }
        if ($h < 11) {
            $list = ['Bonjour {prenom}', 'Bien dormi, {prenom} ?', 'Belle matinée, {prenom}', 'Bonjour {prenom}, direction l\'écurie ?'];
            if ($day === 1) {
                $list = ['Bon début de semaine, {prenom}', 'Bonjour {prenom}, c\'est reparti !', 'Bon lundi, {prenom}'];
            } elseif ($weekend) {
                $list = ['Bon week-end, {prenom}', 'Bonjour {prenom}, une balade ce matin ?', 'Bon '.($day === 6 ? 'samedi' : 'dimanche').', {prenom}'];
            }

            return $list;
        }
        if ($h < 14) {
            return $weekend
                ? ['Bon appétit, {prenom}', 'Bon '.($day === 6 ? 'samedi' : 'dimanche').', {prenom}', 'Belle journée, {prenom}']
                : ['Bon appétit, {prenom}', 'Bonjour {prenom}', 'Une pause à l\'écurie, {prenom} ?'];
        }
        if ($h < 18) {
            $list = ['Bon après-midi, {prenom}', 'Re-bonjour {prenom}', 'En selle cet après-midi, {prenom} ?'];
            if ($day === 3) {
                $list[] = 'Bon mercredi, {prenom}';
            } elseif ($day === 5) {
                $list = ['Bientôt le week-end, {prenom} !', 'Bon vendredi, {prenom}', 'Bon après-midi, {prenom}'];
            } elseif ($weekend) {
                $list = ['Bon week-end, {prenom}', 'Belle après-midi au paddock, {prenom} ?', 'Bon '.($day === 6 ? 'samedi' : 'dimanche').', {prenom}'];
            }

            return $list;
        }

        return match (true) {
            $day === 5 => ['Bon vendredi soir, {prenom}', 'Vive le week-end, {prenom} !', 'Bonsoir {prenom}'],
            $day === 7 => ['Bonsoir {prenom}', 'Bonne fin de week-end, {prenom}', 'Une nouvelle semaine se prépare, {prenom}'],
            default => ['Bonsoir {prenom}', 'Bonne soirée, {prenom}', 'Une dernière visite au box, {prenom} ?'],
        };
    }

    public static function for(?string $name, ?CarbonInterface $now = null, int|string $seed = 0): string
    {
        $now ??= now();
        $first = trim((string) strtok(trim((string) $name), ' '));
        $options = self::options($now);
        // Même formule pendant tout le créneau (jour + tranche horaire), différente selon l'utilisateur.
        $slot = $now->format('Y-m-d').'|'.intdiv(($now->hour + 19) % 24, 3).'|'.$seed;
        $text = $options[crc32($slot) % count($options)];
        if ($first === '') {
            // Sans prénom : « Bien dormi ? », « Bonjour », « Bonjour, direction l'écurie ? »
            $text = str_replace([', {prenom},', ', {prenom}', ' {prenom},', ' {prenom}'], [',', '', ',', ''], $text);
        }

        return str_replace('{prenom}', $first, $text);
    }
}
