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
use App\Services\Stripe;

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
$hoodie = Database::one("SELECT v.id FROM product_variants v JOIN products p ON p.id = v.product_id WHERE p.personalizable = 1 AND p.print_placement = 'front' ORDER BY v.id LIMIT 1");
$poster = Database::one("SELECT v.id, v.price FROM product_variants v JOIN products p ON p.id = v.product_id WHERE v.price IS NOT NULL ORDER BY v.price DESC LIMIT 1");
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

echo $failures ? "\n$failures échec(s)\n" : "\nTous les tests passent.\n";
exit($failures ? 1 : 0);
