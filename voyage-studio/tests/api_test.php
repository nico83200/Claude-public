<?php
declare(strict_types=1);

/**
 * Tests d'intégration de l'API (SQLite temporaire). Lancer : php tests/api_test.php
 */
require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/../app/Demo.php';

$fails = 0;
$n = 0;
function check(string $label, bool $ok, mixed $debug = null): void
{
    global $fails, $n;
    $n++;
    echo ($ok ? "  ✔ " : "  ✖ ") . $label . ($ok || $debug === null ? '' : ' → ' . json_encode($debug, JSON_UNESCAPED_UNICODE)) . "\n";
    if (!$ok) {
        $fails++;
    }
}

$path = sys_get_temp_dir() . '/vs-test-' . bin2hex(random_bytes(4)) . '.sqlite';
$db = new Database(['driver' => 'sqlite', 'path' => $path]);
$db->migrate();
$db->migrate(); // idempotent
$_SESSION = ['csrf' => 'tok'];
$auth = new Auth($db);
$auth->createUser('agent@example.com', 'motdepasse-solide');
$api = new Api($db, $auth);
$call = fn(string $m, string $r, array $b = [], array $q = []) => $api->handle($m, $r, $q, $b, 'tok', '127.0.0.1');

echo "Authentification\n";
check('accès refusé sans session', $call('GET', 'clients')['status'] === 401);
check('mauvais mot de passe', $call('POST', 'auth/login', ['email' => 'agent@example.com', 'password' => 'faux'])['status'] === 401);
$res = $api->handle('POST', 'auth/login', [], ['email' => 'agent@example.com', 'password' => 'motdepasse-solide'], 'tok', '127.0.0.1');
check('connexion', $res['status'] === 200, $res);
$tok = $_SESSION['csrf'];
$call = fn(string $m, string $r, array $b = [], array $q = []) => $api->handle($m, $r, $q, $b, $tok, '127.0.0.1');
check('CSRF obligatoire', $api->handle('POST', 'clients', [], ['nom' => 'X'], 'mauvais', '')['status'] === 419);

echo "CRUD & validation\n";
$r = $call('POST', 'clients', ['prenom' => 'Sans nom']);
check('champ obligatoire', $r['status'] === 422 && isset($r['json']['fields']['nom']), $r);
$r = $call('POST', 'clients', ['nom' => 'Dupont', 'email' => 'pas-un-mail']);
check('e-mail invalide', $r['status'] === 422 && isset($r['json']['fields']['email']));
$r = $call('POST', 'clients', ['nom' => 'Dupont', 'prenom' => 'Léa', 'date_naissance' => '14/07/1985', 'champ_pirate' => 'x']);
check('création + date française', $r['status'] === 200 && $r['json']['data']['date_naissance'] === '1985-07-14', $r);
$cid = $r['json']['data']['id'];
check('colonne inconnue ignorée', !array_key_exists('champ_pirate', $r['json']['data']));
$r = $call('POST', 'suppliers', ['nom' => 'MSC', 'commission_pct' => '12,5']);
check('virgule décimale', $r['json']['data']['commission_pct'] === 12.5, $r);
$r = $call('GET', 'clients', [], ['q' => 'dup']);
check('recherche', count($r['json']['data']) === 1);
$r = $call('PUT', "clients/$cid", ['telephone' => '0600000000']);
check('mise à jour partielle', $r['json']['data']['telephone'] === '0600000000' && $r['json']['data']['nom'] === 'Dupont');

echo "Dossiers\n";
$r = $call('POST', 'trips', ['titre' => 'Test Japon', 'client_id' => $cid, 'date_depart' => '2027-04-01', 'date_retour' => '2027-04-15']);
$trip = $r['json']['data'];
check('référence auto', (bool)preg_match('/^VS\d{2}-\d{4}$/', $trip['reference']), $trip);
check('variante créée par défaut', !empty($trip['selected_option_id']));
$full = $call('GET', "trips/{$trip['id']}/full")['json']['data'];
check('dossier complet', count($full['options']) === 1 && $full['client']['nom'] === 'Dupont');
$opt = $full['options'][0];
check('marge par défaut appliquée', $opt['marge_mode'] === 'coef' && $opt['marge_valeur'] === 12.0, $opt);
$it = $call('POST', 'items', ['option_id' => $opt['id'], 'type' => 'vol', 'libelle' => 'Vol', 'heure_debut' => '7h30', 'prix_unitaire' => 800]);
check('heure normalisée', $it['json']['data']['heure_debut'] === '07:30', $it);
$r = $call('POST', 'items', ['option_id' => $opt['id'], 'type' => 'vaisseau', 'libelle' => 'X']);
check('enum invalide refusé', $r['status'] === 422);
$r = $call('DELETE', "options/{$opt['id']}");
check('dernière variante protégée', $r['status'] === 409);
$dup = $call('POST', "options/{$opt['id']}/duplicate")['json']['data'];
$full = $call('GET', "trips/{$trip['id']}/full")['json']['data'];
check('duplication variante + prestations', count($full['options']) === 2 && count($full['options'][1]['items']) === 1, $dup);
$copy = $call('POST', "trips/{$trip['id']}/duplicate")['json']['data'];
check('duplication dossier', $copy['reference'] !== $trip['reference'] && str_ends_with($copy['titre'], '(copie)'));
$fullCopy = $call('GET', "trips/{$copy['id']}/full")['json']['data'];
check('copie profonde', count($fullCopy['options']) === 2 && in_array($copy['selected_option_id'], array_column($fullCopy['options'], 'id'), true));
$call('DELETE', "trips/{$copy['id']}");
check('suppression en cascade', (int)$db->value('SELECT COUNT(*) FROM options WHERE trip_id = ?', [$copy['id']]) === 0
    && (int)$db->value('SELECT COUNT(*) FROM items') === 2);

echo "Tableau de bord & démo\n";
Demo::seed(new Repository($db), $db);
$d = $call('GET', 'dashboard')['json']['data'];
check('tableau de bord', isset($d['counts']['devis']) && $d['counts']['devis'] >= 1, $d['counts'] ?? $d);
check('alerte option fournisseur', count(array_filter($d['options'], fn($o) => str_contains($o['quoi'], 'MSC'))) === 1, $d['options']);
$s = $call('GET', 'search', [], ['q' => 'mart'])['json']['data'];
check('recherche globale', count($s) >= 2, $s);

echo "Réglages, devises, export\n";
$r = $call('PUT', 'settings', ['marge_valeur' => '15', 'inconnu' => 'x']);
check('réglages', $r['json']['data']['marge_valeur'] === '15' && !isset($r['json']['data']['inconnu']));
$r = $call('PUT', 'settings', ['agence_logo' => 'javascript:alert(1)']);
check('logo non image refusé', $r['status'] === 422);
$rates = new Rates(new Repository($db), $db);
$rates->seedIfEmpty();
$xml = '<?xml version="1.0"?><gesmes:Envelope xmlns:gesmes="http://www.gesmes.org/xml/2002-08-01" xmlns="http://www.ecb.int/vocabulary/2002-08-01/eurofxref"><Cube><Cube time="2026-10-09"><Cube currency="USD" rate="1.1"/><Cube currency="THB" rate="39.2"/></Cube></Cube></gesmes:Envelope>';
$u = $rates->refreshFromEcb($xml);
check('import taux BCE', $u['updated'] === 2 && (float)$db->value("SELECT taux FROM currencies WHERE code='USD'") === 1.1);
$r = $call('GET', 'export/clients');
check('export CSV', $r['status'] === 200 && str_contains($r['raw'], 'Dupont') && str_starts_with($r['raw'], "\xEF\xBB\xBF"));
$b = $call('GET', 'backup');
$dump = json_decode($b['raw'], true);
check('sauvegarde', $dump['app'] === 'VoyageStudio' && count($dump['tables']['clients']) >= 4);
$call('DELETE', "clients/$cid");
$r = $call('POST', 'backup', $dump);
check('restauration', $r['status'] === 200 && (int)$db->value("SELECT COUNT(*) FROM clients WHERE nom='Dupont'") === 1, $r);

echo "Assistant IA\n";
$st = $call('GET', 'ai/status')['json']['data'];
check('statut IA (sans clé)', $st['enabled'] === false);
$r = $call('POST', 'ai/extract', ['text' => 'Vol AF123']);
check('IA non configurée → 503', $r['status'] === 503, $r);

echo "Déconnexion\n";
$call('POST', 'auth/logout');
check('session fermée', $api->handle('GET', 'clients', [], [], null, '')['status'] === 401);

@unlink($path);
echo "\n" . ($n - $fails) . "/$n tests OK\n";
exit($fails ? 1 : 0);
