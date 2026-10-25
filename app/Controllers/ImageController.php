<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\Thumbs;

final class ImageController
{
    /** Première demande d'une miniature : fabrication, puis le serveur web la sert directement. */
    public function thumb(string $width, string $name): void
    {
        $hash = preg_replace('/\.webp$/', '', $name);
        try {
            $path = Thumbs::generate((int) $width, $hash);
        } catch (\Throwable $e) {
            log_message('images', 'Miniature impossible', ['hash' => $hash, 'error' => $e->getMessage()]);
            $path = null;
        }
        if (!$path) {
            $src = Thumbs::source($hash);
            $src ? redirect(image_url($src), 302) : abort(404);
        }
        header('Content-Type: image/webp');
        header('Cache-Control: public, max-age=2592000');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }
}
