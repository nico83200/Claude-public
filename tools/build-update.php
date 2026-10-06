<?php
declare(strict_types=1);

/**
 * Fabrique un paquet de mise à jour à installer depuis l'interface d'administration.
 *
 *   php tools/build-update.php            → dist/commandes-centres-<version>.zip (sans vendor/)
 *   php tools/build-update.php --vendor   → inclut les dépendances (assistant IA)
 *
 * La version est lue dans le fichier VERSION ; les notes dans la première section de CHANGELOG.md.
 */
const ROOT_FILES = ['cron.php', 'sw.js', 'manifest.webmanifest', 'offline.html'];

if (PHP_SAPI !== 'cli') {
    exit("À lancer en ligne de commande.\n");
}
$root = dirname(__DIR__);
$withVendor = in_array('--vendor', $argv, true);
$version = trim((string)file_get_contents("$root/VERSION"));
if (!preg_match('/^\d+\.\d+\.\d+/', $version)) {
    exit("Fichier VERSION invalide.\n");
}
$notes = '';
if (is_file("$root/CHANGELOG.md") && preg_match('/^##[^\n]*\n(.*?)(?=^## |\z)/ms', (string)file_get_contents("$root/CHANGELOG.md"), $m)) {
    $notes = trim($m[1]);
}
$include = ['index.php', 'cron.php', 'sw.js', 'manifest.webmanifest', 'offline.html', 'VERSION', 'CHANGELOG.md', 'README.md', 'composer.json', 'composer.lock', '.htaccess', 'config.sample.php', 'app', 'assets', 'tools', 'tests'];
if ($withVendor) {
    $include[] = 'vendor';
}
@mkdir("$root/dist", 0755, true);
$out = "$root/dist/commandes-centres-$version" . ($withVendor ? '-complet' : '') . '.zip';
$zip = new ZipArchive();
$zip->open($out, ZipArchive::CREATE | ZipArchive::OVERWRITE);
$zip->addFromString('version.json', json_encode(['version' => $version, 'date' => date('Y-m-d'), 'notes' => $notes], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
$n = 0;
foreach ($include as $item) {
    $path = "$root/$item";
    if (is_file($path)) {
        $zip->addFile($path, $item);
        $n++;
    } elseif (is_dir($path)) {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            $rel = ltrim(str_replace('\\', '/', substr($f->getPathname(), strlen($root))), '/');
            if ($f->isFile()) {
                $zip->addFile($f->getPathname(), $rel);
                $n++;
            }
        }
    }
}
// Copie des fichiers racine dans app/root/ : une version antérieure du module de mise à jour,
// qui ne connaît pas ces fichiers, les installe quand même ; ils sont remis à la racine au chargement suivant.
foreach (ROOT_FILES as $f) {
    if (is_file("$root/$f")) {
        $zip->addFile("$root/$f", "app/root/$f");
        $n++;
    }
}
$zip->close();
printf("Paquet v%s créé : %s (%d fichiers, %.1f Mo)\n", $version, $out, $n, filesize($out) / 1048576);
