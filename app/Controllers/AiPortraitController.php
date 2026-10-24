<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Services\AiPortrait;
use App\Services\Personalizer;
use App\Services\Upload;

/**
 * Création du portrait IA depuis la fiche produit (en AJAX) :
 * POST /portrait-ia (photo) → GET /portrait-ia/{jeton} (suivi) → GET /portrait-ia/{jeton}/apercu (image filigranée).
 */
final class AiPortraitController
{
    public function create(): void
    {
        if (!AiPortrait::enabled()) {
            json_response(['ok' => false, 'message' => 'Le portrait IA n\'est pas encore disponible.'], 503);
        }
        $product = Database::one('SELECT personalizable, personalization FROM products WHERE id = ? AND active = 1', [(int) input('product_id')]);
        if (!$product || !$product['personalizable'] || Personalizer::config($product['personalization'])['mode'] !== 'ai_portrait') {
            json_response(['ok' => false, 'message' => 'Cet article ne propose pas de portrait IA.'], 422);
        }
        if (input('consent') !== '1') {
            json_response(['ok' => false, 'message' => 'Merci d\'accepter l\'envoi de votre photo au service de dessin.'], 422);
        }
        $file = Upload::files('photo')[0] ?? null;
        if (!$file) {
            json_response(['ok' => false, 'message' => 'Ajoutez la photo de votre cheval.'], 422);
        }
        try {
            $row = AiPortrait::create($file);
        } catch (\RuntimeException $e) {
            json_response(['ok' => false, 'message' => $e->getMessage()], 422);
        }
        $remaining = AiPortrait::remaining();
        session_write_close(); // le dessin peut durer une minute : ne bloque pas les autres requêtes

        // Réponse immédiate puis dessin en arrière-plan lorsque le serveur le permet (LiteSpeed, PHP-FPM) ;
        // sinon le navigateur attend la fin du dessin. Dans les deux cas il suit ensuite l'avancement.
        ignore_user_abort(true);
        @set_time_limit(300);
        if (function_exists('litespeed_finish_request') || function_exists('fastcgi_finish_request')) {
            $body = json_encode(['ok' => true, 'remaining' => $remaining] + AiPortrait::publicState($row), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            header('Content-Type: application/json; charset=utf-8');
            header('Content-Length: ' . strlen($body));
            header('Cache-Control: no-store');
            echo $body;
            function_exists('litespeed_finish_request') ? litespeed_finish_request() : fastcgi_finish_request();
            AiPortrait::run($row);
            exit;
        }
        $row = AiPortrait::run($row);
        json_response(['ok' => true, 'remaining' => $remaining] + AiPortrait::publicState($row));
    }

    public function status(string $token): void
    {
        $row = $this->owned($token);
        session_write_close();
        header('Cache-Control: no-store');
        json_response(['ok' => true] + AiPortrait::publicState(AiPortrait::refresh($row)));
    }

    public function preview(string $token): void
    {
        $row = $this->owned($token);
        session_write_close();
        $path = AiPortrait::previewPath($row) ?? abort(404);
        header('Content-Type: image/jpeg');
        header('Cache-Control: private, max-age=86400');
        readfile($path);
        exit;
    }

    private function owned(string $token): array
    {
        if (!AiPortrait::owns($token)) {
            abort(404);
        }
        return AiPortrait::find($token) ?? abort(404);
    }
}
