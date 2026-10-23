<?php
declare(strict_types=1);

namespace App\Services;

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
