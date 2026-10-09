<?php
declare(strict_types=1);

/**
 * Fabrique un paquet de mise à jour à installer depuis l'interface d'administration.
 *
 *   php tools/build-update.php            → dist/approvia-<version>.zip (sans vendor/)
 *   php tools/build-update.php --vendor   → inclut les dépendances (assistant IA)
 *   php tools/build-update.php --hub      → paquet du centre d'assistance NLapps (dossier support-hub/, à installer sur nlapps.fr)
 *   php tools/build-update.php --install  → paquet de première installation (avec install.php et vendor/),
 *                                           à décompresser tel quel chez l'hébergeur (ex. Hostinger)
 *
 * La version est lue dans le fichier VERSION ; les notes dans la première section de CHANGELOG.md.
 */
const ROOT_FILES = ['cron.php', 'console.php', 'sw.js', 'manifest.webmanifest', 'offline.html'];

if (PHP_SAPI !== 'cli') {
    exit("À lancer en ligne de commande.\n");
}
$root = dirname(__DIR__);
$forInstall = in_array('--install', $argv, true);
if (in_array('--hub', $argv, true)) {
    // Deux paquets : complet (avec la bibliothèque de l'IA) et allégé pour les mises à jour (sans vendor/)
    @mkdir("$root/dist", 0755, true);
    $hubVersion = trim((string)@file_get_contents("$root/support-hub/VERSION")) ?: '0.0.0';
    foreach (['nlapps-assistance.zip' => true, 'nlapps-assistance-maj.zip' => false] as $name => $withVendorHub) {
        $out = "$root/dist/$name";
        $zip = new ZipArchive();
        $zip->open($out, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$root/support-hub", FilesystemIterator::SKIP_DOTS));
        $n = 0;
        foreach ($it as $f) {
            $rel = 'assistance/' . ltrim(str_replace('\\', '/', substr($f->getPathname(), strlen("$root/support-hub"))), '/');
            // Données, configuration locale et tests jamais livrés
            if ($f->isFile() && !preg_match('#^assistance/(config\.php|vendor/.*|tests/.*|data/(?!\.htaccess$).*)$#', $rel)) {
                $zip->addFile($f->getPathname(), $rel);
                $n++;
            }
        }
        // Bibliothèque Anthropic (suggestion de réponse par l'IA), la même que celle d'Approvia
        if ($withVendorHub && is_dir("$root/vendor")) {
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$root/vendor", FilesystemIterator::SKIP_DOTS));
            foreach ($it as $f) {
                $rel = ltrim(str_replace('\\', '/', substr($f->getPathname(), strlen($root))), '/');
                if (!$f->isFile() || preg_match('#/(\.git|\.github|tests?|docs?|examples?)/#i', '/' . $rel) || preg_match('#^vendor/standard-webhooks/standard-webhooks/libraries/(?!php/)#', $rel)) {
                    continue;
                }
                $zip->addFile($f->getPathname(), 'assistance/' . $rel);
                $n++;
            }
        }
        $zip->close();
        printf("Centre d'assistance v%s : %s (%d fichiers, %.1f Mo)\n", $hubVersion, $out, $n, filesize($out) / 1048576);
    }
    exit;
}
$withVendor = $forInstall || in_array('--vendor', $argv, true);
$version = trim((string)file_get_contents("$root/VERSION"));
if (!preg_match('/^\d+\.\d+\.\d+/', $version)) {
    exit("Fichier VERSION invalide.\n");
}
$notes = '';
if (is_file("$root/CHANGELOG.md") && preg_match('/^##[^\n]*\n(.*?)(?=^## |\z)/ms', (string)file_get_contents("$root/CHANGELOG.md"), $m)) {
    $notes = trim($m[1]);
}
$include = ['index.php', 'cron.php', 'console.php', 'sw.js', 'manifest.webmanifest', 'offline.html', 'VERSION', 'CHANGELOG.md', 'README.md', 'composer.json', 'composer.lock', '.htaccess', 'config.sample.php', 'app', 'assets', 'tools', 'tests'];
if ($withVendor) {
    if (!is_file("$root/vendor/autoload.php")) {
        exit("Dossier vendor/ absent : lancez d'abord composer install --no-dev.\n");
    }
    $include[] = 'vendor';
}
if ($forInstall) {
    // Assistant d'installation et protections des dossiers de données (vides)
    array_push($include, 'install.php', 'storage/.htaccess', 'uploads/products/.htaccess', 'uploads/brand/.htaccess');
}
@mkdir("$root/dist", 0755, true);
$out = "$root/dist/approvia-$version" . ($forInstall ? '-installation' : ($withVendor ? '-complet' : '')) . '.zip';
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
            // Dépendances : on écarte l'historique git et les fichiers de développement
            if ($item === 'vendor' && preg_match('#/(\.git|\.github|tests?|docs?|examples?)/#i', '/' . $rel)) {
                continue;
            }
            if ($item === 'vendor' && preg_match('#^vendor/standard-webhooks/standard-webhooks/libraries/(?!php/)#', $rel)) {
                continue;
            }
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
