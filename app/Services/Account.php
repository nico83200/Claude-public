<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Comptes clientes : inscription, connexion, mot de passe oublié, commandes rattachées.
 * Les commandes passées avec la même adresse e-mail ne sont visibles qu'une fois l'adresse confirmée
 * (lien envoyé par e-mail, ou compte créé juste après un paiement avec cette adresse).
 */
final class Account
{
    private const MAX_ATTEMPTS = 8;
    private const LOCK_MINUTES = 15;
    public const MIN_PASSWORD = 8;

    public static function current(): ?array
    {
        $id = $_SESSION['customer_id'] ?? null;
        if (!$id) {
            return null;
        }
        $customer = Database::one('SELECT * FROM customers WHERE id = ?', [$id]);
        if (!$customer) {
            unset($_SESSION['customer_id']);
        }
        return $customer ?: null;
    }

    public static function findByEmail(string $email): ?array
    {
        return Database::one('SELECT * FROM customers WHERE email = ?', [self::normalize($email)]) ?: null;
    }

    public static function normalize(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    /** Prénom pour les salutations (« Bonjour Léa »). */
    public static function firstName(array $customer): string
    {
        $first = trim(explode(' ', trim((string) $customer['name']))[0] ?? '');
        return $first !== '' ? $first : explode('@', (string) $customer['email'])[0];
    }

    public static function validatePassword(string $password): ?string
    {
        if (mb_strlen($password) < self::MIN_PASSWORD) {
            return 'Votre mot de passe doit contenir au moins ' . self::MIN_PASSWORD . ' caractères.';
        }
        if (mb_strlen($password) > 200) {
            return 'Ce mot de passe est trop long.';
        }
        return null;
    }

    /** Crée le compte. $verified = adresse déjà prouvée (paiement Stripe avec cette adresse). */
    public static function register(string $email, string $password, string $name = '', bool $verified = false): array
    {
        $email = self::normalize($email);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('Merci d\'indiquer une adresse e-mail valide.');
        }
        if ($error = self::validatePassword($password)) {
            throw new \RuntimeException($error);
        }
        if (self::findByEmail($email)) {
            throw new \RuntimeException('Un compte existe déjà avec cette adresse. Connectez-vous, ou utilisez « Mot de passe oublié ».');
        }
        $id = Database::insert('customers', [
            'email'             => $email,
            'name'              => mb_substr(trim($name), 0, 120),
            'password_hash'     => password_hash($password, PASSWORD_DEFAULT),
            'email_verified_at' => $verified ? now() : null,
            'created_at'        => now(),
        ]);
        $customer = Database::one('SELECT * FROM customers WHERE id = ?', [$id]);
        if ($verified) {
            self::linkOrders($customer);
        } else {
            self::sendVerification($customer);
        }
        return $customer;
    }

    public static function isLocked(string $ip): bool
    {
        $since = date('Y-m-d H:i:s', time() - self::LOCK_MINUTES * 60);
        return (int) Database::value('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND created_at > ?', ['c:' . $ip, $since]) >= self::MAX_ATTEMPTS;
    }

    public static function attempt(string $email, string $password, string $ip): ?array
    {
        $customer = self::findByEmail($email);
        if (!$customer || !password_verify($password, $customer['password_hash'])) {
            Database::insert('login_attempts', ['ip' => 'c:' . $ip, 'created_at' => now()]);
            return null;
        }
        if (password_needs_rehash($customer['password_hash'], PASSWORD_DEFAULT)) {
            Database::update('customers', ['password_hash' => password_hash($password, PASSWORD_DEFAULT)], 'id = :id', ['id' => $customer['id']]);
        }
        Database::query('DELETE FROM login_attempts WHERE ip = ?', ['c:' . $ip]);
        return $customer;
    }

    public static function login(array $customer): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        $_SESSION['customer_id'] = (int) $customer['id'];
        Database::update('customers', ['last_login_at' => now()], 'id = :id', ['id' => $customer['id']]);
    }

    public static function logout(): void
    {
        unset($_SESSION['customer_id']);
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    /** Commandes payées de la cliente (rattachées, ou à son adresse confirmée). */
    public static function orders(array $customer): array
    {
        if ($customer['email_verified_at']) {
            return Database::all("SELECT * FROM orders WHERE (customer_id = ? OR email = ?) AND status <> 'pending' AND status <> 'canceled' ORDER BY id DESC", [$customer['id'], $customer['email']]);
        }
        return Database::all("SELECT * FROM orders WHERE customer_id = ? AND status <> 'pending' AND status <> 'canceled' ORDER BY id DESC", [$customer['id']]);
    }

    /** Rattache au compte les commandes passées avec son adresse (une fois l'adresse prouvée). */
    public static function linkOrders(array $customer): void
    {
        Database::query('UPDATE orders SET customer_id = ? WHERE email = ? AND customer_id IS NULL', [$customer['id'], $customer['email']]);
    }

    // --- Confirmation d'adresse et mot de passe oublié : jeton aléatoire, seul son empreinte est stockée ---

    private static function issueToken(array $customer, string $purpose, int $ttl): string
    {
        $token = bin2hex(random_bytes(24));
        Database::update('customers', [
            'token_hash'       => hash('sha256', $purpose . ':' . $token),
            'token_expires_at' => date('Y-m-d H:i:s', time() + $ttl),
        ], 'id = :id', ['id' => $customer['id']]);
        return $token;
    }

    private static function consumeToken(string $token, string $purpose): ?array
    {
        if (!preg_match('/^[a-f0-9]{48}$/', $token)) {
            return null;
        }
        $customer = Database::one('SELECT * FROM customers WHERE token_hash = ?', [hash('sha256', $purpose . ':' . $token)]);
        if (!$customer || strtotime((string) $customer['token_expires_at']) < time()) {
            return null;
        }
        return $customer;
    }

    private static function clearToken(array $customer): void
    {
        Database::update('customers', ['token_hash' => null, 'token_expires_at' => null], 'id = :id', ['id' => $customer['id']]);
    }

    public static function sendVerification(array $customer): bool
    {
        $token = self::issueToken($customer, 'verify', 7 * 86400);
        return Mailer::sendTemplate($customer['email'], 'Confirmez votre adresse e-mail', 'account_verify', [
            'name' => self::firstName($customer),
            'link' => url('compte/confirmer/' . $token),
        ]);
    }

    public static function verify(string $token): ?array
    {
        $customer = self::consumeToken($token, 'verify');
        if (!$customer) {
            return null;
        }
        self::clearToken($customer);
        Database::update('customers', ['email_verified_at' => $customer['email_verified_at'] ?: now()], 'id = :id', ['id' => $customer['id']]);
        $customer = Database::one('SELECT * FROM customers WHERE id = ?', [$customer['id']]);
        self::linkOrders($customer);
        return $customer;
    }

    /** Envoie le lien de réinitialisation (silencieux si l'adresse est inconnue). */
    public static function sendReset(string $email): void
    {
        $customer = self::findByEmail($email);
        if (!$customer) {
            return;
        }
        $token = self::issueToken($customer, 'reset', 3600);
        Mailer::sendTemplate($customer['email'], 'Réinitialiser votre mot de passe', 'account_reset', [
            'name' => self::firstName($customer),
            'link' => url('compte/nouveau-mot-de-passe/' . $token),
        ]);
    }

    public static function resetCustomer(string $token): ?array
    {
        return self::consumeToken($token, 'reset');
    }

    public static function resetPassword(string $token, string $password): array
    {
        $customer = self::consumeToken($token, 'reset');
        if (!$customer) {
            throw new \RuntimeException('Ce lien a expiré. Demandez-en un nouveau.');
        }
        if ($error = self::validatePassword($password)) {
            throw new \RuntimeException($error);
        }
        self::clearToken($customer);
        // Le lien reçu par e-mail prouve aussi l'adresse
        Database::update('customers', [
            'password_hash'     => password_hash($password, PASSWORD_DEFAULT),
            'email_verified_at' => $customer['email_verified_at'] ?: now(),
        ], 'id = :id', ['id' => $customer['id']]);
        $customer = Database::one('SELECT * FROM customers WHERE id = ?', [$customer['id']]);
        self::linkOrders($customer);
        return $customer;
    }

    public static function delete(array $customer): void
    {
        Database::query('UPDATE orders SET customer_id = NULL WHERE customer_id = ?', [$customer['id']]);
        Database::query('DELETE FROM customers WHERE id = ?', [$customer['id']]);
    }
}
