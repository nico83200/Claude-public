<?php
declare(strict_types=1);

/**
 * Tests automatiques des règles métier, sur une base SQLite temporaire.
 *
 *   php tests/run.php
 *
 * À lancer avant de fabriquer un paquet de mise à jour (tools/build-update.php).
 */
if (PHP_SAPI !== 'cli') {
    exit("À lancer en ligne de commande.\n");
}
$tmp = sys_get_temp_dir() . '/cmd-tests-' . getmypid();
@mkdir($tmp);
$db = "$tmp/test.sqlite";
@unlink($db); // base neuve à chaque exécution (un numéro de processus peut être réutilisé)
file_put_contents("$tmp/config.php", "<?php return ['db' => ['driver' => 'sqlite', 'path' => " . var_export($db, true) . "], 'app_name' => 'Tests', 'timezone' => 'Europe/Paris', 'anthropic_api_key' => ''];");
putenv("CMD_CONFIG=$tmp/config.php");
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REMOTE_ADDR'] = '10.0.0.1';
$_SESSION = [];

// Initialisation de la base avant l'amorçage (qui suppose les tables existantes)
define('ROOT', dirname(__DIR__));
define('APP', ROOT . '/app');
$GLOBALS['config'] = require "$tmp/config.php";
require APP . '/instances.php';
require APP . '/db.php';
require APP . '/helpers.php';
require APP . '/schema.php';
schema_install();
require APP . '/auth.php';
require APP . '/domain.php';
require APP . '/search.php';
require APP . '/stock.php';
require APP . '/notify.php';
require APP . '/updater.php';
require APP . '/features.php';
require APP . '/cleanup.php';
require APP . '/spreadsheet.php';
require APP . '/import.php';
require APP . '/support.php';
require APP . '/videos.php';
require APP . '/contracts.php';
require APP . '/barcode.php';
require APP . '/licence.php';
require APP . '/reports.php';
require APP . '/security.php';
require APP . '/pdf.php';
require APP . '/cron.php';
define('APP_VERSION', trim((string)file_get_contents(ROOT . '/VERSION')));
require APP . '/install_demo.php';

$pass = 0;
$fail = 0;
function check(bool $cond, string $label): void
{
    global $pass, $fail;
    $cond ? $pass++ : $fail++;
    echo ($cond ? "  \033[32m✔\033[0m " : "  \033[31m✘\033[0m ") . $label . "\n";
}
function section(string $t): void
{
    echo "\n\033[1m$t\033[0m\n";
}
function as_user(string $email): array
{
    $u = one('SELECT * FROM users WHERE email = ?', [$email]);
    $_SESSION['uid'] = (int)$u['id'];
    return $u;
}

$adminId = insert('users', ['email' => 'admin@test.fr', 'password_hash' => password_hash('admin1234', PASSWORD_DEFAULT), 'first_name' => 'Ada', 'last_name' => 'Admin',
    'role' => 'admin', 'status' => 'active', 'created_at' => now()]);
install_base_data();
$_SESSION['uid'] = $adminId;
install_demo_data($adminId);
$c1 = (int)val("SELECT id FROM centers WHERE code = 'TIL'");
$c2 = (int)val("SELECT id FROM centers WHERE code = 'PORT'");
$pid = fn(string $ref) => (int)val('SELECT id FROM products WHERE reference = ?', [$ref]);

section('Installation et schéma');
check((int)val('SELECT COUNT(*) FROM products') > 40, 'catalogue de démonstration chargé');
check(column_exists('purchase_orders', 'invoice_status') && column_exists('products', 'compare_group'), 'colonnes v1.3 présentes');
schema_migrate();
check(true, 'migration rejouable sans erreur (idempotente)');

section('Recherche');
$prods = catalog_products($c1);
$byId = array_column($prods, null, 'id');
$top = fn(string $q) => ($k = array_key_first(search_local($prods, $q))) ? $byId[$k]['name'] : '';
check(str_contains($top('gans nitril'), 'Gants'), 'tolérance aux fautes : « gans nitril »');
check(str_contains($top('fievre'), 'Thermomètre'), 'synonymes : « fièvre » → thermomètre');
check(str_contains($top((string)val('SELECT barcode FROM products WHERE reference = ?', ['GN-M'])), 'taille M'), 'recherche par code-barres');
check(!isset(array_column(catalog_products($c2), null, 'reference')['TAPE-5']), 'fournisseur restreint invisible dans un autre centre');

section('Panier, validation par le responsable, bon de commande, réception, stock');
$claire = as_user('claire.secretaire@demo.fr');
cart_add((int)$claire['id'], $c1, $pid('THERM-IR'), 6);   // 6 × 29,90 € > seuil de 150 €
$req = cart_submit((int)$claire['id'], $c1, 'test', false);
check(val('SELECT approval_status FROM requests WHERE id = ?', [$req]) === 'pending', 'demande au-dessus du seuil → à valider par le responsable');
check((int)val("SELECT COUNT(*) FROM request_lines WHERE request_id = ? AND status = 'awaiting'", [$req]) === 1, 'lignes en attente de validation (hors circuit achats)');
as_user('sophie.responsable@demo.fr');
check(in_array($c1, approval_center_ids(), true) && approvals_pending_count() >= 1, 'le responsable voit la demande à valider');
q("UPDATE request_lines SET status = 'pending', qty = 4 WHERE request_id = ?", [$req]);
update('requests', ['approval_status' => 'approved'], 'id = ?', [$req]);
as_user('admin@test.fr');
$small = as_user('claire.secretaire@demo.fr');
cart_add((int)$small['id'], $c1, $pid('ABL-100'), 1);
$req2 = cart_submit((int)$small['id'], $c1, '', false);
check(val('SELECT approval_status FROM requests WHERE id = ?', [$req2]) === null, 'petite demande → directement au service achats');
as_user('admin@test.fr');
$lines = array_column(all("SELECT id FROM request_lines WHERE request_id IN (?, ?) AND supplier_id = ?", [$req, $req2, (int)val('SELECT supplier_id FROM products WHERE id = ?', [$pid('THERM-IR')])]), 'id');
$po = po_create($c1, (int)val('SELECT supplier_id FROM products WHERE id = ?', [$pid('THERM-IR')]), $lines);
check(po_totals($po)['lines'] === 2, 'bon de commande créé à partir des demandes');
update('purchase_orders', ['status' => 'commande', 'ordered_at' => now()], 'id = ?', [$po]);
$poRow = one('SELECT po.*, s.name AS supplier_name FROM purchase_orders po JOIN suppliers s ON s.id = po.supplier_id WHERE po.id = ?', [$po]);
$stockBefore = (int)(stock_row($c1, $pid('THERM-IR'))['qty'] ?? 0);
foreach (all('SELECT * FROM purchase_order_lines WHERE purchase_order_id = ?', [$po]) as $l) {
    $rec = (int)$l['product_id'] === $pid('THERM-IR') ? 2 : (int)$l['qty'];
    update('purchase_order_lines', ['qty_received' => $rec], 'id = ?', [$l['id']]);
    stock_from_reception($poRow, $l, 0, $rec);
}
check(po_refresh_reception_status($po) === 'partiel', 'réception partielle → statut « Reçu partiellement »');
check((int)stock_row($c1, $pid('THERM-IR'))['qty'] === $stockBefore + 2, 'le stock augmente des quantités reçues');
stock_move($c1, $pid('THERM-IR'), -1, 'sortie', 'test');
stock_count($c1, $pid('THERM-IR'), 10, 'inventaire test');
check((int)stock_row($c1, $pid('THERM-IR'))['qty'] === 10, 'inventaire : le stock compté remplace le stock théorique');

section('Corrections de stock (administrateur)');
$sp = (int)val('SELECT id FROM products WHERE id NOT IN (SELECT product_id FROM stock) AND id NOT IN (SELECT product_id FROM stock_movements) ORDER BY id LIMIT 1');
$hist = fn(int $c) => array_map(fn($m) => [$m['type'], (int)$m['delta'], (int)$m['qty_after']], all('SELECT * FROM stock_movements WHERE center_id = ? AND product_id = ? ORDER BY created_at, id', [$c, $sp]));
$qty = fn(int $c) => (int)(stock_row($c, $sp)['qty'] ?? -1);
stock_move($c1, $sp, 10, 'ajout', 'livraison');
$mOut = (int)val('SELECT MAX(id) FROM stock_movements');
stock_move($c1, $sp, -3, 'sortie', 'soins');
$mOut = (int)val('SELECT MAX(id) FROM stock_movements');
stock_move($c1, $sp, -2, 'sortie', 'cabinet');
check($qty($c1) === 5, 'stock de départ : 10 − 3 − 2 = 5');
stock_move_delete($mOut);
check($qty($c1) === 8 && $hist($c1) === [['ajout', 10, 10], ['sortie', -2, 8]], 'suppression d\'une sortie : stock et « stock après » recalculés');
$mAdd = (int)val("SELECT id FROM stock_movements WHERE product_id = ? AND type = 'ajout'", [$sp]);
$newId = stock_move_update($mAdd, $c1, 12, 'livraison corrigée');
check($qty($c1) === 10 && $hist($c1) === [['ajout', 12, 12], ['sortie', -2, 10]], 'quantité d\'une entrée corrigée : la suite est recalculée');
stock_count($c1, $sp, 7, 'comptage');
stock_move($c1, $sp, -1, 'sortie');
$mOut2 = (int)val("SELECT id FROM stock_movements WHERE product_id = ? AND type = 'sortie' AND delta = -2", [$sp]);
stock_move_delete($mOut2);
check($qty($c1) === 6 && $hist($c1)[1] === ['inventaire', -5, 7], 'suppression avant un inventaire : la quantité comptée reste la référence');
$newId = stock_move_update($newId, $c2, 12, 'livraison corrigée');
check($qty($c2) === 12 && $hist($c2) === [['ajout', 12, 12]] && $hist($c1)[0] === ['inventaire', 7, 7] && $qty($c1) === 6, 'mouvement rattaché à un autre centre : retiré du premier, appliqué au second');
$sp2 = (int)val('SELECT id FROM products WHERE id NOT IN (SELECT product_id FROM stock) AND id NOT IN (SELECT product_id FROM stock_movements) ORDER BY id LIMIT 1');
stock_move($c1, $sp2, 4, 'ajout');
stock_set_alert($c1, $sp2, 2);
check(stock_transfer($c1, $c2, $sp2) === 'moved' && !stock_row($c1, $sp2) && (int)stock_row($c2, $sp2)['qty'] === 4 && (int)stock_row($c2, $sp2)['alert_qty'] === 2
    && (int)val('SELECT COUNT(*) FROM stock_movements WHERE product_id = ? AND center_id = ?', [$sp2, $c2]) === 1, 'stock saisi dans le mauvais centre : déplacé avec son historique');
stock_move($c1, $sp2, 3, 'ajout');
check(stock_transfer($c1, $c2, $sp2) === 'merged' && !stock_row($c1, $sp2) && (int)stock_row($c2, $sp2)['qty'] === 7, 'article déjà suivi dans le centre de destination : quantités additionnées');

section('Étiquettes et codes-barres');
check(ean_valid('4006381333931') && !ean_valid('4006381333932') && ean_valid('96385074') && ean_valid('036000291452'), 'clé de contrôle EAN-13 / EAN-8 / UPC vérifiée');
$e13 = barcode_encode('4006381333931');
check($e13['type'] === 'EAN-13' && strlen($e13['bits']) === 95 && str_starts_with($e13['bits'], '101') && substr($e13['bits'], 45, 5) === '01010', 'EAN-13 : 95 modules, gardes début / milieu / fin');
check(barcode_encode('96385074')['type'] === 'EAN-8' && strlen(barcode_encode('96385074')['bits']) === 67, 'EAN-8 : 67 modules');
$c128 = barcode_encode('GN-S');
check($c128['type'] === 'Code 128' && strlen($c128['bits']) === 11 * 6 + 13, 'référence non numérique → Code 128 (départ, 4 caractères, clé, arrêt)');
check(strlen(code128_bits('12345678')) === 11 * 6 + 13, 'code numérique → Code 128 jeu C compact (2 chiffres par caractère)');
check(barcode_encode('3401579804419')['type'] === 'Code 128', 'EAN à clé invalide → Code 128 (le code reste scannable tel quel)');
$svg = barcode_svg('4006381333931', 65, 9);
check($svg['readable'] && abs($svg['module'] - 0.33) < 0.001 && str_contains($svg['svg'], '<rect'), 'code-barres SVG à la taille nominale sur une étiquette 70 × 37 mm');
check(!barcode_svg(str_repeat('AB-', 20), 50, 7)['readable'], 'code trop long pour l\'étiquette signalé');

section('Emplacement de rangement');
stock_set_location($c1, $pid('THERM-IR'), '  Réserve 1   ·  étagère B2 ');
check(stock_row($c1, $pid('THERM-IR'))['location'] === 'Réserve 1 · étagère B2' && (int)stock_row($c1, $pid('THERM-IR'))['qty'] === 10, 'emplacement enregistré (espaces nettoyés), stock inchangé');
stock_set_location($c1, $pid('THERM-IR'), '');
check(stock_row($c1, $pid('THERM-IR'))['location'] === null, 'emplacement effacé');
stock_set_location($c1, $pid('THERM-IR'), 'Réserve 1 · étagère B2');

section('Stock du centre dans les demandes à traiter');
$sp3 = (int)val('SELECT id FROM products WHERE id NOT IN (SELECT product_id FROM stock) AND active = 1 ORDER BY id LIMIT 1');
$u3 = as_user('claire.secretaire@demo.fr');
cart_add((int)$u3['id'], $c1, $sp3, 2);
cart_submit((int)$u3['id'], $c1, '', false);
as_user('admin@test.fr');
$findLine = function () use ($sp3, $c1) { foreach (pending_groups($c1) as $g) { foreach ($g['lines'] as $l) { if ((int)$l['product_id'] === $sp3) return $l; } } return null; };
check(($l3 = $findLine()) && $l3['stock_qty'] === null, 'article non suivi : pas de stock affiché');
stock_move($c1, $sp3, 5, 'ajout');
stock_set_alert($c1, $sp3, 2);
check(($l3 = $findLine()) && (int)$l3['stock_qty'] === 5 && (int)$l3['stock_alert'] === 2, 'article suivi : stock et seuil du centre joints à la ligne');

section('Licence NLapps et FAQ partagée');
check(licence_status() === 'unmanaged' && licence_ai_allowed() && !licence_blocked() && licence_notice() === null, 'sans clé NLapps : aucune restriction');
set_setting('support_hub_url', 'https://hub.example/api.php');
set_setting('support_hub_key', encrypt_secret('nlh_abcdefabcdefabcdefabcdef'));
$setLic = fn(array $l) => set_setting('licence_cache', json_encode($l + ['checked_at' => now()]));
$setLic(['status' => 'active', 'ai' => false, 'paid_until' => date('Y-m-d', strtotime('+60 days')), 'days_left' => 60]);
check(!licence_ai_allowed() && licence_updates_allowed() && licence_notice() === null, 'licence active sans option IA : assistant IA coupé, mises à jour possibles');
$setLic(['status' => 'active', 'ai' => true, 'paid_until' => date('Y-m-d', strtotime('+5 days')), 'days_left' => 5]);
check(licence_ai_allowed() && (licence_notice()['level'] ?? '') === 'info', 'échéance proche : bandeau d\'information pour l\'administrateur');
$setLic(['status' => 'grace', 'ai' => true, 'paid_until' => date('Y-m-d', strtotime('-3 days')), 'grace_until' => date('Y-m-d', strtotime('+12 days'))]);
check(licence_ai_allowed() && licence_updates_allowed() && (licence_notice()['level'] ?? '') === 'warn', 'délai de grâce : tout reste actif, avertissement');
$setLic(['status' => 'expired', 'ai' => true]);
check(!licence_ai_allowed() && !licence_updates_allowed() && licence_blocked(), 'licence expirée : accès au logiciel coupé');
$setLic(['status' => 'active', 'ai' => true, 'paid_until' => date('Y-m-d', strtotime('-1 day')), 'grace_until' => date('Y-m-d', strtotime('-1 day'))]);
check(licence_status() === 'expired' && licence_blocked(), 'échéance dépassée détectée localement, sans attendre la vérification (coupure immédiate)');
$setLic(['status' => 'active', 'ai' => true, 'paid_until' => date('Y-m-d'), 'grace_until' => date('Y-m-d')]);
check(licence_status() === 'active' && !licence_blocked(), 'jour de l\'échéance encore couvert');
$setLic(['status' => 'active', 'ai' => true, 'paid_until' => date('Y-m-d', strtotime('-2 days')), 'grace_until' => date('Y-m-d', strtotime('+3 days'))]);
check(licence_status() === 'grace' && !licence_blocked(), 'délai de grâce accordé par NLapps respecté');
$setLic(['status' => 'suspended']);
check(licence_blocked() && (licence_notice()['level'] ?? '') === 'danger', 'accès suspendu par NLapps');
$setLic(['status' => 'active', 'ai' => true, 'latest' => ['version' => '99.0.0', 'sha256' => 'x']]);
check((licence_update_available()['version'] ?? '') === '99.0.0', 'nouvelle version publiée détectée');
$setLic(['status' => 'active', 'ai' => true, 'latest' => ['version' => APP_VERSION]]);
check(licence_update_available() === null, 'version déjà installée : rien à proposer');
set_setting('faq_remote', json_encode([['q' => 'Comment exporter la comptabilité ?', 'k' => 'export comptable compta fec', 'a' => 'Menu Exports.', 'link' => ['Exports', 'admin/exports'], 'admin' => true]]));
check(count(support_faq()) === count(support_faq_builtin()) + 1 && support_match('export comptable', true)[0]['title'] === 'Comment exporter la comptabilité ?', 'question partagée par NLapps connue du chatbot');
check(!support_match('export comptable', false) || support_match('export comptable', false)[0]['title'] !== 'Comment exporter la comptabilité ?', 'question partagée réservée aux administrateurs respectée');
check(licence_stats()['centers'] > 0 && isset(licence_stats()['users']), 'statistiques d\'usage sans donnée personnelle');
set_setting('support_hub_url', null); set_setting('support_hub_key', null); set_setting('licence_cache', null); set_setting('faq_remote', null);

section('Pilotage du stock');
check(supplier_lead_days('48 h') === 2 && supplier_lead_days('3 jours') === 3 && supplier_lead_days('2 à 4 jours') === 4 && supplier_lead_days('1 semaine') === 7 && supplier_lead_days('') === 3, 'délai fournisseur lu dans le texte (48 h, 3 jours, 2 à 4 jours, 1 semaine)');
check(stock_advised_threshold(90, 3) === 10 && stock_advised_threshold(0, 3) === null && stock_advised_threshold(1, 2) === 1, 'seuil conseillé = consommation/jour × (délai + 7 j)');
check(stock_reorder_qty(2, 5, 0) === 8 && stock_reorder_qty(2, 5, 8) === 0 && stock_reorder_qty(0, 1, 0, 5) === 5, 'quantité de réapprovisionnement (double du seuil, en commande déduite, minimum de commande)');
$ap = (int)val('SELECT id FROM products WHERE id NOT IN (SELECT product_id FROM stock) AND id NOT IN (SELECT product_id FROM request_lines) AND active = 1 ORDER BY id LIMIT 1');
stock_move($c1, $ap, 10, 'ajout');
stock_set_alert($c1, $ap, 4);
$reqBefore = (int)val('SELECT COUNT(*) FROM request_lines WHERE product_id = ?', [$ap]);
stock_move($c1, $ap, -7, 'sortie', 'soins');
$auto = one("SELECT rl.*, r.comment AS rcomment FROM request_lines rl JOIN requests r ON r.id = rl.request_id WHERE rl.product_id = ? AND rl.center_id = ? AND rl.status = 'pending'", [$ap, $c1]);
check($reqBefore === 0 && $auto && (int)$auto['qty'] === 5 && str_contains($auto['rcomment'], 'automatique'), 'passage sous le seuil : demande de réapprovisionnement créée automatiquement (8 − 3 = 5)');
stock_move($c1, $ap, 5, 'ajout'); stock_move($c1, $ap, -6, 'sortie');
check((int)val("SELECT COUNT(*) FROM request_lines WHERE product_id = ? AND center_id = ? AND status = 'pending'", [$ap, $c1]) === 1, 'pas de doublon tant qu\'une demande est en cours');
set_setting('auto_reorder', '0');
q("UPDATE request_lines SET status = 'cancelled' WHERE product_id = ?", [$ap]);
stock_move($c1, $ap, 6, 'ajout'); stock_move($c1, $ap, -6, 'sortie');
check((int)val("SELECT COUNT(*) FROM request_lines WHERE product_id = ? AND status = 'pending'", [$ap]) === 0, 'réapprovisionnement automatique désactivable');
set_setting('auto_reorder', '1');
$adv = stock_advice($c1)[$ap] ?? null;
check($adv && $adv['out90'] === 19 && $adv['advised'] >= 1, 'conseil calculé à partir des sorties réelles du centre');
// Transfert entre centres
stock_move($c2, $ap, 30, 'ajout');
stock_set_alert($c2, $ap, 5);
$offers = stock_transfer_offers([$ap])[$ap] ?? [];
check(count($offers) >= 1 && $offers[0]['center_id'] === $c2 && $offers[0]['spare'] === 25, 'excédent du centre du Port proposé (30 en stock, seuil 5)');
$u4 = as_user('claire.secretaire@demo.fr');
cart_add((int)$u4['id'], $c1, $ap, 6);
cart_submit((int)$u4['id'], $c1, '', false);
as_user('admin@test.fr');
$line = (int)val("SELECT id FROM request_lines WHERE product_id = ? AND center_id = ? ORDER BY id DESC LIMIT 1", [$ap, $c1]);
q("UPDATE request_lines SET status = 'pending' WHERE id = ?", [$line]); // validée par le responsable
$before1 = (int)stock_row($c1, $ap)['qty'];
request_line_transfer($line, $c2);
check((int)stock_row($c2, $ap)['qty'] === 24 && (int)stock_row($c1, $ap)['qty'] === $before1 + 6 && val('SELECT status FROM request_lines WHERE id = ?', [$line]) === 'transferred', 'demande servie par transfert : stocks des deux centres ajustés, ligne « transférée »');
try { request_line_transfer($line, $c2); check(false, 'ligne déjà servie refusée'); } catch (RuntimeException) { check(true, 'ligne déjà servie refusée'); }
// Inventaire tournant
$list = cycle_count_list($c1, '2099-W01');
check(count($list) >= 1 && count($list) <= CYCLE_COUNT_SIZE && cycle_count_list($c1, '2099-W01') === $list, 'liste de la semaine stable (au plus 10 articles)');
$first = $list[0];
$r = cycle_count_save($c1, [$first['product_id'] => (int)$first['qty'] - 1], '2099-W01');
check($r['counted'] === 1 && $r['gaps'] === 1 && $r['value'] < 0, 'comptage enregistré, écart chiffré en euros');
$hist = cycle_count_history($c1, 5000);
check(($hist[0]['week'] ?? '') === '2099-W01' && $hist[0]['gaps'] === 1, 'historique des écarts par semaine');

section('Pilotage direction, alertes de prix, double authentification');
$rg = report_range('last_month', strtotime('2026-03-15'));
check($rg['from'] === '2026-02-01' && $rg['to'] === '2026-03-01' && $rg['prev_from'] === '2026-01-01' && $rg['prev_to'] === '2026-02-01', 'période « mois dernier » et période de comparaison');
check(report_range('quarter', strtotime('2026-05-10'))['from'] === '2026-04-01' && report_range('12m', strtotime('2026-05-10'))['from'] === '2025-06-01', 'trimestre et 12 derniers mois');
$sumAll = report_summary('2000-01-01', '2100-01-01');
$expected = (float)val("SELECT COALESCE(SUM(l.qty * l.unit_price), 0) FROM purchase_order_lines l JOIN purchase_orders po ON po.id = l.purchase_order_id WHERE po.status IN ('commande','partiel','recu')")
    + (float)val("SELECT COALESCE(SUM(shipping_fee), 0) FROM purchase_orders WHERE status IN ('commande','partiel','recu')");
check(abs($sumAll['spent'] - $expected) < 0.01 && $sumAll['orders'] > 0, 'dépenses = lignes des bons commandés + frais de port');
check(abs(array_sum(array_column($sumAll['centers'], 'amount')) - $sumAll['goods']) < 0.01 && abs(array_sum(array_column($sumAll['suppliers'], 'amount')) - $sumAll['goods']) < 0.01, 'répartitions par centre et par fournisseur cohérentes avec le total');
check($sumAll['savings'] >= 0 && count(report_monthly()) === 12, 'économies négociées et série sur 12 mois');
$pdfOut = report_pdf(date('Y-m'));
check(str_starts_with($pdfOut, '%PDF') && strlen($pdfOut) > 1500, 'rapport PDF mensuel généré');
check(report_delta(120, 100) === 20.0 && report_delta(5, 0) === null, 'variation par rapport à la période précédente');
check(price_alert_pct() === 5.0, 'seuil d\'alerte de hausse de prix : 5 % par défaut');
check(totp_code(base32_encode('12345678901234567890'), 59) === '287082', 'double authentification : codes conformes à la RFC 6238');
$adm = one("SELECT * FROM users WHERE email = 'admin@test.fr'");
$sec = base32_encode(random_bytes(20));
update('users', ['totp_secret' => encrypt_secret($sec), 'totp_last' => null], 'id = ?', [$adm['id']]);
$adm = one('SELECT * FROM users WHERE id = ?', [$adm['id']]);
check(user_has_2fa($adm) && !str_contains((string)$adm['totp_secret'], $sec), 'secret de double authentification chiffré en base');
$code = totp_code($sec);
check(user_totp_verify($adm, $code) && !user_totp_verify(one('SELECT * FROM users WHERE id = ?', [$adm['id']]), $code), 'code accepté une seule fois (pas de rejeu)');
update('users', ['totp_secret' => null, 'totp_last' => null], 'id = ?', [$adm['id']]);

section('Inventaire tablette : stock actuel uniquement');
$tp = (int)val('SELECT id FROM products WHERE id NOT IN (SELECT product_id FROM stock) AND active = 1 ORDER BY id DESC LIMIT 1');
stock_move($c1, $tp, 10, 'ajout');
$r1 = stock_set_counted($c1, $tp, 7);
$m1 = one('SELECT * FROM stock_movements WHERE product_id = ? AND center_id = ? ORDER BY id DESC LIMIT 1', [$tp, $c1]);
check($r1 === ['qty' => 7, 'delta' => -3, 'type' => 'sortie'] && $m1['type'] === 'sortie' && (int)$m1['delta'] === -3, 'stock saisi inférieur : sortie de 3 calculée');
$r2 = stock_set_counted($c1, $tp, 12);
check($r2['delta'] === 5 && $r2['type'] === 'ajout' && (int)stock_row($c1, $tp)['qty'] === 12, 'stock saisi supérieur : entrée de 5 calculée');
$r3 = stock_set_counted($c1, $tp, 12);
check($r3['delta'] === 0 && one('SELECT type FROM stock_movements WHERE product_id = ? ORDER BY id DESC LIMIT 1', [$tp])['type'] === 'inventaire' && stock_row($c1, $tp)['counted_at'] !== null, 'stock confirmé : pas de mouvement de quantité, date de comptage mise à jour');
check((stock_usage_map($c1)[$tp] ?? 0) === 3, 'la sortie calculée compte dans la consommation (seuils conseillés)');

section('Factures');
$po2 = one('SELECT * FROM purchase_orders WHERE id = ?', [$po]);
$expected = invoice_check($po2)['expected'];
check(invoice_check(array_merge($po2, ['invoice_amount' => $expected]))['status'] === 'ok', 'facture égale au reçu → conforme');
check(invoice_check(array_merge($po2, ['invoice_amount' => $expected + 25]))['status'] === 'ecart', 'facture supérieure au reçu → écart détecté');

section('Budgets');
$b = budget_status($c1);
check($b['defined'] && $b['amount'] == 1500.0 && $b['spent'] > 0, 'budget du centre calculé (commandé, engagé, reste)');

section('Prix, comparateur et bascule vers le moins cher');
$gnm = one('SELECT * FROM products WHERE reference = ?', ['GN-M']);
$eq = cheaper_equivalent($gnm, $c1);
check($eq !== null && $eq['reference'] === 'HPS-GNM', 'équivalent moins cher trouvé chez un autre fournisseur');
check(count(recent_price_increases()) >= 1, 'hausse de prix détectée dans l\'historique');
$before = (int)val('SELECT COUNT(*) FROM price_history WHERE product_id = ?', [$gnm['id']]);
price_record((int)$gnm['id'], (float)$gnm['catalog_price'], (float)$gnm['negotiated_price'], 'test');
check((int)val('SELECT COUNT(*) FROM price_history WHERE product_id = ?', [$gnm['id']]) === $before, 'pas de doublon d\'historique si le prix ne change pas');

section('Commande groupée multi-centres');
$medi = (int)val("SELECT id FROM suppliers WHERE name = 'MédiDistrib'");
$pending = array_column(all("SELECT id FROM request_lines WHERE status = 'pending' AND supplier_id = ?", [$medi]), 'id');
$centersInvolved = (int)val("SELECT COUNT(DISTINCT center_id) FROM request_lines WHERE status = 'pending' AND supplier_id = ?", [$medi]);
$ref = po_create_group($medi, $pending);
$pos = all('SELECT * FROM purchase_orders WHERE group_ref = ?', [$ref]);
check(count($pos) === $centersInvolved && $centersInvolved >= 2, 'un bon par centre sous une même référence de groupe');
check(count(array_unique(array_column($pos, 'center_id'))) === count($pos), 'chaque bon reste attaché à un seul centre');
check(count(array_filter($pos, fn($p) => (float)$p['shipping_fee'] > 0)) <= 1, 'frais de port portés au plus par un bon (franco sur le total)');

section('PDF du bon de commande');
$pdf = po_pdf(array_map('intval', array_column($pos, 'id')));
check(str_starts_with($pdf, '%PDF-1.4') && str_contains($pdf, 'startxref') && str_contains($pdf, '%%EOF'), 'PDF valide généré (commande groupée)');
check(strlen($pdf) > 1500, 'PDF non vide');

section('Listes types et réapprovisionnement');
$claire = as_user('claire.secretaire@demo.fr');
$kits = kits_for($claire, $c1);
check(count($kits) >= 2, 'listes partagées visibles par le salarié');
check(count(kit_items((int)$kits[0]['id'], $c1)) >= 3, 'contenu de la liste type');
q('DELETE FROM cart_items WHERE user_id = ?', [$claire['id']]);
check(count(reorder_suggestions((int)$claire['id'], $c1)) >= 1, 'stocks bas proposés au réapprovisionnement');

section('Sécurité de connexion et mot de passe oublié');
for ($i = 0; $i < 5; $i++) {
    login_record('cible@test.fr', false);
}
check(login_blocked('cible@test.fr'), 'compte bloqué après 5 échecs en 15 minutes');
check(!login_blocked('autre@test.fr'), 'un autre compte n\'est pas bloqué');
$token = password_reset_create($claire);
check(password_reset_find($token) !== null, 'lien de réinitialisation valide');
check(password_reset_find(str_repeat('a', 64)) === null, 'jeton inconnu refusé');
update('password_resets', ['expires_at' => date('Y-m-d H:i:s', time() - 10)], 'user_id = ?', [$claire['id']]);
check(password_reset_find($token) === null, 'jeton expiré refusé');

section('Notifications, file d\'e-mails et tâches planifiées');
set_setting('mail_enabled', '1');
set_setting('smtp_host', '127.0.0.1');
set_setting('smtp_port', '1');            // aucun serveur : l'envoi doit échouer proprement
set_setting('smtp_secure', 'none');
as_user('admin@test.fr');   // on ne se notifie pas soi-même : l'action vient de l'administrateur
notify([(int)$claire['id']], 'po_ordered', 'Test', 'Corps du message', url('requests'));
check((int)val('SELECT COUNT(*) FROM mail_queue WHERE sent_at IS NULL') >= 1, 'e-mail mis en file d\'attente');
mail_queue_process(5);
check((int)val('SELECT MAX(attempts) FROM mail_queue') >= 1 && val('SELECT last_error FROM mail_queue ORDER BY id DESC LIMIT 1') !== null, 'échec SMTP enregistré pour nouvelle tentative (sans planter)');
set_setting('mail_enabled', '0');
insert('deadlines', ['title' => 'Test rappel', 'deadline_at' => date('Y-m-d H:i:s', time() + 3600 * 5), 'center_id' => $c1, 'created_at' => now()]);
$before = (int)val("SELECT COUNT(*) FROM notifications WHERE type = 'deadline_reminder'");
cron_deadline_reminders();
check((int)val("SELECT COUNT(*) FROM notifications WHERE type = 'deadline_reminder'") > $before, 'rappel envoyé la veille d\'une date limite');
cron_deadline_reminders();
check((int)val("SELECT COUNT(*) FROM deadlines WHERE title = 'Test rappel' AND reminded_at IS NOT NULL") === 1, 'un seul rappel par date limite');
update('purchase_orders', ['ordered_at' => date('Y-m-d H:i:s', strtotime('-20 days')), 'late_notified_at' => null], 'id = ?', [$po]);
check(cron_late_deliveries() >= 1, 'livraison en retard signalée');

section('E-mails au cas par cas');
as_user('admin@test.fr');
set_setting('mail_enabled', '1');
set_setting('mailev_po_ordered', '0');
$mq = (int)val('SELECT COUNT(*) FROM mail_queue');
$nb = (int)val("SELECT COUNT(*) FROM notifications WHERE type = 'po_ordered'");
notify([(int)$claire['id']], 'po_ordered', 'Cas désactivé', '', '');
check((int)val('SELECT COUNT(*) FROM mail_queue') === $mq, 'e-mail désactivé pour ce cas : aucun envoi');
check((int)val("SELECT COUNT(*) FROM notifications WHERE type = 'po_ordered'") === $nb + 1, '… mais la notification dans l\'application est créée');
set_setting('mailev_po_ordered', '1');
notify([(int)$claire['id']], 'po_ordered', 'Cas activé', '', '');
check((int)val('SELECT COUNT(*) FROM mail_queue') === $mq + 1, 'e-mail activé pour ce cas : mis en file');
set_setting('mail_enabled', '0');
check(!mail_case_enabled('po_ordered') && !send_mail('x@test.fr', 'a', 'b'), 'interrupteur général coupé : aucun e-mail, quel que soit le cas');
set_setting('mail_enabled', '1');
set_setting('mailev_supplier_po', '0');
check(!mail_case_enabled('supplier_po') && mail_case_enabled('password_reset'), 'envoi aux fournisseurs désactivable indépendamment');
set_setting('mail_enabled', '0');

section('Clé API et secrets chiffrés');
$GLOBALS['config']['anthropic_api_key'] = 'sk-ant-depuis-config-0000000000000000';
$plain = 'sk-ant-api03-TEST-' . bin2hex(random_bytes(12));
$enc = encrypt_secret($plain);
check(str_starts_with($enc, 'enc:') && !str_contains($enc, $plain), 'secret chiffré (illisible en base)');
check(decrypt_secret($enc) === $plain, 'déchiffrement fidèle');
check(decrypt_secret('enc:' . base64_encode(str_repeat('x', 60))) === '', 'secret altéré rejeté');
check(decrypt_secret('ancien-mot-de-passe') === 'ancien-mot-de-passe', 'ancienne valeur non chiffrée toujours lisible');
set_setting('ai_api_key', $enc);
check(ai_key_info()['source'] === 'settings' && ai_api_key() === $plain, 'la clé des paramètres est prioritaire sur config.php');
check(mask_secret($plain) === 'sk-ant-…' . substr($plain, -4), 'clé affichée masquée');

section('Centres : identifiants légaux et facturation');
check(siren_valid('732829320') && !siren_valid('732829321'), 'SIREN : clé de Luhn contrôlée');
check(siret_valid('73282932000074') && !siret_valid('73282932000075'), 'SIRET : clé de Luhn contrôlée');
check(siret_valid('35600000000048') && siret_valid('35600000049837'), 'SIRET : exception La Poste prise en compte (siège et établissements)');
check(vat_from_siren('732829320') === 'FR44732829320', 'TVA intracommunautaire calculée à partir du SIREN');
check(finess_valid('830100001') && finess_valid('2A0001234') && !finess_valid('83010000'), 'FINESS : format contrôlé (dont Corse 2A/2B)');
$tilleuls = one('SELECT * FROM centers WHERE id = ?', [$c1]);
$b = center_billing($tilleuls);
check(!$b['same'] && str_contains($b['address'], 'Liberté') && $b['email'] === 'compta@demo.fr', 'adresse de facturation distincte de la livraison');
$port = one('SELECT * FROM centers WHERE id = ?', [$c2]);
check(center_billing($port)['same'] && center_billing($port)['address'] === trim($port['address'] . ' ' . ($port['address2'] ?? '')), 'facturation identique à la livraison par défaut');
check(siret_valid((string)$tilleuls['siret']) && str_starts_with((string)$tilleuls['siret'], (string)$tilleuls['siren']), 'données de démonstration cohérentes (SIRET ⊃ SIREN)');

section('Logo de l\'entreprise');
$png = imagecreatetruecolor(300, 120);
imagesavealpha($png, true);
imagefill($png, 0, 0, imagecolorallocatealpha($png, 0, 0, 0, 127));
imagefilledrectangle($png, 20, 20, 280, 100, imagecolorallocate($png, 20, 30, 80));
imagepng($png, "$tmp/logo.png");
$_FILES['logo'] = ['name' => 'logo.png', 'tmp_name' => "$tmp/logo.png", 'error' => UPLOAD_ERR_OK, 'size' => filesize("$tmp/logo.png")];
brand_logo_save('logo');
check(brand_logo_url() !== null && brand_logo_pdf_path() !== null, 'logo enregistré (version écran et version PDF)');
check(getimagesize(brand_logo_pdf_path())[2] === IMAGETYPE_JPEG, 'version PDF au format JPEG (fond blanc)');
$pdfLogo = po_pdf([$po]);
check(str_contains($pdfLogo, '/Subtype /Image') && str_contains($pdfLogo, '/DCTDecode') && str_contains($pdfLogo, '%%EOF'), 'logo intégré au PDF du bon de commande');
$old = setting('brand_logo');
brand_logo_delete();
check(brand_logo_url() === null && !is_file(ROOT . '/uploads/brand/' . $old), 'logo supprimé (fichiers effacés)');

section('Mises à jour et sauvegardes');
check(update_path_allowed('app/domain.php') && !update_path_allowed('../evil.php') && !update_path_allowed('config.php') && !update_path_allowed('uploads/products/x.php'), 'chemins dangereux refusés dans un paquet');
$bk = "$tmp/base.zip";
backup_db_only($bk, 'test');
check(is_file($bk) && filesize($bk) > 1000, 'sauvegarde de la base créée');
$count = (int)val('SELECT COUNT(*) FROM products');
q("DELETE FROM kit_items");
$zip = new ZipArchive();
$zip->open($bk);
file_put_contents("$tmp/dump.jsonl", (string)$zip->getFromName('__database.jsonl'));
$zip->close();
db_restore_from("$tmp/dump.jsonl");
check((int)val('SELECT COUNT(*) FROM products') === $count && (int)val('SELECT COUNT(*) FROM kit_items') > 0, 'restauration de la base fidèle');

section('Journal d\'audit');
audit('Test audit', 'test', 1, ['a' => 1]);
check((int)val("SELECT COUNT(*) FROM audit_log WHERE action = 'Test audit'") === 1, 'entrée d\'audit enregistrée');
check(audit_diff(['p' => '1.00'], ['p' => '1.5'], ['p' => 'Prix']) === 'Prix : 1.00 → 1.5', 'différences décrites lisiblement');

section('Mode de commande fournisseur');
check(supplier_order_method(['order_method' => 'Site web']) === 'online' && supplier_order_method(['order_method' => 'E-mail']) === 'email'
    && supplier_order_method(['order_method' => 'Téléphone']) === 'phone' && supplier_order_method(['order_method' => 'Commercial']) === 'other'
    && supplier_order_method(['order_method' => 'online']) === 'online' && supplier_order_method(['order_method' => '', 'email' => 'a@b.fr']) === 'email', 'anciennes saisies libres reconnues');
check(supplier_order_url(['order_url' => '', 'website' => 'www.fournisseur.fr']) === 'https://www.fournisseur.fr'
    && supplier_order_url(['order_url' => 'https://shop.x.fr/commande', 'website' => 'https://x.fr']) === 'https://shop.x.fr/commande'
    && supplier_order_url(['website' => '']) === null, 'adresse de commande en ligne (dédiée, sinon site web)');
$poE = (int)val('SELECT id FROM purchase_orders ORDER BY id LIMIT 1');
$eml = po_eml([$poE]);
$draftE = po_mail_draft([$poE]);
check(str_starts_with($eml, 'X-Unsent: 1') && str_contains($eml, 'Content-Type: application/pdf; name="' . $draftE['filename'] . '"')
    && str_contains(base64_decode(preg_replace('/\s+/', '', explode("\r\n\r\n", explode('--cmd-', $eml)[2])[1])), '%PDF'), 'brouillon .eml : message non envoyé avec le PDF joint');
check(str_contains($draftE['body'], $draftE['label']) && str_starts_with($draftE['subject'], 'Commande '), 'texte de l\'e-mail pré-rédigé');

section('Assistance (chatbot et contact NLapps)');
$m = support_match('la caméra marche pas sur ma tablette', false);
check($m && $m[0]['title'] === 'La caméra ne s\'ouvre pas', 'question courante : bonne réponse (caméra)');
check(support_match('ou en est ma commande', false)[0]['title'] === 'Où en est ma demande ?', 'tournure « où en est » → suivi des demandes');
check(!support_match('importer un fichier excel', false) && support_match('importer un fichier excel', true)[0]['title'] === 'Comment importer un catalogue ?', 'réponses réservées aux administrateurs');
$r = support_answer('facture erronée du mois dernier', false);
check($r['source'] === 'none' && $r['confident'] === false, 'question hors base : relais vers l\'équipe');
check(!support_live_enabled() && support_sync() === 0, 'conversation en direct inactive sans centre d\'assistance configuré');
check(support_contact()['email'] === 'contact@nlapps.fr' && support_contact()['editor'] === 'NLapps', 'coordonnées NLapps par défaut');
$p = support_hub_parse("    'support_hub_url' => 'https://nlapps.fr/assistance/api.php',\n    'support_hub_key' => 'nlh_0123456789abcdef0123456789abcdef01234567',");
check($p['url'] === 'https://nlapps.fr/assistance/api.php' && $p['key'] === 'nlh_0123456789abcdef0123456789abcdef01234567', 'lignes NLapps collées → adresse et clé');
$p = support_hub_parse("Adresse : https://hub.example/api.php  clé nlh_abcdefabcdefabcdefabcdef");
check($p['url'] === 'https://hub.example/api.php' && $p['key'] === 'nlh_abcdefabcdefabcdefabcdef', 'adresse et clé collées en texte libre');
check(support_hub_parse('rien ici') === ['url' => null, 'key' => null], 'texte sans accès reconnu');
set_setting('support_hub_url', 'https://hub.example/api.php');
set_setting('support_hub_key', encrypt_secret('nlh_abcdefabcdefabcdefabcdef'));
$h = support_hub_config();
check($h['source'] === 'settings' && $h['key'] === 'nlh_abcdefabcdefabcdefabcdef' && support_live_enabled(), 'accès assistance enregistré dans les paramètres (clé chiffrée)');
check(!str_contains((string)setting('support_hub_key'), 'nlh_'), 'clé d\'assistance jamais stockée en clair');
set_setting('support_hub_url', null);
set_setting('support_hub_key', null);
check(!support_live_enabled(), 'accès assistance supprimé');
check(app_name() !== '' && str_contains((string)file_get_contents(ROOT . '/manifest.webmanifest'), 'Approvia'), 'nom commercial Approvia');

section('Suppression d\'articles');
$supT = (int)val('SELECT id FROM suppliers LIMIT 1');
$solo = insert('products', ['supplier_id' => $supT, 'reference' => 'DEL1', 'name' => 'Article jetable', 'unit' => 'U', 'catalog_price' => 3, 'vat_rate' => 20, 'min_qty' => 1, 'active' => 1, 'created_at' => now()]);
price_record($solo, 3.0, null, 'test');
$someUser = (int)val("SELECT id FROM users WHERE status = 'active' LIMIT 1");
$someCenter = (int)val('SELECT id FROM centers LIMIT 1');
insert('favorites', ['user_id' => $someUser, 'product_id' => $solo]);
insert('stock', ['center_id' => $someCenter, 'product_id' => $solo, 'qty' => 4, 'updated_at' => now()]);
check(product_delete($solo) === 'deleted' && !one('SELECT id FROM products WHERE id = ?', [$solo])
    && (int)val('SELECT COUNT(*) FROM price_history WHERE product_id = ?', [$solo]) === 0
    && (int)val('SELECT COUNT(*) FROM favorites WHERE product_id = ?', [$solo]) === 0, 'article jamais commandé : effacé avec ses favoris, stock et prix');
$ordered = (int)val('SELECT product_id FROM request_lines WHERE product_id IS NOT NULL LIMIT 1');
if ($ordered) {
    insert('cart_items', ['user_id' => $someUser, 'center_id' => $someCenter, 'product_id' => $ordered, 'qty' => 1, 'created_at' => now()]);
    check(product_delete($ordered) === 'archived' && (int)val('SELECT active FROM products WHERE id = ?', [$ordered]) === 0
        && (int)val('SELECT COUNT(*) FROM cart_items WHERE product_id = ?', [$ordered]) === 0, 'article déjà commandé : masqué et retiré des paniers, historique intact');
}
check(product_delete(999999) === 'Article introuvable.', 'article inexistant : message');

section('Suppression de comptes');
$adminId = (int)val("SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1");
$fresh = insert('users', ['email' => 'jetable@test.fr', 'password_hash' => 'x', 'first_name' => 'Jean', 'last_name' => 'Jetable', 'role' => 'user', 'status' => 'active', 'created_at' => now()]);
insert('user_centers', ['user_id' => $fresh, 'center_id' => (int)val('SELECT id FROM centers LIMIT 1')]);
check(user_delete($fresh, $adminId) === 'deleted' && !one('SELECT id FROM users WHERE id = ?', [$fresh]), 'compte sans historique effacé définitivement');
$withHist = (int)val('SELECT user_id FROM requests ORDER BY id LIMIT 1');
$nbReq = (int)val('SELECT COUNT(*) FROM requests WHERE user_id = ?', [$withHist]);
check(user_delete($withHist, $adminId) === 'anonymized', 'compte avec commandes anonymisé');
$anon = one('SELECT * FROM users WHERE id = ?', [$withHist]);
check($anon['deleted_at'] !== null && $anon['status'] === 'disabled' && str_ends_with($anon['email'], '@compte.invalid') && $anon['first_name'] === 'Ancien compte', 'nom et e-mail effacés, connexion impossible');
check((int)val('SELECT COUNT(*) FROM requests WHERE user_id = ?', [$withHist]) === $nbReq && (int)val('SELECT COUNT(*) FROM user_centers WHERE user_id = ?', [$withHist]) === 0, 'historique des demandes conservé, accès aux centres retiré');
check(user_delete($adminId, $adminId) !== 'deleted', 'impossible de supprimer son propre compte');
$otherAdmin = insert('users', ['email' => 'admin2@test.fr', 'password_hash' => 'x', 'first_name' => 'A', 'last_name' => 'B', 'role' => 'admin', 'status' => 'active', 'created_at' => now()]);
q("UPDATE users SET status = 'disabled' WHERE id = ?", [$adminId]);
check(user_delete($otherAdmin, $adminId) === 'Impossible de supprimer le dernier administrateur.', 'le dernier administrateur est protégé');
q("UPDATE users SET status = 'active' WHERE id = ?", [$adminId]);
check(user_delete($otherAdmin, $adminId) === 'deleted', 'un autre administrateur peut être supprimé');

section('Suppression des données de démonstration');
// Un centre, un fournisseur et un article « réels » qui doivent survivre
$realCenter = insert('centers', ['name' => 'Centre IMSS Réel', 'code' => 'IMSS', 'color' => '#000000', 'active' => 1, 'created_at' => now()]);
$realSup = insert('suppliers', ['name' => 'Fournisseur réel', 'email' => 'achats@reel.fr', 'min_order_amount' => 0, 'all_centers' => 1, 'color' => '#111111', 'active' => 1, 'created_at' => now()]);
$realProd = insert('products', ['supplier_id' => $realSup, 'reference' => 'R1', 'name' => 'Article réel', 'unit' => 'Unité', 'catalog_price' => 10, 'vat_rate' => 20, 'min_qty' => 1, 'active' => 1, 'created_at' => now()]);
$realUser = insert('users', ['email' => 'salarie@imss.fr', 'password_hash' => 'x', 'first_name' => 'Réel', 'last_name' => 'Salarié', 'role' => 'user', 'status' => 'active', 'created_at' => now()]);
// Une demande réelle qui contient aussi un article de démonstration
$demoProd = (int)val("SELECT p.id FROM products p JOIN suppliers s ON s.id = p.supplier_id WHERE s.name = 'MédiDistrib' LIMIT 1");
$rq = insert('requests', ['center_id' => $realCenter, 'user_id' => $realUser, 'urgent' => 0, 'created_at' => now()]);
foreach ([[$realProd, $realSup], [$demoProd, (int)val('SELECT supplier_id FROM products WHERE id = ?', [$demoProd])]] as [$p, $sp]) {
    insert('request_lines', ['request_id' => $rq, 'center_id' => $realCenter, 'product_id' => $p, 'supplier_id' => $sp, 'qty' => 1, 'unit_price' => 5, 'status' => 'pending', 'created_at' => now()]);
}
check(demo_present() && demo_summary()['centres'] === 3 && demo_summary()['fournisseurs'] === 5, 'jeu de démonstration détecté');
demo_purge();
check(!demo_present(), 'plus aucune donnée de démonstration');
check((int)val("SELECT COUNT(*) FROM users WHERE email LIKE '%@demo.fr'") === 0 && (int)val("SELECT COUNT(*) FROM users WHERE role = 'admin'") >= 1, 'comptes de démo supprimés, administrateur conservé');
check((bool)one('SELECT id FROM centers WHERE id = ?', [$realCenter]) && (bool)one('SELECT id FROM products WHERE id = ?', [$realProd]) && (bool)one('SELECT id FROM users WHERE id = ?', [$realUser]), 'centre, article et compte réels conservés');
check((int)val('SELECT COUNT(*) FROM request_lines WHERE request_id = ?', [$rq]) === 1, 'demande réelle conservée, ligne sur article de démo retirée');
$orphans = (int)val('SELECT COUNT(*) FROM request_lines l LEFT JOIN products p ON p.id = l.product_id WHERE p.id IS NULL')
    + (int)val('SELECT COUNT(*) FROM purchase_orders po LEFT JOIN centers c ON c.id = po.center_id WHERE c.id IS NULL')
    + (int)val('SELECT COUNT(*) FROM stock s LEFT JOIN products p ON p.id = s.product_id WHERE p.id IS NULL')
    + (int)val('SELECT COUNT(*) FROM requests r LEFT JOIN users u ON u.id = r.user_id WHERE u.id IS NULL');
check($orphans === 0, 'aucune donnée orpheline');
activity_purge();
check((int)val('SELECT COUNT(*) FROM requests') === 0 && (int)val('SELECT COUNT(*) FROM purchase_orders') === 0 && (bool)one('SELECT id FROM products WHERE id = ?', [$realProd]), 'effacement de l\'activité : demandes et bons vidés, catalogue conservé');

section('Import assisté : lecture des fichiers');
check(parse_number('1 234,56 €') === 1234.56 && parse_number('1,234.56') === 1234.56 && parse_number('12,5') === 12.5 && parse_number('8.90') === 8.9 && parse_number('') === null && parse_number('n/c') === null, 'nombres français et anglais');
// CSV Windows-1252 avec lignes de titre, séparateur « ; »
file_put_contents("$tmp/f.csv", mb_convert_encoding("TARIF 2026 - Médi Fournitures;;;\n;;;\nRéf.;Libellé article;Prix public TTC;EAN\nA1;Compresses stériles 7,5 cm;7,20 €;3401234567893\nA2;Gants nitrile taille M;10,80 €;\n", 'Windows-1252', 'UTF-8'));
$rows = spreadsheet_read("$tmp/f.csv", 'tarif.csv');
$h = sheet_header_row($rows);
check($h === 1 && $rows[$h][1] === 'Libellé article' && $rows[$h + 1][1] === 'Compresses stériles 7,5 cm', 'CSV Windows-1252 : accents, ligne de titre ignorée');
// XLSX minimal (chaînes partagées + nombres)
$z = new ZipArchive();
$z->open("$tmp/f.xlsx", ZipArchive::CREATE | ZipArchive::OVERWRITE);
$z->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>');
$z->addFromString('xl/workbook.xml', '<?xml version="1.0"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Tarif" sheetId="1" r:id="rId1"/></sheets></workbook>');
$z->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
$z->addFromString('xl/sharedStrings.xml', '<?xml version="1.0"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><si><t>Désignation</t></si><si><t>Prix net HT</t></si><si><r><t>Spray </t></r><r><t>désinfectant</t></r></si></sst>');
$z->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData><row r="1"><c r="A1" t="s"><v>0</v></c><c r="C1" t="s"><v>1</v></c></row><row r="2"><c r="A2" t="s"><v>2</v></c><c r="C2"><v>5.9000000000000004</v></c></row></sheetData></worksheet>');
$z->close();
$rows = spreadsheet_read("$tmp/f.xlsx", 'tarif.xlsx');
check($rows[0] === ['Désignation', '', 'Prix net HT'] && $rows[1] === ['Spray désinfectant', '', '5.9'], 'Excel .xlsx : cellules, colonnes vides et texte enrichi');
file_put_contents("$tmp/f.xls", '<html><body><table><tr><th>Code</th><th>Produit</th></tr><tr><td>Z9</td><td>Savon doux 1 L</td></tr></table></body></html>');
check(spreadsheet_read("$tmp/f.xls", 'export.xls')[1] === ['Z9', 'Savon doux 1 L'], '.xls exporté au format HTML');
file_put_contents("$tmp/bin.xls", "\xD0\xCF\x11\xE0" . str_repeat("\0", 100));
try { spreadsheet_read("$tmp/bin.xls", 'vieux.xls'); $okBin = false; } catch (RuntimeException $e) { $okBin = str_contains($e->getMessage(), '.xlsx'); }
check($okBin, '.xls binaire : message clair (enregistrer en .xlsx)');

section('Import assisté : correspondances et import');
$map = import_guess_mapping(['Réf.', 'Libellé article', 'Prix public TTC', 'EAN', 'Prix net', 'Famille', 'Marque']);
check(($map['reference'] ?? null) === 0 && ($map['name'] ?? null) === 1 && ($map['catalog_price'] ?? null) === 2 && ($map['barcode'] ?? null) === 3 && ($map['negotiated_price'] ?? null) === 4 && ($map['category'] ?? null) === 5 && ($map['brand'] ?? null) === 6, 'colonnes reconnues par leurs intitulés');
$legacy = import_guess_mapping(IMPORT_COLUMNS);
check(count($legacy) === 12 && $legacy['name'] === 2 && $legacy['catalog_price'] === 6, 'ancien modèle CSV (export du catalogue) toujours reconnu');
$sup = insert('suppliers', ['name' => 'Médi Fournitures', 'email' => 'x@medi.fr', 'min_order_amount' => 0, 'all_centers' => 1, 'color' => '#123456', 'active' => 1, 'created_at' => now()]);
$existing = insert('products', ['supplier_id' => $sup, 'reference' => 'A1', 'name' => 'Compresses (ancien nom)', 'unit' => 'Boîte', 'catalog_price' => 5, 'vat_rate' => 20, 'min_qty' => 1, 'active' => 1, 'created_at' => now()]);
$hyg = (int)val("SELECT id FROM categories WHERE name LIKE 'Hygi%' LIMIT 1");
$_SESSION['uid'] = (int)val("SELECT id FROM users WHERE role = 'admin' AND deleted_at IS NULL LIMIT 1");
file_put_contents("$tmp/g.csv", "Réf;Désignation;Prix public TTC;Prix net TTC;Famille;Marque;TVA\nA1;Compresses stériles;12,00;9,60;Hygiène & Désinfection;Hartmann;20\nA2;Gants nitrile M;10,80;;Gants;;20\nA2;Gants nitrile M;10,80;;Gants;;20\n;Sous-total;;;;;\nA3;Sans prix;;;;;\n");
$st = import_start("$tmp/g.csv", 'g.csv', (int)$_SESSION['uid']);
$st['mapping'] = import_guess_mapping($st['headers']);
$st['prices_ttc'] = true;
$st['default_supplier'] = $sup;
import_match_values($st, false);
check(($st['category_map']['Hygiène & Désinfection'] ?? 0) === $hyg && ($st['category_map']['Gants'] ?? -1) === 0, 'famille du fichier rapprochée d\'une catégorie existante (sans accents/majuscules)');
$prep = import_prepare($st);
$byLine = array_column($prep, null, 'line');
check(count($prep) === 4, 'lignes sans désignation (sous-totaux) écartées');
check($byLine[0]['status'] === 'update' && $byLine[0]['existing_id'] === $existing, 'article existant reconnu (même fournisseur + référence)');
check($byLine[0]['catalog_price'] === 10.0 && $byLine[0]['negotiated_price'] === 8.0, 'prix TTC convertis en HT');
check($byLine[0]['old_price'] === 5.0 && $byLine[0]['price_pct'] === 60.0, 'hausse de prix calculée à l\'aperçu (5 € → 8 € : +60 %)');
check($byLine[2]['status'] === 'error' && str_contains((string)$byLine[2]['error'], 'doublon'), 'doublon dans le fichier signalé');
check($byLine[4]['status'] === 'error', 'ligne sans prix signalée');
$st['create_categories'] = true;
$rep = import_apply($st, [0 => 1, 1 => 1], true);
check($rep['created'] === 1 && $rep['updated'] === 1 && $rep['categories'] === 1, 'import : 1 créé, 1 mis à jour, catégorie « Gants » créée');
check(count($rep['increases']) === 1 && $rep['increases'][0]['pct'] === 60.0, 'hausse au-delà du seuil signalée dans le compte rendu d\'import');
$upd = one('SELECT * FROM products WHERE id = ?', [$existing]);
check($upd['name'] === 'Compresses stériles' && (float)$upd['catalog_price'] === 10.0 && str_contains((string)$upd['keywords'], 'Hartmann') && (int)$upd['category_id'] === $hyg, 'article mis à jour (nom, prix, marque en mot-clé, catégorie)');
check((int)val("SELECT COUNT(*) FROM price_history WHERE product_id = ? AND source = 'Import fichier'", [$existing]) === 1, 'historique des prix alimenté');

section('Tutoriels vidéo');
$ch = video_chapters_parse("0:00 Se connecter | connexion\n4:12 Réceptionner une livraison | réception colis livré\n1:02:05 Très long\nligne ignorée\n2:05 Scanner un code-barres");
check(count($ch) === 4 && $ch[1]['t'] === 125 && $ch[2]['t'] === 252 && $ch[2]['k'] === 'réception colis livré' && $ch[3]['t'] === 3725, 'chapitres lus (m:ss et h:mm:ss), triés, lignes invalides ignorées');
check(video_time(252) === '4:12' && video_time(3725) === '1:02:05', 'durées affichées en m:ss / h:mm:ss');
check(video_chapters_parse(video_chapters_text($ch)) === $ch, 'chapitres : aller-retour texte ↔ données');
file_put_contents(videos_dir() . '/test-tuto.mp4', str_repeat('x', 4096));
$vid = insert('videos', ['uid' => 'test-tuto', 'source' => 'local', 'title' => 'Tutoriel salarié', 'keywords' => 'commander demande panier inventaire', 'description' => 'Faire une demande, réceptionner, compter le stock.',
    'chapters' => json_encode(video_chapters_parse("0:00 Se connecter | connexion mot de passe\n2:05 Scanner deux articles à la suite | code-barres scan caméra\n4:12 Réceptionner une livraison | réception colis livré carton\n6:20 Inventaire tablette | stock comptage")),
    'audience' => 'all', 'file' => 'test-tuto.mp4', 'active' => 1, 'created_at' => now()]);
insert('videos', ['uid' => 'test-admin', 'source' => 'local', 'title' => 'Créer un bon de commande', 'keywords' => 'bon commande fournisseur', 'chapters' => '[]',
    'audience' => 'admin', 'file' => 'test-tuto.mp4', 'active' => 1, 'created_at' => now()]);
$m = video_match('comment réceptionner un colis ?', false);
check($m && $m['video']['uid'] === 'test-tuto' && $m['chapter']['t'] === 252 && str_contains($m['url'], 't=252'), 'aide : « réceptionner un colis » → tutoriel, chapitre « Réceptionner une livraison » (4:12)');
$m = video_match('je veux voir la vidéo pour scanner', false);
check($m && $m['chapter']['t'] === 125, 'aide : demande de vidéo sur le scan → chapitre « Scanner »');
$m = video_match('montre-moi en vidéo comment faire l\'inventaire', false);
check($m && $m['chapter']['t'] === 380, 'aide : « montre-moi en vidéo comment faire l\'inventaire » → chapitre « Inventaire tablette » plutôt que toute la vidéo');
check(video_match('quelle est la météo demain', false) === null, 'aide : question hors sujet → pas de vidéo proposée');
check((video_match('créer un bon de commande fournisseur', false)['video']['uid'] ?? null) !== 'test-admin', 'vidéo réservée aux administrateurs jamais proposée à un salarié');
check(video_match('créer un bon de commande fournisseur', true)['video']['uid'] === 'test-admin', 'vidéo administrateur proposée à un administrateur');
check(count(video_list(false)) === 1 && count(video_list(true)) === 2, 'liste des vidéos selon le profil');
update('videos', ['file' => 'absent.mp4'], 'id = ?', [$vid]);
check(count(video_list(false)) === 0 && video_match('réceptionner un colis', false) === null, 'vidéo dont le fichier manque : ni listée ni proposée');
q("DELETE FROM videos WHERE uid IN ('test-tuto', 'test-admin')");
// Vidéos publiées par NLapps : création, mise à jour (nouveau fichier → retéléchargement), retrait
videos_sync_remote([['uid' => 'nl-abc', 'title' => 'Tutoriel salarié', 'k' => 'commander', 'chapters' => [['t' => 252, 'title' => 'Réceptionner', 'k' => 'colis']], 'sha256' => str_repeat('a', 64), 'size' => 10]]);
$r = one("SELECT * FROM videos WHERE uid = 'nl-abc'");
check($r && $r['source'] === 'hub' && $r['file'] === null && (json_decode($r['chapters'], true)[0]['t'] ?? 0) === 252, 'vidéo NLapps enregistrée, en attente de téléchargement');
copy(videos_dir() . '/test-tuto.mp4', videos_dir() . '/nl-abc-1.mp4');
update('videos', ['file' => 'nl-abc-1.mp4', 'active' => 0], 'id = ?', [$r['id']]);
videos_sync_remote([['uid' => 'nl-abc', 'title' => 'Tutoriel salarié (v2)', 'sha256' => str_repeat('b', 64)]]);
$r = one("SELECT * FROM videos WHERE uid = 'nl-abc'");
check($r['title'] === 'Tutoriel salarié (v2)' && $r['file'] === null && !is_file(videos_dir() . '/nl-abc-1.mp4') && (int)$r['active'] === 0, 'nouvelle version : ancien fichier supprimé, à retélécharger ; vidéo masquée par l\'administrateur reste masquée');
videos_sync_remote([]);
check(!one("SELECT id FROM videos WHERE uid = 'nl-abc'"), 'vidéo retirée par NLapps supprimée de l\'installation');
// Vidéo d'accueil des salariés
insert('videos', ['uid' => 'w-tuto', 'source' => 'hub', 'title' => 'Tutoriel salarié', 'chapters' => '[]', 'audience' => 'all', 'file' => 'test-tuto.mp4', 'active' => 1, 'welcome' => 1, 'created_at' => now()]);
insert('videos', ['uid' => 'w-autre', 'source' => 'local', 'title' => 'Autre vidéo', 'chapters' => '[]', 'audience' => 'all', 'file' => 'test-tuto.mp4', 'active' => 1, 'created_at' => now()]);
set_setting('welcome_video', '');
check((video_welcome()['uid'] ?? '') === 'w-tuto', 'vidéo d\'accueil automatique : celle désignée par NLapps');
set_setting('welcome_video', 'w-autre');
check((video_welcome()['uid'] ?? '') === 'w-autre', 'vidéo d\'accueil choisie par l\'administrateur');
set_setting('welcome_video', 'none');
check(video_welcome() === null, 'aucune fenêtre d\'accueil si l\'administrateur l\'a désactivée');
set_setting('welcome_video', '');
unset($_SESSION['welcome_shown']);
check(video_welcome_due(['role' => 'admin', 'welcome_video_off' => 0]) === null, 'jamais montrée aux administrateurs');
check((video_welcome_due(['role' => 'user', 'welcome_video_off' => 0])['uid'] ?? '') === 'w-tuto', 'montrée au salarié à sa connexion');
check(video_welcome_due(['role' => 'user', 'welcome_video_off' => 0]) === null, 'une seule fois par connexion (pas à chaque page)');
unset($_SESSION['welcome_shown']);
check(video_welcome_due(['role' => 'user', 'welcome_video_off' => 1]) === null, '« Ne plus afficher » respecté');
videos_sync_remote([['uid' => 'nl-acc', 'title' => 'Accueil', 'welcome' => true, 'sha256' => str_repeat('c', 64)]]);
check((int)val("SELECT welcome FROM videos WHERE uid = 'nl-acc'") === 1, 'indication « vidéo d\'accueil » reçue du centre d\'assistance');
q("DELETE FROM videos WHERE uid IN ('w-tuto', 'w-autre', 'nl-acc')");
unset($_SESSION['welcome_shown']);
@unlink(videos_dir() . '/test-tuto.mp4');

section('Rôle acheteur');
$buyerId = insert('users', ['email' => 'acheteur@test.fr', 'password_hash' => 'x', 'first_name' => 'Alice', 'last_name' => 'Acheteuse', 'role' => 'buyer', 'status' => 'active', 'created_at' => now()]);
$buyer = as_user('acheteur@test.fr');
check(is_admin() && !is_superadmin(), 'l\'acheteur fait partie du service achats, sans être administrateur');
check(count(user_centers($buyer)) === (int)val('SELECT COUNT(*) FROM centers WHERE active = 1'), 'l\'acheteur voit tous les centres');
check(in_array($buyerId, admin_ids(), true) && !in_array($buyerId, admin_ids(true), true), 'notifié des demandes, pas des comptes à valider');
check(video_welcome_due($buyer) === null, 'pas de vidéo d\'accueil des salariés pour l\'acheteur');
check(role_label('buyer') === 'Acheteur', 'libellé du rôle');
q('DELETE FROM users WHERE id = ?', [$buyerId]);
as_user('admin@test.fr');
check(is_superadmin(), 'l\'administrateur garde l\'organisation et les paramètres');

section('Contrats et marchés');
$cp = one('SELECT * FROM products WHERE active = 1 AND reference IS NOT NULL ORDER BY id LIMIT 1');
$cpSup = (int)$cp['supplier_id'];
$today = date('Y-m-d');
$cid = insert('contracts', ['supplier_id' => $cpSup, 'name' => 'Marché test', 'buying_group' => 'UniHA', 'start_date' => date('Y-m-d', strtotime('-1 year')),
    'end_date' => date('Y-m-d', strtotime('+200 days')), 'notice_days' => 90, 'tacit_renewal' => 0, 'created_at' => now()]);
check(contract_status(one('SELECT * FROM contracts WHERE id = ?', [$cid]))['key'] === 'active', 'contrat en cours');
[$parsed, $unknown] = contract_parse_prices($cp['reference'] . "\t1,23\nINCONNU;4,00\n", $cpSup);
check(($parsed[(int)$cp['id']] ?? null) === 1.23 && count($unknown) === 1, 'annexe tarifaire collée : référence reconnue, ligne inconnue signalée');
insert('contract_prices', ['contract_id' => $cid, 'product_id' => $cp['id'], 'price' => 1.23]);
check(contracts_apply_prices() >= 1 && (float)val('SELECT negotiated_price FROM products WHERE id = ?', [$cp['id']]) === 1.23, 'prix contractuel appliqué à l\'article');
check(str_starts_with((string)val('SELECT source FROM price_history WHERE product_id = ? ORDER BY id DESC LIMIT 1', [$cp['id']]), 'Contrat'), 'historique des prix : origine « Contrat »');
check((product_contract((int)$cp['id'])['buying_group'] ?? '') === 'UniHA', 'contrat retrouvé depuis l\'article');
check(cron_contracts() === 0, 'pas d\'alerte avant le préavis');
update('contracts', ['end_date' => date('Y-m-d', strtotime('+60 days'))], 'id = ?', [$cid]);
check(cron_contracts() === 1 && val('SELECT alerted_at FROM contracts WHERE id = ?', [$cid]) !== null, 'préavis atteint : service achats prévenu');
check(cron_contracts() === 0, 'alerte envoyée une seule fois');
check(count(contracts_attention()) === 1, 'contrat signalé sur le pilotage');
$r = contract_renew($cid, 12);
check($r['end_date'] === date('Y-m-d', strtotime(date('Y-m-d', strtotime('+60 days')) . ' +12 months')) && $r['alerted_at'] === null, 'prolongation de 12 mois, alertes réarmées');
update('contracts', ['end_date' => date('Y-m-d', strtotime('-2 days'))], 'id = ?', [$cid]);
check(contract_status(one('SELECT * FROM contracts WHERE id = ?', [$cid]))['key'] === 'expired' && cron_contracts() >= 1, 'contrat expiré signalé');
check(product_contract((int)$cp['id']) === null && !isset(contract_prices_now()[(int)$cp['id']]), 'prix contractuel plus en vigueur après l\'échéance');
q('DELETE FROM contract_prices WHERE contract_id = ?', [$cid]);
q('DELETE FROM contracts WHERE id = ?', [$cid]);

section('Multi-clients');
check(instance_valid_slug('sante-var') && instance_valid_slug('imss') && !instance_valid_slug('Santé') && !instance_valid_slug('-x') && !instance_valid_slug('a/b'), 'identifiants de client contrôlés');
check(instance_normalize_host(' HTTPS://Imss.Approvia.fr/index.php ') === 'imss.approvia.fr', 'adresse normalisée');
$ip = instance_paths('imss');
check(str_ends_with($ip['config'], '/instances/imss/config.php') && $ip['uploads_url'] === 'uploads/i/imss', 'chemins propres au client');
check(instance_paths(null)['storage'] === ROOT . '/storage' && storage_path('x') === ROOT . '/storage/x', 'installation simple inchangée');
check(uploads_url('products/a.png') === 'uploads/products/a.png', 'photos de l\'installation simple');

// Nettoyage
array_map('unlink', glob("$tmp/*"));
@rmdir($tmp);
echo "\n" . ($fail ? "\033[31m$fail échec(s)\033[0m, " : '') . "\033[32m$pass test(s) réussi(s)\033[0m\n";
exit($fail ? 1 : 0);
