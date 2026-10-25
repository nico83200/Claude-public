<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Images de la page d'accueil modifiables depuis Admin › Page d'accueil.
 * Chaque emplacement garde une image d'origine : « Remettre l'image d'origine » la rétablit.
 */
final class HomeImages
{
    public const DIR = 'uploads/site';

    public const SLOTS = [
        'hero' => [
            'label'   => 'Grande photo en haut de page (arche)',
            'help'    => 'Format portrait 4:5 — idéalement 1000 × 1250 px. Le sujet est centré dans l\'arche.',
            'default' => 'assets/img/hero.jpg',
            'alt'     => 'Sweat Crinière Club crème avec le logo brodé',
        ],
        'portrait' => [
            'label'   => 'Bloc « Portrait aquarelle »',
            'help'    => 'Format carré — 1000 × 1000 px. Par défaut : la photo du produit portrait aquarelle.',
            'default' => null,
            'alt'     => 'Portrait aquarelle d\'un cheval, encadré, avec son nom et son année de naissance',
        ],
        'perso' => [
            'label'   => 'Bloc « Une pièce unique, à son image »',
            'help'    => 'Format carré — 1000 × 1000 px.',
            'default' => 'assets/img/products/cheval-sweat-nom.jpg',
            'alt'     => 'Sweat personnalisé au nom du cheval',
        ],
    ];

    /** Image à afficher (chemin relatif pour image_url), $fallback si l'emplacement n'a pas d'image d'origine. */
    public static function src(string $slot, ?string $fallback = null): ?string
    {
        $custom = (string) setting('home_img_' . $slot, '');
        if ($custom !== '' && is_file(PUBLIC_PATH . '/' . $custom)) {
            return $custom;
        }
        return self::SLOTS[$slot]['default'] ?? $fallback;
    }

    public static function alt(string $slot): string
    {
        $alt = trim((string) setting('home_alt_' . $slot, ''));
        return $alt !== '' ? $alt : self::SLOTS[$slot]['alt'];
    }

    public static function isCustom(string $slot): bool
    {
        return (string) setting('home_img_' . $slot, '') !== '';
    }

    /**
     * Enregistre une image envoyée : vérifiée, redressée, réduite à 2000 px maximum et réenregistrée
     * (JPEG, ou PNG si elle a de la transparence) pour garder une page d'accueil rapide.
     */
    public static function store(array $file, string $folder = self::DIR, int $max = 2000): string
    {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('Échec de l\'envoi de l\'image (code ' . $file['error'] . ').');
        }
        if ($file['size'] > 15 * 1024 * 1024) {
            throw new \RuntimeException('Image trop lourde (15 Mo maximum).');
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw new \RuntimeException('Format non pris en charge : JPG, PNG ou WebP uniquement.');
        }
        $img = @imagecreatefromstring((string) file_get_contents($file['tmp_name']));
        if (!$img) {
            throw new \RuntimeException('Image illisible.');
        }
        if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
            $rotated = match ((int) (@exif_read_data($file['tmp_name'])['Orientation'] ?? 1)) {
                3 => imagerotate($img, 180, 0),
                6 => imagerotate($img, -90, 0),
                8 => imagerotate($img, 90, 0),
                default => null,
            };
            $img = $rotated ?: $img;
        }
        if (max(imagesx($img), imagesy($img)) > $max) {
            $ratio = $max / max(imagesx($img), imagesy($img));
            $img = gd_resize($img, (int) round(imagesx($img) * $ratio), (int) round(imagesy($img) * $ratio));
        }
        $dir = PUBLIC_PATH . '/' . $folder;
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $base = date('Ym') . '-' . bin2hex(random_bytes(8));
        if ($mime === 'image/png') {
            imagesavealpha($img, true);
            imagepng($img, "$dir/$base.png", 7);
            return $folder . "/$base.png";
        }
        $flat = imagecreatetruecolor(imagesx($img), imagesy($img));
        imagefill($flat, 0, 0, imagecolorallocate($flat, 255, 255, 255));
        imagecopy($flat, $img, 0, 0, 0, 0, imagesx($img), imagesy($img));
        imagejpeg($flat, "$dir/$base.jpg", 86);
        return $folder . "/$base.jpg";
    }

    /** Supprime une image précédemment envoyée ici (jamais les images d'origine du thème). */
    public static function discard(?string $path): void
    {
        if ($path && preg_match('#^uploads/(site|reviews)/[0-9]{6}-[a-f0-9]{16}\.(jpg|png)$#', $path)) {
            @unlink(PUBLIC_PATH . '/' . $path);
        }
    }
}
