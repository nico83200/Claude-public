<?php
// Version 1.3.0 : les clés et jetons (Stripe, Printful, e-mail, Brevo, Analytics, webhooks) passent en base.
// Sources, par ordre de priorité : valeurs déjà en base, copie de secours de la 1.2.1, config/config.php.
use App\Core\Config;
use App\Core\Installer;
use App\Core\Secrets;

return function (PDO $pdo, string $driver): void {
    App\Core\Settings::flush();
    Secrets::import(Installer::restoreConfig($pdo), true);

    $fromFile = [];
    foreach (array_keys(Secrets::KEYS) as $key) {
        $value = Config::get($key);
        if ($value !== null) {
            $ref = &$fromFile;
            foreach (explode('.', $key) as $segment) {
                $ref = &$ref[$segment];
            }
            $ref = $value;
            unset($ref);
        }
    }
    Secrets::import($fromFile, true);
    Secrets::ensureGenerated();
    $pdo->exec("DELETE FROM settings WHERE name = '_config_backup'");
};
