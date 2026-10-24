<?php
/**
 * Tests de bout en bout sans réseau (SQLite) :
 *   php bin/install-local.php && php tests/run.php
 * Couvre : panier, création de commande, webhook Stripe signé (idempotence),
 * construction de la commande Printful, webhook Printful d'expédition.
 */
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;
use App\Services\Cart;
use App\Services\OrderService;
use App\Services\Personalizer;
use App\Services\Reviews;
use App\Services\Stripe;
use App\Services\Upload;

$failures = 0;
function check(string $label, bool $ok, string $detail = ''): void
{
    global $failures;
    echo ($ok ? "  ✔ " : "  ✘ ") . $label . ($ok || $detail === '' ? '' : " — $detail") . "\n";
    $failures += $ok ? 0 : 1;
}

if (config('db.driver') !== 'sqlite') {
    exit("Ces tests modifient la base : lancez-les uniquement sur l'installation locale SQLite.\n");
}

echo "Utilitaires\n";
check('to_cents("29,90") = 2990', to_cents('29,90') === 2990);
check('money(2990)', money(2990) === "29,90\u{00A0}€");
check('money(6900) sans décimales', money(6900) === "69\u{00A0}€");
check('slugify', slugify('Sweat « Fer à cheval » !') === 'sweat-fer-a-cheval');
check('sanitize personnalisation', Personalizer::sanitize('<b>Tornado</b> 🐴 du Pré', 30) === 'bTornadob du Pré');

echo "Signature Stripe\n";
$payload = '{"id":"evt_1","type":"ping"}';
$t = time();
$sig = 't=' . $t . ',v1=' . hash_hmac('sha256', "$t.$payload", 'whsec_x');
check('signature valide acceptée', Stripe::verifyWebhook($payload, $sig, 'whsec_x')['id'] === 'evt_1');
foreach (['mauvais secret' => [$sig, 'whsec_y'], 'expirée' => ['t=' . ($t - 1000) . ',v1=' . hash_hmac('sha256', ($t - 1000) . ".$payload", 'whsec_x'), 'whsec_x']] as $label => [$h, $secret]) {
    try {
        Stripe::verifyWebhook($payload, $h, $secret);
        check("signature $label refusée", false);
    } catch (RuntimeException) {
        check("signature $label refusée", true);
    }
}

echo "Panier & commande\n";
$_SESSION = [];
$hoodie = Database::one("SELECT v.id FROM product_variants v JOIN products p ON p.id = v.product_id WHERE p.personalizable = 1 AND p.print_placement = 'front' AND p.personalization LIKE '%\"mode\":\"text\"%' ORDER BY v.id LIMIT 1");
$poster = Database::one("SELECT v.id, v.price FROM product_variants v JOIN products p ON p.id = v.product_id WHERE v.price IS NOT NULL AND p.personalizable = 0 ORDER BY v.price DESC LIMIT 1");
Cart::add((int) $hoodie['id'], 2, 'Tornado');
Cart::add((int) $hoodie['id'], 1, 'Tornado');
Cart::add((int) $poster['id'], 1);
$items = Cart::items();
check('lignes fusionnées (même texte)', count($items) === 2 && $items[0]['qty'] === 3);
check('prix de variante appliqué', $items[1]['unit_price'] === (int) $poster['price']);
$subtotal = Cart::subtotal($items);
check('livraison offerte au-dessus du seuil', Cart::shippingOptions($subtotal)[0]['price'] === 0);

$order = OrderService::createFromCart($items, true);
check('commande créée en attente', $order['status'] === 'pending' && str_starts_with($order['number'], 'CC-'));
Database::update('orders', ['stripe_session_id' => 'cs_test_123'], 'id = :id', ['id' => $order['id']]);

$session = [
    'id' => 'cs_test_123', 'object' => 'checkout.session', 'payment_status' => 'paid', 'currency' => 'eur',
    'metadata' => ['order_id' => (string) $order['id']],
    'amount_subtotal' => $subtotal, 'amount_total' => $subtotal, 'payment_intent' => 'pi_test_123',
    'total_details' => ['amount_discount' => 0, 'amount_tax' => 0, 'amount_shipping' => 0],
    'shipping_cost' => ['amount_total' => 0, 'shipping_rate' => ['metadata' => ['code' => 'STANDARD']]],
    'customer_details' => ['email' => 'Cliente@Example.com', 'name' => 'Marie Dupont', 'phone' => '+33600000000'],
    'shipping_details' => ['name' => 'Marie Dupont', 'address' => ['line1' => '12 chemin des Écuries', 'line2' => '', 'postal_code' => '14000', 'city' => 'Caen', 'country' => 'FR']],
];

// Sans Printful configuré : paiement validé, transmission en erreur (relancée par le cron)
$paid = OrderService::handleCheckoutCompleted($session);
check('commande payée', $paid['status'] === 'paid' && $paid['email'] === 'cliente@example.com');
check('transmission en erreur sans Printful', $paid['fulfillment_status'] === 'error' && (int) $paid['fulfillment_attempts'] === 1);
check('abonnement newsletter au paiement', (bool) Database::value('SELECT id FROM newsletter_subscribers WHERE email = ?', ['cliente@example.com']));
OrderService::handleCheckoutCompleted($session);
check('webhook rejoué sans double traitement', (int) Database::value("SELECT COUNT(*) FROM order_events WHERE order_id = ? AND type = 'payment'", [$order['id']]) === 1);

echo "Commande Printful\n";
Database::query('UPDATE product_variants SET printful_variant_id = 4012 WHERE id = ?', [$hoodie['id']]);
Database::query('UPDATE product_variants SET printful_sync_variant_id = 987654 WHERE id = ?', [$poster['id']]);
$payload = OrderService::buildPrintfulPayload(OrderService::find((int) $order['id']));
$line = $payload['items'][0];
check('external_id = numéro de commande', $payload['external_id'] === $order['number']);
check('article personnalisé : variant_id + fichier', $line['variant_id'] === 4012 && str_contains($line['files'][0]['url'], '/uploads/print/') && $line['quantity'] === 3);
check('fichier d\'impression généré', is_file(PUBLIC_PATH . '/' . parse_url($line['files'][0]['url'], PHP_URL_PATH)));
check('article synchronisé : sync_variant_id', ($payload['items'][1]['sync_variant_id'] ?? null) === 987654);
check('destinataire', $payload['recipient']['country_code'] === 'FR' && $payload['recipient']['zip'] === '14000');

echo "Expédition (webhook Printful)\n";
Database::update('orders', ['status' => 'in_production', 'fulfillment_status' => 'submitted', 'printful_order_id' => 555], 'id = :id', ['id' => $order['id']]);
OrderService::handlePrintfulEvent(['type' => 'package_shipped', 'data' => [
    'order' => ['id' => 555, 'external_id' => $order['number']],
    'shipment' => ['carrier' => 'DPD', 'service' => 'Classic', 'tracking_number' => 'TRK123', 'tracking_url' => 'https://tracking.example/TRK123'],
]]);
$shipped = OrderService::find((int) $order['id']);
check('commande expédiée avec suivi', $shipped['status'] === 'shipped' && $shipped['tracking_number'] === 'TRK123');

echo "Remboursement & abandon\n";
OrderService::handleRefund(['payment_intent' => 'pi_test_123', 'amount_refunded' => $subtotal, 'refunded' => true]);
check('remboursement total', OrderService::find((int) $order['id'])['status'] === 'refunded');
$_SESSION = [];
Cart::add((int) $poster['id'], 1);
$old = OrderService::createFromCart(Cart::items());
Database::update('orders', ['created_at' => date('Y-m-d H:i:s', time() - 72 * 3600)], 'id = :id', ['id' => $old['id']]);
check('panier abandonné annulé', OrderService::cancelAbandoned() === 1 && OrderService::find((int) $old['id'])['status'] === 'canceled');

echo "Photo du cheval & portrait illustré\n";
$makeJpeg = static function (int $w, int $h): string {
    $path = tempnam(sys_get_temp_dir(), 'photo');
    $img = imagecreatetruecolor($w, $h);
    imagefill($img, 0, 0, imagecolorallocate($img, 120, 90, 60));
    imagejpeg($img, $path, 90);
    return $path;
};
try {
    Upload::customerPhoto(['error' => UPLOAD_ERR_OK, 'size' => 1000, 'tmp_name' => $makeJpeg(300, 300), 'name' => 'petite.jpg']);
    check('photo trop petite refusée', false);
} catch (RuntimeException) {
    check('photo trop petite refusée', true);
}
$photo = Upload::customerPhoto(['error' => UPLOAD_ERR_OK, 'size' => 50000, 'tmp_name' => $makeJpeg(1600, 1200), 'name' => 'tornado.jpg']);
check('photo enregistrée hors du web', Upload::customerPhotoPath($photo) !== null && !str_contains(Upload::customerPhotoPath($photo), '/public/'));
check('nom de photo malveillant refusé', Upload::customerPhotoPath('../../config/config.php') === null);

$mug = Database::one("SELECT p.*, v.id AS variant_id FROM products p JOIN product_variants v ON v.product_id = p.id WHERE p.slug = 'mug-photo-de-votre-cheval'");
$cfg = Personalizer::config($mug['personalization']);
$printFile = Personalizer::render($mug['design_file'], 'Tornado', $cfg, Upload::customerPhotoPath($photo));
$im = imagecreatefrompng(PUBLIC_PATH . '/' . $printFile);
$cx = (int) (($cfg['photo_x'] + $cfg['photo_w'] / 2) * imagesx($im));
$cy = (int) (($cfg['photo_y'] + $cfg['photo_h'] * 0.75) * imagesy($im));
$corner = imagecolorat($im, (int) ($cfg['photo_x'] * imagesx($im)) + 5, (int) ($cfg['photo_y'] * imagesy($im)) + 5);
check('photo composée dans l\'arche', ((imagecolorat($im, $cx, $cy) >> 24) & 127) === 0);
check('coins de l\'arche laissés transparents', (($corner >> 24) & 127) === 127);

$portrait = Database::one("SELECT v.id FROM product_variants v JOIN products p ON p.id = v.product_id WHERE p.slug = 'portrait-illustre-de-votre-cheval' LIMIT 1");
$_SESSION = [];
Cart::add((int) $mug['variant_id'], 1, 'Tornado', $photo);
Cart::add((int) $portrait['id'], 1, 'Tornado', $photo);
check('photo rattachée au panier de la session', Cart::ownsPhoto($photo) && !Cart::ownsPhoto('20260101-' . str_repeat('a', 24) . '.jpg'));
$order2 = OrderService::createFromCart(Cart::items());
Database::update('orders', ['stripe_session_id' => 'cs_test_456'], 'id = :id', ['id' => $order2['id']]);
$paid2 = OrderService::handleCheckoutCompleted(['id' => 'cs_test_456', 'payment_status' => 'paid', 'metadata' => ['order_id' => (string) $order2['id']],
    'amount_total' => 8480, 'customer_details' => ['email' => 'lea@example.com', 'name' => 'Léa Martin'],
    'shipping_details' => ['name' => 'Léa Martin', 'address' => ['line1' => '1 rue du Haras', 'postal_code' => '61310', 'city' => 'Le Pin-au-Haras', 'country' => 'FR']]]);
check('commande en attente du portrait', $paid2['fulfillment_status'] === 'artwork');
$portraitItem = Database::one("SELECT * FROM order_items WHERE order_id = ? AND personalization_mode = 'portrait'", [$order2['id']]);
check('photo enregistrée sur l\'article commandé', $portraitItem['photo_file'] === $photo);
Database::update('order_items', ['print_file_url' => 'https://exemple.fr/uploads/print/portrait.png'], 'id = :id', ['id' => $portraitItem['id']]);
Database::query('UPDATE product_variants SET printful_variant_id = 1320 WHERE id IN (?, ?)', [$mug['variant_id'], $portrait['id']]);
$payload2 = OrderService::buildPrintfulPayload(OrderService::find((int) $order2['id']));
check('mug photo : fichier composé envoyé', str_contains($payload2['items'][0]['files'][0]['url'] ?? '', '/uploads/print/'));
check('portrait : fichier de l\'illustratrice envoyé', ($payload2['items'][1]['files'][0]['url'] ?? '') === 'https://exemple.fr/uploads/print/portrait.png');
check('délai de création ajouté à la date estimée', delivery_window('standard', 5) !== delivery_window());

echo "Avis clients & conversion\n";
Database::update('orders', ['status' => 'shipped', 'shipped_at' => date('Y-m-d H:i:s', time() - 20 * 86400)], 'id = :id', ['id' => $order2['id']]);
check('demande d\'avis envoyée après livraison', Reviews::sendRequests() === 1 && Reviews::sendRequests() === 0);
$order2 = OrderService::find((int) $order2['id']);
check('avis accepté pour un article acheté', Reviews::submit($order2, (int) $mug['id'], 5, 'Léa M.', 'Magnifique', 'La photo de Tornado est superbe !'));
check('un seul avis par article et par commande', !Reviews::submit($order2, (int) $mug['id'], 1, 'Léa M.', '', ''));
check('avis refusé pour un article non acheté', !Reviews::submit($order2, (int) $hoodie['id'] + 999, 5, 'X', '', ''));
check('avis en attente de modération', Reviews::summary((int) $mug['id'])['count'] === 0);
Database::query("UPDATE reviews SET status = 'approved'");
$summary = Reviews::summary((int) $mug['id']);
check('note publiée après modération', $summary['count'] === 1 && $summary['avg'] === 5.0);
check('note visible sur la fiche', App\Services\Catalog::product('mug-photo-de-votre-cheval')['rating_count'] === 1);
$boost = App\Services\Catalog::shippingBoosters(4410, [(int) $mug['id']]);
check('suggestions couvrant le montant manquant', $boost && min(array_column($boost, 'price')) >= 4410 && !in_array((int) $mug['id'], array_map('intval', array_column($boost, 'id')), true));

echo "Clés en base de données\n";
$pdo = Database::pdo();
$configFile = require CONFIG_FILE;
check('config.php limité à l\'URL et à la base', array_keys($configFile) === ['app', 'db']);
check('clés de l\'installation enregistrées en base', (App\Core\Secrets::stored()['stripe.webhook_secret'] ?? '') === 'whsec_local_test');
App\Core\Secrets::set('printful.api_token', 'pf_TEST_123');
check('clé appliquée immédiatement', config('printful.api_token') === 'pf_TEST_123');
App\Core\Config::set('printful.api_token', 'valeur-du-fichier');
App\Core\Secrets::apply();
check('la base l\'emporte sur config.php', config('printful.api_token') === 'pf_TEST_123');
check('clé masquée à l\'affichage', App\Core\Secrets::mask('sk_live_abcdef1234567890') === 'sk_live_••••••••7890');
check('clés invisibles dans la page Réglages', !array_filter(array_keys(App\Core\Settings::DEFAULTS), static fn ($k) => str_starts_with($k, 'config.')));

// Migration 005 : reprise des clés d'une ancienne installation (copie de secours 1.2.1 + config.php)
$pdo->exec("DELETE FROM settings WHERE name LIKE 'config.%'");
$pdo->exec("DELETE FROM migrations WHERE name = '005_cles_en_base'");
$pdo->prepare("INSERT INTO settings (name, value) VALUES ('_config_backup', ?)")->execute([json_encode(['stripe' => ['secret_key' => 'sk_test_SAUVEGARDE'], 'printful' => ['webhook_token' => 'tok-ancien']])]);
App\Core\Config::set('printful.api_token', 'pf_DU_FICHIER');
App\Core\Migrator::run();
$stored = App\Core\Secrets::stored();
check('migration : clé reprise de la copie de secours', ($stored['stripe.secret_key'] ?? '') === 'sk_test_SAUVEGARDE');
check('migration : jeton webhook conservé', ($stored['printful.webhook_token'] ?? '') === 'tok-ancien');
check('migration : clé reprise de config.php', ($stored['printful.api_token'] ?? '') === 'pf_DU_FICHIER');
check('migration : jeton de tâche planifiée créé', ($stored['cron.token'] ?? '') !== '');
check('migration : ancienne copie supprimée', !Database::value("SELECT COUNT(*) FROM settings WHERE name = '_config_backup'"));
check('base de boutique reconnue', App\Core\Installer::isShopDatabase($pdo));
@unlink(STORAGE_PATH . '/installed.lock');
check('verrou perdu recréé automatiquement', App\Core\Installer::recover() && is_file(STORAGE_PATH . '/installed.lock'));

echo "Mise à jour par ZIP\n";
$root = sys_get_temp_dir() . '/cc-update-' . bin2hex(random_bytes(4));
foreach (['app', 'public', 'database', 'config', 'storage/fonts'] as $d) {
    mkdir("$root/$d", 0755, true);
}
file_put_contents("$root/VERSION", "1.0.0\n");
file_put_contents("$root/app/bootstrap.php", "<?php // v1");
file_put_contents("$root/config/config.php", "<?php return ['secret' => 'garder'];");
$makeZip = static function (array $files, string $prefix = 'boutique-main/') use ($root): string {
    $path = "$root/../pkg-" . bin2hex(random_bytes(4)) . '.zip';
    $zip = new ZipArchive();
    $zip->open($path, ZipArchive::CREATE);
    foreach ($files as $name => $content) {
        $zip->addFromString($name[0] === '!' ? substr($name, 1) : $prefix . $name, $content);
    }
    $zip->close();
    return $path;
};
$base = ['VERSION' => "1.2.0\n", 'app/bootstrap.php' => '<?php // v2', 'public/index.php' => '<?php echo 1;', 'database/schema.sql' => '--'];
$updater = new App\Services\Updater($root);

$refused = static function (string $zip, bool $downgrade = false) use ($updater): bool {
    try {
        $updater->install($zip, $downgrade);
        return false;
    } catch (RuntimeException) {
        return true;
    }
};
check('ZIP avec « ../ » refusé', $refused($makeZip($base + ['!../evil.php' => '<?php'])));
check('ZIP avec erreur de syntaxe refusé', $refused($makeZip($base + ['app/casse.php' => '<?php function ('])));
check('ZIP étranger refusé', $refused($makeZip(['index.html' => 'x'])));
check('rien modifié après refus', trim(file_get_contents("$root/VERSION")) === '1.0.0' && !is_file("$root/app/casse.php"));

$result = $updater->install($makeZip($base + [
    'app/nouveau.php'      => '<?php // ajouté',
    'config/config.php'    => '<?php return [];',
    'storage/database.sqlite' => 'écrasé ?',
    'storage/fonts/x.ttf'  => 'police',
]));
check('mise à jour 1.0.0 → 1.2.0', $result['from'] === '1.0.0' && $result['to'] === '1.2.0' && $updater->currentVersion() === '1.2.0');
check('nouveaux fichiers installés', is_file("$root/app/nouveau.php") && is_file("$root/storage/fonts/x.ttf"));
check('configuration et données préservées', str_contains(file_get_contents("$root/config/config.php"), 'garder') && !is_file("$root/storage/database.sqlite"));
check('sauvegarde créée', count($updater->backups()) === 1 && !$updater->inMaintenance());
check('version plus ancienne refusée', $refused($makeZip(['VERSION' => "1.1.0\n"] + $base)));

$restored = $updater->restore($result['backup']);
check('restauration de la sauvegarde', $restored['version'] === '1.0.0' && file_get_contents("$root/app/bootstrap.php") === '<?php // v1');
exec('rm -rf ' . escapeshellarg($root) . ' ' . escapeshellarg(dirname($root)) . '/pkg-*.zip');

echo "Migrations\n";
App\Core\Migrator::ensureTable();
check('aucune migration en attente après installation', App\Core\Migrator::pending() === []);
check('cinq migrations connues', count(App\Core\Migrator::files()) === 5);

echo $failures ? "\n$failures échec(s)\n" : "\nTous les tests passent.\n";
exit($failures ? 1 : 0);
