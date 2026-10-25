<?php
declare(strict_types=1);

namespace App\Core;

final class Auth
{
    private const MAX_ATTEMPTS = 5;
    private const LOCK_MINUTES = 15;

    public static function admin(): ?array
    {
        $id = $_SESSION['admin_id'] ?? null;
        if (!$id) {
            return null;
        }
        static $admin = null;
        return $admin ??= Database::one('SELECT id, email, name FROM admins WHERE id = ?', [$id]);
    }

    public static function requireAdmin(): array
    {
        $admin = self::admin();
        if (!$admin) {
            redirect('admin/connexion');
        }
        return $admin;
    }

    public static function isLocked(string $ip): bool
    {
        $since = ago(self::LOCK_MINUTES * 60);
        $count = (int) Database::value('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND created_at > ?', [$ip, $since]);
        return $count >= self::MAX_ATTEMPTS;
    }

    public static function attempt(string $email, string $password, string $ip): bool
    {
        $admin = Database::one('SELECT * FROM admins WHERE email = ?', [mb_strtolower($email)]);
        if (!$admin || !password_verify($password, $admin['password_hash'])) {
            Database::insert('login_attempts', ['ip' => $ip, 'created_at' => now()]);
            return false;
        }
        if (password_needs_rehash($admin['password_hash'], PASSWORD_DEFAULT)) {
            Database::update('admins', ['password_hash' => password_hash($password, PASSWORD_DEFAULT)], 'id = :id', ['id' => $admin['id']]);
        }
        Database::query('DELETE FROM login_attempts WHERE ip = ?', [$ip]);
        Database::update('admins', ['last_login_at' => now()], 'id = :id', ['id' => $admin['id']]);
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        $_SESSION['admin_id'] = (int) $admin['id'];
        return true;
    }

    /** Envoie un lien de réinitialisation (rien n'est révélé si l'adresse est inconnue). */
    public static function sendReset(string $email, string $ip): void
    {
        Database::insert('login_attempts', ['ip' => $ip, 'created_at' => now()]); // limite les envois en rafale
        $admin = Database::one('SELECT * FROM admins WHERE email = ?', [mb_strtolower(trim($email))]);
        if (!$admin) {
            return;
        }
        $token = bin2hex(random_bytes(24));
        Database::update('admins', [
            'reset_token_hash' => hash('sha256', $token),
            'reset_expires_at' => date('Y-m-d H:i:s', time() + 3600),
        ], 'id = :id', ['id' => $admin['id']]);
        \App\Services\Mailer::sendTemplate($admin['email'], 'Réinitialisation de votre mot de passe administrateur', 'admin_reset', [
            'link' => url('admin/nouveau-mot-de-passe/' . $token),
            'ip'   => $ip,
        ]);
    }

    public static function resetAdmin(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{48}$/', $token)) {
            return null;
        }
        $admin = Database::one('SELECT * FROM admins WHERE reset_token_hash = ?', [hash('sha256', $token)]);
        return $admin && strtotime((string) $admin['reset_expires_at']) >= time() ? $admin : null;
    }

    public static function resetPassword(string $token, string $password): array
    {
        $admin = self::resetAdmin($token);
        if (!$admin) {
            throw new \RuntimeException('Ce lien a expiré. Demandez-en un nouveau.');
        }
        if (mb_strlen($password) < 10) {
            throw new \RuntimeException('Le mot de passe doit contenir au moins 10 caractères.');
        }
        Database::update('admins', [
            'password_hash'    => password_hash($password, PASSWORD_DEFAULT),
            'reset_token_hash' => null,
            'reset_expires_at' => null,
            'last_login_at'    => now(),
        ], 'id = :id', ['id' => $admin['id']]);
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        $_SESSION['admin_id'] = (int) $admin['id'];
        return $admin;
    }

    public static function logout(): void
    {
        unset($_SESSION['admin_id']);
        session_regenerate_id(true);
    }
}
