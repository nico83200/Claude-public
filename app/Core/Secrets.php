<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Clés et jetons des intégrations, enregistrés en base (table `settings`, préfixe « config. »).
 *
 * La base est la source de référence : config/config.php ne contient plus que l'accès à la base.
 * Ainsi, une mise à jour, une réinstallation ou un fichier écrasé ne fait jamais perdre les clés,
 * et elles se modifient depuis Administration › Intégrations.
 */
final class Secrets
{
    private const PREFIX = 'config.';

    /**
     * Clés gérées : libellé, groupe, type (text | secret | bool | int | select:a,b).
     * Les « secret » ne sont jamais réaffichés en clair dans l'administration.
     */
    public const KEYS = [
        'stripe.secret_key'       => ['Clé secrète (sk_live_… ou sk_test_…)', 'Stripe', 'secret'],
        'stripe.publishable_key'  => ['Clé publique (pk_live_…)', 'Stripe', 'text'],
        'stripe.webhook_secret'   => ['Secret du webhook (whsec_…)', 'Stripe', 'secret'],
        'printful.api_token'      => ['Jeton API privé', 'Printful', 'secret'],
        'printful.store_id'       => ['ID de la boutique (facultatif avec un jeton « single store »)', 'Printful', 'text'],
        'printful.auto_confirm'   => ['Confirmer automatiquement les commandes (sinon brouillon)', 'Printful', 'bool'],
        'printful.webhook_token'  => ['Jeton de l\'adresse du webhook', 'Printful', 'secret'],
        'mail.transport'          => ['Mode d\'envoi', 'E-mails', 'select:smtp,mail,log'],
        'mail.host'               => ['Serveur SMTP', 'E-mails', 'text'],
        'mail.port'               => ['Port (465 = SSL, 587 = TLS)', 'E-mails', 'int'],
        'mail.encryption'         => ['Chiffrement', 'E-mails', 'select:ssl,tls,none'],
        'mail.username'           => ['Identifiant (adresse e-mail)', 'E-mails', 'text'],
        'mail.password'           => ['Mot de passe de la boîte e-mail', 'E-mails', 'secret'],
        'mail.from_email'         => ['Adresse d\'expédition', 'E-mails', 'text'],
        'mail.from_name'          => ['Nom d\'expéditeur', 'E-mails', 'text'],
        'mail.admin_email'        => ['Adresse qui reçoit les alertes', 'E-mails', 'text'],
        'brevo.api_key'           => ['Clé API Brevo', 'Newsletter (Brevo)', 'secret'],
        'brevo.list_id'           => ['N° de liste Brevo', 'Newsletter (Brevo)', 'int'],
        'google.client_id'        => ['ID client OAuth (….apps.googleusercontent.com)', 'Connexion Google', 'text'],
        'google.client_secret'    => ['Code secret du client OAuth', 'Connexion Google', 'secret'],
        'analytics.ga4_id'        => ['Google Analytics 4 (G-XXXXXXX)', 'Mesure d\'audience', 'text'],
        'analytics.meta_pixel_id' => ['Meta Pixel (ID)', 'Mesure d\'audience', 'text'],
        'cron.token'              => ['Jeton de la tâche planifiée (URL)', 'Tâche planifiée', 'secret'],
        'ai.provider'             => ['Service de dessin (replicate recommandé ; demo = effet croquis sans IA)', 'Portrait IA', 'select:replicate,openai,demo'],
        'ai.openai_key'           => ['Clé API OpenAI (sk-…)', 'Portrait IA', 'secret'],
        'ai.openai_model'         => ['Modèle OpenAI', 'Portrait IA', 'text'],
        'ai.openai_quality'       => ['Qualité OpenAI (coût par portrait croissant)', 'Portrait IA', 'select:medium,high,low'],
        'ai.replicate_token'      => ['Jeton API Replicate (r8_…)', 'Portrait IA', 'secret'],
        'ai.replicate_model'      => ['Modèle Replicate', 'Portrait IA', 'text'],
        'ai.upscale'              => ['Agrandir le dessin par IA avant impression (nécessite Replicate)', 'Portrait IA', 'bool'],
        'ai.upscale_model'        => ['Modèle d\'agrandissement Replicate', 'Portrait IA', 'text'],
        'ai.daily_per_visitor'    => ['Créations offertes par visiteuse et par jour', 'Portrait IA', 'int'],
        'ai.daily_budget'         => ['Créations maximum par jour pour toute la boutique (plafond de coût)', 'Portrait IA', 'int'],
    ];

    /** Jetons générés automatiquement s'ils n'existent pas encore. */
    public const GENERATED = ['printful.webhook_token', 'cron.token'];

    /** Applique les valeurs enregistrées en base par-dessus config/config.php. */
    public static function apply(): void
    {
        foreach (self::stored() as $key => $value) {
            Config::set($key, self::cast($key, $value));
        }
    }

    /** @return array<string, string> clé => valeur brute, pour les clés présentes en base */
    public static function stored(): array
    {
        $values = [];
        foreach (Settings::all() as $name => $value) {
            if (str_starts_with($name, self::PREFIX) && isset(self::KEYS[substr($name, strlen(self::PREFIX))])) {
                $values[substr($name, strlen(self::PREFIX))] = (string) $value;
            }
        }
        return $values;
    }

    public static function set(string $key, mixed $value): void
    {
        if (!isset(self::KEYS[$key])) {
            throw new \InvalidArgumentException("Clé inconnue : $key");
        }
        if (is_bool($value)) {
            $value = $value ? '1' : '0';
        }
        Settings::set(self::PREFIX . $key, trim((string) $value));
        Config::set($key, self::cast($key, $value));
    }

    /**
     * Enregistre en base les clés présentes dans un tableau de configuration.
     * @param bool $onlyMissing n'écrase pas une valeur déjà enregistrée
     * @return int nombre de clés écrites
     */
    public static function import(array $config, bool $onlyMissing = false): int
    {
        $stored = $onlyMissing ? self::stored() : [];
        $count = 0;
        foreach (array_keys(self::KEYS) as $key) {
            $value = self::dig($config, $key);
            if ($value === null || ($onlyMissing && array_key_exists($key, $stored))) {
                continue;
            }
            if ($value === '' && $onlyMissing) {
                continue;
            }
            self::set($key, $value);
            $count++;
        }
        return $count;
    }

    /** Crée les jetons de webhook et de tâche planifiée s'ils manquent. */
    public static function ensureGenerated(): void
    {
        $stored = self::stored();
        foreach (self::GENERATED as $key) {
            if (($stored[$key] ?? '') === '' && (string) Config::get($key, '') === '') {
                self::set($key, bin2hex(random_bytes(20)));
            } elseif (($stored[$key] ?? '') === '') {
                self::set($key, (string) Config::get($key));
            }
        }
    }

    /** « sk_live_••••••••3f9a » */
    public static function mask(?string $value): string
    {
        $value = (string) $value;
        if ($value === '') {
            return '';
        }
        $visible = min(4, intdiv(strlen($value), 4));
        $prefix = preg_match('/^([a-z]{2,4}_(?:live|test)_)/', $value, $m) ? $m[1] : '';
        return $prefix . str_repeat('•', 8) . ($visible ? substr($value, -$visible) : '');
    }

    private static function cast(string $key, mixed $value): mixed
    {
        return match (self::KEYS[$key][2]) {
            'bool'  => in_array($value, [true, 1, '1', 'true', 'on'], true),
            'int'   => (int) $value,
            default => (string) $value,
        };
    }

    private static function dig(array $data, string $path): mixed
    {
        foreach (explode('.', $path) as $segment) {
            if (!is_array($data) || !array_key_exists($segment, $data)) {
                return null;
            }
            $data = $data[$segment];
        }
        return $data;
    }
}
