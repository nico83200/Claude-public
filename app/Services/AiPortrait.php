<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Portrait « Encre & Poudre » : la photo du cheval est redessinée par IA (trait fin bordeaux et lavis
 * aquarelle poudré sur papier crème), puis composée avec son nom et son année de naissance pour une
 * impression encadrée.
 *
 * Cycle : queued → processing → ready | failed. L'aperçu montré à la cliente est réduit et filigrané ;
 * le dessin pleine définition n'est publié (pour Printful) qu'une fois la commande payée.
 */
final class AiPortrait
{
    public const DIR = STORAGE_PATH . '/ai-portraits';

    /** Le style maison, décrit pour le modèle de dessin. */
    public const STYLE_PROMPT = 'Transform this photo into an elegant fine-art portrait of this exact horse, in the "Encre & Poudre" style. '
        . 'Keep the horse perfectly recognizable: same head shape and proportions, ears, eyes, face markings (blaze, star, snip), '
        . 'coat color and pattern, mane and forelock, and the bridle or halter if it wears one. Show the horse alone: remove any rider, person, fence or background object. '
        . 'Style: a refined fine-line ink drawing with delicate, confident deep burgundy outlines (#5A2A3A) and a few expressive hairline strokes in the mane, '
        . 'combined with soft, transparent watercolor washes. Keep the real coat colors but softened into a powdery, muted palette harmonized with '
        . 'dusty rose (#D9B8B3), warm beige (#E8DCCB) and taupe (#8C7A6B). '
        . 'Composition: head and upper neck only, centered, facing the same way as in the photo, with generous empty space around; '
        . 'the neck dissolves at the bottom into loose watercolor bleeds and a few drips. '
        . 'Background: plain warm off-white paper (#F9F7F3) with a subtle paper grain, nothing else. '
        . 'Mood: modern, minimalist, feminine, luminous, gallery quality, with lively detailed eyes and a soft highlight. '
        . 'Strictly no text, letters, numbers, signature, logo, frame or border.';

    /**
     * Mise en page de l'affiche (proportions de la feuille, de 0 à 1), partagée par le fichier
     * d'impression et l'aperçu en direct de la fiche produit.
     */
    public const LAYOUT = [
        'art_x' => 0.10, 'art_y' => 0.05, 'art_w' => 0.80, 'art_h' => 0.69,
        'name_y' => 0.838, 'name_y_alone' => 0.862, 'name_size' => 0.078, 'name_max_w' => 0.74,
        'year_y' => 0.905, 'year_size' => 0.0215, 'year_rule' => 0.055,
    ];
    public const PAPER = '#f9f7f3';
    public const INK = '#5a2a3a';
    public const TAUPE = '#8c7a6b';

    private const STALE_SECONDS = 300;

    // ---------------------------------------------------------------- Identité

    public static function sessionHash(): string
    {
        return sha1('session|' . session_id() . '|' . config('app.key'));
    }

    public static function ipHash(): string
    {
        return sha1('ip|' . client_ip() . '|' . config('app.key'));
    }

    public static function find(?string $token): ?array
    {
        if (!$token || !preg_match('/^[a-f0-9]{32}$/', $token)) {
            return null;
        }
        return Database::one('SELECT * FROM ai_portraits WHERE token = ?', [$token]);
    }

    /** Vrai si le portrait a été créé depuis la session de cette visiteuse. */
    public static function owns(?string $token): bool
    {
        return is_string($token) && isset($_SESSION['ai_portraits'][$token]);
    }

    private static function remember(string $token): void
    {
        $list = (array) ($_SESSION['ai_portraits'] ?? []);
        $list[$token] = time();
        $_SESSION['ai_portraits'] = array_slice($list, -30, null, true);
    }

    /** Vrai si un produit « portrait IA » est en ligne : la page /portrait-aquarelle est alors proposée. */
    public static function landingAvailable(): bool
    {
        static $available = null;
        if ($available === null) {
            try {
                $available = self::enabled() && (bool) Database::value(
                    "SELECT COUNT(*) FROM products WHERE active = 1 AND personalizable = 1 AND personalization LIKE ?",
                    ['%"mode":"ai_portrait"%']
                );
            } catch (\Throwable) {
                $available = false;
            }
        }
        return $available;
    }

    public static function enabled(): bool
    {
        try {
            Database::value('SELECT COUNT(*) FROM ai_portraits WHERE 1 = 0');
            return true;
        } catch (\Throwable) {
            return false; // migration 006 pas encore appliquée
        }
    }

    // ------------------------------------------------------------------ Quotas

    /** Créations encore possibles aujourd'hui pour cette visiteuse (limite individuelle et plafond global). */
    public static function remaining(): int
    {
        $since = date('Y-m-d 00:00:00');
        $count = static fn (string $where, array $params) => (int) Database::value(
            "SELECT COUNT(*) FROM ai_portraits WHERE $where AND created_at >= ? AND status <> 'failed'",
            [...$params, $since]
        );
        $limit = max(1, (int) config('ai.daily_per_visitor', 3));
        $budget = max(1, (int) config('ai.daily_budget', 60));
        return max(0, min(
            $limit - $count('session_hash = ?', [self::sessionHash()]),
            $limit * 3 - $count('ip_hash = ?', [self::ipHash()]),   // réseau partagé (box, 4G) : marge
            $budget - $count('1 = 1', [])
        ));
    }

    // ---------------------------------------------------------------- Création

    /**
     * Enregistre la photo et crée le portrait « en file d'attente ». Le dessin est lancé par run().
     * @param array $file entrée de $_FILES
     */
    public static function create(array $file): array
    {
        if (self::remaining() <= 0) {
            throw new \RuntimeException('Vous avez atteint le nombre de créations offertes pour aujourd\'hui. Revenez demain, ou commandez l\'un de vos portraits déjà créés ♡');
        }
        $busy = Database::value(
            "SELECT COUNT(*) FROM ai_portraits WHERE session_hash = ? AND status IN ('queued','processing') AND created_at >= ?",
            [self::sessionHash(), date('Y-m-d H:i:s', time() - self::STALE_SECONDS)]
        );
        if ($busy) {
            throw new \RuntimeException('Un portrait est déjà en cours de création, patientez quelques secondes.');
        }
        $photo = Upload::customerPhoto($file);
        $token = bin2hex(random_bytes(16));
        Database::insert('ai_portraits', [
            'token'        => $token,
            'session_hash' => self::sessionHash(),
            'ip_hash'      => self::ipHash(),
            'provider'     => AiImage::provider(),
            'status'       => 'queued',
            'photo_file'   => $photo,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
        self::remember($token);
        return self::find($token);
    }

    /** Lance le dessin (appel au service d'IA) ; peut durer jusqu'à une minute avec OpenAI. */
    public static function run(array $row): array
    {
        if ($row['status'] !== 'queued') {
            return $row;
        }
        $claimed = Database::update('ai_portraits', ['status' => 'processing', 'updated_at' => now()], "id = :id AND status = 'queued'", ['id' => $row['id']]);
        if ($claimed === 0) {
            return self::find($row['token']);
        }
        $row['status'] = 'processing';
        try {
            $jpeg = self::aiInput((string) Upload::customerPhotoPath($row['photo_file']));
            switch ($row['provider']) {
                case 'openai':
                    return self::storeResult($row, AiImage::openaiEdit($jpeg, self::STYLE_PROMPT));
                case 'replicate':
                    $prediction = AiImage::replicateCreate((string) config('ai.replicate_model') ?: 'black-forest-labs/flux-kontext-pro', [
                        'prompt'           => self::STYLE_PROMPT,
                        'input_image'      => 'data:image/jpeg;base64,' . base64_encode($jpeg),
                        'aspect_ratio'     => '3:4',
                        'output_format'    => 'png',
                        'safety_tolerance' => 2,
                    ], 50);
                    Database::update('ai_portraits', ['prediction_id' => $prediction['id'], 'updated_at' => now()], 'id = :id', ['id' => $row['id']]);
                    $row['prediction_id'] = $prediction['id'];
                    return self::applyPrediction($row, $prediction);
                default:
                    return self::storeResult($row, AiImage::demoSketch($jpeg));
            }
        } catch (\Throwable $e) {
            return self::fail($row, $e->getMessage());
        }
    }

    /** Fait avancer un portrait en cours (suivi Replicate, délai dépassé, agrandissement). */
    public static function refresh(array $row): array
    {
        try {
            if (in_array($row['status'], ['queued', 'processing'], true)) {
                if (strtotime($row['updated_at']) < time() - self::STALE_SECONDS) {
                    return self::fail($row, 'Délai dépassé sans réponse du service de dessin.');
                }
                if ($row['status'] === 'processing' && $row['provider'] === 'replicate' && $row['prediction_id']) {
                    return self::applyPrediction($row, AiImage::replicateGet($row['prediction_id']));
                }
            } elseif ($row['status'] === 'ready' && $row['master_prediction_id'] && !$row['master_file']) {
                $prediction = AiImage::replicateGet($row['master_prediction_id']);
                $error = null;
                if ($prediction['status'] === 'succeeded' && ($url = AiImage::outputUrl($prediction['output']))) {
                    try {
                        $file = $row['token'] . '-hd.png';
                        self::savePng(AiImage::download($url), self::DIR . '/' . $file);
                        Database::update('ai_portraits', ['master_file' => $file], 'id = :id', ['id' => $row['id']]);
                    } catch (\Throwable $e) {
                        $error = $e->getMessage(); // fichier expiré chez Replicate (1 h) : version standard
                    }
                } elseif (in_array($prediction['status'], ['failed', 'canceled'], true)) {
                    $error = $prediction['error'] ?: $prediction['status'];
                }
                if ($error !== null) {
                    log_message('ai', 'Agrandissement abandonné', ['token' => $row['token'], 'error' => $error]);
                    Database::update('ai_portraits', ['master_prediction_id' => null], 'id = :id', ['id' => $row['id']]);
                }
                return self::find($row['token']);
            }
        } catch (\Throwable $e) {
            log_message('ai', 'Suivi du portrait', ['token' => $row['token'], 'error' => $e->getMessage()]);
        }
        return $row;
    }

    private static function applyPrediction(array $row, array $prediction): array
    {
        if ($prediction['status'] === 'succeeded') {
            $url = AiImage::outputUrl($prediction['output']);
            if (!$url) {
                return self::fail($row, 'Réponse Replicate sans image.');
            }
            return self::storeResult($row, AiImage::download($url), $url);
        }
        if (in_array($prediction['status'], ['failed', 'canceled'], true)) {
            return self::fail($row, 'Replicate : ' . ($prediction['error'] ?: $prediction['status']));
        }
        return $row; // starting / processing : on repassera
    }

    private static function storeResult(array $row, string $image, ?string $sourceUrl = null): array
    {
        $file = $row['token'] . '.png';
        self::savePng($image, self::DIR . '/' . $file);
        Database::update('ai_portraits', ['status' => 'ready', 'result_file' => $file, 'error' => null, 'updated_at' => now()], 'id = :id', ['id' => $row['id']]);
        $row = self::find($row['token']);
        self::startUpscale($row, $sourceUrl);
        return self::find($row['token']);
    }

    /** Agrandissement facultatif (Replicate) lancé en arrière-plan : utilisé pour l'impression s'il aboutit. */
    private static function startUpscale(array $row, ?string $sourceUrl): void
    {
        if (!AiImage::canUpscale()) {
            return;
        }
        try {
            $url = $sourceUrl ?? AiImage::replicateUpload((string) file_get_contents(self::DIR . '/' . $row['result_file']), 'image/png', 'portrait.png');
            $prediction = AiImage::replicateCreate((string) config('ai.upscale_model') ?: 'recraft-ai/recraft-crisp-upscale', ['image' => $url]);
            Database::update('ai_portraits', ['master_prediction_id' => $prediction['id']], 'id = :id', ['id' => $row['id']]);
        } catch (\Throwable $e) {
            log_message('ai', 'Agrandissement non lancé', ['token' => $row['token'], 'error' => $e->getMessage()]);
        }
    }

    private static function fail(array $row, string $error): array
    {
        log_message('ai', 'Portrait non créé', ['token' => $row['token'], 'provider' => $row['provider'], 'error' => $error]);
        Database::update('ai_portraits', ['status' => 'failed', 'error' => mb_substr($error, 0, 500), 'updated_at' => now()], 'id = :id', ['id' => $row['id']]);
        return self::find($row['token']);
    }

    /** Message affiché à la cliente pour un portrait en échec (le détail technique reste dans les journaux). */
    public static function customerError(array $row): string
    {
        $error = mb_strtolower((string) $row['error']);
        if (preg_match('/safety|moderation|nsfw|content_policy|flagged/', $error)) {
            return 'Cette photo n\'a pas pu être dessinée. Essayez une autre photo, avec votre cheval seul et bien visible.';
        }
        return 'Le dessin n\'a pas pu être réalisé cette fois-ci. Réessayez dans un instant : cette tentative ne compte pas dans vos créations.';
    }

    /** Photo réduite à 1024 px et compressée (< 250 Ko) pour l'envoi au service d'IA. */
    private static function aiInput(string $photoPath): string
    {
        $img = $photoPath !== '' ? @imagecreatefromjpeg($photoPath) : false;
        if (!$img) {
            throw new \RuntimeException('Photo du cheval introuvable.');
        }
        foreach ([[1024, 86], [1024, 72], [896, 70], [768, 68]] as [$max, $quality]) {
            $ratio = min(1, $max / max(imagesx($img), imagesy($img)));
            $small = $ratio < 1 ? imagescale($img, (int) round(imagesx($img) * $ratio), (int) round(imagesy($img) * $ratio), IMG_BICUBIC) : $img;
            ob_start();
            imagejpeg($small, null, $quality);
            $jpeg = (string) ob_get_clean();
            if (strlen($jpeg) <= 250 * 1024) {
                break;
            }
        }
        return $jpeg;
    }

    /** Réenregistre l'image en PNG (vérifie le contenu et supprime les métadonnées). */
    private static function savePng(string $bytes, string $target): void
    {
        $img = @imagecreatefromstring($bytes);
        if (!$img) {
            throw new \RuntimeException('Image produite illisible.');
        }
        if (!is_dir(self::DIR)) {
            mkdir(self::DIR, 0755, true);
        }
        imagepng($img, $target, 6);
        imagedestroy($img);
    }

    // ---------------------------------------------------------------- Fichiers

    /** Dessin à utiliser pour l'impression : version agrandie si disponible. */
    public static function artworkPath(array $row): ?string
    {
        foreach ([$row['master_file'] ?? null, $row['result_file'] ?? null] as $file) {
            if ($file && is_file(self::DIR . '/' . basename($file))) {
                return self::DIR . '/' . basename($file);
            }
        }
        return null;
    }

    /** Aperçu réduit et filigrané (seule version visible avant l'achat). */
    public static function previewPath(array $row): ?string
    {
        if ($row['status'] !== 'ready' || !$row['result_file']) {
            return null;
        }
        $target = self::DIR . '/' . $row['token'] . '-apercu.jpg';
        if (is_file($target)) {
            return $target;
        }
        $src = imagecreatefrompng(self::DIR . '/' . basename($row['result_file']));
        $w = 720;
        $h = (int) round(imagesy($src) * $w / imagesx($src));
        $img = imagecreatetruecolor($w, $h);
        imagecopyresampled($img, $src, 0, 0, 0, 0, $w, $h, imagesx($src), imagesy($src));
        imagedestroy($src);
        imagealphablending($img, true);
        $font = STORAGE_PATH . '/fonts/Montserrat-SemiBold.ttf';
        $color = imagecolorallocatealpha($img, 90, 42, 58, 104);
        $text = 'APERÇU  ·  CRINIÈRE CLUB';
        for ($line = 0, $y = 0; $y < $h + 240; $line++, $y += 120) {
            for ($x = -400 + ($line % 2) * 200; $x < $w; $x += 400) {
                imagettftext($img, 15, 30, $x, $y, $color, $font, $text);
            }
        }
        imagejpeg($img, $target, 84);
        imagedestroy($img);
        return $target;
    }

    public static function sanitizeYear(mixed $value): string
    {
        $year = preg_replace('/\D/', '', (string) $value);
        if ($year === '') {
            return '';
        }
        if (strlen($year) !== 4 || (int) $year < 1950 || (int) $year > (int) date('Y')) {
            throw new \InvalidArgumentException('Année de naissance invalide : saisissez 4 chiffres, par exemple 2014.');
        }
        return $year;
    }

    /**
     * Compose le fichier d'impression : papier crème, dessin, nom manuscrit et année.
     * @return string chemin public relatif (« uploads/print/… »)
     */
    public static function composePrint(array $row, string $name, string $year, array $cfg): string
    {
        $art = self::artworkPath($row) ?? throw new \RuntimeException('Dessin du portrait introuvable.');
        $W = max(1200, min(9000, (int) ($cfg['print_w'] ?? 3600)));
        $H = max(1600, min(12000, (int) ($cfg['print_h'] ?? 4800)));
        $relative = 'uploads/print/portrait-ia-' . sha1(config('app.key') . '|' . $row['token'] . '|' . $name . '|' . $year . "|$W|$H|" . basename($art)) . '.png';
        if (!is_file(PUBLIC_PATH . '/' . $relative)) {
            self::compose($art, $name, $year, $cfg, PUBLIC_PATH . '/' . $relative);
        }
        return $relative;
    }

    /** Dessine l'affiche complète dans $target (PNG). */
    public static function compose(string $art, string $name, string $year, array $cfg, string $target): void
    {
        $W = max(1200, min(9000, (int) ($cfg['print_w'] ?? 3600)));
        $H = max(1600, min(12000, (int) ($cfg['print_h'] ?? 4800)));
        if ((int) ini_get('memory_limit') > 0 && (int) ini_get('memory_limit') < 512) {
            @ini_set('memory_limit', '512M');
        }

        $L = self::LAYOUT;
        $src = imagecreatefromstring((string) file_get_contents($art)) ?: throw new \RuntimeException('Dessin illisible.');
        $img = imagecreatetruecolor($W, $H);
        [$pr, $pg, $pb] = self::paperColor($src);
        imagefill($img, 0, 0, imagecolorallocate($img, $pr, $pg, $pb));

        // Dessin ajusté dans sa zone, bords fondus dans le papier
        $boxW = $L['art_w'] * $W;
        $boxH = $L['art_h'] * $H;
        $scale = min($boxW / imagesx($src), $boxH / imagesy($src));
        $dw = (int) round(imagesx($src) * $scale);
        $dh = (int) round(imagesy($src) * $scale);
        $dx = (int) round($L['art_x'] * $W + ($boxW - $dw) / 2);
        $dy = (int) round($L['art_y'] * $H + ($boxH - $dh) / 2);
        imagecopyresampled($img, $src, $dx, $dy, 0, 0, $dw, $dh, imagesx($src), imagesy($src));
        imagedestroy($src);
        imagealphablending($img, true);
        $band = (int) round(min($dw, $dh) * 0.05);
        for ($i = 0; $i < $band; $i++) {
            $alpha = (int) round(127 * $i / $band);
            $c = imagecolorallocatealpha($img, $pr, $pg, $pb, $alpha);
            imagerectangle($img, $dx + $i, $dy + $i, $dx + $dw - 1 - $i, $dy + $dh - 1 - $i, $c);
        }

        // Nom en écriture manuscrite, année en capitales espacées entre deux filets
        [$ir, $ig, $ib] = Personalizer::hexToRgb(self::INK);
        [$tr, $tg, $tb] = Personalizer::hexToRgb(self::TAUPE);
        if ($name !== '') {
            $font = STORAGE_PATH . '/fonts/' . Personalizer::FONTS['script'];
            $size = $L['name_size'] * $H;
            while ($size > 20 && self::textWidth($font, $size, $name) > $L['name_max_w'] * $W) {
                $size *= 0.95;
            }
            $box = imagettfbbox($size, 0, $font, $name);
            $x = (int) round($W / 2 - (abs($box[2] - $box[0])) / 2 - $box[0]);
            $y = (int) round(($year !== '' ? $L['name_y'] : $L['name_y_alone']) * $H);
            imagettftext($img, $size, 0, $x, $y, imagecolorallocate($img, $ir, $ig, $ib), $font, $name);
        }
        if ($year !== '') {
            $font = STORAGE_PATH . '/fonts/' . Personalizer::FONTS['sans'];
            $size = $L['year_size'] * $H;
            $spacing = $size * 0.42;
            $chars = mb_str_split($year);
            $widths = array_map(static fn ($c) => self::textWidth($font, $size, $c), $chars);
            $total = array_sum($widths) + $spacing * (count($chars) - 1);
            $y = (int) round(($name !== '' ? $L['year_y'] : $L['name_y_alone']) * $H);
            $x = $W / 2 - $total / 2;
            $color = imagecolorallocate($img, $tr, $tg, $tb);
            foreach ($chars as $i => $c) {
                imagettftext($img, $size, 0, (int) round($x), $y, $color, $font, $c);
                $x += $widths[$i] + $spacing;
            }
            $box = imagettfbbox($size, 0, $font, $year);
            $mid = (int) round($y - abs($box[7] - $box[1]) / 2);
            $gap = $W * 0.028;
            $len = $L['year_rule'] * $W;
            $thick = max(2, (int) round($H * 0.0011));
            $rule = imagecolorallocate($img, $tr, $tg, $tb);
            foreach ([-1, 1] as $side) {
                $from = $W / 2 + $side * ($total / 2 + $gap);
                $to = $from + $side * $len;
                imagefilledrectangle($img, (int) round(min($from, $to)), $mid - intdiv($thick, 2), (int) round(max($from, $to)), $mid + intdiv($thick, 2), $rule);
            }
        }

        if (!is_dir(dirname($target))) {
            mkdir(dirname($target), 0755, true);
        }
        imagepng($img, $target, 6);
        imagedestroy($img);
    }

    /**
     * Couleur du papier : celle du fond du dessin (moyenne de ses bords) quand elle est claire,
     * pour que le dessin se fonde sans démarcation ; sinon le crème de la marque.
     */
    private static function paperColor(\GdImage $src): array
    {
        $w = imagesx($src);
        $h = imagesy($src);
        $sum = [0, 0, 0];
        $n = 0;
        for ($i = 0; $i < 60; $i++) {
            foreach ([[(int) ($i * ($w - 1) / 59), 2], [(int) ($i * ($w - 1) / 59), $h - 3], [2, (int) ($i * ($h - 1) / 59)], [$w - 3, (int) ($i * ($h - 1) / 59)]] as [$x, $y]) {
                $c = imagecolorsforindex($src, imagecolorat($src, $x, $y));
                $sum[0] += $c['red'];
                $sum[1] += $c['green'];
                $sum[2] += $c['blue'];
                $n++;
            }
        }
        $avg = array_map(static fn ($v) => (int) round($v / $n), $sum);
        $luma = 0.299 * $avg[0] + 0.587 * $avg[1] + 0.114 * $avg[2];
        return $luma >= 215 ? $avg : Personalizer::hexToRgb(self::PAPER);
    }

    private static function textWidth(string $font, float $size, string $text): float
    {
        $box = imagettfbbox($size, 0, $font, $text);
        return (float) abs($box[2] - $box[0]);
    }

    /** État renvoyé au navigateur (JSON). */
    public static function publicState(array $row): array
    {
        return [
            'token'   => $row['token'],
            'status'  => $row['status'],
            'preview' => $row['status'] === 'ready' ? url('portrait-ia/' . $row['token'] . '/apercu', ['v' => strtotime($row['updated_at'])]) : null,
            'message' => $row['status'] === 'failed' ? self::customerError($row) : null,
            'upscaling' => $row['status'] === 'ready' && $row['master_prediction_id'] && !$row['master_file'],
        ];
    }

    // --------------------------------------------------------------- Nettoyage

    /** Supprime les portraits (et photos) jamais commandés au-delà de $days jours. */
    public static function cleanup(int $days = 30): int
    {
        if (!self::enabled()) {
            return 0;
        }
        $rows = Database::all(
            "SELECT * FROM ai_portraits WHERE created_at < ? AND token NOT IN (
                SELECT i.ai_portrait FROM order_items i JOIN orders o ON o.id = i.order_id
                WHERE i.ai_portrait IS NOT NULL AND o.status NOT IN ('pending', 'canceled'))",
            [date('Y-m-d H:i:s', time() - $days * 86400)]
        );
        foreach ($rows as $row) {
            foreach (glob(self::DIR . '/' . $row['token'] . '*') ?: [] as $file) {
                @unlink($file);
            }
            Database::query('DELETE FROM ai_portraits WHERE id = ?', [$row['id']]);
        }
        return count($rows);
    }
}
