<?php
/**
 * Faux serveur OpenAI / Replicate pour tests/run.php (lancé avec php -S 127.0.0.1:PORT).
 * Vérifie la forme des requêtes, consigne ce qu'il reçoit dans FAKE_AI_LOG et renvoie des images factices.
 */
declare(strict_types=1);

$log = getenv('FAKE_AI_LOG') ?: sys_get_temp_dir() . '/fake-ai.json';
$path = (string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];
$auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$base = 'http://' . $_SERVER['HTTP_HOST'];

$record = static function (array $entry) use ($log): void {
    $all = is_file($log) ? (json_decode((string) file_get_contents($log), true) ?: []) : [];
    $all[] = $entry;
    file_put_contents($log, json_encode($all));
};
$json = static function (array $data, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
};
$png = static function (int $w, int $h): string {
    $img = imagecreatetruecolor($w, $h);
    imagefill($img, 0, 0, imagecolorallocate($img, 249, 247, 243));
    imagefilledellipse($img, (int) ($w / 2), (int) ($h / 2), (int) ($w / 2), (int) ($h / 1.6), imagecolorallocate($img, 190, 138, 112));
    ob_start();
    imagepng($img);
    return (string) ob_get_clean();
};
$state = sys_get_temp_dir() . '/fake-ai-polls';

if ($method === 'POST' && $path === '/openai/images/edits') {
    $record(['route' => 'openai.edit', 'auth' => $auth, 'fields' => $_POST, 'file' => isset($_FILES['image']) && !is_array($_FILES['image']['tmp_name']) ? ['size' => $_FILES['image']['size'], 'type' => mime_content_type($_FILES['image']['tmp_name'])] : null,
        'files' => isset($_FILES['image']) && is_array($_FILES['image']['tmp_name']) ? array_map('mime_content_type', $_FILES['image']['tmp_name']) : []]);
    if (!str_starts_with($auth, 'Bearer sk-') || empty($_FILES['image']) || ($_POST['prompt'] ?? '') === '') {
        $json(['error' => ['message' => 'invalid request']], 400);
    }
    $json(['data' => [['b64_json' => base64_encode($png(1024, 1536))]]]);
}
if ($method === 'GET' && str_starts_with($path, '/openai/models/')) {
    $json(['id' => substr($path, 15)]);
}
if ($method === 'POST' && preg_match('#^/replicate/models/([^/]+/[^/]+)/predictions$#', $path, $m)) {
    $body = json_decode((string) file_get_contents('php://input'), true);
    $record(['route' => 'replicate.create', 'model' => $m[1], 'auth' => $auth, 'prefer' => $_SERVER['HTTP_PREFER'] ?? null, 'input' => array_map(static fn ($v) => is_string($v) && strlen($v) > 120 ? substr($v, 0, 40) . '…(' . strlen($v) . ')' : $v, $body['input'] ?? [])]);
    $id = $m[1] === 'black-forest-labs/flux-kontext-pro' ? 'pred1' : 'up1';
    @unlink("$state-$id");
    $json(['id' => $id, 'status' => 'starting', 'output' => null, 'error' => null], 201);
}
if ($method === 'GET' && preg_match('#^/replicate/predictions/(\w+)$#', $path, $m)) {
    $polls = (int) @file_get_contents("$state-{$m[1]}") + 1;
    file_put_contents("$state-{$m[1]}", (string) $polls);
    if ($polls < 2) {
        $json(['id' => $m[1], 'status' => 'processing', 'output' => null, 'error' => null]);
    }
    $json(['id' => $m[1], 'status' => 'succeeded', 'output' => "$base/replicate/files/{$m[1]}.png", 'error' => null]);
}
if ($method === 'GET' && preg_match('#^/replicate/files/(\w+)\.png$#', $path, $m)) {
    header('Content-Type: image/png');
    echo $m[1] === 'up1' ? $png(3520, 4704) : $png(880, 1184);
    exit;
}
if ($method === 'POST' && $path === '/stripe/coupons') {
    $record(['route' => 'stripe.coupon', 'fields' => $_POST]);
    $json(['id' => 'coupon_bienvenue', 'amount_off' => (int) ($_POST['amount_off'] ?? 0)]);
}
if ($method === 'POST' && $path === '/stripe/promotion_codes') {
    $record(['route' => 'stripe.promo', 'fields' => $_POST]);
    $json(['id' => 'promo_' . ($_POST['code'] ?? ''), 'code' => $_POST['code'] ?? '']);
}
if ($method === 'POST' && $path === '/stripe/refunds') {
    $record(['route' => 'stripe.refund', 'fields' => $_POST]);
    $json(['id' => 're_test', 'status' => 'succeeded']);
}
$json(['error' => 'not found: ' . $method . ' ' . $path], 404);
