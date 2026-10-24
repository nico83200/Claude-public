<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Génère le fichier d'impression personnalisé à partir du visuel de base :
 * - mode « text »     : nom du cheval (ou de l'écurie) ;
 * - mode « photo »    : photo du cheval cadrée dans une forme (rectangle, cercle, arche) + nom facultatif ;
 * - mode « portrait » : portrait illustré réalisé à la main d'après la photo — le fichier final est
 *                       déposé par l'administratrice sur la commande (voir OrderService::fulfill) ;
 * - mode « ai_portrait » : portrait redessiné par IA, composé avec le nom et l'année (voir AiPortrait).
 * Le PNG produit est publié dans /uploads/print/ pour que Printful puisse le télécharger.
 */
final class Personalizer
{
    public const FONTS = [
        'serif'  => 'PlayfairDisplay-SemiBold.ttf',
        'script' => 'Allura-Regular.ttf',
        'sans'   => 'Montserrat-SemiBold.ttf',
    ];

    public const MODES = [
        'text'     => 'Nom (texte)',
        'photo'    => 'Photo du cheval (+ nom facultatif)',
        'portrait' => 'Portrait illustré d\'après photo (création manuelle)',
        'ai_portrait' => 'Portrait IA « Encre & Poudre » d\'après photo (affiche encadrée)',
    ];

    public const SHAPES = ['rect' => 'Rectangle', 'circle' => 'Cercle', 'arch' => 'Arche'];

    public const DEFAULTS = [
        'mode'         => 'text',
        'label'        => 'Nom de votre cheval',
        'max_length'   => 18,
        'required'     => false, // texte obligatoire ?
        'font'         => 'serif',
        'color'        => '#5a2a3a',
        'x'            => 0.5,   // centre horizontal du texte (0–1, sur le visuel d'impression)
        'y'            => 0.82,  // ligne de base du texte (0–1)
        'size'         => 0.10,  // hauteur de police relative à la hauteur du visuel
        'max_width'    => 0.85,  // largeur max relative
        'uppercase'    => true,
        'preview_x'    => 0.5,   // position de l'aperçu du texte sur la photo produit (0–1)
        'preview_y'    => 0.62,
        'preview_size' => 0.045,
        'preview_color' => '',
        // Photo : zone sur le visuel d'impression (coin haut-gauche + taille, 0–1)
        'photo_x'      => 0.2,
        'photo_y'      => 0.08,
        'photo_w'      => 0.6,
        'photo_h'      => 0.62,
        'photo_shape'  => 'arch',
        // Photo : zone d'aperçu sur la photo produit (0–1)
        'preview_photo_x' => 0.3,
        'preview_photo_y' => 0.22,
        'preview_photo_w' => 0.4,
        'preview_photo_h' => 0.38,
        // Image produit sans exemple de nom/photo, affichée pendant l'aperçu en direct (vide = image principale)
        'preview_image' => '',
        // Délai de fabrication supplémentaire (jours ouvrés), ex. création d'un portrait
        'extra_days'   => 0,
        // Portrait IA : année de naissance facultative sous le nom, taille du fichier d'impression (px)
        'year_enabled' => true,
        'year_label'   => 'Année de naissance',
        'print_w'      => 3600,
        'print_h'      => 4800,
    ];

    public static function config(?string $json): array
    {
        $cfg = array_replace(self::DEFAULTS, json_field($json));
        if (!isset(self::MODES[$cfg['mode']])) {
            $cfg['mode'] = 'text';
        }
        return $cfg;
    }

    public static function needsPhoto(array $cfg): bool
    {
        return in_array($cfg['mode'], ['photo', 'portrait'], true);
    }

    public static function sanitize(string $text, int $maxLength): string
    {
        $text = preg_replace('/[^\p{L}\p{N} \'’&.\-]/u', '', $text);
        $text = trim((string) preg_replace('/\s+/u', ' ', (string) $text));
        return mb_substr($text, 0, max(1, $maxLength));
    }

    /**
     * Compose le fichier d'impression (photo éventuelle, puis texte éventuel) sur le visuel de base.
     * @param string|null $photoPath chemin absolu de la photo client (déjà normalisée en JPEG)
     * @return string chemin public relatif (ex. "uploads/print/ab12….png")
     */
    public static function render(string $designFile, string $text, array $cfg, ?string $photoPath = null): string
    {
        $cfg = array_replace(self::DEFAULTS, $cfg);
        $source = STORAGE_PATH . '/designs/' . basename($designFile);
        if (!is_file($source)) {
            throw new \RuntimeException('Visuel d\'impression introuvable : ' . $designFile);
        }
        if ($photoPath !== null && !is_file($photoPath)) {
            throw new \RuntimeException('Photo client introuvable : ' . basename($photoPath));
        }
        if ($cfg['uppercase'] && $cfg['font'] !== 'script') {
            $text = mb_strtoupper($text);
        }

        $name = sha1_file($source) . '-' . sha1($text . json_encode($cfg) . ($photoPath ? sha1_file($photoPath) : '')) . '.png';
        $relative = 'uploads/print/' . $name;
        $target = PUBLIC_PATH . '/' . $relative;
        if (is_file($target)) {
            return $relative;
        }

        $img = imagecreatefrompng($source);
        if (!$img) {
            throw new \RuntimeException('Le visuel d\'impression doit être un PNG valide.');
        }
        imagealphablending($img, true);
        imagesavealpha($img, true);
        $w = imagesx($img);
        $h = imagesy($img);

        if ($photoPath !== null) {
            self::placePhoto($img, $photoPath, [
                (int) round((float) $cfg['photo_x'] * $w),
                (int) round((float) $cfg['photo_y'] * $h),
                max(1, (int) round((float) $cfg['photo_w'] * $w)),
                max(1, (int) round((float) $cfg['photo_h'] * $h)),
            ], (string) $cfg['photo_shape']);
        }

        if ($text !== '') {
            self::drawText($img, $text, $cfg, $w, $h);
        }

        if (!is_dir(dirname($target))) {
            mkdir(dirname($target), 0755, true);
        }
        imagepng($img, $target, 6);
        imagedestroy($img);
        return $relative;
    }

    private static function drawText(\GdImage $img, string $text, array $cfg, int $w, int $h): void
    {
        $font = STORAGE_PATH . '/fonts/' . (self::FONTS[$cfg['font']] ?? self::FONTS['serif']);
        $size = max(8.0, (float) $cfg['size'] * $h);
        $maxWidth = (float) $cfg['max_width'] * $w;
        // Réduit la taille jusqu'à ce que le texte tienne dans la largeur autorisée.
        while ($size > 8) {
            $box = imagettfbbox($size, 0, $font, $text);
            if (abs($box[2] - $box[0]) <= $maxWidth) {
                break;
            }
            $size *= 0.94;
        }
        $box = imagettfbbox($size, 0, $font, $text);
        $textWidth = abs($box[2] - $box[0]);
        $x = (int) round((float) $cfg['x'] * $w - $textWidth / 2 - $box[0]);
        $y = (int) round((float) $cfg['y'] * $h);
        [$r, $g, $b] = self::hexToRgb((string) $cfg['color']);
        imagettftext($img, $size, 0, $x, $y, imagecolorallocate($img, $r, $g, $b), $font, $text);
    }

    /** Recadre la photo pour remplir la zone (« cover »), puis la découpe selon la forme choisie. */
    private static function placePhoto(\GdImage $canvas, string $photoPath, array $box, string $shape): void
    {
        [$bx, $by, $bw, $bh] = $box;
        $photo = imagecreatefromstring((string) file_get_contents($photoPath));
        if (!$photo) {
            throw new \RuntimeException('Photo client illisible.');
        }
        $pw = imagesx($photo);
        $ph = imagesy($photo);
        $scale = max($bw / $pw, $bh / $ph);
        $srcW = (int) round($bw / $scale);
        $srcH = (int) round($bh / $scale);
        $srcX = (int) round(($pw - $srcW) / 2);
        $srcY = (int) round(($ph - $srcH) * 0.35); // légèrement vers le haut : la tête du cheval

        $layer = imagecreatetruecolor($bw, $bh);
        imagealphablending($layer, false);
        imagesavealpha($layer, true);
        imagecopyresampled($layer, $photo, 0, 0, $srcX, $srcY, $bw, $bh, $srcW, $srcH);
        imagedestroy($photo);

        // Rend transparents les pixels hors de la forme, ligne par ligne (rapide même en haute définition).
        $clear = imagecolorallocatealpha($layer, 0, 0, 0, 127);
        if ($shape === 'circle' || $shape === 'arch') {
            $r = $bw / 2;
            $cy = $shape === 'circle' ? $bh / 2 : $r;
            $ry = $shape === 'circle' ? $bh / 2 : $r;
            for ($y = 0; $y < $bh; $y++) {
                if ($shape === 'arch' && $y >= $cy) {
                    break;
                }
                $dy = ($y + .5 - $cy) / $ry;
                $half = $dy * $dy >= 1 ? 0 : $r * sqrt(1 - $dy * $dy);
                $left = (int) floor($r - $half);
                $right = (int) ceil($r + $half);
                if ($left > 0) {
                    imagefilledrectangle($layer, 0, $y, $left - 1, $y, $clear);
                }
                if ($right < $bw) {
                    imagefilledrectangle($layer, $right, $y, $bw - 1, $y, $clear);
                }
            }
        }

        imagealphablending($canvas, true);
        imagecopy($canvas, $layer, $bx, $by, 0, 0, $bw, $bh);
        imagedestroy($layer);
    }

    /** Publie le visuel non personnalisé (stocké hors du web) pour que Printful puisse le récupérer. */
    public static function publish(string $designFile): string
    {
        $source = STORAGE_PATH . '/designs/' . basename($designFile);
        if (!is_file($source)) {
            throw new \RuntimeException('Visuel d\'impression introuvable : ' . $designFile);
        }
        $ext = strtolower(pathinfo($source, PATHINFO_EXTENSION)) ?: 'png';
        $relative = 'uploads/print/' . sha1_file($source) . '.' . $ext;
        if (!is_file(PUBLIC_PATH . '/' . $relative)) {
            copy($source, PUBLIC_PATH . '/' . $relative);
        }
        return $relative;
    }

    public static function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if (!preg_match('/^[0-9a-f]{6}$/i', $hex)) {
            return [0, 0, 0];
        }
        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }
}
