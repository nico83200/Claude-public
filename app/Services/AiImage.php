<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Clients des services de dessin par IA, appelés en cURL (sans dépendance) :
 * - OpenAI  : POST /v1/images/edits (réponse synchrone, image en base64) ;
 * - Replicate : prédiction asynchrone (FLUX Kontext par défaut), suivie par son identifiant ;
 * - démo    : effet croquis calculé localement avec GD, pour tester le parcours sans compte IA.
 */
final class AiImage
{
    private const OPENAI = 'https://api.openai.com/v1/';
    private const REPLICATE = 'https://api.replicate.com/v1/';

    /** Adresse de l'API (modifiable dans config.php, utilisé par les tests avec un faux serveur). */
    private static function base(string $service): string
    {
        $default = $service === 'openai' ? self::OPENAI : self::REPLICATE;
        return rtrim((string) config('ai.' . $service . '_base', $default), '/') . '/';
    }

    public static function provider(): string
    {
        $provider = (string) config('ai.provider', 'demo');
        return match (true) {
            $provider === 'openai' && (string) config('ai.openai_key') !== ''         => 'openai',
            $provider === 'replicate' && (string) config('ai.replicate_token') !== '' => 'replicate',
            default                                                                     => 'demo',
        };
    }

    public static function canUpscale(): bool
    {
        return (bool) config('ai.upscale') && (string) config('ai.replicate_token') !== '';
    }

    // ------------------------------------------------------------------ OpenAI

    /** Redessine l'image (JPEG) selon le prompt ; retourne l'image produite (PNG). */
    public static function openaiEdit(string $jpeg, string $prompt): string
    {
        $model = (string) config('ai.openai_model') ?: 'gpt-image-1.5';
        $tmp = tempnam(sys_get_temp_dir(), 'ai') . '.jpg';
        file_put_contents($tmp, $jpeg);
        $fields = [
            'model'         => $model,
            'image'         => new \CURLFile($tmp, 'image/jpeg', 'cheval.jpg'),
            'prompt'        => $prompt,
            'size'          => '1024x1536',
            'quality'       => in_array(config('ai.openai_quality'), ['low', 'medium', 'high'], true) ? config('ai.openai_quality') : 'medium',
            'output_format' => 'png',
            'n'             => '1',
        ];
        // Fidélité élevée à la photo (marques, robe, morphologie) : non proposée par les modèles « mini ».
        if (!str_contains($model, 'mini')) {
            $fields['input_fidelity'] = 'high';
        }
        try {
            $res = self::openai($fields);
            if ($res['status'] === 400 && str_contains($res['body'], 'input_fidelity')) {
                unset($fields['input_fidelity']);
                $res = self::openai($fields);
            }
        } finally {
            @unlink($tmp);
        }
        if ($res['status'] >= 300) {
            throw new \RuntimeException('OpenAI : ' . self::errorMessage($res));
        }
        $b64 = (string) ($res['json']['data'][0]['b64_json'] ?? '');
        $png = $b64 !== '' ? base64_decode($b64, true) : false;
        if (!$png) {
            throw new \RuntimeException('OpenAI : réponse sans image.');
        }
        return $png;
    }

    private static function openai(array $fields): array
    {
        return Http::request('POST', self::base('openai') . 'images/edits', [
            'Authorization: Bearer ' . config('ai.openai_key'),
        ], $fields, 240);
    }

    /** Vérifie la clé et l'accès au modèle (bouton « Tester » de l'administration). */
    public static function openaiCheck(): string
    {
        $model = (string) config('ai.openai_model') ?: 'gpt-image-1.5';
        $res = Http::request('GET', self::base('openai') . 'models/' . rawurlencode($model), ['Authorization: Bearer ' . config('ai.openai_key')]);
        if ($res['status'] >= 300) {
            throw new \RuntimeException('OpenAI : ' . self::errorMessage($res));
        }
        return 'Connexion OpenAI OK, modèle « ' . $model . ' » disponible.';
    }

    // --------------------------------------------------------------- Replicate

    /**
     * Lance une prédiction. « propriétaire/modèle » (modèle officiel) ou « propriétaire/modèle:version ».
     * $wait > 0 : Replicate attend jusqu'à $wait secondes avant de répondre (mode synchrone).
     * @return array{id:string, status:string, output:mixed, error:?string}
     */
    public static function replicateCreate(string $model, array $input, int $wait = 0): array
    {
        $headers = ['Authorization: Bearer ' . config('ai.replicate_token'), 'Content-Type: application/json'];
        if ($wait > 0) {
            $headers[] = 'Prefer: wait=' . min(60, $wait);
        }
        if (str_contains($model, ':')) {
            $url = self::base('replicate') . 'predictions';
            $body = ['version' => substr($model, strpos($model, ':') + 1), 'input' => $input];
        } else {
            if (!preg_match('#^[a-z0-9_.-]+/[a-z0-9_.-]+$#i', $model)) {
                throw new \RuntimeException('Modèle Replicate invalide : ' . $model);
            }
            $url = self::base('replicate') . 'models/' . $model . '/predictions';
            $body = ['input' => $input];
        }
        $res = Http::request('POST', $url, $headers, json_encode($body, JSON_UNESCAPED_SLASHES), $wait + 30);
        if ($res['status'] >= 300 || empty($res['json']['id'])) {
            throw new \RuntimeException('Replicate : ' . self::errorMessage($res));
        }
        return self::prediction($res['json']);
    }

    /** @return array{id:string, status:string, output:mixed, error:?string} */
    public static function replicateGet(string $id): array
    {
        if (!preg_match('/^[a-z0-9]+$/i', $id)) {
            throw new \RuntimeException('Identifiant de prédiction invalide.');
        }
        $res = Http::request('GET', self::base('replicate') . 'predictions/' . $id, ['Authorization: Bearer ' . config('ai.replicate_token')]);
        if ($res['status'] >= 300) {
            throw new \RuntimeException('Replicate : ' . self::errorMessage($res));
        }
        return self::prediction($res['json'] ?? []);
    }

    /** Téléverse un fichier chez Replicate (entrée d'une prédiction) ; retourne son URL. */
    public static function replicateUpload(string $bytes, string $mime, string $filename): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'rf');
        file_put_contents($tmp, $bytes);
        try {
            $res = Http::request('POST', self::base('replicate') . 'files', ['Authorization: Bearer ' . config('ai.replicate_token')], [
                'content' => new \CURLFile($tmp, $mime, $filename),
            ], 120);
        } finally {
            @unlink($tmp);
        }
        $url = (string) ($res['json']['urls']['get'] ?? '');
        if ($res['status'] >= 300 || $url === '') {
            throw new \RuntimeException('Replicate (fichier) : ' . self::errorMessage($res));
        }
        return $url;
    }

    public static function replicateCheck(): string
    {
        $res = Http::request('GET', self::base('replicate') . 'account', ['Authorization: Bearer ' . config('ai.replicate_token')]);
        if ($res['status'] >= 300) {
            throw new \RuntimeException('Replicate : ' . self::errorMessage($res));
        }
        return 'Connexion Replicate OK (compte « ' . ($res['json']['username'] ?? '?') . ' »), modèle « ' . (config('ai.replicate_model') ?: 'black-forest-labs/flux-kontext-pro') . ' ».';
    }

    /** Première URL d'image d'une sortie Replicate (chaîne, liste ou objet). */
    public static function outputUrl(mixed $output): ?string
    {
        if (is_array($output)) {
            $output = $output['image'] ?? $output['url'] ?? (array_is_list($output) ? ($output[0] ?? null) : null);
        }
        return is_string($output) && (str_starts_with($output, 'https://') || str_starts_with($output, self::base('replicate'))) ? $output : null;
    }

    /** Télécharge une image produite (HTTPS uniquement, 40 Mo maximum, contenu vérifié). */
    public static function download(string $url): string
    {
        $api = str_starts_with($url, self::base('replicate'));
        if (!str_starts_with($url, 'https://') && !$api) {
            throw new \RuntimeException('Adresse d\'image refusée.');
        }
        $headers = $api ? ['Authorization: Bearer ' . config('ai.replicate_token')] : [];
        $res = Http::request('GET', $url, $headers, null, 120);
        if ($res['status'] >= 300 || strlen($res['body']) > 40 * 1024 * 1024 || @getimagesizefromstring($res['body']) === false) {
            throw new \RuntimeException('Image produite introuvable ou invalide (HTTP ' . $res['status'] . ').');
        }
        return $res['body'];
    }

    private static function prediction(array $json): array
    {
        return [
            'id'     => (string) ($json['id'] ?? ''),
            'status' => (string) ($json['status'] ?? 'failed'),
            'output' => $json['output'] ?? null,
            'error'  => isset($json['error']) && $json['error'] !== null ? (is_string($json['error']) ? $json['error'] : json_encode($json['error'])) : null,
        ];
    }

    private static function errorMessage(array $res): string
    {
        $json = $res['json'] ?? [];
        $message = $json['error']['message'] ?? $json['detail'] ?? $json['title'] ?? (is_string($json['error'] ?? null) ? $json['error'] : null);
        return 'HTTP ' . $res['status'] . ($message ? ' — ' . mb_substr((string) $message, 0, 300) : '');
    }

    // -------------------------------------------------------------------- Démo

    /**
     * Effet « croquis à l'encre bordeaux + lavis poudré » calculé avec GD (aucun appel externe).
     * Ne remplace pas une IA : il sert à tester le parcours d'achat avant d'ouvrir un compte.
     */
    public static function demoSketch(string $jpeg): string
    {
        $src = imagecreatefromstring($jpeg);
        if (!$src) {
            throw new \RuntimeException('Photo illisible.');
        }
        // Cadrage 3:4, légèrement vers le haut (la tête du cheval)
        $w = 600;
        $h = 800;
        $sw = imagesx($src);
        $sh = imagesy($src);
        $scale = max($w / $sw, $h / $sh);
        $cw = (int) round($w / $scale);
        $ch = (int) round($h / $scale);
        $base = imagecreatetruecolor($w, $h);
        imagecopyresampled($base, $src, 0, 0, (int) (($sw - $cw) / 2), (int) (($sh - $ch) * 0.3), $w, $h, $cw, $ch);
        imagedestroy($src);

        // Niveaux de gris + version floutée inversée → « densité » des traits (fondu de couleur)
        $gray = imagecreatetruecolor($w, $h);
        imagecopy($gray, $base, 0, 0, 0, 0, $w, $h);
        imagefilter($gray, IMG_FILTER_GRAYSCALE);
        $blur = imagecreatetruecolor($w, $h);
        imagecopy($blur, $gray, 0, 0, 0, 0, $w, $h);
        imagefilter($blur, IMG_FILTER_NEGATE);
        for ($i = 0; $i < 6; $i++) {
            imagefilter($blur, IMG_FILTER_GAUSSIAN_BLUR);
        }
        // Lavis : couleurs d'origine très floutées et adoucies
        $wash = imagescale($base, 60, 80, IMG_BILINEAR_FIXED);
        $wash = imagescale($wash, $w, $h, IMG_BICUBIC);
        imagefilter($wash, IMG_FILTER_GAUSSIAN_BLUR);

        $paper = [249, 247, 243];
        $ink = [90, 42, 58];
        $powder = [217, 184, 179];
        $out = imagecreatetruecolor($w, $h);
        for ($y = 0; $y < $h; $y++) {
            // Fondu vers le papier en bas (l'encolure s'efface) et sur les bords
            $fadeY = $y > $h * 0.62 ? max(0.0, 1 - ($y - $h * 0.62) / ($h * 0.36)) : 1.0;
            for ($x = 0; $x < $w; $x++) {
                $g = imagecolorat($gray, $x, $y) & 0xFF;
                $b = imagecolorat($blur, $x, $y) & 0xFF;
                $dodge = $b >= 255 ? 255 : min(255, (int) ($g * 255 / (256 - $b)));
                $line = 1 - ($dodge / 255) ** 3;              // 0 = papier, 1 = trait
                $line = $line < 0.08 ? 0.0 : min(1.0, $line * 1.6);
                $edge = min($x, $w - 1 - $x) / ($w * 0.12);
                $fade = $fadeY * min(1.0, $edge);
                $c = imagecolorat($wash, $x, $y);
                $tone = (((($c >> 16) & 0xFF) + (($c >> 8) & 0xFF) + ($c & 0xFF)) / 765);
                $px = [];
                for ($k = 0; $k < 3; $k++) {
                    $washK = [($c >> 16) & 0xFF, ($c >> 8) & 0xFF, $c & 0xFF][$k];
                    $tint = $paper[$k] * 0.55 + ($washK * 0.45 + $powder[$k] * 0.55) * 0.45 * (1 - $tone * 0.4);
                    $col = $paper[$k] + ($tint - $paper[$k]) * $fade * 0.9;
                    $px[$k] = (int) max(0, min(255, $col + ($ink[$k] - $col) * $line * $fade));
                }
                imagesetpixel($out, $x, $y, ($px[0] << 16) | ($px[1] << 8) | $px[2]);
            }
        }
        imagedestroy($gray);
        imagedestroy($blur);
        imagedestroy($wash);
        imagedestroy($base);
        ob_start();
        imagepng($out, null, 6);
        imagedestroy($out);
        return (string) ob_get_clean();
    }
}
