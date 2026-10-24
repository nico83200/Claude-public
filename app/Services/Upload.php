<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

final class Upload
{
    private const IMAGE_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];

    /** Normalise $_FILES['x'] (simple ou multiple) en liste de fichiers. */
    public static function files(string $field): array
    {
        $f = $_FILES[$field] ?? null;
        if (!$f) {
            return [];
        }
        if (!is_array($f['name'])) {
            return $f['error'] === UPLOAD_ERR_NO_FILE ? [] : [$f];
        }
        $list = [];
        foreach ($f['name'] as $i => $name) {
            if ($f['error'][$i] !== UPLOAD_ERR_NO_FILE) {
                $list[] = ['name' => $name, 'tmp_name' => $f['tmp_name'][$i], 'error' => $f['error'][$i], 'size' => $f['size'][$i]];
            }
        }
        return $list;
    }

    /** Image produit publique → "uploads/products/xxx.jpg" */
    public static function productImage(array $file): string
    {
        $ext = self::validate($file, self::IMAGE_TYPES, 8);
        $name = date('Ym') . '-' . bin2hex(random_bytes(8)) . '.' . $ext;
        self::move($file, PUBLIC_PATH . '/uploads/products/' . $name);
        return 'uploads/products/' . $name;
    }

    /** Visuel d'impression haute définition, stocké hors du web → nom de fichier */
    public static function design(array $file): string
    {
        self::validate($file, ['image/png' => 'png'], 40);
        $name = bin2hex(random_bytes(10)) . '.png';
        self::move($file, STORAGE_PATH . '/designs/' . $name);
        return $name;
    }

    /** Fichier d'impression final (ex. portrait illustré) → URL publique lue par Printful. */
    public static function printFile(array $file): string
    {
        $ext = self::validate($file, ['image/png' => 'png', 'image/jpeg' => 'jpg'], 60);
        $name = 'portrait-' . bin2hex(random_bytes(12)) . '.' . $ext;
        self::move($file, PUBLIC_PATH . '/uploads/print/' . $name);
        return 'uploads/print/' . $name;
    }

    public const PHOTO_DIR = STORAGE_PATH . '/customer-photos';

    /**
     * Photo du cheval envoyée par une cliente : vérifiée, redressée (EXIF), réduite à 4000 px
     * et réenregistrée en JPEG — ce qui supprime au passage les métadonnées (GPS…).
     * Stockée hors du web. Retourne le nom de fichier.
     */
    public static function customerPhoto(array $file): string
    {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new \RuntimeException($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE
                ? 'Votre photo est trop lourde (20 Mo maximum).'
                : 'L\'envoi de la photo a échoué, merci de réessayer.');
        }
        if ($file['size'] > 20 * 1024 * 1024) {
            throw new \RuntimeException('Votre photo est trop lourde (20 Mo maximum).');
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw new \RuntimeException('Format non pris en charge : envoyez une photo JPG, PNG ou WebP.');
        }
        $img = @imagecreatefromstring((string) file_get_contents($file['tmp_name']));
        if (!$img) {
            throw new \RuntimeException('Cette photo est illisible, merci d\'en choisir une autre.');
        }
        if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
            $orientation = (int) (@exif_read_data($file['tmp_name'])['Orientation'] ?? 1);
            $rotated = match ($orientation) {
                3 => imagerotate($img, 180, 0),
                6 => imagerotate($img, -90, 0),
                8 => imagerotate($img, 90, 0),
                default => null,
            };
            if ($rotated) {
                $img = $rotated;
            }
        }
        if (min(imagesx($img), imagesy($img)) < 500) {
            throw new \RuntimeException('Photo trop petite pour une impression de qualité (500 px minimum).');
        }
        $max = 4000;
        if (max(imagesx($img), imagesy($img)) > $max) {
            $ratio = $max / max(imagesx($img), imagesy($img));
            $img = gd_resize($img, (int) round(imagesx($img) * $ratio), (int) round(imagesy($img) * $ratio));
        }
        // Fond blanc sous une éventuelle transparence (PNG)
        $flat = imagecreatetruecolor(imagesx($img), imagesy($img));
        imagefill($flat, 0, 0, imagecolorallocate($flat, 255, 255, 255));
        imagecopy($flat, $img, 0, 0, 0, 0, imagesx($img), imagesy($img));

        if (!is_dir(self::PHOTO_DIR)) {
            mkdir(self::PHOTO_DIR, 0755, true);
        }
        $name = date('Ymd') . '-' . bin2hex(random_bytes(12)) . '.jpg';
        imagejpeg($flat, self::PHOTO_DIR . '/' . $name, 92);
        return $name;
    }

    /** Chemin absolu d'une photo cliente à partir de son nom (protégé contre les remontées de dossier). */
    public static function customerPhotoPath(?string $name): ?string
    {
        if (!$name || !preg_match('/^\d{8}-[a-f0-9]{24}\.jpg$/', $name)) {
            return null;
        }
        $path = self::PHOTO_DIR . '/' . $name;
        return is_file($path) ? $path : null;
    }

    /**
     * Supprime les photos clientes de plus de $days jours qui ne servent à aucune commande payée
     * ni à un portrait IA conservé (paniers abandonnés, essais) : minimisation des données (RGPD).
     */
    public static function cleanupCustomerPhotos(int $days = 30): int
    {
        $keep = array_flip(array_column(Database::all(
            "SELECT i.photo_file FROM order_items i JOIN orders o ON o.id = i.order_id
             WHERE i.photo_file IS NOT NULL AND o.status NOT IN ('pending', 'canceled')"
        ), 'photo_file'));
        if (AiPortrait::enabled()) {
            $keep += array_flip(array_column(Database::all('SELECT photo_file FROM ai_portraits'), 'photo_file'));
        }
        $limit = date('Ymd', time() - $days * 86400);
        $deleted = 0;
        foreach (glob(self::PHOTO_DIR . '/*.jpg') ?: [] as $path) {
            $name = basename($path);
            if (preg_match('/^(\d{8})-[a-f0-9]{24}\.jpg$/', $name, $m) && $m[1] < $limit && !isset($keep[$name])) {
                $deleted += @unlink($path) ? 1 : 0;
            }
        }
        return $deleted;
    }

    private static function validate(array $file, array $types, int $maxMb): string
    {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('Échec du téléversement (code ' . $file['error'] . ') — vérifiez upload_max_filesize.');
        }
        if ($file['size'] > $maxMb * 1024 * 1024) {
            throw new \RuntimeException("Fichier trop volumineux (max $maxMb Mo).");
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        if (!isset($types[$mime])) {
            throw new \RuntimeException('Format non autorisé (' . $mime . ').');
        }
        if (@getimagesize($file['tmp_name']) === false) {
            throw new \RuntimeException('Le fichier n\'est pas une image valide.');
        }
        return $types[$mime];
    }

    private static function move(array $file, string $target): void
    {
        if (!is_dir(dirname($target))) {
            mkdir(dirname($target), 0755, true);
        }
        if (!move_uploaded_file($file['tmp_name'], $target) && !rename($file['tmp_name'], $target)) {
            throw new \RuntimeException('Impossible d\'enregistrer le fichier.');
        }
    }
}
