<?php
declare(strict_types=1);

/**
 * Authentification par session PHP + protection CSRF (jeton envoyé en en-tête X-CSRF-Token)
 * + limitation des tentatives de connexion (anti brute-force).
 */
final class Auth
{
    private const MAX_ATTEMPTS = 8;
    private const WINDOW = 900; // 15 min

    public function __construct(private Database $db)
    {
    }

    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        session_name('VSSESSID');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_start();
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        // Expiration après 8 h d'inactivité
        if (isset($_SESSION['last']) && time() - $_SESSION['last'] > 8 * 3600) {
            $_SESSION = ['csrf' => bin2hex(random_bytes(32))];
        }
        $_SESSION['last'] = time();
    }

    public function user(): ?array
    {
        $id = $_SESSION['uid'] ?? null;
        if (!$id) {
            return null;
        }
        $u = $this->db->one('SELECT id, email, nom FROM users WHERE id = ?', [$id]);
        return $u ?: null;
    }

    public function csrfToken(): string
    {
        return $_SESSION['csrf'] ?? '';
    }

    public function checkCsrf(?string $token): bool
    {
        return is_string($token) && $token !== '' && hash_equals($this->csrfToken(), $token);
    }

    public function login(string $email, string $password, string $ip): ?array
    {
        $since = time() - self::WINDOW;
        $this->db->query('DELETE FROM login_attempts WHERE ts < ?', [$since]);
        $count = (int)$this->db->value('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND ts >= ?', [$ip, $since]);
        if ($count >= self::MAX_ATTEMPTS) {
            throw new RuntimeException('Trop de tentatives. Réessayez dans 15 minutes.');
        }
        $u = $this->db->one('SELECT * FROM users WHERE email = ?', [mb_strtolower(trim($email))]);
        if (!$u || !password_verify($password, $u['password_hash'])) {
            $this->db->query('INSERT INTO login_attempts (ip, ts) VALUES (?, ?)', [$ip, time()]);
            usleep(300000);
            return null;
        }
        if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
            $this->db->query('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $u['id']]);
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        $_SESSION['uid'] = (int)$u['id'];
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
        $this->db->query('DELETE FROM login_attempts WHERE ip = ?', [$ip]);
        return ['id' => (int)$u['id'], 'email' => $u['email'], 'nom' => $u['nom']];
    }

    public function logout(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }

    public function createUser(string $email, string $password, string $nom = ''): int
    {
        self::assertPassword($password);
        $now = date('Y-m-d H:i:s');
        $this->db->query('INSERT INTO users (email, nom, password_hash, created_at, updated_at) VALUES (?, ?, ?, ?, ?)',
            [mb_strtolower(trim($email)), $nom, password_hash($password, PASSWORD_DEFAULT), $now, $now]);
        return (int)$this->db->pdo()->lastInsertId();
    }

    public function changePassword(int $uid, string $current, string $new): void
    {
        $hash = $this->db->value('SELECT password_hash FROM users WHERE id = ?', [$uid]);
        if (!$hash || !password_verify($current, $hash)) {
            throw new ValidationException(['current' => 'Mot de passe actuel incorrect']);
        }
        self::assertPassword($new);
        $this->db->query('UPDATE users SET password_hash = ?, updated_at = ? WHERE id = ?',
            [password_hash($new, PASSWORD_DEFAULT), date('Y-m-d H:i:s'), $uid]);
    }

    public static function assertPassword(string $p): void
    {
        if (mb_strlen($p) < 10) {
            throw new ValidationException(['password' => 'Au moins 10 caractères']);
        }
    }
}
