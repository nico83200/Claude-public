<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

if (!vs_config()) {
    header('Location: install.php');
    exit;
}
// Empreinte par fichier : après une mise à jour, le navigateur recharge chaque module modifié
// (les imports JavaScript sont redirigés vers leur version via une « import map »).
$stamp = fn(string $f) => substr(md5(VS_VERSION . '|' . @filemtime($f) . '|' . @filesize($f)), 0, 10);
$imports = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__ . '/assets/js', FilesystemIterator::SKIP_DOTS));
foreach ($it as $file) {
    if ($file->isFile() && $file->getExtension() === 'js') {
        $rel = 'assets/js/' . str_replace('\\', '/', substr($file->getPathname(), strlen(__DIR__ . '/assets/js/')));
        $imports['./' . $rel] = './' . $rel . '?v=' . $stamp($file->getPathname());
    }
}
$nonce = base64_encode(random_bytes(16));
vs_security_headers($nonce);
header('Cache-Control: no-cache');
$css = $stamp(__DIR__ . '/assets/css/app.css');
?><!doctype html>
<html lang="fr" data-version="<?= htmlspecialchars(VS_VERSION) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>VoyageStudio</title>
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90'%3E✈%3C/text%3E%3C/svg%3E">
<link rel="stylesheet" href="assets/css/app.css?v=<?= $css ?>">
<script type="importmap" nonce="<?= $nonce ?>"><?= json_encode(['imports' => $imports], JSON_UNESCAPED_SLASHES) ?></script>
</head>
<body>
<div id="app"><div class="boot">Chargement…</div></div>
<script type="module" src="<?= htmlspecialchars($imports['./assets/js/app.js'] ?? 'assets/js/app.js') ?>"></script>
</body>
</html>
