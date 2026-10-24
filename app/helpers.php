<?php
declare(strict_types=1);

use App\Core\Config;
use App\Core\Settings;
use App\Core\View;

function config(string $key, mixed $default = null): mixed
{
    return Config::get($key, $default);
}

function setting(string $key, mixed $default = null): mixed
{
    return Settings::get($key, $default);
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** URL publique du site ; avant l'installation, déduite de la requête (le domaine n'est pas encore configuré). */
function base_url(): string
{
    $url = rtrim((string) config('app.url'), '/');
    if ($url === '' && PHP_SAPI !== 'cli') {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        $host = preg_replace('/[^a-z0-9.\-:\[\]]/i', '', (string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
        $url = ($https ? 'https' : 'http') . '://' . $host;
    }
    return $url;
}

function url(string $path = '', array $query = []): string
{
    $url = base_url() . '/' . ltrim($path, '/');
    return $query ? $url . '?' . http_build_query($query) : $url;
}

function asset(string $path): string
{
    $file = PUBLIC_PATH . '/' . ltrim($path, '/');
    $version = is_file($file) ? (string) filemtime($file) : '1';
    return url($path) . '?v=' . $version;
}

/** Image produit : chemin local (/uploads/...) ou URL absolue (CDN Printful). */
function image_url(?string $path): string
{
    if (!$path) {
        return url('assets/img/products/placeholder.svg');
    }
    return preg_match('#^https?://#', $path) ? $path : url($path);
}

function money(int|string|null $cents, string $currency = 'EUR'): string
{
    $amount = number_format(((int) $cents) / 100, 2, ',', "\u{202F}");
    return $currency === 'EUR' ? $amount . "\u{00A0}€" : $amount . "\u{00A0}" . $currency;
}

/** "29,90" ou "29.90" → 2990 */
function to_cents(mixed $value): int
{
    $value = str_replace([' ', "\u{00A0}", '€'], '', (string) $value);
    $value = str_replace(',', '.', $value);
    return (int) round(((float) $value) * 100);
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

function date_fr(?string $datetime, bool $withTime = true): string
{
    if (!$datetime) {
        return '—';
    }
    $ts = strtotime($datetime);
    return $withTime ? date('d/m/Y à H:i', $ts) : date('d/m/Y', $ts);
}

function slugify(string $text): string
{
    $text = function_exists('transliterator_transliterate')
        ? (string) transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $text)
        : strtolower((string) iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text));
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    return trim((string) $text, '-') ?: bin2hex(random_bytes(4));
}

function view(string $template, array $data = [], ?string $layout = 'layouts/shop'): void
{
    echo View::render($template, $data, $layout);
}

function partial(string $template, array $data = []): void
{
    echo View::capture('partials/' . $template, $data);
}

function redirect(string $to, int $status = 302): never
{
    $location = preg_match('#^https?://#', $to) ? $to : url($to);
    header('Location: ' . $location, true, $status);
    exit;
}

function back(string $fallback = '/'): never
{
    $ref = $_SERVER['HTTP_REFERER'] ?? '';
    if ($ref !== '' && str_starts_with($ref, base_url())) {
        header('Location: ' . $ref, true, 302);
        exit;
    }
    redirect($fallback);
}

function abort(int $code, string $message = ''): never
{
    http_response_code($code);
    $titles = [403 => 'Accès refusé', 404 => 'Page introuvable', 405 => 'Méthode non autorisée', 419 => 'Session expirée', 500 => 'Erreur serveur'];
    $title = $titles[$code] ?? 'Erreur';
    try {
        echo View::render('shop/error', ['code' => $code, 'title' => $title, 'message' => $message, 'pageTitle' => $title]);
    } catch (\Throwable) {
        echo "<h1>$code — " . e($title) . '</h1>';
    }
    exit;
}

function json_response(mixed $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function flash(string $type, ?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['_flash'][$type] = $message;
        return null;
    }
    $value = $_SESSION['_flash'][$type] ?? null;
    unset($_SESSION['_flash'][$type]);
    return $value;
}

function old(string $key, mixed $default = ''): mixed
{
    return $_SESSION['_old'][$key] ?? $default;
}

function remember_input(array $data): void
{
    $_SESSION['_old'] = $data;
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $token = $_POST['_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        abort(419, 'Votre session a expiré. Merci de recharger la page et de réessayer.');
    }
}

function input(string $key, mixed $default = null): mixed
{
    $value = $_POST[$key] ?? $_GET[$key] ?? $default;
    return is_string($value) ? trim($value) : $value;
}

function request_path(): string
{
    $path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $basePath = (string) parse_url(base_url(), PHP_URL_PATH);
    if ($basePath !== '' && $basePath !== '/' && str_starts_with($path, $basePath)) {
        $path = substr($path, strlen($basePath));
    }
    return '/' . trim($path, '/');
}

function is_active(string $prefix): bool
{
    $path = request_path();
    return $prefix === '/' ? $path === '/' : str_starts_with($path, $prefix);
}

function client_ip(): string
{
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

function log_message(string $channel, string $message, array $context = []): void
{
    $line = sprintf(
        "[%s] %s %s\n",
        date('Y-m-d H:i:s'),
        $message,
        $context ? json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : ''
    );
    @file_put_contents(STORAGE_PATH . '/logs/' . $channel . '-' . date('Y-m') . '.log', $line, FILE_APPEND | LOCK_EX);
}

function json_field(?string $json): array
{
    if (!$json) {
        return [];
    }
    $data = json_decode($json, true);
    return is_array($data) ? $data : [];
}

function excerpt(?string $html, int $length = 160): string
{
    $text = trim(preg_replace('/\s+/', ' ', strip_tags((string) $html)));
    return mb_strlen($text) > $length ? rtrim(mb_substr($text, 0, $length - 1)) . '…' : $text;
}

function order_status_label(string $status): string
{
    return [
        'pending'       => 'En attente de paiement',
        'paid'          => 'Payée',
        'in_production' => 'En production',
        'shipped'       => 'Expédiée',
        'canceled'      => 'Annulée',
        'refunded'      => 'Remboursée',
    ][$status] ?? $status;
}

function fulfillment_status_label(string $status): string
{
    return [
        'none'      => '—',
        'pending'   => 'À transmettre',
        'draft'     => 'Brouillon Printful (à confirmer)',
        'submitted' => 'Transmise à Printful',
        'error'     => 'Erreur de transmission',
        'shipped'   => 'Expédiée',
        'canceled'  => 'Annulée',
    ][$status] ?? $status;
}
