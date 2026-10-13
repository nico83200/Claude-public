<?php

namespace App\Support;

/** Prépare un fichier pour Claude : PDF tel quel, photo réduite à 1 600 px (JPEG) pour rester rapide. */
class AiImage
{
    /** @return array{0: ?string, 1: string} contenu (null si illisible) et type */
    public static function prepare(string $path, string $mime): array
    {
        if ($mime === 'application/pdf') {
            return [file_get_contents($path), 'application/pdf'];
        }
        $image = @imagecreatefromstring((string) file_get_contents($path));
        if (! $image) {
            return [null, $mime];
        }
        $w = imagesx($image);
        $h = imagesy($image);
        $ratio = min(1, 1600 / max($w, $h));
        $out = imagecreatetruecolor((int) round($w * $ratio), (int) round($h * $ratio));
        imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));
        imagecopyresampled($out, $image, 0, 0, 0, 0, imagesx($out), imagesy($out), $w, $h);
        ob_start();
        imagejpeg($out, null, 85);

        return [(string) ob_get_clean(), 'image/jpeg'];
    }
}
