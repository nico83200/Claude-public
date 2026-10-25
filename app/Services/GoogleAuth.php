<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Connexion et création de compte avec Google (OAuth 2.0 / OpenID Connect, flux « code d'autorisation »).
 * Identifiants : Admin › Intégrations › Connexion Google. Adresse de retour à déclarer chez Google : redirectUri().
 */
final class GoogleAuth
{
    private const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';

    public static function enabled(): bool
    {
        return (string) config('google.client_id') !== '' && (string) config('google.client_secret') !== '';
    }

    public static function redirectUri(): string
    {
        return url('compte/google/retour');
    }

    private static function base(string $service): string
    {
        // Surchargeable pour les tests (faux serveur)
        return rtrim((string) config('google.api_base', ''), '/') ?: match ($service) {
            'token'    => 'https://oauth2.googleapis.com',
            'userinfo' => 'https://openidconnect.googleapis.com/v1',
        };
    }

    /** Adresse de la page Google ; l'état (anti-falsification) est gardé en session. */
    public static function authorizationUrl(string $return): string
    {
        $state = bin2hex(random_bytes(16));
        $_SESSION['google_oauth'] = ['state' => $state, 'return' => $return, 'at' => time()];
        return self::AUTH_URL . '?' . http_build_query([
            'client_id'     => config('google.client_id'),
            'redirect_uri'  => self::redirectUri(),
            'response_type' => 'code',
            'scope'         => 'openid email profile',
            'state'         => $state,
            'prompt'        => 'select_account',
        ]);
    }

    /**
     * Vérifie le retour de Google et renvoie le profil (sub, email, name) d'une adresse vérifiée.
     * @return array{sub:string, email:string, name:string, return:string}
     */
    public static function handleCallback(string $code, string $state): array
    {
        $pending = $_SESSION['google_oauth'] ?? null;
        unset($_SESSION['google_oauth']);
        if (!is_array($pending) || $state === '' || !hash_equals((string) $pending['state'], $state) || time() - (int) $pending['at'] > 900) {
            throw new \RuntimeException('La connexion avec Google a expiré. Réessaie.');
        }
        if ($code === '') {
            throw new \RuntimeException('Connexion avec Google annulée.');
        }
        $token = Http::request('POST', self::base('token') . '/token', ['Content-Type: application/x-www-form-urlencoded'], http_build_query([
            'code'          => $code,
            'client_id'     => config('google.client_id'),
            'client_secret' => config('google.client_secret'),
            'redirect_uri'  => self::redirectUri(),
            'grant_type'    => 'authorization_code',
        ]), 20);
        $access = (string) ($token['json']['access_token'] ?? '');
        if ($token['status'] >= 300 || $access === '') {
            log_message('security', 'Connexion Google refusée', ['status' => $token['status'], 'error' => $token['json']['error'] ?? null]);
            throw new \RuntimeException('Google n\'a pas validé la connexion. Réessaie dans un instant.');
        }
        $info = Http::request('GET', self::base('userinfo') . '/userinfo', ['Authorization: Bearer ' . $access], null, 20)['json'] ?? [];
        $email = Account::normalize((string) ($info['email'] ?? ''));
        if (empty($info['sub']) || !filter_var($email, FILTER_VALIDATE_EMAIL) || ($info['email_verified'] ?? false) !== true) {
            throw new \RuntimeException('Ton adresse Google n\'est pas vérifiée : crée plutôt ton compte avec ton e-mail.');
        }
        return [
            'sub'    => (string) $info['sub'],
            'email'  => $email,
            'name'   => trim((string) ($info['given_name'] ?? $info['name'] ?? '')),
            'return' => (string) ($pending['return'] ?? ''),
        ];
    }

    /** Compte existant (même identifiant Google ou même adresse) ou nouveau compte, adresse vérifiée par Google. */
    public static function customerFor(array $profile): array
    {
        $customer = Database::one('SELECT * FROM customers WHERE google_sub = ?', [$profile['sub']])
            ?? Account::findByEmail($profile['email']);
        if ($customer) {
            $changes = ['google_sub' => $profile['sub']];
            if (!$customer['email_verified_at']) {
                $changes['email_verified_at'] = now();
            }
            if ($customer['name'] === '' && $profile['name'] !== '') {
                $changes['name'] = mb_substr($profile['name'], 0, 120);
            }
            Database::update('customers', $changes, 'id = :id', ['id' => $customer['id']]);
            $customer = Database::one('SELECT * FROM customers WHERE id = ?', [$customer['id']]);
            Account::linkOrders($customer);
            return $customer;
        }
        // Mot de passe aléatoire inconnu : « Mot de passe oublié » permet d'en choisir un plus tard
        $customer = Account::register($profile['email'], bin2hex(random_bytes(24)), $profile['name'], true);
        Database::update('customers', ['google_sub' => $profile['sub']], 'id = :id', ['id' => $customer['id']]);
        return Database::one('SELECT * FROM customers WHERE id = ?', [$customer['id']]);
    }
}
