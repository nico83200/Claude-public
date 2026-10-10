<?php
declare(strict_types=1);

/** Indicateurs et alertes du tableau de bord. */
final class Dashboard
{
    private const WON = ['confirme', 'solde', 'termine'];
    private const OPEN = ['prospect', 'devis', 'option'];

    public function __construct(private Database $db, private Repository $repo)
    {
    }

    public function build(?string $today = null): array
    {
        $today ??= date('Y-m-d');
        $year = substr($today, 0, 4);
        $in7 = self::addDays($today, 7);
        $in30 = self::addDays($today, 30);
        $in45 = self::addDays($today, 45);

        $trips = $this->repo->list('trips', [], null, 5000);
        $byId = array_column($trips, null, 'id');
        $label = fn(?array $t) => $t ? trim(($t['reference'] ?? '') . ' — ' . $t['titre']) : '';

        // KPI
        $counts = array_fill_keys(array_keys(Schema::entity('trips')['fields']['statut']['options']), 0);
        $ca = $marge = $pipeline = 0.0;
        $months = array_fill(1, 12, ['ca' => 0.0, 'marge' => 0.0]);
        $wonYear = $closedYear = 0;
        foreach ($trips as $t) {
            $counts[$t['statut'] ?? 'prospect'] = ($counts[$t['statut'] ?? 'prospect'] ?? 0) + 1;
            $inYear = str_starts_with((string)$t['date_depart'], $year);
            if (in_array($t['statut'], self::WON, true) && $inYear) {
                $ca += (float)$t['total_vente'];
                $marge += (float)$t['marge'];
                $m = (int)substr((string)$t['date_depart'], 5, 2);
                $months[$m]['ca'] += (float)$t['total_vente'];
                $months[$m]['marge'] += (float)$t['marge'];
            }
            if (in_array($t['statut'], ['devis', 'option'], true)) {
                $pipeline += (float)$t['total_vente'];
            }
            if (str_starts_with((string)$t['created_at'], $year)) {
                if (in_array($t['statut'], self::WON, true)) {
                    $wonYear++;
                    $closedYear++;
                } elseif ($t['statut'] === 'annule' || ($t['statut'] !== 'prospect' && $t['date_option_limite'] && $t['date_option_limite'] < $today)) {
                    $closedYear++;
                }
            }
        }

        // Échéances
        $payments = $this->db->all(
            "SELECT * FROM payments WHERE paye = 0 AND date_echeance IS NOT NULL AND date_echeance <= ? ORDER BY date_echeance ASC",
            [$in30]
        );
        $payments = array_values(array_filter(array_map(function ($p) use ($byId, $label, $today) {
            $t = $byId[(int)$p['trip_id']] ?? null;
            if (!$t || $t['statut'] === 'annule') {
                return null;
            }
            return [
                'id' => (int)$p['id'], 'trip_id' => (int)$p['trip_id'], 'trip' => $label($t), 'libelle' => $p['libelle'],
                'sens' => $p['sens'], 'montant' => (float)$p['montant'], 'date' => $p['date_echeance'],
                'retard' => $p['date_echeance'] < $today,
            ];
        }, $payments)));

        // Options / devis arrivant à échéance
        $options = [];
        foreach ($trips as $t) {
            if (in_array($t['statut'], ['devis', 'option'], true) && $t['date_option_limite'] && $t['date_option_limite'] <= $in7) {
                $options[] = ['trip_id' => $t['id'], 'trip' => $label($t), 'quoi' => $t['statut'] === 'option' ? 'Option client' : 'Validité du devis',
                    'date' => $t['date_option_limite'], 'expire' => $t['date_option_limite'] < $today];
            }
        }
        $itemOpts = $this->db->all(
            "SELECT i.id, i.libelle, i.date_limite_option, o.trip_id FROM items i JOIN options o ON o.id = i.option_id
             WHERE i.statut = 'option' AND i.date_limite_option IS NOT NULL AND i.date_limite_option <= ?",
            [$in7]
        );
        $seen = [];
        foreach ($itemOpts as $i) {
            $t = $byId[(int)$i['trip_id']] ?? null;
            $key = $i['trip_id'] . '|' . $i['libelle'] . '|' . $i['date_limite_option'];
            if (!$t || !in_array($t['statut'], [...self::OPEN, 'confirme'], true) || isset($seen[$key])) {
                continue; // une même option peut figurer dans plusieurs variantes
            }
            $seen[$key] = true;
            $options[] = ['trip_id' => $t['id'], 'trip' => $label($t), 'quoi' => 'Option fournisseur : ' . $i['libelle'],
                'date' => $i['date_limite_option'], 'expire' => $i['date_limite_option'] < $today];
        }
        usort($options, fn($a, $b) => strcmp($a['date'], $b['date']));

        // Départs à venir
        $departures = [];
        foreach ($trips as $t) {
            if (in_array($t['statut'], self::WON, true) && $t['date_depart'] && $t['date_depart'] >= $today && $t['date_depart'] <= $in45) {
                $departures[] = ['trip_id' => $t['id'], 'trip' => $label($t), 'date' => $t['date_depart'],
                    'j' => (int)((strtotime($t['date_depart']) - strtotime($today)) / 86400)];
            }
        }
        usort($departures, fn($a, $b) => strcmp($a['date'], $b['date']));

        // Prestations non confirmées sur dossiers confirmés
        $toBook = $this->db->all(
            "SELECT i.id, i.libelle, i.statut, t.id AS trip_id FROM items i
             JOIN trips t ON t.selected_option_id = i.option_id
             WHERE t.statut IN ('confirme','solde') AND i.optionnel = 0 AND i.statut IN ('a_demander','demande','option')
               AND (t.date_depart IS NULL OR t.date_depart >= ?)",
            [$today]
        );
        $toBook = array_map(fn($i) => ['trip_id' => (int)$i['trip_id'], 'trip' => $label($byId[(int)$i['trip_id']] ?? null),
            'libelle' => $i['libelle'], 'statut' => $i['statut']], $toBook);

        return [
            'today' => $today,
            'counts' => $counts,
            'ca' => round($ca, 2),
            'marge' => round($marge, 2),
            'taux_marge' => $ca > 0 ? round($marge / $ca * 100, 1) : 0,
            'pipeline' => round($pipeline, 2),
            'conversion' => $closedYear > 0 ? round($wonYear / $closedYear * 100) : null,
            'months' => array_values($months),
            'payments' => $payments,
            'options' => $options,
            'departures' => $departures,
            'documents' => $this->documentAlerts($trips, $today),
            'to_book' => $toBook,
        ];
    }

    /** Passeports expirant moins de 6 mois après le retour (règle de nombreux pays hors UE). */
    private function documentAlerts(array $trips, string $today): array
    {
        $alerts = [];
        $clients = array_column($this->repo->list('clients', [], null, 5000), null, 'id');
        $dests = array_column($this->repo->list('destinations', [], null, 5000), null, 'id');
        foreach ($trips as $t) {
            if (in_array($t['statut'], ['annule', 'termine'], true) || !$t['date_depart'] || $t['date_depart'] < $today) {
                continue;
            }
            $dest = $dests[$t['destination_id']] ?? null;
            if ($dest && !(int)$dest['hors_ue']) {
                continue;
            }
            $limit = self::addDays($t['date_retour'] ?: $t['date_depart'], 182);
            $ids = array_column($this->db->all('SELECT client_id FROM travelers WHERE trip_id = ?', [$t['id']]), 'client_id');
            if ($t['client_id']) {
                $ids[] = $t['client_id'];
            }
            foreach (array_unique(array_map('intval', $ids)) as $cid) {
                $c = $clients[$cid] ?? null;
                if (!$c) {
                    continue;
                }
                $exp = $c['passeport_expiration'];
                if ($exp === null || $exp < $limit) {
                    $alerts[] = [
                        'trip_id' => $t['id'], 'trip' => trim($t['reference'] . ' — ' . $t['titre']), 'client_id' => $cid,
                        'client' => trim($c['nom'] . ' ' . ($c['prenom'] ?? '')),
                        'message' => $exp === null ? 'Date d\'expiration du passeport non renseignée' : "Passeport expirant le $exp (< 6 mois après le retour)",
                    ];
                }
            }
        }
        return $alerts;
    }

    private static function addDays(string $date, int $days): string
    {
        return (new DateTimeImmutable($date))->modify(($days >= 0 ? '+' : '') . $days . ' days')->format('Y-m-d');
    }
}
