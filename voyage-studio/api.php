<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

vs_security_headers();
header('Cache-Control: no-store');

if (!vs_config()) {
    http_response_code(503);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Application non installée — ouvrez install.php']);
    exit;
}

Auth::startSession();
$db = vs_db();
$api = new Api($db, new Auth($db), vs_config()['ai'] ?? null);

// Certains hébergements mutualisés bloquent PUT/DELETE : on accepte POST + X-HTTP-Method-Override.
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$override = strtoupper($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] ?? '');
if ($method === 'POST' && in_array($override, ['PUT', 'DELETE'], true)) {
    $method = $override;
}

$body = [];
$isMultipart = str_starts_with(strtolower($_SERVER['CONTENT_TYPE'] ?? ''), 'multipart/form-data');
if ($isMultipart) {
    $body = $_POST;
    $f = $_FILES['package'] ?? null;
    if ($f && $f['error'] === UPLOAD_ERR_OK && is_uploaded_file($f['tmp_name'])) {
        $api->setUpload($f['tmp_name']);
    } elseif ($f && in_array($f['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
        http_response_code(413);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Fichier trop volumineux pour la configuration PHP de l\'hébergement (upload_max_filesize). Utilisez le paquet sans vendor/ ou le FTP.']);
        exit;
    }
} elseif (($raw = file_get_contents('php://input')) !== '' && $raw !== false) {
    $body = json_decode($raw, true);
    if (!is_array($body)) {
        $body = [];
    }
}
$query = $_GET;
$route = (string)($query['r'] ?? '');
unset($query['r']);

$res = $api->handle($method, $route, $query, $body, $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null, $_SERVER['REMOTE_ADDR'] ?? '');

http_response_code($res['status']);
if (isset($res['raw'])) {
    foreach ($res['headers'] ?? [] as $k => $v) {
        header("$k: $v");
    }
    echo $res['raw'];
    exit;
}
header('Content-Type: application/json; charset=utf-8');
echo json_encode($res['json'], JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
