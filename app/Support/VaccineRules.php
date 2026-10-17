<?php

namespace App\Support;

use App\Models\ApplicationSetting;

/**
 * Règles de vaccination exigées pour concourir (FFE, FEI), réglables par le super-administrateur
 * (Administration › Vaccins concours) car les règlements évoluent.
 *
 * Valeurs par défaut vérifiées le 17 octobre 2026 dans les textes officiels :
 * - FFE, Règlement des compétitions, Dispositions générales (juillet 2026, applicable au 1er septembre 2026), art. 1.6 § 3 :
 *   grippe pour toutes les divisions, grippe et rhinopneumonie pour Pro, Amateur, Prépa/Warm up FFE Compet et Enseignants ;
 *   concours possible à partir du 8e jour suivant une injection ; rappel au plus tard 1 an après le précédent ;
 *   primo-vaccination depuis le 1er janvier 2024 : 2e injection 21 à 60 jours après la 1re, 1er rappel 120 à 180 jours après la 2e.
 * - FEI, Veterinary Regulations 2026 (en vigueur au 1er janvier 2026), art. 1002 et 1003 : grippe ; dernier rappel dans les
 *   6 mois + 21 jours avant l'arrivée sur le concours et pas dans les 7 jours qui la précèdent ; V3 dans les 6 mois + 21 jours après V2.
 */
final class VaccineRules
{
    public const SETTING = 'competition_vaccine_rules';

    /** Concours sans vérification des vaccins. */
    public const NONE = 'none';

    /** Vaccins réglables par règlement. */
    public const VACCINES = ['flu', 'rhino'];

    private const FFE_SOURCE = 'FFE, Règlement des compétitions, Dispositions générales (juillet 2026, applicable au 1er septembre 2026), art. 1.6 § 3 « Protection sanitaire : vaccinations ».';

    private const FFE_DOSE = ['months' => 12, 'days' => 0, 'primo_months' => 0, 'primo_days' => 180, 'min_days' => 8];

    private const FFE_NOTE = 'Tolérance d\'un jour sur les rappels : un cheval vacciné le 10 janvier doit l\'être à nouveau au plus tard le 10 janvier suivant. Un mois compte 30 jours.';

    public const DEFAULTS = [
        'default' => 'ffe_amateur',
        'reminder_days' => [21, 10],
        'sets' => [
            'ffe_amateur' => [
                'label' => 'FFE · Amateur, Pro, Enseignants, Prépa Compet', 'short' => 'FFE Amateur/Pro', 'active' => true, 'arrival' => false,
                'vaccines' => ['flu' => ['required' => true] + self::FFE_DOSE, 'rhino' => ['required' => true] + self::FFE_DOSE],
                'source' => self::FFE_SOURCE, 'note' => self::FFE_NOTE, 'checked_on' => '2026-10-17',
            ],
            'ffe_club' => [
                'label' => 'FFE · Club, Poney, Préparatoire club', 'short' => 'FFE Club/Poney', 'active' => true, 'arrival' => false,
                'vaccines' => ['flu' => ['required' => true] + self::FFE_DOSE, 'rhino' => ['required' => false] + self::FFE_DOSE],
                'source' => self::FFE_SOURCE, 'note' => self::FFE_NOTE, 'checked_on' => '2026-10-17',
            ],
            'fei' => [
                'label' => 'FEI · concours international', 'short' => 'FEI', 'active' => true, 'arrival' => true,
                'vaccines' => [
                    'flu' => ['required' => true, 'months' => 6, 'days' => 21, 'primo_months' => 6, 'primo_days' => 21, 'min_days' => 7],
                    'rhino' => ['required' => false, 'months' => 12, 'days' => 0, 'primo_months' => 0, 'primo_days' => 180, 'min_days' => 7],
                ],
                'source' => 'FEI, Veterinary Regulations 2026 (15e édition, en vigueur au 1er janvier 2026), art. 1002 et 1003.',
                'note' => 'Rappel en retard de moins de 4 semaines : amende de 200 à 400 CHF ; au-delà, le cheval ne peut ni concourir ni entrer aux écuries (annexe VI, sanctions 27 à 30). Le vaccin est aussi à enregistrer dans la FEI HorseApp.',
                'checked_on' => '2026-10-17',
            ],
        ],
    ];

    /** Réglages en vigueur : ceux du super-administrateur, complétés par les valeurs par défaut. */
    public static function config(): array
    {
        $stored = ApplicationSetting::get(self::SETTING);
        $stored = is_array($stored) ? $stored : [];
        $config = array_replace_recursive(self::DEFAULTS, collect($stored)->except('reminder_days')->all());
        $config['sets'] = array_intersect_key($config['sets'], self::DEFAULTS['sets']);
        $config['reminder_days'] = array_key_exists('reminder_days', $stored) && is_array($stored['reminder_days']) ? array_values($stored['reminder_days']) : self::DEFAULTS['reminder_days'];

        return $config;
    }

    public static function sets(): array
    {
        return self::config()['sets'];
    }

    /** Règlement utilisable pour un concours (null : règlement par défaut), avec ses seuls vaccins exigés ; null si aucune vérification. */
    public static function get(?string $key): ?array
    {
        $key = $key ?: self::defaultKey();
        $set = $key === self::NONE ? null : (self::sets()[$key] ?? null);
        if (! $set || ! $set['active']) {
            return null;
        }
        $set['key'] = $key;
        $set['vaccines'] = array_filter($set['vaccines'], fn ($p) => ! empty($p['required']));

        return $set['vaccines'] ? $set : null;
    }

    public static function defaultKey(): string
    {
        $config = self::config();
        $active = array_keys(array_filter($config['sets'], fn ($s) => $s['active']));

        return in_array($config['default'], $active, true) ? $config['default'] : ($active[0] ?? self::NONE);
    }

    /** Choix du formulaire d'un concours : règlements actifs, puis « Pas de vérification ». */
    public static function options(): array
    {
        return collect(self::sets())->filter(fn ($s) => $s['active'])->map(fn ($s) => $s['label'])->all() + [self::NONE => 'Pas de vérification des vaccins'];
    }

    /** Rappels envoyés N jours avant le concours quand les vaccins sont à revoir. */
    public static function reminderDays(): array
    {
        return collect(self::config()['reminder_days'])->map(fn ($d) => (int) $d)->filter(fn ($d) => $d >= 1 && $d <= 90)->unique()->sortDesc()->values()->all();
    }

    public static function save(array $config): void
    {
        ApplicationSetting::put(self::SETTING, $config);
    }

    public static function reset(): void
    {
        ApplicationSetting::put(self::SETTING, null);
    }

    /** « 12 mois », « 6 mois et 21 jours », « 180 jours » */
    public static function window(int $months, int $days): string
    {
        return collect([$months ? $months.' mois' : null, $days ? Fr::count($days, 'jour', 'jours') : null])->filter()->implode(' et ') ?: '0 jour';
    }

    /** Résumé d'un vaccin exigé : « rappel valable 12 mois (180 jours après la 2e injection de la primo-vaccination) ; au moins 8 jours entre l'injection et le concours ». */
    public static function describe(array $p, bool $arrival = false): string
    {
        return 'rappel valable '.self::window((int) $p['months'], (int) $p['days'])
            .' ('.self::window((int) $p['primo_months'], (int) $p['primo_days']).' après la 2e injection de la primo-vaccination) ; au moins '
            .Fr::count((int) $p['min_days'], 'jour', 'jours').' entre l\'injection et '.($arrival ? 'l\'arrivée sur le concours' : 'le concours');
    }
}
