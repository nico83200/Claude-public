<?php
/**
 * Tests de bout en bout sans réseau (SQLite) :
 *   php bin/install-local.php && php tests/run.php
 * Couvre : panier, création de commande, webhook Stripe signé (idempotence),
 * construction de la commande Printful, webhook Printful d'expédition, portrait IA (faux serveur d'API).
 */
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Config;
use App\Core\Database;
use App\Services\AiImage;
use App\Services\AiPortrait;
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

echo "Bibliothèque d'illustrations\n";
$library = App\Services\DesignLibrary::all();
check('10 illustrations dont 4 personnalisables', count($library) === 10 && count(array_filter($library, static fn ($d) => $d['personalizable'])) === 4);
$installed = App\Services\DesignLibrary::install('06-mon-cheval-ma-fierte', 'creme');
check('visuel copié dans storage/designs', is_file(STORAGE_PATH . '/designs/' . $installed['file']));
check('réglage du nom en couleur crème', ($installed['preset']['color'] ?? '') === '#f9f7f3' && $installed['preset']['font'] === 'script');
$out = Personalizer::render($installed['file'], 'Tornado', Personalizer::config(json_encode($installed['preset'])));
check('nom imprimé sur le visuel de la bibliothèque', is_file(PUBLIC_PATH . '/' . $out));
try {
    App\Services\DesignLibrary::install('../../config', 'creme');
    check('visuel inconnu refusé', false);
} catch (RuntimeException) {
    check('visuel inconnu refusé', true);
}

echo "Portrait IA « Encre & Poudre »\n";
check('clés du service d\'IA gérées en base', (App\Core\Secrets::KEYS['ai.openai_key'][2] ?? '') === 'secret' && (App\Core\Secrets::KEYS['ai.replicate_token'][2] ?? '') === 'secret');
Config::set('ai.provider', 'openai');
Config::set('ai.openai_key', '');
check('sans clé : mode démonstration', AiImage::provider() === 'demo');
Config::set('ai.provider', 'demo');
Config::set('ai.daily_per_visitor', 2);
$aiFile = static fn () => ['error' => UPLOAD_ERR_OK, 'size' => 60000, 'tmp_name' => $makeJpeg(1600, 1200), 'name' => 'cheval.jpg'];
$demo = AiPortrait::run(AiPortrait::create($aiFile()));
check('portrait de démonstration créé', $demo['status'] === 'ready' && is_file(AiPortrait::DIR . '/' . $demo['result_file']));
$preview = AiPortrait::previewPath($demo);
check('aperçu réduit et filigrané', $preview !== null && getimagesize($preview)[0] === 720);
check('portrait rattaché à la session', AiPortrait::owns($demo['token']) && !AiPortrait::owns(str_repeat('a', 32)));
check('jeton malveillant refusé', AiPortrait::find('../../config') === null);

// Faux serveur OpenAI / Replicate
$port = 18765 + random_int(0, 500);
$aiLog = sys_get_temp_dir() . '/fake-ai-' . getmypid() . '.json';
@unlink($aiLog);
$server = proc_open([PHP_BINARY, '-S', "127.0.0.1:$port", __DIR__ . '/fake-ai-server.php'], [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes, null, ['FAKE_AI_LOG' => $aiLog] + getenv());
for ($i = 0; $i < 30 && !@fsockopen('127.0.0.1', $port); $i++) {
    usleep(100000);
}
Config::set('ai.openai_base', "http://127.0.0.1:$port/openai");
Config::set('ai.replicate_base', "http://127.0.0.1:$port/replicate");
Config::set('ai.provider', 'openai');
Config::set('ai.openai_key', 'sk-test');
$openai = AiPortrait::run(AiPortrait::create($aiFile()));
$sent = json_decode((string) @file_get_contents($aiLog), true)[0] ?? [];
check('OpenAI : portrait reçu', $openai['status'] === 'ready' && $openai['provider'] === 'openai', (string) $openai['error']);
check('OpenAI : photo JPEG, style, format et fidélité envoyés', ($sent['file']['type'] ?? '') === 'image/jpeg' && str_contains($sent['fields']['prompt'] ?? '', 'Encre & Poudre')
    && ($sent['fields']['size'] ?? '') === '1024x1536' && ($sent['fields']['input_fidelity'] ?? '') === 'high' && ($sent['fields']['model'] ?? '') === 'gpt-image-1.5');
check('limite quotidienne par visiteuse atteinte', AiPortrait::remaining() === 0);
try {
    AiPortrait::create($aiFile());
    check('création refusée au-delà de la limite', false);
} catch (RuntimeException) {
    check('création refusée au-delà de la limite', true);
}

Config::set('ai.daily_per_visitor', 6);
Config::set('ai.provider', 'replicate');
Config::set('ai.replicate_token', 'r8_test');
Config::set('ai.replicate_model', 'pas un modèle');
$failed = AiPortrait::run(AiPortrait::create($aiFile()));
check('échec du service : message clair, création non décomptée', $failed['status'] === 'failed' && AiPortrait::customerError($failed) !== '' && AiPortrait::remaining() === 4);
Config::set('ai.replicate_model', 'black-forest-labs/flux-kontext-pro');
Config::set('ai.upscale', true);
$rep = AiPortrait::run(AiPortrait::create($aiFile()));
check('Replicate : prédiction lancée puis suivie', $rep['status'] === 'processing' && $rep['prediction_id'] === 'pred1');
$rep = AiPortrait::refresh(AiPortrait::refresh($rep));
check('Replicate : portrait téléchargé', $rep['status'] === 'ready' && is_file(AiPortrait::DIR . '/' . $rep['result_file']));
check('agrandissement lancé en arrière-plan', $rep['master_prediction_id'] === 'up1' && !$rep['master_file']);
$rep = AiPortrait::refresh(AiPortrait::refresh($rep));
check('version agrandie retenue pour l\'impression', (string) $rep['master_file'] !== '' && str_ends_with((string) AiPortrait::artworkPath($rep), '-hd.png'));
$creates = array_values(array_filter(json_decode((string) file_get_contents($aiLog), true), static fn ($c) => $c['route'] === 'replicate.create'));
check('Replicate : modèle, format 3:4, photo et attente synchrone', ($creates[0]['model'] ?? '') === 'black-forest-labs/flux-kontext-pro' && ($creates[0]['input']['aspect_ratio'] ?? '') === '3:4'
    && str_starts_with((string) ($creates[0]['input']['input_image'] ?? ''), 'data:image/jpeg;base64,') && ($creates[0]['prefer'] ?? '') === 'wait=50' && ($creates[0]['auth'] ?? '') === 'Bearer r8_test');
App\Core\Settings::set('ai_prompt', 'Turn this horse into a charcoal sketch. No text.');
$_SESSION['admin_id'] = 1;
$adminRemaining = AiPortrait::remaining();
$custom = AiPortrait::run(AiPortrait::create($aiFile()));
Database::update('ai_portraits', ['status' => 'failed', 'error' => 'test'], 'id = :id', ['id' => $custom['id']]);
unset($_SESSION['admin_id']);
$creates = array_values(array_filter(json_decode((string) file_get_contents($aiLog), true), static fn ($c) => $c['route'] === 'replicate.create'));
check('prompt personnalisé des Réglages envoyé à l\'IA', (end($creates)['input']['prompt'] ?? '') === 'Turn this horse into a charcoal sketch. No text.');
check('administratrice : essais non limités à 3 par jour', $adminRemaining > 3);
App\Core\Settings::set('ai_prompt', '');
check('prompt d\'origine si le réglage est vide', AiPortrait::prompt() === AiPortrait::STYLE_PROMPT);
echo "Comptes clientes\n";
$mailLog = STORAGE_PATH . '/logs/mail-' . date('Y-m') . '.log';
$lastLink = static function (string $path) use ($mailLog): ?string {
    preg_match_all('#' . preg_quote($path, '#') . '/([a-f0-9]{48})#', (string) @file_get_contents($mailLog), $m);
    return $m[1] ? end($m[1]) : null;
};
$paidOrder = Database::one("SELECT * FROM orders WHERE email = 'cliente@example.com' AND status <> 'pending' ORDER BY id LIMIT 1");
// Une inconnue s'inscrit avec l'adresse d'une cliente : les commandes ne sont pas visibles avant confirmation
$acc = App\Services\Account::register('Cliente@Example.com', 'motdepasse1', 'Léa Martin');
check('compte créé (adresse normalisée, mot de passe haché)', $acc['email'] === 'cliente@example.com' && password_verify('motdepasse1', $acc['password_hash']) && !$acc['email_verified_at']);
check('commandes cachées avant confirmation', App\Services\Account::orders($acc) === []);
$refused = false;
try { App\Services\Account::register('cliente@example.com', 'autremotdepasse'); } catch (RuntimeException) { $refused = true; }
check('doublon refusé', $refused);
$short = false;
try { App\Services\Account::register('court@example.com', 'abc'); } catch (RuntimeException) { $short = true; }
check('mot de passe trop court refusé', $short);
check('mauvais mot de passe refusé', App\Services\Account::attempt('cliente@example.com', 'faux', '10.9.9.9') === null);
check('connexion acceptée', App\Services\Account::attempt('cliente@example.com', 'motdepasse1', '10.9.9.9') !== null);
$verifyToken = $lastLink('compte/confirmer');
$verified = $verifyToken ? App\Services\Account::verify($verifyToken) : null;
check('lien de confirmation envoyé et valide', $verified && $verified['email_verified_at']);
check('commandes de l\'adresse visibles après confirmation', in_array($paidOrder['number'], array_column(App\Services\Account::orders($verified), 'number'), true));
check('lien de confirmation à usage unique', App\Services\Account::verify((string) $verifyToken) === null);
App\Services\Account::sendReset('cliente@example.com');
App\Services\Account::sendReset('inconnue@example.com');
$resetToken = $lastLink('compte/nouveau-mot-de-passe');
App\Services\Account::resetPassword((string) $resetToken, 'nouveau-secret');
check('mot de passe réinitialisé par e-mail', App\Services\Account::attempt('cliente@example.com', 'nouveau-secret', '10.9.9.9') !== null);
$reused = false;
try { App\Services\Account::resetPassword((string) $resetToken, 'encore-un-autre'); } catch (RuntimeException) { $reused = true; }
check('lien de réinitialisation à usage unique', $reused);
for ($i = 0; $i < 8; $i++) { App\Services\Account::attempt('cliente@example.com', 'faux', '10.8.8.8'); }
check('connexion bloquée après trop d\'essais', App\Services\Account::isLocked('10.8.8.8') && !App\Core\Auth::isLocked('10.8.8.8'));
// Compte créé juste après paiement : adresse prouvée, commande rattachée tout de suite
Database::update('orders', ['email' => 'acheteuse@example.com', 'customer_id' => null], 'id = :id', ['id' => $paidOrder['id']]);
$buyer = App\Services\Account::register('acheteuse@example.com', 'motdepasse2', 'Zoé', true);
check('compte après paiement : commande rattachée', (int) Database::value('SELECT customer_id FROM orders WHERE id = ?', [$paidOrder['id']]) === (int) $buyer['id'] && count(App\Services\Account::orders($buyer)) >= 1);
App\Services\Account::login($buyer);
ob_start();
(new App\Controllers\AccountController())->index();
$accountPage = (string) ob_get_clean();
check('page « Mon compte » : commandes et bouton d\'en-tête', str_contains($accountPage, 'Bonjour <em>Zoé</em>') && str_contains($accountPage, e($paidOrder['number'])) && str_contains($accountPage, 'account-btn is-logged'));
App\Services\Account::logout();
ob_start();
(new App\Controllers\ShopController())->portraitLanding();
check('visuel « Mon compte » dans le menu', str_contains((string) ob_get_clean(), 'Mon compte — se connecter ou créer un compte'));
App\Services\Account::delete($buyer);
check('suppression du compte (commandes conservées)', App\Services\Account::findByEmail('acheteuse@example.com') === null && OrderService::find((int) $paidOrder['id'])['customer_id'] === null);
Database::update('orders', ['email' => 'cliente@example.com'], 'id = :id', ['id' => $paidOrder['id']]);

echo "Édition rapide et images de l'accueil\n";
$_SESSION['admin_id'] = (int) Database::value('SELECT id FROM admins ORDER BY id LIMIT 1');
$tshirt = App\Services\Catalog::product('t-shirt-criniere-club');
$_SERVER['REQUEST_URI'] = '/produit/t-shirt-criniere-club';
ob_start();
(new App\Controllers\ShopController())->product('t-shirt-criniere-club');
$adminProductPage = (string) ob_get_clean();
check('bouton « Modifier l\'article » pour l\'admin', str_contains($adminProductPage, 'Modifier l&#039;article') && str_contains($adminProductPage, 'admin/produits/' . $tshirt['id']));
$big = $makeJpeg(3000, 2400);
$stored = App\Services\HomeImages::store(['error' => UPLOAD_ERR_OK, 'size' => filesize($big), 'tmp_name' => $big, 'name' => 'accueil.jpg']);
App\Core\Settings::set('home_img_hero', $stored);
App\Core\Settings::set('home_alt_hero', 'Ma jument au pré');
$_SERVER['REQUEST_URI'] = '/';
ob_start();
(new App\Controllers\HomeController())->index();
$homeAdmin = (string) ob_get_clean();
check('image d\'accueil remplacée et réduite à 2000 px', str_contains($homeAdmin, thumb($stored, 800)) && str_contains($homeAdmin, 'alt="Ma jument au pré"') && getimagesize(PUBLIC_PATH . '/' . $stored)[0] === 2000);
check('bouton « Modifier la page d\'accueil »', str_contains($homeAdmin, 'Modifier la page d&#039;accueil'));
unset($_SESSION['admin_id']);
ob_start();
(new App\Controllers\HomeController())->index();
check('barre d\'édition invisible pour les visiteuses', !str_contains((string) ob_get_clean(), 'admin-bar'));
App\Services\HomeImages::discard($stored);
App\Services\HomeImages::discard('assets/img/hero.jpg');
App\Core\Settings::set('home_img_hero', '');
check('retour à l\'image d\'origine (fichier supprimé, thème intact)', App\Services\HomeImages::src('hero') === 'assets/img/hero.jpg' && !is_file(PUBLIC_PATH . '/' . $stored) && is_file(PUBLIC_PATH . '/assets/img/hero.jpg'));
$_SERVER['REQUEST_URI'] = '/';

echo "Descriptions mises en forme\n";
$plain = rich_text("Un sweat **tout doux**.\nLavable à 30°.\n\n- coton bio\n- brodé\n\n## Entretien");
check('texte brut : retours à la ligne, gras, liste, intertitre', str_contains($plain, '<strong>tout doux</strong>.<br>Lavable') && str_contains($plain, '<ul><li>coton bio</li><li>brodé</li></ul>') && str_contains($plain, '<h3>Entretien</h3>'));
check('texte brut échappé', rich_text('<3 & "x"') === '<p>&lt;3 &amp; &quot;x&quot;</p>');
$dirty = rich_text('<p style="color:red" class="MsoNormal" onclick="x()">Texte <b>gras</b><script>alert(1)</script> <a href="javascript:alert(1)">lien</a></p><div>bloc</div><p class="lead">Intro</p>');
check('HTML nettoyé (styles, scripts, liens dangereux)', $dirty === '<p>Texte <b>gras</b> <a>lien</a></p><p>bloc</p><p class="lead">Intro</p>');
check('pages d\'origine intactes', str_contains(rich_text('<table><tr><td colspan="2">A</td></tr></table>'), '<td colspan="2">A</td>'));

echo "Mot de passe administrateur oublié\n";
$adminRow = Database::one('SELECT * FROM admins ORDER BY id LIMIT 1');
App\Core\Auth::sendReset('inconnue@example.com', '10.7.7.7');
App\Core\Auth::sendReset(strtoupper($adminRow['email']), '10.7.7.7');
$adminToken = $lastLink('admin/nouveau-mot-de-passe');
check('lien de réinitialisation admin envoyé', $adminToken !== null && App\Core\Auth::resetAdmin($adminToken) !== null);
$tooShort = false;
try { App\Core\Auth::resetPassword((string) $adminToken, 'court'); } catch (RuntimeException) { $tooShort = true; }
check('mot de passe admin trop court refusé', $tooShort);
App\Core\Auth::resetPassword((string) $adminToken, 'nouveau-mdp-admin');
check('nouveau mot de passe admin actif', App\Core\Auth::attempt($adminRow['email'], 'nouveau-mdp-admin', '10.7.7.8'));
check('lien admin à usage unique', App\Core\Auth::resetAdmin((string) $adminToken) === null);
Database::update('admins', ['password_hash' => $adminRow['password_hash']], 'id = :id', ['id' => $adminRow['id']]);
unset($_SESSION['admin_id']);

echo "Miniatures d'images\n";
$thumbUrl = thumb('assets/img/products/club-hoodie.jpg', 380);
check('URL de miniature WebP à la largeur utile', (bool) preg_match('#/uploads/thumbs/400/[a-f0-9]{20}\.webp$#', $thumbUrl));
preg_match('#/(\d+)/([a-f0-9]{20})\.webp$#', $thumbUrl, $tm);
$thumbPath = App\Services\Thumbs::generate((int) $tm[1], $tm[2]);
check('miniature fabriquée (400 px, WebP, bien plus légère)', $thumbPath && getimagesize($thumbPath)[0] === 400 && getimagesize($thumbPath)['mime'] === 'image/webp'
    && filesize($thumbPath) < filesize(PUBLIC_PATH . '/assets/img/products/club-hoodie.jpg') / 3);
check('empreinte inconnue refusée', App\Services\Thumbs::generate(400, str_repeat('a', 20)) === null && App\Services\Thumbs::generate(333, $tm[2]) === null);
check('SVG laissé tel quel', thumb('assets/img/brand/logo.svg', 400) === image_url('assets/img/brand/logo.svg'));
check('srcset pour le navigateur', str_contains(img_attrs('assets/img/products/club-hoodie.jpg', [400, 800], '50vw'), 'srcset="') && str_contains(img_attrs('assets/img/products/club-hoodie.jpg', [400, 800], '50vw'), ' 800w'));
@unlink($thumbPath);

echo "Commande cadeau et relance de panier\n";
$_SESSION = [];
Cart::add((int) $hoodie['id'], 1, 'Pépite');
$giftOrder = OrderService::createFromCart(Cart::items(), true);
Database::update('orders', ['is_gift' => 1, 'gift_to' => 'Léa', 'gift_message' => 'Joyeux anniversaire !', 'shipping_address' => json_encode(['name' => 'Léa', 'line1' => '1 rue des Écuries', 'city' => 'Lyon', 'postal_code' => '69001', 'country' => 'FR'])], 'id = :id', ['id' => $giftOrder['id']]);
$giftPayload = OrderService::buildPrintfulPayload(OrderService::find((int) $giftOrder['id']));
check('message cadeau transmis à Printful', ($giftPayload['gift']['subject'] ?? '') === 'Pour Léa' && ($giftPayload['gift']['message'] ?? '') === 'Joyeux anniversaire !');
check('pas de bloc cadeau sinon', !isset(OrderService::buildPrintfulPayload(OrderService::find((int) $order['id']))['gift']));
OrderService::handleSessionExpired(['id' => 'cs_relance', 'metadata' => ['order_id' => (string) $giftOrder['id']], 'customer_details' => ['email' => 'Relance@Example.com']]);
$relance = OrderService::find((int) $giftOrder['id']);
check('panier expiré : e-mail récupéré et relance envoyée', $relance['status'] === 'canceled' && $relance['email'] === 'relance@example.com' && $relance['recovery_sent_at'] !== null
    && str_contains((string) file_get_contents(STORAGE_PATH . '/logs/mail-' . date('Y-m') . '.log'), 'Votre panier vous attend'));
check('une seule relance', OrderService::sendRecovery((int) $giftOrder['id']) === false);
$recoveryUrl = OrderService::recoveryUrl($relance);
parse_str((string) parse_url($recoveryUrl, PHP_URL_QUERY), $rq);
check('lien de reprise signé', OrderService::checkRecoveryToken($relance, $rq['t']) && !OrderService::checkRecoveryToken($relance, str_repeat('0', 32)));
$_SESSION = [];
Cart::add((int) $hoodie['id'], 1, 'Sans consentement');
$noConsent = OrderService::createFromCart(Cart::items(), false);
Database::update('orders', ['email' => 'jamais-inscrite@example.com', 'status' => 'canceled'], 'id = :id', ['id' => $noConsent['id']]);
check('pas de relance sans consentement', OrderService::sendRecovery((int) $noConsent['id']) === false);
$_SESSION = [];

echo "Code de bienvenue\n";
Config::set('stripe.api_base', "http://127.0.0.1:$port/stripe");
$oldKey = config('stripe.secret_key');
Config::set('stripe.secret_key', 'sk_test_fake');

echo "Annulation et suppression de commande\n";
$toCancel = Database::one("SELECT * FROM orders WHERE paid_at IS NOT NULL AND status IN ('paid', 'in_production') AND printful_order_id IS NULL AND fulfillment_status <> 'shipped' ORDER BY id LIMIT 1");
if (!$toCancel) {
    $toCancel = Database::one("SELECT * FROM orders WHERE paid_at IS NOT NULL ORDER BY id LIMIT 1");
    Database::update('orders', ['status' => 'paid', 'fulfillment_status' => 'pending', 'printful_order_id' => null, 'stripe_payment_intent' => 'pi_test_cancel'], 'id = :id', ['id' => $toCancel['id']]);
} elseif (!$toCancel['stripe_payment_intent']) {
    Database::update('orders', ['stripe_payment_intent' => 'pi_test_cancel'], 'id = :id', ['id' => $toCancel['id']]);
}
file_put_contents($aiLog, '[]');
$canceled = OrderService::cancel((int) $toCancel['id'], true, true, 'à votre demande');
$refunds = array_values(array_filter(json_decode((string) file_get_contents($aiLog), true) ?: [], fn ($c) => $c['route'] === 'stripe.refund'));
check('commande payée annulée et remboursée', $canceled['status'] === 'refunded' && count($refunds) === 1 && $refunds[0]['fields']['payment_intent'] !== '');
check('annulation tracée dans l\'historique', str_contains(implode(' ', array_column(OrderService::events((int) $toCancel['id']), 'message')), 'Motif : à votre demande'));
check('e-mail d\'annulation envoyé', str_contains((string) file_get_contents(STORAGE_PATH . '/logs/mail-' . date('Y-m') . '.log'), 'Annulation de votre commande ' . $toCancel['number']));
$refused = false;
try { OrderService::delete((int) $toCancel['id']); } catch (RuntimeException) { $refused = true; }
check('commande payée non supprimable', $refused && OrderService::find((int) $toCancel['id']) !== null);
$cancelAgain = false;
try { OrderService::cancel((int) $toCancel['id'], true, false); } catch (RuntimeException) { $cancelAgain = true; }
check('pas de double annulation', $cancelAgain || OrderService::find((int) $toCancel['id'])['status'] === 'refunded');
$abandonedId = Database::insert('orders', ['number' => 'TEST-ABANDON', 'access_token' => 'x', 'status' => 'pending', 'subtotal' => 1000, 'total' => 1000, 'created_at' => date('Y-m-d H:i:s', time() - 9 * 86400), 'updated_at' => now()]);
Database::insert('order_items', ['order_id' => $abandonedId, 'product_name' => 'Test', 'variant_name' => 'Test', 'quantity' => 1, 'unit_price' => 1000]);
check('paniers abandonnés de plus de 7 jours supprimés', OrderService::purgeAbandoned(7) >= 1 && OrderService::find($abandonedId) === null && !Database::value('SELECT COUNT(*) FROM order_items WHERE order_id = ?', [$abandonedId]));

file_put_contents($aiLog, '[]');
App\Services\Newsletter::subscribe('club1@example.com', 'popup');
$code1 = App\Services\WelcomeOffer::codeFor('club1@example.com');
App\Services\Newsletter::subscribe('club2@example.com', 'popup');
$code2 = App\Services\WelcomeOffer::codeFor('club2@example.com');
$calls = json_decode((string) file_get_contents($aiLog), true) ?: [];
$coupons = array_values(array_filter($calls, fn ($c) => $c['route'] === 'stripe.coupon'));
$promos = array_values(array_filter($calls, fn ($c) => $c['route'] === 'stripe.promo'));
check('code personnel CLUB-XXXXXX', (bool) preg_match('/^CLUB-[A-Z0-9]{6}$/', (string) $code1) && $code1 !== $code2);
check('coupon Stripe de 5 € créé une seule fois', count($coupons) === 1 && $coupons[0]['fields']['amount_off'] === '500' && $coupons[0]['fields']['duration'] === 'once');
check('code limité à une utilisation', count($promos) === 2 && $promos[0]['fields']['max_redemptions'] === '1');
check('même code redonné à une inscrite', App\Services\WelcomeOffer::codeFor('club1@example.com') === $code1);
check('libellé « 5 € offerts »', App\Services\WelcomeOffer::label() === "5\u{00A0}€ offerts");
Config::set('stripe.secret_key', $oldKey);

proc_terminate($server);
@unlink($aiLog);
check('diagnostic admin : jeton refusé', str_contains(AiPortrait::adminDiagnostic(['error' => 'Replicate : HTTP 401 — Unauthenticated', 'provider' => 'replicate']), 'jeton'));
check('diagnostic admin : crédit manquant', str_contains(AiPortrait::adminDiagnostic(['error' => 'Replicate : HTTP 402 — Insufficient credit', 'provider' => 'replicate']), 'Crédit'));
$_SESSION['admin_id'] = 1;
check('cause visible pour l\'administratrice', str_contains((string) AiPortrait::publicState($failed)['admin'], 'Modèle introuvable'));
unset($_SESSION['admin_id']);
check('cause masquée aux clientes', AiPortrait::publicState($failed)['admin'] === null);
$stale = AiPortrait::create($aiFile());
Database::update('ai_portraits', ['status' => 'processing', 'updated_at' => date('Y-m-d H:i:s', time() - 7200)], 'id = :id', ['id' => $stale['id']]);
check('création bloquée trop longtemps passée en échec', AiPortrait::refresh(AiPortrait::find($stale['token']))['status'] === 'failed');

$refusedYear = static function (string $year): bool {
    try {
        AiPortrait::sanitizeYear($year);
        return false;
    } catch (InvalidArgumentException) {
        return true;
    }
};
check('année de naissance contrôlée', AiPortrait::sanitizeYear('2014') === '2014' && AiPortrait::sanitizeYear('') === '' && $refusedYear('1850') && $refusedYear('3000') && $refusedYear('14'));
$frames = Database::all("SELECT v.id, v.size, v.printful_variant_id FROM product_variants v JOIN products p ON p.id = v.product_id WHERE p.slug = 'portrait-aquarelle-de-votre-cheval-encadre' ORDER BY v.id");
$frame = $frames[0] ?? null;
check('produit « portrait aquarelle encadré » installé, variantes Printful renseignées', count($frames) === 5 && (int) $frame['printful_variant_id'] === 9357);
Cart::clear();
Cart::add((int) $frame['id'], 1, 'Tornado', null, ['portrait' => $rep['token'], 'year' => '2014']);
$line3 = Cart::items()[0];
check('panier : portrait et année', $line3['portrait'] === $rep['token'] && $line3['year'] === '2014' && $line3['mode'] === 'ai_portrait');
Cart::add((int) $frames[1]['id'], 1, 'Tornado', null, ['portrait' => $rep['token'], 'year' => '2014']);
$order3 = OrderService::createFromCart(Cart::items());
$item3 = Database::one('SELECT * FROM order_items WHERE order_id = ?', [$order3['id']]);
check('commande : portrait, année et photo d\'origine enregistrés', $item3['ai_portrait'] === $rep['token'] && $item3['personalization_year'] === '2014' && $item3['photo_file'] === $rep['photo_file']);
Database::update('orders', ['status' => 'paid', 'email' => 'zoe@example.com', 'shipping_address' => json_encode(['name' => 'Zoé', 'line1' => '2 allée des Pins', 'postal_code' => '33000', 'city' => 'Bordeaux', 'country' => 'FR'])], 'id = :id', ['id' => $order3['id']]);
$payload3 = OrderService::buildPrintfulPayload(OrderService::find((int) $order3['id']));
$file3 = $payload3['items'][0]['files'][0] ?? [];
$path3 = PUBLIC_PATH . '/' . ltrim((string) parse_url((string) ($file3['url'] ?? ''), PHP_URL_PATH), '/');
check('affiche encadrée : fichier composé 3600 × 4800 (emplacement default)', ($file3['type'] ?? '') === 'default' && str_contains((string) ($file3['url'] ?? ''), 'portrait-ia-') && is_file($path3) && getimagesize($path3)[0] === 3600 && getimagesize($path3)[1] === 4800);
$im3 = imagecreatefrompng($path3);
$inked = false;
for ($x = 900; $x < 2700 && !$inked; $x += 3) {
    for ($y = (int) (0.79 * 4800); $y < (int) (0.84 * 4800) && !$inked; $y += 3) {
        $inked = ((imagecolorat($im3, $x, $y) >> 16) & 0xFF) < 140;
    }
}
check('nom imprimé sous le portrait', $inked);
$path45 = PUBLIC_PATH . '/' . ltrim((string) parse_url((string) ($payload3['items'][1]['files'][0]['url'] ?? ''), PHP_URL_PATH), '/');
check('format 45 × 61 cm : feuille aux bonnes proportions', $frames[1]['size'] === '45 × 61 cm' && is_file($path45) && getimagesize($path45)[1] === 4880 && ($payload3['items'][1]['variant_id'] ?? 0) === 19646);
Database::update('ai_portraits', ['created_at' => date('Y-m-d H:i:s', time() - 40 * 86400)], 'token IN (:a, :b)', ['a' => $demo['token'], 'b' => $rep['token']]);
$oldPhoto = Upload::PHOTO_DIR . '/20200101-' . str_repeat('b', 24) . '.jpg';
copy($makeJpeg(600, 600), $oldPhoto);
check('portrait non commandé supprimé après 30 jours', AiPortrait::cleanup() === 1 && AiPortrait::find($demo['token']) === null && !is_file(AiPortrait::DIR . '/' . $demo['result_file']));
check('portrait commandé conservé', AiPortrait::find($rep['token']) !== null);
check('photo cliente expirée supprimée', Upload::cleanupCustomerPhotos() >= 1 && !is_file($oldPhoto) && Upload::customerPhotoPath($rep['photo_file']) !== null);

echo "Page dédiée au portrait aquarelle\n";
check('page proposée (produit portrait IA en ligne)', AiPortrait::landingAvailable() && (App\Controllers\ShopController::aiPortraitProduct()['slug'] ?? '') === 'portrait-aquarelle-de-votre-cheval-encadre');
ob_start();
(new App\Controllers\ShopController())->portraitLanding();
$landing = (string) ob_get_clean();
check('page affichée avec créateur intégré', str_contains($landing, 'data-ai-box') && str_contains($landing, 'id="creer"') && str_contains($landing, 'Encre &amp; Poudre'));
check('exemples, formats et prix réels', substr_count($landing, 'class="lp-example"') === 6 && str_contains($landing, '45 × 61 cm') && str_contains($landing, money(11900)));
check('FAQ transmise à Google (FAQPage)', str_contains($landing, '"@type":"FAQPage"') && str_contains($landing, '"@type":"Product"'));
check('une seule balise h1', substr_count($landing, '<h1') === 1);
foreach (['exemple-alezan', 'exemple-bai', 'exemple-gris', 'exemple-palomino', 'exemple-noir', 'exemple-clair', 'affiche-tornado'] as $visual) {
    if (!is_file(PUBLIC_PATH . '/assets/img/portrait-ia/' . $visual . '.jpg')) {
        check('visuel ' . $visual, false);
    }
}

echo "Migration 007 (Replicate par défaut)\n";
App\Core\Settings::set('config.ai.provider', 'demo');
App\Core\Settings::set('config.ai.upscale', '0');
App\Core\Settings::set('announcement', 'Livraison offerte dès 69 € d\'achat ♡|Personnalisation offerte : son nom, sa photo, son portrait|Chaque pièce est imprimée rien que pour vous');
(require BASE_PATH . '/database/migrations/007_replicate_par_defaut.php')($pdo, 'sqlite');
check('service passé à Replicate, agrandissement activé', App\Core\Secrets::stored()['ai.provider'] === 'replicate' && App\Core\Secrets::stored()['ai.upscale'] === '1');
check('annonce du portrait aquarelle', str_starts_with((string) setting('announcement'), 'Nouveau : son portrait aquarelle'));
App\Core\Settings::set('announcement', 'Mon annonce');
(require BASE_PATH . '/database/migrations/007_replicate_par_defaut.php')($pdo, 'sqlite');
check('annonce personnalisée conservée', setting('announcement') === 'Mon annonce');

echo "Migration 008 (boutique restée sur l'ancien catalogue)\n";
$aiProduct = (int) Database::value("SELECT id FROM products WHERE slug = 'portrait-aquarelle-de-votre-cheval-encadre'");
Database::query('DELETE FROM product_variants WHERE product_id = ?', [$aiProduct]);
Database::query('DELETE FROM products WHERE id = ?', [$aiProduct]);
Database::query("UPDATE categories SET slug = 'ancienne-collection' WHERE slug = 'mon-cheval-ma-fierte'");
check('page indisponible sans le produit', !App\Controllers\ShopController::aiPortraitProduct());
(require BASE_PATH . '/database/migrations/008_portrait_ia_produit.php')($pdo, 'sqlite');
$created = App\Controllers\ShopController::aiPortraitProduct();
check('collection « Mon cheval, ma fierté » recréée et produit installé', $created && $created['category_slug'] === 'mon-cheval-ma-fierte' && count(App\Services\Catalog::variants((int) $created['id'])) === 5);
(require BASE_PATH . '/database/migrations/008_portrait_ia_produit.php')($pdo, 'sqlite');
check('pas de doublon si la migration est rejouée', (int) Database::value("SELECT COUNT(*) FROM products WHERE personalization LIKE ?", ['%"mode":"ai_portrait"%']) === 1);

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
check('douze migrations connues', count(App\Core\Migrator::files()) === 12);

echo $failures ? "\n$failures échec(s)\n" : "\nTous les tests passent.\n";
exit($failures ? 1 : 0);
