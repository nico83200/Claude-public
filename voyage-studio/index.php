<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

if (!vs_config()) {
    header('Location: install.php');
    exit;
}
vs_security_headers();
$v = rawurlencode(VS_VERSION . '-' . substr(md5((string)@filemtime(__DIR__ . '/assets/js/app.js') . @filemtime(__DIR__ . '/assets/css/app.css')), 0, 6));
?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>VoyageStudio</title>
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90'%3E✈%3C/text%3E%3C/svg%3E">
<link rel="stylesheet" href="assets/css/app.css?v=<?= $v ?>">
</head>
<body>
<div id="app"><div class="boot">Chargement…</div></div>
<script type="module" src="assets/js/app.js?v=<?= $v ?>"></script>
</body>
</html>
