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
    $cents = (int) $cents;
    // « 69 € » plutôt que « 69,00 € » pour les montants ronds
    $amount = number_format($cents / 100, $cents % 100 === 0 ? 0 : 2, ',', "\u{202F}");
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
        'artwork'   => 'Portrait à réaliser',
        'draft'     => 'Brouillon Printful (à confirmer)',
        'submitted' => 'Transmise à Printful',
        'error'     => 'Erreur de transmission',
        'shipped'   => 'Expédiée',
        'canceled'  => 'Annulée',
    ][$status] ?? $status;
}

/** Échappe un texte et met en italique les passages entre *astérisques* (titres éditables). */
function emphasis(?string $text): string
{
    return (string) preg_replace('/\*(.+?)\*/u', '<em>$1</em>', e($text));
}

/** Étoiles de notation accessibles (remplissage partiel en CSS). */
function stars(float $rating, ?int $count = null, string $class = ''): string
{
    $label = 'Note : ' . number_format($rating, 1, ',', '') . ' sur 5' . ($count !== null ? ' (' . $count . ' avis)' : '');
    $html = '<span class="stars ' . e($class) . '" style="--rating: ' . number_format($rating, 2, '.', '') . '" role="img" aria-label="' . e($label) . '"></span>';
    if ($count !== null) {
        $html .= ' <span class="stars-count">' . number_format($rating, 1, ',', '') . ' (' . $count . ')</span>';
    }
    return $html;
}

function add_business_days(int $timestamp, int $days): int
{
    while ($days > 0) {
        $timestamp += 86400;
        if ((int) date('N', $timestamp) < 6) {
            $days--;
        }
    }
    return $timestamp;
}

/** « jeu. 6 nov. » */
function date_short_fr(int $timestamp): string
{
    $days = ['lun.', 'mar.', 'mer.', 'jeu.', 'ven.', 'sam.', 'dim.'];
    $months = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
    return $days[(int) date('N', $timestamp) - 1] . ' ' . date('j', $timestamp) . ' ' . $months[(int) date('n', $timestamp) - 1];
}

/** Fenêtre de livraison estimée (fabrication + transport, en jours ouvrés). */
function delivery_window(string $method = 'standard', int $extraDays = 0): array
{
    $prefix = $method === 'express' ? 'shipping_express_' : 'shipping_standard_';
    $min = (int) setting('production_min', 2) + (int) setting($prefix . 'min', 4) + $extraDays;
    $max = (int) setting('production_max', 5) + (int) setting($prefix . 'max', 8) + $extraDays;
    return ['from' => date_short_fr(add_business_days(time(), $min)), 'to' => date_short_fr(add_business_days(time(), $max))];
}

/** Icônes SVG au trait (24 × 24), sans dépendance externe. */
function icon(string $name, int $size = 20): string
{
    $paths = [
        'bag'      => 'M6 8h12l-1 12H7L6 8Zm3 0V6a3 3 0 0 1 6 0v2',
        'search'   => 'M11 18a7 7 0 1 0 0-14 7 7 0 0 0 0 14Zm5-2 5 5',
        'truck'    => 'M3 6h11v10H3zM14 9h4l3 3.5V16h-7M7 19a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Zm10 0a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Z',
        'lock'     => 'M6 11h12v9H6zM8.5 11V8a3.5 3.5 0 0 1 7 0v3',
        'sparkle'  => 'M12 3l1.8 5.2L19 10l-5.2 1.8L12 17l-1.8-5.2L5 10l5.2-1.8Z',
        'heart'    => 'M12 20s-7-4.4-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 10c0 5.6-7 10-7 10Z',
        'refresh'  => 'M20 11a8 8 0 0 0-14.7-4M4 4v4h4M4 13a8 8 0 0 0 14.7 4M20 20v-4h-4',
        'gift'     => 'M4 11h16v9H4zM3 7h18v4H3zM12 7v13M12 7c-1.5-3-5-3.5-5-1.5S10 7 12 7Zm0 0c1.5-3 5-3.5 5-1.5S14 7 12 7Z',
        'check'    => 'M5 12.5l4.5 4.5L19 7',
        'close'    => 'M6 6l12 12M18 6 6 18',
        'menu'     => 'M4 7h16M4 12h16M4 17h10',
        'leaf'     => 'M5 19c0-8 6-14 14-14 0 8-6 14-14 14Zm0 0 7-7',
        'ruler'    => 'M3 15 15 3l6 6L9 21Zm4-4 2 2m2-6 2 2m2-6 2 2',
        'arrow'    => 'M5 12h14m-5-5 5 5-5 5',
        'brush'    => 'M14 4l6 6-8.5 8.5a3 3 0 0 1-4.2 0l-1.8-1.8a3 3 0 0 1 0-4.2Zm-4 9 3 3M4 20l3-3',
        'calendar' => 'M4 6h16v14H4zM4 10h16M8 3v4m8-4v4',
        'star'     => 'M12 3.5l2.6 5.3 5.9.9-4.3 4.1 1 5.8L12 16.9l-5.2 2.7 1-5.8-4.3-4.1 5.9-.9Z',
        'copy'     => 'M9 9h11v11H9zM5 15H4V4h11v1',
        'user'     => 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm-7.5 8a7.5 7.5 0 0 1 15 0',
    ];
    $d = $paths[$name] ?? $paths['sparkle'];
    return '<svg class="icon icon-' . e($name) . '" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="' . $d . '"/></svg>';
}

/**
 * Redimensionne une image GD (hauteur déduite si $height <= 0).
 * N'utilise pas imagescale(), qui renvoie false avec certaines versions de libgd (hébergements mutualisés).
 */
function gd_resize(\GdImage $src, int $width, int $height = 0): \GdImage
{
    $width = max(1, $width);
    $height = $height > 0 ? $height : max(1, (int) round(imagesy($src) * $width / imagesx($src)));
    $dst = imagecreatetruecolor($width, $height);
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $width, $height, imagesx($src), imagesy($src));
    return $dst;
}
