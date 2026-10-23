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
        $since = date('Y-m-d H:i:s', time() - self::LOCK_MINUTES * 60);
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
        session_regenerate_id(true);
        $_SESSION['admin_id'] = (int) $admin['id'];
        return true;
    }

    public static function logout(): void
    {
        unset($_SESSION['admin_id']);
        session_regenerate_id(true);
    }
}
