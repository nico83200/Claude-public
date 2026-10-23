<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Génère le fichier d'impression personnalisé (ex. nom du cheval) à partir du visuel de base.
 * Le PNG produit est publié dans /uploads/print/ pour que Printful puisse le télécharger.
 */
final class Personalizer
{
    public const FONTS = [
        'serif' => 'LiberationSerif-Bold.ttf',
        'sans'  => 'LiberationSans-Bold.ttf',
    ];

    public const DEFAULTS = [
        'label'      => 'Nom de votre cheval',
        'max_length' => 18,
        'required'   => false, // si false, le client peut commander la version sans texte
        'font'       => 'serif',
        'color'      => '#1f2a44',
        'x'          => 0.5,   // centre horizontal du texte (0–1, sur le visuel d'impression)
        'y'          => 0.82,  // ligne de base du texte (0–1)
        'size'       => 0.10,  // hauteur de police relative à la hauteur du visuel
        'max_width'  => 0.85,  // largeur max relative
        'uppercase'  => true,
        'preview_x'  => 0.5,   // position de l'aperçu sur la photo produit (0–1)
        'preview_y'  => 0.62,
        'preview_size' => 0.045,
        'preview_color' => '',
    ];

    public static function config(?string $json): array
    {
        return array_replace(self::DEFAULTS, json_field($json));
    }

    public static function sanitize(string $text, int $maxLength): string
    {
        $text = preg_replace('/[^\p{L}\p{N} \'’&.\-]/u', '', $text);
        $text = trim((string) preg_replace('/\s+/u', ' ', (string) $text));
        return mb_substr($text, 0, max(1, $maxLength));
    }

    /**
     * @return string chemin public relatif (ex. "uploads/print/ab12….png")
     */
    public static function render(string $designFile, string $text, array $cfg): string
    {
        $cfg = array_replace(self::DEFAULTS, $cfg);
        $source = STORAGE_PATH . '/designs/' . basename($designFile);
        if (!is_file($source)) {
            throw new \RuntimeException('Visuel d\'impression introuvable : ' . $designFile);
        }
        if ($cfg['uppercase']) {
            $text = mb_strtoupper($text);
        }

        $name = sha1_file($source) . '-' . sha1($text . json_encode($cfg)) . '.png';
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

        if (!is_dir(dirname($target))) {
            mkdir(dirname($target), 0755, true);
        }
        imagepng($img, $target, 6);
        imagedestroy($img);
        return $relative;
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
