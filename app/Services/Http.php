<?php
declare(strict_types=1);

namespace App\Services;

final class Http
{
    /**
     * @param string|array|null $body tableau = envoi multipart (champs + \CURLFile)
     * @return array{status:int, body:string, json:?array}
     */
    public static function request(string $method, string $url, array $headers = [], string|array|null $body = null, int $timeout = 30): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_USERAGENT      => 'CriniereEtCo/1.0 (+' . base_url() . ')',
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $response = curl_exec($ch);
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException('Erreur réseau : ' . $error);
        }
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        $json = json_decode((string) $response, true);
        return ['status' => $status, 'body' => (string) $response, 'json' => is_array($json) ? $json : null];
    }
}
