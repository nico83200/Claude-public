<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Settings;
use App\Services\AiPortrait;
use App\Services\PortraitStyles;
use App\Services\Upload;

/**
 * Styles du portrait IA : styles proposés, consigne de chacun et images d'exemple,
 * générées une à une à partir d'une photo test (même service et même consigne que pour les clientes).
 */
final class PortraitStylesController extends BaseController
{
    public function index(): void
    {
        $photo = (string) setting('ai_style_test_photo', '');
        $path = $photo !== '' ? Upload::customerPhotoPath($photo) : null;
        $this->render('portrait-styles', [
            'pageTitle' => 'Styles du portrait',
            'enabled'   => array_keys(PortraitStyles::enabled()),
            'testPhoto' => $path && is_file($path) ? self::thumbnail($path) : null,
            'provider'  => \App\Services\AiImage::provider(),
        ]);
    }

    public function save(): void
    {
        $enabled = array_values(array_intersect(array_keys(PortraitStyles::STYLES), (array) ($_POST['styles'] ?? [])));
        if (!$enabled) {
            flash('error', 'Gardez au moins un style proposé.');
            redirect('admin/portrait-styles');
        }
        Settings::set('ai_styles', implode(',', $enabled));
        foreach (array_keys(PortraitStyles::STYLES) as $key) {
            $value = trim((string) ($_POST['prompt'][$key] ?? ''));
            $same = preg_replace('/\s+/', ' ', $value) === preg_replace('/\s+/', ' ', PortraitStyles::defaultPrompt($key));
            if (!empty($_POST['reset'][$key]) || $same) {
                $value = ''; // consigne d'origine, mise à jour avec le site
            }
            Settings::set(PortraitStyles::settingKey($key), mb_substr($value, 0, 6000));
        }
        flash('success', 'Styles du portrait enregistrés.');
        redirect('admin/portrait-styles');
    }

    /** Photo test utilisée pour les exemples. */
    public function photo(): void
    {
        $file = Upload::files('photo')[0] ?? null;
        try {
            $file ?? throw new \RuntimeException('Choisissez une photo.');
            Settings::set('ai_style_test_photo', Upload::customerPhoto($file));
            flash('success', 'Photo test enregistrée : vous pouvez générer les exemples.');
        } catch (\RuntimeException $e) {
            flash('error', $e->getMessage());
        }
        redirect('admin/portrait-styles');
    }

    /** Lance le dessin d'un style avec la photo test (appelé style par style par la page). */
    public function generate(): void
    {
        $style = (string) input('style');
        $photo = (string) setting('ai_style_test_photo', '');
        if (!isset(PortraitStyles::STYLES[$style]) || $photo === '' || !is_file((string) Upload::customerPhotoPath($photo))) {
            json_response(['ok' => false, 'message' => 'Ajoutez d\'abord la photo test.'], 422);
        }
        try {
            AiPortrait::guard();
            $row = AiPortrait::createFromPhoto($photo, $style);
        } catch (\RuntimeException $e) {
            json_response(['ok' => false, 'message' => $e->getMessage()], 422);
        }
        session_write_close();
        ignore_user_abort(true);
        @set_time_limit(300);
        if (function_exists('litespeed_finish_request') || function_exists('fastcgi_finish_request')) {
            $body = json_encode(['ok' => true] + self::state($row), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            header('Content-Type: application/json; charset=utf-8');
            header('Content-Length: ' . strlen($body));
            echo $body;
            function_exists('litespeed_finish_request') ? litespeed_finish_request() : fastcgi_finish_request();
            AiPortrait::run($row);
            exit;
        }
        json_response(['ok' => true] + self::state(AiPortrait::run($row)));
    }

    public function status(string $token): void
    {
        $row = AiPortrait::find($token) ?? abort(404);
        session_write_close();
        header('Cache-Control: no-store');
        json_response(['ok' => true] + self::state(AiPortrait::refresh($row)));
    }

    /** État d'un exemple ; une fois prêt, il devient l'image d'exemple du style. */
    private static function state(array $row): array
    {
        $state = ['token' => $row['token'], 'status' => $row['status'], 'style' => $row['style']];
        if ($row['status'] === 'ready') {
            $state['example'] = url(self::publishExample($row));
        } elseif ($row['status'] === 'failed') {
            $state['message'] = AiPortrait::adminDiagnostic($row);
        }
        return $state;
    }

    /** Copie le dessin (sans filigrane, 600 × 800) dans uploads/site/styles et l'associe au style. */
    public static function publishExample(array $row): string
    {
        $relative = 'uploads/site/styles/' . $row['style'] . '-' . substr($row['token'], 0, 8) . '.jpg';
        $target = PUBLIC_PATH . '/' . $relative;
        if (!is_file($target)) {
            $art = AiPortrait::artworkPath($row) ?? throw new \RuntimeException('Dessin introuvable.');
            $src = imagecreatefromstring((string) file_get_contents($art)) ?: throw new \RuntimeException('Dessin illisible.');
            $img = imagecreatetruecolor(600, 800);
            imagefill($img, 0, 0, imagecolorallocate($img, 249, 247, 243));
            $scale = min(600 / imagesx($src), 800 / imagesy($src));
            $w = (int) round(imagesx($src) * $scale);
            $h = (int) round(imagesy($src) * $scale);
            imagecopyresampled($img, $src, (int) ((600 - $w) / 2), (int) ((800 - $h) / 2), 0, 0, $w, $h, imagesx($src), imagesy($src));
            if (!is_dir(dirname($target))) {
                mkdir(dirname($target), 0755, true);
            }
            imagejpeg($img, $target, 86);
            imagedestroy($img);
            imagedestroy($src);
            // Les anciens exemples de ce style ne servent plus
            foreach (glob(PUBLIC_PATH . '/uploads/site/styles/' . $row['style'] . '-*.jpg') ?: [] as $old) {
                if ($old !== $target) {
                    @unlink($old);
                }
            }
            PortraitStyles::setExample($row['style'], $relative);
        }
        return PortraitStyles::example($row['style']) ?? $relative;
    }

    private static function thumbnail(string $path): ?string
    {
        $src = @imagecreatefromjpeg($path);
        if (!$src) {
            return null;
        }
        $img = gd_resize($src, 240, (int) round(imagesy($src) * 240 / imagesx($src)));
        ob_start();
        imagejpeg($img, null, 80);
        return 'data:image/jpeg;base64,' . base64_encode((string) ob_get_clean());
    }
}
