<?php
declare(strict_types=1);

/** Paramètres de l'agence (clé/valeur) avec valeurs par défaut. */
final class Settings
{
    public const DEFAULTS = [
        // Agence (mentions obligatoires sur les devis — Code du tourisme)
        'agence_nom' => 'Mon agence de voyages',
        'agence_adresse' => '',
        'agence_telephone' => '',
        'agence_email' => '',
        'agence_site' => '',
        'agence_siret' => '',
        'agence_immatriculation' => '',     // n° IM Atout France
        'agence_garantie' => '',            // garant financier
        'agence_rcp' => '',                 // assureur RC professionnelle
        'agence_logo' => '',                // data URL
        'agence_couleur' => '#0f766e',
        // Tarification
        'devise_base' => 'EUR',
        'tva_regime' => 'marge',            // marge | franchise
        'tva_taux' => '20',
        'marge_mode' => 'coef',
        'marge_valeur' => '12',
        'frais_dossier' => '0',
        'frais_dossier_pers' => '0',
        'arrondi' => '5',
        'securite_change' => '2',           // % de couverture du risque de change sur achats en devises
        // Échéancier
        'acompte_pct' => '30',
        'solde_jours' => '30',
        // Textes
        'devis_intro' => "Suite à votre demande, nous avons le plaisir de vous proposer le voyage suivant.",
        'devis_conditions' => "Prix établis sur la base des tarifs et taux de change en vigueur à la date du devis, sous réserve de disponibilité au moment de la réservation.\nAcompte à la réservation, solde à régler au plus tard 30 jours avant le départ.\nLes conditions particulières d'annulation des prestataires vous seront communiquées avant toute confirmation.\nLe formulaire d'information standard (Directive (UE) 2015/2302) est joint au présent devis.",
        'devis_inclus' => '',
        'devis_non_inclus' => "Les assurances (sauf mention contraire)\nLes dépenses personnelles, pourboires et boissons\nLes frais de visa / ESTA",
    ];

    public function __construct(private Database $db)
    {
    }

    public function all(): array
    {
        $out = self::DEFAULTS;
        foreach ($this->db->all('SELECT k, v FROM settings') as $r) {
            $out[$r['k']] = $r['v'];
        }
        return $out;
    }

    public function save(array $values): array
    {
        foreach ($values as $k => $v) {
            if (!array_key_exists($k, self::DEFAULTS) || !is_scalar($v) && $v !== null) {
                continue;
            }
            $v = (string)$v;
            if ($k === 'agence_logo' && $v !== '' && !preg_match('#^data:image/(png|jpeg|gif|webp|svg\+xml);base64,[A-Za-z0-9+/=]+$#', $v)) {
                throw new ValidationException(['agence_logo' => 'Image invalide']);
            }
            if ($k === 'agence_logo' && strlen($v) > 700000) {
                throw new ValidationException(['agence_logo' => 'Logo trop lourd (500 Ko max)']);
            }
            if ($k === 'agence_couleur' && !preg_match('/^#[0-9a-fA-F]{6}$/', $v)) {
                continue;
            }
            $exists = $this->db->value('SELECT COUNT(*) FROM settings WHERE k = ?', [$k]);
            $now = date('Y-m-d H:i:s');
            if ($exists) {
                $this->db->query('UPDATE settings SET v = ?, updated_at = ? WHERE k = ?', [$v, $now, $k]);
            } else {
                $this->db->query('INSERT INTO settings (k, v, created_at, updated_at) VALUES (?, ?, ?, ?)', [$k, $v, $now, $now]);
            }
        }
        return $this->all();
    }
}
