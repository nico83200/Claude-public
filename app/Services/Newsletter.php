<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

final class Newsletter
{
    public static function subscribe(string $email, string $source = 'footer'): bool
    {
        $email = mb_strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        if (!Database::value('SELECT id FROM newsletter_subscribers WHERE email = ?', [$email])) {
            Database::insert('newsletter_subscribers', [
                'email' => $email, 'source' => $source, 'ip' => client_ip(), 'synced' => 0, 'created_at' => now(),
            ]);
        }
        self::syncPending(5);
        return true;
    }

    /** Pousse les nouveaux inscrits vers Brevo (si configuré). */
    public static function syncPending(int $limit = 50): int
    {
        $apiKey = (string) config('brevo.api_key');
        if ($apiKey === '') {
            return 0;
        }
        $synced = 0;
        foreach (Database::all('SELECT id, email FROM newsletter_subscribers WHERE synced = 0 ORDER BY id LIMIT ' . (int) $limit) as $row) {
            $body = ['email' => $row['email'], 'updateEnabled' => true];
            if ((int) config('brevo.list_id') > 0) {
                $body['listIds'] = [(int) config('brevo.list_id')];
            }
            try {
                $res = Http::request('POST', 'https://api.brevo.com/v3/contacts', [
                    'api-key: ' . $apiKey, 'Content-Type: application/json', 'Accept: application/json',
                ], json_encode($body), 15);
                if ($res['status'] < 300 || ($res['json']['code'] ?? '') === 'duplicate_parameter') {
                    Database::update('newsletter_subscribers', ['synced' => 1], 'id = :id', ['id' => $row['id']]);
                    $synced++;
                } else {
                    log_message('brevo', 'Échec synchro', ['email' => $row['email'], 'response' => $res['body']]);
                }
            } catch (\Throwable $e) {
                log_message('brevo', 'Erreur réseau', ['error' => $e->getMessage()]);
                break;
            }
        }
        return $synced;
    }
}
