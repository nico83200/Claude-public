<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Services\OrderService;
use App\Services\Stripe;

final class WebhookController
{
    public function stripe(): void
    {
        $payload = (string) file_get_contents('php://input');
        try {
            $event = Stripe::verifyWebhook($payload, (string) ($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? ''), (string) config('stripe.webhook_secret'));
        } catch (\Throwable $e) {
            log_message('stripe', 'Webhook rejeté', ['error' => $e->getMessage(), 'ip' => client_ip()]);
            json_response(['error' => 'invalid signature'], 400);
        }

        if (!self::firstDelivery('stripe', (string) $event['id'], (string) $event['type'])) {
            json_response(['received' => true, 'duplicate' => true]);
        }

        $object = $event['data']['object'] ?? [];
        try {
            match ($event['type']) {
                'checkout.session.completed',
                'checkout.session.async_payment_succeeded' => OrderService::handleCheckoutCompleted($object),
                'checkout.session.expired',
                'checkout.session.async_payment_failed'    => OrderService::handleSessionExpired($object),
                'charge.refunded'                          => OrderService::handleRefund($object),
                default                                    => null,
            };
        } catch (\Throwable $e) {
            // On libère l'événement pour que Stripe puisse le renvoyer.
            Database::query('DELETE FROM webhook_events WHERE provider = ? AND event_id = ?', ['stripe', $event['id']]);
            log_message('stripe', 'Erreur de traitement du webhook', ['event' => $event['id'], 'error' => $e->getMessage()]);
            json_response(['error' => 'processing failed'], 500);
        }
        json_response(['received' => true]);
    }

    public function printful(string $token): void
    {
        $expected = (string) config('printful.webhook_token');
        if ($expected === '' || !hash_equals($expected, $token)) {
            json_response(['error' => 'forbidden'], 403);
        }
        $payload = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($payload) || empty($payload['type'])) {
            json_response(['error' => 'invalid payload'], 400);
        }

        $eventId = sha1($payload['type'] . '|' . ($payload['created'] ?? '') . '|' . json_encode($payload['data']['order']['id'] ?? null) . '|' . json_encode($payload['data']['shipment']['id'] ?? null));
        if (!self::firstDelivery('printful', $eventId, (string) $payload['type'])) {
            json_response(['received' => true, 'duplicate' => true]);
        }
        try {
            OrderService::handlePrintfulEvent($payload);
        } catch (\Throwable $e) {
            Database::query('DELETE FROM webhook_events WHERE provider = ? AND event_id = ?', ['printful', $eventId]);
            log_message('printful', 'Erreur de traitement du webhook', ['error' => $e->getMessage()]);
            json_response(['error' => 'processing failed'], 500);
        }
        json_response(['received' => true]);
    }

    /** Enregistre l'événement ; retourne false s'il a déjà été traité (idempotence). */
    private static function firstDelivery(string $provider, string $eventId, string $type): bool
    {
        try {
            Database::insert('webhook_events', ['provider' => $provider, 'event_id' => $eventId, 'type' => mb_substr($type, 0, 80), 'created_at' => now()]);
            return true;
        } catch (\PDOException) {
            return false;
        }
    }
}
