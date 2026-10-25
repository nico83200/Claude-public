<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Miniatures d'images en WebP, aux largeurs utiles seulement (une carte produit de 300 px ne
 * télécharge plus une photo de 2000 px).
 *
 * L'URL pointe vers public/uploads/thumbs/{largeur}/{empreinte}.webp : une fois créée, l'image est
 * servie directement par le serveur web. À la première demande, le fichier n'existe pas encore :
 * la requête arrive au site qui la fabrique (route /uploads/thumbs/…) à partir de la source notée
 * dans storage/cache/thumbs (jamais depuis l'URL : seules les images affichées par le site
 * peuvent être miniaturisées).
 */
final class Thumbs
{
    public const WIDTHS = [160, 400, 600, 800, 1200];
    public const DIR = 'uploads/thumbs';
    private const MAP = STORAGE_PATH . '/cache/thumbs';

    public static function enabled(): bool
    {
        return function_exists('imagewebp') && (gd_info()['WebP Support'] ?? false);
    }

    /** URL de la miniature (ou de l'image d'origine si elle ne s'y prête pas : SVG, GIF, données…). */
    public static function url(?string $src, int $width): string
    {
        if (!$src || !self::enabled() || !preg_match('/\.(jpe?g|png|webp)(\?|$)/i', $src) && !str_starts_with($src, 'https://')) {
            return image_url($src);
        }
        $width = self::fit($width);
        $local = !preg_match('#^https?://#', $src);
        if ($local) {
            $file = PUBLIC_PATH . '/' . ltrim($src, '/');
            if (!is_file($file)) {
                return image_url($src);
            }
            $hash = substr(sha1($src . '|' . filemtime($file)), 0, 20);
        } else {
            $hash = substr(sha1($src), 0, 20);
        }
        $map = self::MAP . '/' . $hash;
        if (!is_file($map)) {
            if (!is_dir(self::MAP)) {
                @mkdir(self::MAP, 0755, true);
            }
            @file_put_contents($map, $src);
        }
        return url(self::DIR . '/' . $width . '/' . $hash . '.webp');
    }

    /** Attributs src/srcset prêts à insérer dans une balise <img>. */
    public static function attrs(?string $src, array $widths, string $sizes): string
    {
        $default = self::url($src, $widths[(int) floor((count($widths) - 1) / 2)]);
        $set = [];
        foreach ($widths as $w) {
            $u = self::url($src, $w);
            if ($u === image_url($src)) {
                return 'src="' . e($default) . '"';
            }
            $set[] = e($u) . ' ' . self::fit($w) . 'w';
        }
        return 'src="' . e($default) . '" srcset="' . implode(', ', $set) . '" sizes="' . e($sizes) . '"';
    }

    private static function fit(int $width): int
    {
        foreach (self::WIDTHS as $w) {
            if ($w >= $width) {
                return $w;
            }
        }
        return self::WIDTHS[count(self::WIDTHS) - 1];
    }

    /** Fabrique la miniature demandée ; renvoie son chemin, ou null (la source sera servie). */
    public static function generate(int $width, string $hash): ?string
    {
        if (!in_array($width, self::WIDTHS, true) || !preg_match('/^[a-f0-9]{20}$/', $hash) || !is_file(self::MAP . '/' . $hash)) {
            return null;
        }
        $src = trim((string) file_get_contents(self::MAP . '/' . $hash));
        if (preg_match('#^https?://#', $src)) {
            $response = Http::request('GET', $src, [], null, 20);
            if ($response['status'] !== 200 || strlen($response['body']) > 25 * 1024 * 1024) {
                return null;
            }
            $data = $response['body'];
        } else {
            $file = realpath(PUBLIC_PATH . '/' . ltrim($src, '/'));
            if (!$file || !str_starts_with($file, realpath(PUBLIC_PATH) . DIRECTORY_SEPARATOR) || !is_file($file)) {
                return null;
            }
            $data = (string) file_get_contents($file);
        }
        $img = @imagecreatefromstring($data);
        if (!$img) {
            return null;
        }
        if (imagesx($img) > $width) {
            $img = gd_resize($img, $width);
        }
        imagepalettetotruecolor($img);
        imagealphablending($img, true);
        imagesavealpha($img, true);
        $dir = PUBLIC_PATH . '/' . self::DIR . '/' . $width;
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $path = "$dir/$hash.webp";
        imagewebp($img, $path, 80);
        return is_file($path) ? $path : null;
    }

    /** Source d'origine (pour rediriger si la miniature ne peut pas être fabriquée). */
    public static function source(string $hash): ?string
    {
        $map = self::MAP . '/' . $hash;
        return preg_match('/^[a-f0-9]{20}$/', $hash) && is_file($map) ? trim((string) file_get_contents($map)) : null;
    }
}
