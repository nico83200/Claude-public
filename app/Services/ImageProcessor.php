<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Compression et redimensionnement des photos (GD). Les originaux lourds ne
 * sont pas conservés : on stocke une version optimisée et une miniature.
 */
class ImageProcessor
{
    /** @return array{path: string, thumb: string, size: int} */
    public function store(UploadedFile $file, string $directory): array
    {
        $base = $directory.'/'.Str::uuid();
        $image = @imagecreatefromstring(file_get_contents($file->getRealPath()));
        if (! $image) {
            // Format non pris en charge par GD (ex. HEIC) : stockage tel quel.
            $path = $file->storeAs($directory, Str::uuid().'.'.$file->extension(), 'local');

            return ['path' => $path, 'thumb' => $path, 'size' => $file->getSize()];
        }
        $image = $this->orient($image, $file->getRealPath());

        $large = $this->resize($image, 1600);
        $thumb = $this->resize($image, 400);
        $disk = Storage::disk('local');
        $disk->put($base.'.jpg', $this->jpeg($large, 82));
        $disk->put($base.'_thumb.jpg', $this->jpeg($thumb, 75));

        return ['path' => $base.'.jpg', 'thumb' => $base.'_thumb.jpg', 'size' => $disk->size($base.'.jpg') + $disk->size($base.'_thumb.jpg')];
    }

    private function resize($image, int $max)
    {
        $w = imagesx($image);
        $h = imagesy($image);
        $ratio = min(1, $max / max($w, $h));
        $nw = (int) round($w * $ratio);
        $nh = (int) round($h * $ratio);
        $out = imagecreatetruecolor($nw, $nh);
        imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));
        imagecopyresampled($out, $image, 0, 0, 0, 0, $nw, $nh, $w, $h);

        return $out;
    }

    private function jpeg($image, int $quality): string
    {
        ob_start();
        imagejpeg($image, null, $quality);

        return (string) ob_get_clean();
    }

    private function orient($image, string $path)
    {
        if (! function_exists('exif_read_data')) {
            return $image;
        }
        $exif = @exif_read_data($path);
        $angle = match ($exif['Orientation'] ?? 1) {
            3 => 180, 6 => -90, 8 => 90, default => 0
        };

        return $angle ? imagerotate($image, $angle, 0) : $image;
    }
}
