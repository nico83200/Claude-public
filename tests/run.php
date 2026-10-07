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
file_put_contents("$tmp/config.php", "<?php return ['db' => ['driver' => 'sqlite', 'path' => " . var_export($db, true) . "], 'app_name' => 'Tests', 'timezone' => 'Europe/Paris', 'anthropic_api_key' => ''];");
putenv("CMD_CONFIG=$tmp/config.php");
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REMOTE_ADDR'] = '10.0.0.1';
$_SESSION = [];

// Initialisation de la base avant l'amorçage (qui suppose les tables existantes)
define('ROOT', dirname(__DIR__));
define('APP', ROOT . '/app');
$GLOBALS['config'] = require "$tmp/config.php";
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
check($byLine[2]['status'] === 'error' && str_contains((string)$byLine[2]['error'], 'doublon'), 'doublon dans le fichier signalé');
check($byLine[4]['status'] === 'error', 'ligne sans prix signalée');
$st['create_categories'] = true;
$rep = import_apply($st, [0 => 1, 1 => 1], true);
check($rep['created'] === 1 && $rep['updated'] === 1 && $rep['categories'] === 1, 'import : 1 créé, 1 mis à jour, catégorie « Gants » créée');
$upd = one('SELECT * FROM products WHERE id = ?', [$existing]);
check($upd['name'] === 'Compresses stériles' && (float)$upd['catalog_price'] === 10.0 && str_contains((string)$upd['keywords'], 'Hartmann') && (int)$upd['category_id'] === $hyg, 'article mis à jour (nom, prix, marque en mot-clé, catégorie)');
check((int)val("SELECT COUNT(*) FROM price_history WHERE product_id = ? AND source = 'Import fichier'", [$existing]) === 1, 'historique des prix alimenté');

// Nettoyage
array_map('unlink', glob("$tmp/*"));
@rmdir($tmp);
echo "\n" . ($fail ? "\033[31m$fail échec(s)\033[0m, " : '') . "\033[32m$pass test(s) réussi(s)\033[0m\n";
exit($fail ? 1 : 0);
