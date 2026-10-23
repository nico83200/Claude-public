<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Client API Printful (v1) : commandes, produits synchronisés, webhooks.
 * Documentation : https://developers.printful.com/docs/
 */
final class Printful
{
    private const API = 'https://api.printful.com/';

    public const WEBHOOK_TYPES = ['package_shipped', 'package_returned', 'order_failed', 'order_canceled', 'order_put_hold', 'order_remove_hold'];

    public function __construct(private readonly string $token, private readonly string $storeId = '')
    {
        if ($token === '') {
            throw new \RuntimeException('Jeton API Printful non configuré.');
        }
    }

    public static function fromConfig(): self
    {
        return new self((string) config('printful.api_token'), (string) config('printful.store_id'));
    }

    public static function configured(): bool
    {
        return (string) config('printful.api_token') !== '';
    }

    public function request(string $method, string $path, ?array $body = null, array $query = []): array
    {
        $headers = ['Authorization: Bearer ' . $this->token, 'Content-Type: application/json'];
        if ($this->storeId !== '') {
            $headers[] = 'X-PF-Store-Id: ' . $this->storeId;
        }
        $url = self::API . ltrim($path, '/') . ($query ? '?' . http_build_query($query) : '');
        $res = Http::request($method, $url, $headers, $body !== null ? json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null, 60);

        if ($res['status'] >= 400 || $res['json'] === null) {
            $message = $res['json']['error']['message'] ?? $res['json']['result'] ?? ('HTTP ' . $res['status']);
            log_message('printful', 'Erreur API', ['path' => $path, 'status' => $res['status'], 'message' => $message]);
            throw new \RuntimeException('Printful : ' . (is_string($message) ? $message : json_encode($message)));
        }
        return $res['json'];
    }

    public function stores(): array
    {
        return $this->request('GET', 'stores')['result'] ?? [];
    }

    public function createOrder(array $payload, bool $confirm): array
    {
        return $this->request('POST', 'orders', $payload, ['confirm' => $confirm ? 'true' : 'false', 'update_existing' => 'true'])['result'];
    }

    /** $id : identifiant Printful ou "@<external_id>" */
    public function getOrder(string $id): array
    {
        return $this->request('GET', 'orders/' . rawurlencode($id))['result'];
    }

    public function confirmOrder(string $id): array
    {
        return $this->request('POST', 'orders/' . rawurlencode($id) . '/confirm')['result'];
    }

    public function listSyncProducts(int $offset = 0, int $limit = 100): array
    {
        $res = $this->request('GET', 'store/products', null, ['offset' => $offset, 'limit' => $limit]);
        return ['items' => $res['result'] ?? [], 'total' => (int) ($res['paging']['total'] ?? 0)];
    }

    public function getSyncProduct(int|string $id): array
    {
        return $this->request('GET', 'store/products/' . rawurlencode((string) $id))['result'];
    }

    public function shippingRates(array $recipient, array $items): array
    {
        return $this->request('POST', 'shipping/rates', ['recipient' => $recipient, 'items' => $items, 'currency' => 'EUR', 'locale' => 'fr_FR'])['result'] ?? [];
    }

    public function setWebhooks(string $url, array $types = self::WEBHOOK_TYPES): array
    {
        return $this->request('POST', 'webhooks', ['url' => $url, 'types' => $types])['result'] ?? [];
    }

    public function getWebhooks(): array
    {
        return $this->request('GET', 'webhooks')['result'] ?? [];
    }
}
