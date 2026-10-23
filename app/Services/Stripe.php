<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Client Stripe minimal (sans SDK) : Checkout Sessions + vérification des webhooks.
 */
final class Stripe
{
    private const API = 'https://api.stripe.com/v1/';
    private const API_VERSION = '2024-06-20';

    public function __construct(private readonly string $secretKey)
    {
        if ($secretKey === '') {
            throw new \RuntimeException('Clé secrète Stripe non configurée.');
        }
    }

    public static function fromConfig(): self
    {
        return new self((string) config('stripe.secret_key'));
    }

    public function request(string $method, string $path, array $params = [], ?string $idempotencyKey = null): array
    {
        $headers = [
            'Authorization: Bearer ' . $this->secretKey,
            'Stripe-Version: ' . self::API_VERSION,
            'Content-Type: application/x-www-form-urlencoded',
        ];
        if ($idempotencyKey) {
            $headers[] = 'Idempotency-Key: ' . $idempotencyKey;
        }
        $url = self::API . ltrim($path, '/');
        $body = null;
        if ($method === 'GET') {
            if ($params) {
                $url .= '?' . http_build_query($params);
            }
        } else {
            $body = http_build_query($params);
        }

        $res = Http::request($method, $url, $headers, $body);
        if ($res['status'] >= 400 || $res['json'] === null) {
            $message = $res['json']['error']['message'] ?? ('HTTP ' . $res['status']);
            log_message('stripe', 'Erreur API', ['path' => $path, 'status' => $res['status'], 'message' => $message]);
            throw new \RuntimeException('Stripe : ' . $message);
        }
        return $res['json'];
    }

    public function createCheckoutSession(array $params, string $idempotencyKey): array
    {
        return $this->request('POST', 'checkout/sessions', $params, $idempotencyKey);
    }

    public function retrieveCheckoutSession(string $id): array
    {
        return $this->request('GET', 'checkout/sessions/' . rawurlencode($id));
    }

    public function expireCheckoutSession(string $id): array
    {
        return $this->request('POST', 'checkout/sessions/' . rawurlencode($id) . '/expire');
    }

    public function refund(string $paymentIntent, ?int $amount = null): array
    {
        $params = ['payment_intent' => $paymentIntent];
        if ($amount !== null) {
            $params['amount'] = $amount;
        }
        return $this->request('POST', 'refunds', $params);
    }

    /**
     * Vérifie l'en-tête Stripe-Signature (HMAC-SHA256 de "timestamp.payload").
     * @throws \RuntimeException si la signature est invalide ou trop ancienne.
     */
    public static function verifyWebhook(string $payload, string $header, string $secret, int $tolerance = 300, ?int $now = null): array
    {
        if ($secret === '') {
            throw new \RuntimeException('Secret de webhook Stripe non configuré.');
        }
        $timestamp = null;
        $signatures = [];
        foreach (explode(',', $header) as $part) {
            [$k, $v] = array_pad(explode('=', trim($part), 2), 2, '');
            if ($k === 't') {
                $timestamp = (int) $v;
            } elseif ($k === 'v1') {
                $signatures[] = $v;
            }
        }
        if (!$timestamp || !$signatures) {
            throw new \RuntimeException('En-tête de signature mal formé.');
        }
        if (abs(($now ?? time()) - $timestamp) > $tolerance) {
            throw new \RuntimeException('Signature expirée.');
        }
        $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                $event = json_decode($payload, true);
                if (!is_array($event)) {
                    throw new \RuntimeException('Charge utile JSON invalide.');
                }
                return $event;
            }
        }
        throw new \RuntimeException('Signature invalide.');
    }
}
