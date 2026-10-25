<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Settings;
use App\Services\AiPortrait;
use App\Services\MangaIllustration;
use App\Services\Upload;

/**
 * Illustration manga (t-shirt) : consignes (base + vue de face / de dos), modèle, illustration de référence
 * et exemples générés à partir de deux photos test.
 */
final class MangaController extends BaseController
{
    public function index(): void
    {
        $thumb = static function (string $key): ?string {
            $photo = (string) setting($key, '');
            $path = $photo !== '' ? Upload::customerPhotoPath($photo) : null;
            return $path && is_file($path) ? self::thumbnail($path) : null;
        };
        $reference = MangaIllustration::referencePath();
        $this->render('manga', [
            'pageTitle' => 'Illustration manga',
            'rider'     => $thumb('ai_manga_test_rider'),
            'horse'     => $thumb('ai_manga_test_horse'),
            'reference' => $reference ? self::thumbnail($reference) : null,
            'customReference' => is_file(AiPortrait::DIR . '/reference-manga.jpg'),
            'provider'  => \App\Services\AiImage::provider(),
        ]);
    }

    public function save(): void
    {
        $fields = ['ai_manga_prompt' => MangaIllustration::BASE_PROMPT] + array_combine(
            array_map(static fn ($v) => 'ai_manga_' . $v, array_keys(MangaIllustration::VIEW_PROMPTS)),
            array_values(MangaIllustration::VIEW_PROMPTS)
        );
        foreach ($fields as $key => $default) {
            $value = trim(str_replace("\r\n", "\n", (string) ($_POST[$key] ?? '')));
            $same = preg_replace('/\s+/', ' ', $value) === preg_replace('/\s+/', ' ', $default);
            Settings::set($key, !empty($_POST['reset'][$key]) || $same ? '' : mb_substr($value, 0, 12000));
        }
        $model = trim((string) input('ai_manga_model'));
        Settings::set('ai_manga_model', preg_match('#^[a-z0-9_.-]+/[a-z0-9_.-]+(:[a-f0-9]{64})?$#i', $model) && $model !== MangaIllustration::DEFAULT_MODEL ? $model : '');
        flash('success', 'Illustration manga enregistrée.');
        redirect('admin/illustration-manga');
    }

    /** Photos test (cavalière, cheval) et illustration de référence. */
    public function photos(): void
    {
        try {
            foreach (['rider' => 'ai_manga_test_rider', 'horse' => 'ai_manga_test_horse'] as $field => $key) {
                if ($file = Upload::files($field)[0] ?? null) {
                    Settings::set($key, Upload::customerPhoto($file));
                }
            }
            if ($file = Upload::files('reference')[0] ?? null) {
                $stored = (string) Upload::customerPhotoPath(Upload::customerPhoto($file));
                if (!is_dir(AiPortrait::DIR)) {
                    mkdir(AiPortrait::DIR, 0755, true);
                }
                copy($stored, AiPortrait::DIR . '/reference-manga.jpg');
            } elseif (input('reference_reset') === '1') {
                @unlink(AiPortrait::DIR . '/reference-manga.jpg');
            }
            flash('success', 'Images enregistrées.');
        } catch (\RuntimeException $e) {
            flash('error', $e->getMessage());
        }
        redirect('admin/illustration-manga');
    }

    public function generate(): void
    {
        $rider = (string) setting('ai_manga_test_rider', '');
        $horse = (string) setting('ai_manga_test_horse', '');
        if ($rider === '' || $horse === '') {
            json_response(['ok' => false, 'message' => 'Ajoutez d\'abord les deux photos test.'], 422);
        }
        try {
            AiPortrait::guard();
            $row = AiPortrait::createManga($rider, $horse, (string) input('view'));
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

    private static function state(array $row): array
    {
        $state = ['token' => $row['token'], 'status' => $row['status'], 'view' => $row['view']];
        if ($row['status'] === 'ready') {
            $state['example'] = url(self::publishExample($row));
        } elseif ($row['status'] === 'failed') {
            $state['message'] = AiPortrait::adminDiagnostic($row);
        }
        return $state;
    }

    /** Copie l'illustration (sans filigrane, 800 px de large) dans uploads/site/manga et l'associe à sa vue. */
    public static function publishExample(array $row): string
    {
        $view = MangaIllustration::view($row['view'] ?? null);
        $relative = 'uploads/site/manga/' . $view . '-' . substr($row['token'], 0, 8) . '.jpg';
        $target = PUBLIC_PATH . '/' . $relative;
        if (!is_file($target)) {
            $art = AiPortrait::artworkPath($row) ?? throw new \RuntimeException('Illustration introuvable.');
            $src = imagecreatefromstring((string) file_get_contents($art)) ?: throw new \RuntimeException('Illustration illisible.');
            $img = gd_resize($src, 800, (int) round(imagesy($src) * 800 / imagesx($src)));
            if (!is_dir(dirname($target))) {
                mkdir(dirname($target), 0755, true);
            }
            imagejpeg($img, $target, 86);
            foreach (glob(PUBLIC_PATH . '/uploads/site/manga/' . $view . '-*.jpg') ?: [] as $old) {
                if ($old !== $target) {
                    @unlink($old);
                }
            }
            MangaIllustration::setExample($view, $relative);
        }
        return MangaIllustration::examples()[$view] ?? $relative;
    }

    private static function thumbnail(string $path): ?string
    {
        $src = @imagecreatefromstring((string) file_get_contents($path));
        if (!$src) {
            return null;
        }
        $img = gd_resize($src, 200, (int) round(imagesy($src) * 200 / imagesx($src)));
        ob_start();
        imagejpeg($img, null, 80);
        return 'data:image/jpeg;base64,' . base64_encode((string) ob_get_clean());
    }
}
