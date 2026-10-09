<?php
declare(strict_types=1);

function cfg(string $key, mixed $default = null): mixed
{
    return $GLOBALS['config'][$key] ?? $default;
}

function e(mixed $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $route = 'dashboard', array $params = []): string
{
    $params = array_filter($params, fn($v) => $v !== null);
    return 'index.php?r=' . str_replace('%2F', '/', rawurlencode($route)) . ($params ? '&' . http_build_query($params) : '');
}

function redirect(string $route = 'dashboard', array $params = []): never
{
    header('Location: ' . url($route, $params));
    exit;
}

function redirect_back(string $fallback = 'dashboard'): never
{
    $ref = $_SERVER['HTTP_REFERER'] ?? '';
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if ($ref && parse_url($ref, PHP_URL_HOST) === $host) {
        header('Location: ' . $ref);
        exit;
    }
    redirect($fallback);
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function input(string $key, mixed $default = null): mixed
{
    $v = $_POST[$key] ?? $_GET[$key] ?? $default;
    return is_string($v) ? trim($v) : $v;
}

function input_int(string $key, int $default = 0): int
{
    $v = input($key);
    return is_numeric($v) ? (int)$v : $default;
}

/** Nombre décimal saisi "à la française" (virgule acceptée). */
function input_money(string $key, ?float $default = 0.0): ?float
{
    $v = input($key);
    if ($v === null || $v === '') {
        return $default;
    }
    $v = str_replace([' ', "\u{00A0}", '€'], '', (string)$v);
    $v = str_replace(',', '.', $v);
    return is_numeric($v) ? round((float)$v, 2) : $default;
}

// ---------------------------------------------------------------- CSRF

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    $t = $_POST['_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($t) || !hash_equals(csrf_token(), $t)) {
        http_response_code(419);
        exit('Session expirée ou requête invalide. Merci de recharger la page.');
    }
}

// ---------------------------------------------------------------- Messages flash

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

// ---------------------------------------------------------------- Formats

function money(mixed $n, bool $symbol = true): string
{
    return number_format((float)$n, 2, ',', "\u{202F}") . ($symbol ? "\u{00A0}€" : '');
}

function date_fr(?string $d, bool $time = false): string
{
    if (!$d) {
        return '—';
    }
    $ts = strtotime($d);
    return $ts ? date($time ? 'd/m/Y H:i' : 'd/m/Y', $ts) : '—';
}

function date_long_fr(string $d): string
{
    $days = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
    $months = ['', 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
    $ts = strtotime($d);
    return $days[(int)date('w', $ts)] . ' ' . date('j', $ts) . ' ' . $months[(int)date('n', $ts)];
}

function month_short_fr(int $m): string
{
    return ['', 'janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'][$m];
}

/** "dans 3 jours", "aujourd'hui", "dépassée"… */
function countdown(string $datetime): array
{
    $diff = strtotime($datetime) - time();
    if ($diff < 0) {
        return ['label' => 'Dépassée', 'level' => 'past', 'days' => -1];
    }
    $days = (int)floor($diff / 86400);
    $hours = (int)floor($diff / 3600);
    if ($hours < 24) {
        $label = $hours <= 1 ? 'Moins d\'1 h' : "Dans $hours h";
        return ['label' => $label, 'level' => 'danger', 'days' => 0];
    }
    $level = $days <= 2 ? 'danger' : ($days <= 7 ? 'warning' : 'ok');
    return ['label' => 'J-' . $days, 'level' => $level, 'days' => $days];
}

function initials(string $first, string $last): string
{
    return mb_strtoupper(mb_substr($first, 0, 1) . mb_substr($last, 0, 1));
}

function plural(int $n, string $one, string $many): string
{
    return $n . ' ' . ($n > 1 ? $many : $one);
}

// ---------------------------------------------------------------- Paramètres

function setting(string $key, ?string $default = null): ?string
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            foreach (all('SELECT skey, svalue FROM settings') as $r) {
                $cache[$r['skey']] = $r['svalue'];
            }
        } catch (Throwable) {
        }
    }
    if (func_num_args() === 3) { // invalidation interne
        $cache = null;
        return null;
    }
    return $cache[$key] ?? $default;
}

function set_setting(string $key, ?string $value): void
{
    q('DELETE FROM settings WHERE skey = ?', [$key]);
    insert('settings', ['skey' => $key, 'svalue' => $value]);
    setting($key, null, true);
}

function app_name(): string
{
    $n = setting('app_name') ?: (string)cfg('app_name', 'Centriva');
    return $n === 'Approv' . 'ia' ? 'Centriva' : $n; // ancien nom du logiciel : affiché sous son nouveau nom
}

// ---------------------------------------------------------------- Vues

function render(string $view, array $data = [], ?string $layout = 'layout'): void
{
    extract($data, EXTR_SKIP);
    ob_start();
    require APP . '/views/' . $view . '.php';
    $content = ob_get_clean();
    if ($layout === null) {
        echo $content;
        return;
    }
    require APP . '/views/' . $layout . '.php';
}

function partial(string $view, array $data = []): void
{
    extract($data, EXTR_SKIP);
    require APP . '/views/partials/' . $view . '.php';
}

function json_response(mixed $data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function abort(int $code, string $message = ''): never
{
    http_response_code($code);
    render('error', ['code' => $code, 'message' => $message ?: ($code === 403 ? 'Accès refusé.' : 'Page introuvable.')]);
    exit;
}

function product_image_url(?string $image): ?string
{
    return $image ? uploads_url('products/' . rawurlencode($image)) : null;
}

/** Enregistre une photo envoyée et renvoie le nom de fichier, ou null. */
function handle_image_upload(string $field): ?string
{
    if (empty($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $f = $_FILES[$field];
    if (in_array($f['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
        throw new RuntimeException('La photo dépasse la taille acceptée par le serveur. Réessayez : elle sera réduite automatiquement par votre navigateur.');
    }
    if ($f['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Échec de l\'envoi de la photo.');
    }
    if ($f['size'] > (int)cfg('max_upload', 4 * 1024 * 1024)) {
        throw new RuntimeException('La photo est trop volumineuse.');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'][$mime] ?? null;
    if (!$ext || @getimagesize($f['tmp_name']) === false) {
        throw new RuntimeException('Format de photo non pris en charge (JPG, PNG, WEBP, GIF).');
    }
    $name = date('Ymd') . '-' . bin2hex(random_bytes(8)) . '.' . $ext;
    $dest = uploads_path('products/' . $name);
    if (!move_uploaded_file($f['tmp_name'], $dest)) {
        throw new RuntimeException('Impossible d\'enregistrer la photo.');
    }
    return image_shrink($dest) ?? $name;
}

/**
 * Réduit une photo trop grande (1600 px max, JPEG qualité 85) et corrige l'orientation des photos
 * de smartphone. Renvoie le nouveau nom de fichier si l'image a été réécrite.
 */
function image_shrink(string $path, int $max = 1600): ?string
{
    if (!function_exists('imagecreatetruecolor')) {
        return null;
    }
    $info = @getimagesize($path);
    if (!$info || $info[2] === IMAGETYPE_GIF) {
        return null;
    }
    [$w, $h] = $info;
    $orientation = 1;
    if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $exif = @exif_read_data($path);
        $orientation = (int)($exif['Orientation'] ?? 1);
    }
    if ($w <= $max && $h <= $max && filesize($path) < 600 * 1024 && $orientation === 1) {
        return null;
    }
    $src = match ($info[2]) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
        IMAGETYPE_PNG => @imagecreatefrompng($path),
        IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
        default => false,
    };
    if (!$src) {
        return null;
    }
    $src = match ($orientation) {
        3 => imagerotate($src, 180, 0), 6 => imagerotate($src, -90, 0), 8 => imagerotate($src, 90, 0), default => $src,
    };
    $w = imagesx($src);
    $h = imagesy($src);
    $ratio = min(1, $max / max($w, $h));
    $nw = (int)round($w * $ratio);
    $nh = (int)round($h * $ratio);
    $dst = imagecreatetruecolor($nw, $nh);
    imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255)); // fond blanc pour les PNG transparents
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
    $newName = preg_replace('/\.\w+$/', '.jpg', basename($path));
    $newPath = dirname($path) . '/' . $newName;
    if (!imagejpeg($dst, $newPath, 85)) {
        return null;
    }
    if ($newPath !== $path) {
        @unlink($path);
    }
    return $newName;
}

function delete_image(?string $name): void
{
    if ($name && preg_match('/^[\w.-]+$/', $name)) {
        @unlink(uploads_path('products/' . $name));
    }
}

/** Petite bibliothèque d'icônes SVG (style trait). */
function icon(string $name, int $size = 20, string $class = ''): string
{
    static $p = [
        'home' => '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/><path d="M10 21v-6h4v6"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
        'cart' => '<circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/><path d="M2 3h3l2.7 12.2a2 2 0 0 0 2 1.6h7.7a2 2 0 0 0 2-1.5L21 8H6"/>',
        'box' => '<path d="M21 8 12 3 3 8v8l9 5 9-5z"/><path d="m3 8 9 5 9-5"/><path d="M12 13v8"/>',
        'truck' => '<path d="M3 6h11v10H3z"/><path d="M14 9h4l3 3v4h-7"/><circle cx="7" cy="18" r="2"/><circle cx="17" cy="18" r="2"/>',
        'clipboard' => '<rect x="5" y="4" width="14" height="18" rx="2"/><path d="M9 4V3h6v1"/><path d="M9 11h6M9 15h6"/>',
        'file' => '<path d="M14 3H6a1 1 0 0 0-1 1v16a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V8z"/><path d="M14 3v5h5"/><path d="M9 13h6M9 17h6"/>',
        'check' => '<path d="m5 12 5 5 9-10"/>',
        'check-circle' => '<circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/>',
        'x' => '<path d="M6 6l12 12M18 6 6 18"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'list' => '<path d="M9 6h11M9 12h11M9 18h11"/><circle cx="4.5" cy="6" r="1"/><circle cx="4.5" cy="12" r="1"/><circle cx="4.5" cy="18" r="1"/>',
        'play' => '<circle cx="12" cy="12" r="9"/><path d="M10 8.5v7l6-3.5z"/>',
        'minus' => '<path d="M5 12h14"/>',
        'edit' => '<path d="M4 20h4L19 9l-4-4L4 16z"/><path d="m14 6 4 4"/>',
        'trash' => '<path d="M4 7h16"/><path d="M9 7V4h6v3"/><path d="M6 7l1 13h10l1-13"/>',
        'users' => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><circle cx="17" cy="9" r="2.5"/><path d="M16 14.5a5 5 0 0 1 5.5 5"/>',
        'building' => '<path d="M4 21V5l8-2v18"/><path d="M12 8h8v13"/><path d="M7 8h2M7 12h2M7 16h2M15 12h2M15 16h2"/><path d="M2 21h20"/>',
        'tag' => '<path d="M3 12V4h8l10 10-8 8z"/><circle cx="7.5" cy="8.5" r="1.5"/>',
        'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/>',
        'logout' => '<path d="M15 4h4v16h-4"/><path d="M10 8l-4 4 4 4M6 12h10"/>',
        'sparkles' => '<path d="M12 3l1.8 4.7L18.5 9.5l-4.7 1.8L12 16l-1.8-4.7L5.5 9.5l4.7-1.8z"/><path d="M19 15l.8 2.2L22 18l-2.2.8L19 21l-.8-2.2L16 18l2.2-.8z"/>',
        'star' => '<path d="m12 3 2.8 5.7 6.2.9-4.5 4.4 1 6.2L12 17.3 6.5 20.2l1-6.2L3 9.6l6.2-.9z"/>',
        'chart' => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
        'printer' => '<path d="M6 9V3h12v6"/><rect x="3" y="9" width="18" height="8" rx="2"/><path d="M6 14h12v7H6z"/>',
        'download' => '<path d="M12 3v12M7 10l5 5 5-5"/><path d="M4 21h16"/>',
        'upload' => '<path d="M12 21V9M7 14l5-5 5 5"/><path d="M4 3h16"/>',
        'alert' => '<path d="M12 3 2 21h20z"/><path d="M12 10v5M12 18h.01"/>',
        'info' => '<circle cx="12" cy="12" r="9"/><path d="M12 11v6M12 7.5h.01"/>',
        'mail' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
        'phone' => '<path d="M5 3h4l2 5-3 2a12 12 0 0 0 6 6l2-3 5 2v4a2 2 0 0 1-2 2A17 17 0 0 1 3 5a2 2 0 0 1 2-2"/>',
        'euro' => '<path d="M17 6.5A7 7 0 1 0 17 17.5"/><path d="M4 10h9M4 14h9"/>',
        'menu' => '<path d="M4 6h16M4 12h16M4 18h16"/>',
        'chevron-right' => '<path d="m9 6 6 6-6 6"/>',
        'chevron-down' => '<path d="m6 9 6 6 6-6"/>',
        'arrow-left' => '<path d="M19 12H5M11 6l-6 6 6 6"/>',
        'send' => '<path d="M21 3 3 10l7 3 3 7z"/><path d="m10 13 4-4"/>',
        'heart' => '<path d="M12 20s-7-4.4-9-9a5 5 0 0 1 9-3 5 5 0 0 1 9 3c-2 4.6-9 9-9 9z"/>',
        'repeat' => '<path d="M17 2l4 4-4 4"/><path d="M3 11V9a3 3 0 0 1 3-3h15"/><path d="M7 22l-4-4 4-4"/><path d="M21 13v2a3 3 0 0 1-3 3H3"/>',
        'stethoscope' => '<path d="M5 3v6a5 5 0 0 0 10 0V3"/><path d="M10 14v2a5 5 0 0 0 10 0v-3"/><circle cx="20" cy="11" r="2"/>',
        'pill' => '<rect x="3" y="8" width="18" height="8" rx="4" transform="rotate(-45 12 12)"/><path d="m8.5 8.5 7 7"/>',
        'syringe' => '<path d="m18 2 4 4M17 7l-9.5 9.5-3 .5.5-3L14.5 4.5z"/><path d="m11 8 2 2M8 11l2 2M2 22l3-3"/>',
        'bandage' => '<rect x="2" y="8" width="20" height="8" rx="4" transform="rotate(-45 12 12)"/><path d="M10 10h.01M14 14h.01M10 14h.01M14 10h.01"/>',
        'droplet' => '<path d="M12 3s7 7.5 7 12a7 7 0 0 1-14 0c0-4.5 7-12 7-12z"/>',
        'printer2' => '<path d="M6 9V3h12v6"/><rect x="3" y="9" width="18" height="8" rx="2"/>',
        'pen' => '<path d="M4 20h4L19 9l-4-4L4 16z"/>',
        'shield' => '<path d="M12 3 4 6v6c0 5 3.5 8 8 9 4.5-1 8-4 8-9V6z"/>',
        'coffee' => '<path d="M4 8h13v6a5 5 0 0 1-5 5H9a5 5 0 0 1-5-5z"/><path d="M17 10h2a2 2 0 0 1 0 4h-2"/><path d="M8 3v2M12 3v2"/>',
        'activity' => '<path d="M3 12h4l3-8 4 16 3-8h4"/>',
        'filter' => '<path d="M3 5h18l-7 8v6l-4 2v-8z"/>',
        'eye' => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
        'lock' => '<rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
        'inbox' => '<path d="M3 13h5l1 3h6l1-3h5"/><path d="M5 5h14l2 8v6H3v-6z"/>',
        'camera' => '<path d="M4 8h3l2-3h6l2 3h3v11H4z"/><circle cx="12" cy="13" r="3.5"/>',
        'barcode' => '<path d="M4 5v14M7 5v14M10 5v14M14 5v14M16 5v14M20 5v14"/><path d="M2 3h3M19 3h3M2 21h3M19 21h3"/>',
        'map-pin' => '<path d="M12 21s-7-6.1-7-11.5A7 7 0 0 1 19 9.5C19 14.9 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/>',
        'bell' => '<path d="M6 10a6 6 0 0 1 12 0c0 6 2 7 2 7H4s2-1 2-7"/><path d="M10 20a2 2 0 0 0 4 0"/>',
        'wallet' => '<path d="M3 7h16a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M3 7l12-4v4"/><circle cx="16.5" cy="13.5" r="1.2"/>',
        'layers' => '<path d="m12 3 9 5-9 5-9-5z"/><path d="m3 13 9 5 9-5"/>',
        'refresh' => '<path d="M20 11a8 8 0 0 0-14.9-3M4 4v4h4"/><path d="M4 13a8 8 0 0 0 14.9 3M20 20v-4h-4"/>',
        'archive' => '<rect x="3" y="4" width="18" height="4" rx="1"/><path d="M5 8v12h14V8"/><path d="M10 12h4"/>',
        'minus-circle' => '<circle cx="12" cy="12" r="9"/><path d="M8 12h8"/>',
        'package-check' => '<path d="M21 8 12 3 3 8v8l9 5 9-5z"/><path d="m3 8 9 5 9-5"/><path d="m9 15 2 2 4-4"/>',
    ];
    $inner = $p[$name] ?? $p['box'];
    return '<svg class="ic ' . e($class) . '" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $inner . '</svg>';
}

function icon_choices(): array
{
    return ['box', 'stethoscope', 'pill', 'syringe', 'bandage', 'droplet', 'shield', 'pen', 'printer2', 'coffee', 'activity', 'heart', 'tag', 'clipboard', 'file'];
}

function palette(): array
{
    return ['#6366f1', '#8b5cf6', '#ec4899', '#f43f5e', '#f97316', '#f59e0b', '#84cc16', '#10b981', '#14b8a6', '#06b6d4', '#0ea5e9', '#3b82f6'];
}
