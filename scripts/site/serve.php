<?php
// Aperçu local du site (adresses propres comme sur l'hébergement) :
// php -S 127.0.0.1:8090 -t site scripts/site/serve.php
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$root = __DIR__.'/../../site';
if ($path !== '/' && is_file($root.$path)) {
    return false;
}
if ($path === '/contact.php') {
    return false;
}
$file = $path === '/' ? '/index.html' : rtrim($path, '/').'.html';
if (is_file($root.$file)) {
    header('Content-Type: text/html; charset=utf-8');
    readfile($root.$file);

    return true;
}
http_response_code(404);
readfile($root.'/404.html');
