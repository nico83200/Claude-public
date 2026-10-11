<?php

namespace App\Services;

use App\Models\ApplicationSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Throwable;

/**
 * Réglages email et Stripe modifiables par le super-administrateur.
 *
 * Stockés en base (`application_settings`), les secrets chiffrés avec APP_KEY.
 * Ils priment sur le fichier .env, qui reste la valeur de repli.
 * Les secrets ne sont jamais renvoyés au navigateur (seuls les 4 derniers caractères).
 */
class IntegrationSettings
{
    public const CACHE_KEY = 'integration-settings';

    /** clé => [clé de configuration Laravel, secret ?] */
    public const FIELDS = [
        'mail.mailer' => ['mail.default', false],
        'mail.host' => ['mail.mailers.smtp.host', false],
        'mail.port' => ['mail.mailers.smtp.port', false],
        'mail.encryption' => [null, false],
        'mail.username' => ['mail.mailers.smtp.username', false],
        'mail.password' => ['mail.mailers.smtp.password', true],
        'mail.from_address' => ['mail.from.address', false],
        'mail.from_name' => ['mail.from.name', false],
        'stripe.key' => ['services.stripe.key', false],
        'stripe.secret' => ['services.stripe.secret', true],
        'stripe.webhook_secret' => ['services.stripe.webhook_secret', true],
        'anthropic.key' => ['services.anthropic.key', true],
        'elevenlabs.key' => ['services.elevenlabs.key', true],
    ];

    private ?array $resolved = null;

    /** Valeurs stockées (secrets déchiffrés en mémoire uniquement) — usage serveur. */
    public function all(): array
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }
        // Le cache conserve les valeurs chiffrées : aucun secret en clair hors mémoire.
        $rows = Cache::rememberForever(self::CACHE_KEY, fn () => ApplicationSetting::whereIn('key', array_map(fn ($k) => 'integration.'.$k, array_keys(self::FIELDS)))
            ->get()->mapWithKeys(fn ($r) => [$r->key => $r->value])->all());
        $out = [];
        foreach (self::FIELDS as $key => [, $secret]) {
            $value = $rows['integration.'.$key] ?? null;
            if ($value !== null && $value !== '' && $secret) {
                try {
                    $value = Crypt::decryptString($value);
                } catch (Throwable) {
                    $value = null; // APP_KEY changée : secret à ressaisir
                }
            }
            $out[$key] = $value === null ? null : (string) $value;
        }

        return $this->resolved = $out;
    }

    public function get(string $key): ?string
    {
        $v = $this->all()[$key] ?? null;

        return $v === '' ? null : $v;
    }

    /**
     * Enregistre les valeurs fournies. Pour un secret, une valeur vide conserve
     * l'actuelle ; `clear_<clé>` permet de l'effacer explicitement.
     */
    public function save(array $values, array $clear = []): array
    {
        $changed = [];
        foreach (self::FIELDS as $key => [, $secret]) {
            if (in_array($key, $clear, true)) {
                ApplicationSetting::put('integration.'.$key, null);
                $changed[] = $key;

                continue;
            }
            if (! array_key_exists($key, $values)) {
                continue;
            }
            $value = $values[$key];
            if ($secret && ($value === null || $value === '')) {
                continue;
            }
            ApplicationSetting::put('integration.'.$key, $secret && $value !== null ? Crypt::encryptString((string) $value) : $value);
            $changed[] = $key;
        }
        Cache::forget(self::CACHE_KEY);
        $this->resolved = null;
        $this->apply();

        return $changed;
    }

    /** Applique les réglages enregistrés à la configuration en cours. */
    public function apply(): void
    {
        $s = $this->all();
        $config = [];
        foreach (self::FIELDS as $key => [$configKey]) {
            if ($configKey && filled($s[$key] ?? null)) {
                $config[$configKey] = $key === 'mail.port' ? (int) $s[$key] : $s[$key];
            }
        }
        if (filled($s['mail.encryption'] ?? null)) {
            // ssl = SMTPS (465) ; tls/aucun = SMTP avec STARTTLS si proposé
            $config['mail.mailers.smtp.scheme'] = $s['mail.encryption'] === 'ssl' ? 'smtps' : 'smtp';
        }
        if ($config) {
            config($config);
            if (app()->resolved('mail.manager')) {
                app('mail.manager')->forgetMailers();
            }
        }
    }

    /** Valeur masquée d'un secret (« ••••1a2b ») ou null. */
    public function masked(string $key): ?string
    {
        $v = $this->get($key);

        return $v ? '••••'.substr($v, -4) : null;
    }

    /** Provenance effective : admin, .env ou aucune. */
    public function source(string $key): string
    {
        if (filled($this->get($key))) {
            return 'admin';
        }
        // Sans valeur en base, la configuration courante provient du .env.
        $configKey = self::FIELDS[$key][0] ?? null;

        return $configKey && filled(config($configKey)) ? 'env' : 'none';
    }
}
