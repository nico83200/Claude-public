<?php

namespace App\Services\Backups;

use App\Models\ApplicationSetting;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Google Drive (API REST v3) pour les sauvegardes : même client OAuth Google que la connexion
 * (Administration › Intégrations). Autorisation « drive.file » : l'application ne voit que les
 * fichiers qu'elle a créés elle-même, rien d'autre du Drive du compte.
 *
 * Jeton de renouvellement stocké chiffré (APP_KEY) dans application_settings.
 */
class GoogleDrive
{
    public const SCOPE = 'https://www.googleapis.com/auth/drive.file openid email';

    public const FOLDER_NAME = 'Jack&Cie – sauvegardes';

    private const AUTH = 'https://accounts.google.com/o/oauth2/v2/auth';

    private const TOKEN = 'https://oauth2.googleapis.com/token';

    private const API = 'https://www.googleapis.com/drive/v3';

    private const UPLOAD = 'https://www.googleapis.com/upload/drive/v3/files';

    /** Morceaux d'envoi : multiple de 256 Kio exigé par Google. */
    public const CHUNK = 8 * 1048576;

    public int $chunkSize = self::CHUNK;

    public function clientConfigured(): bool
    {
        return filled(config('services.google.client_id')) && filled(config('services.google.client_secret'));
    }

    public function connected(): bool
    {
        return $this->refreshToken() !== null;
    }

    public function email(): ?string
    {
        return ApplicationSetting::get('backup.google_email');
    }

    public function authorizationUrl(string $redirectUri, string $state): string
    {
        return self::AUTH.'?'.http_build_query([
            'client_id' => config('services.google.client_id'), 'redirect_uri' => $redirectUri, 'response_type' => 'code',
            'scope' => self::SCOPE, 'access_type' => 'offline', 'prompt' => 'consent', 'include_granted_scopes' => 'false', 'state' => $state,
        ]);
    }

    /** Retour de Google : jeton de renouvellement enregistré, adresse du compte retenue pour l'affichage. */
    public function connect(string $code, string $redirectUri): string
    {
        $r = Http::asForm()->acceptJson()->timeout(20)->post(self::TOKEN, [
            'code' => $code, 'client_id' => config('services.google.client_id'), 'client_secret' => config('services.google.client_secret'),
            'redirect_uri' => $redirectUri, 'grant_type' => 'authorization_code',
        ]);
        $this->ensureOk($r, 'Connexion à Google refusée');
        if (! str_contains((string) $r->json('scope'), 'drive.file')) {
            throw new RuntimeException('Google n\'a pas accordé l\'accès au Drive : cochez l\'autorisation demandée lors de la connexion.');
        }
        if (! $r->json('refresh_token')) {
            throw new RuntimeException('Google n\'a pas renvoyé de jeton durable : retirez l\'accès de Jack&Cie dans votre compte Google, puis reconnectez.');
        }
        $email = Http::withToken((string) $r->json('access_token'))->acceptJson()->timeout(15)
            ->get('https://openidconnect.googleapis.com/v1/userinfo')->json('email');
        ApplicationSetting::put('backup.google_refresh_token', Crypt::encryptString((string) $r->json('refresh_token')));
        ApplicationSetting::put('backup.google_email', $email);
        ApplicationSetting::put('backup.connected_at', now()->toIso8601String());
        ApplicationSetting::put('backup.folder_id', null);
        Cache::put('backup.google_access', Crypt::encryptString((string) $r->json('access_token')), now()->addSeconds(max(60, (int) $r->json('expires_in', 3600) - 120)));

        return (string) $email;
    }

    public function disconnect(): void
    {
        if ($token = $this->refreshToken()) {
            rescue(fn () => Http::asForm()->timeout(10)->post('https://oauth2.googleapis.com/revoke', ['token' => $token]), null, false);
        }
        foreach (['backup.google_refresh_token', 'backup.google_email', 'backup.folder_id', 'backup.connected_at'] as $key) {
            ApplicationSetting::put($key, null);
        }
        Cache::forget('backup.google_access');
    }

    /** Dossier de l'application dans le Drive (recréé s'il a été supprimé). */
    public function folderId(): string
    {
        $id = ApplicationSetting::get('backup.folder_id');
        if ($id) {
            $r = $this->api()->get(self::API.'/files/'.$id, ['fields' => 'id,trashed']);
            if ($r->successful() && ! $r->json('trashed')) {
                return $id;
            }
        }
        $r = $this->api()->post(self::API.'/files?fields=id', ['name' => self::FOLDER_NAME, 'mimeType' => 'application/vnd.google-apps.folder']);
        $this->ensureOk($r, 'Création du dossier de sauvegarde impossible');
        ApplicationSetting::put('backup.folder_id', $r->json('id'));

        return $r->json('id');
    }

    /** Envoi « reprenable » par morceaux : aucun fichier n'est chargé entièrement en mémoire. */
    public function upload(string $path, string $name, string $folderId): string
    {
        $size = filesize($path);
        $start = $this->api()->withHeaders(['X-Upload-Content-Type' => 'application/octet-stream', 'X-Upload-Content-Length' => (string) $size])
            ->post(self::UPLOAD.'?uploadType=resumable&fields=id', ['name' => $name, 'parents' => [$folderId]]);
        $this->ensureOk($start, 'Envoi refusé par Google Drive');
        $session = $start->header('Location');
        if (! $session) {
            throw new RuntimeException('Google Drive n\'a pas ouvert d\'envoi.');
        }
        $in = fopen($path, 'rb');
        try {
            $offset = 0;
            do {
                $chunk = $size > 0 ? (string) fread($in, $this->chunkSize) : '';
                $end = $offset + strlen($chunk) - 1;
                $range = $size > 0 ? 'bytes '.$offset.'-'.$end.'/'.$size : 'bytes */0';
                $r = Http::withToken($this->accessToken())->timeout(300)
                    ->withHeaders(['Content-Range' => $range])->withBody($chunk, 'application/octet-stream')->put($session);
                if ($r->status() === 308) {
                    $offset = $end + 1;

                    continue;
                }
                $this->ensureOk($r, 'Envoi interrompu');

                return (string) $r->json('id');
            } while ($offset < $size);
        } finally {
            fclose($in);
        }

        throw new RuntimeException('Envoi incomplet vers Google Drive.');
    }

    /** @return array<int, array{id: string, name: string, createdTime: string, size: int}> */
    public function files(string $folderId): array
    {
        $r = $this->api()->get(self::API.'/files', [
            'q' => "'".$folderId."' in parents and trashed = false", 'fields' => 'files(id,name,createdTime,size)', 'orderBy' => 'createdTime desc', 'pageSize' => 500,
        ]);
        $this->ensureOk($r, 'Liste des sauvegardes illisible');

        return array_map(fn ($f) => ['id' => $f['id'], 'name' => $f['name'], 'createdTime' => $f['createdTime'] ?? '', 'size' => (int) ($f['size'] ?? 0)], $r->json('files', []));
    }

    public function delete(string $fileId): void
    {
        $r = $this->api()->delete(self::API.'/files/'.$fileId);
        if (! $r->successful() && $r->status() !== 404) {
            $this->ensureOk($r, 'Suppression d\'une ancienne sauvegarde impossible');
        }
    }

    private function api()
    {
        return Http::withToken($this->accessToken())->acceptJson()->timeout(60);
    }

    private function accessToken(): string
    {
        $cached = Cache::get('backup.google_access');
        if ($cached) {
            return Crypt::decryptString($cached);
        }
        $refresh = $this->refreshToken() ?? throw new RuntimeException('Google Drive n\'est pas connecté.');
        $r = Http::asForm()->acceptJson()->timeout(20)->post(self::TOKEN, [
            'client_id' => config('services.google.client_id'), 'client_secret' => config('services.google.client_secret'),
            'refresh_token' => $refresh, 'grant_type' => 'refresh_token',
        ]);
        $this->ensureOk($r, 'Accès à Google Drive refusé (reconnectez le compte dans Administration › Sauvegardes)');
        $token = (string) $r->json('access_token');
        Cache::put('backup.google_access', Crypt::encryptString($token), now()->addSeconds(max(60, (int) $r->json('expires_in', 3600) - 120)));

        return $token;
    }

    private function refreshToken(): ?string
    {
        $stored = ApplicationSetting::get('backup.google_refresh_token');

        return $stored ? rescue(fn () => Crypt::decryptString($stored), null, false) : null;
    }

    private function ensureOk(Response $r, string $what): void
    {
        if ($r->successful()) {
            return;
        }
        $detail = $r->json('error.message') ?? $r->json('error_description') ?? (is_string($r->json('error')) ? $r->json('error') : null) ?? 'HTTP '.$r->status();

        throw new RuntimeException($what.' : '.mb_substr((string) $detail, 0, 200));
    }
}
