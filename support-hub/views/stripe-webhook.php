<?php
declare(strict_types=1);

/**
 * Webhook Stripe (api.php?a=stripe, sans clé d'API : la signature Stripe fait foi). À déclarer dans le tableau de bord
 * Stripe (Développeurs → Webhooks) avec les événements checkout.session.completed, invoice.paid,
 * invoice.payment_failed, customer.subscription.updated, customer.subscription.deleted.
 */
defined('HUB') || exit;

header('Content-Type: application/json; charset=utf-8');
$payload = (string)file_get_contents('php://input');
$secret = trim((string)(hsetting('stripe_webhook_secret') ?: hcfg('stripe_webhook_secret') ?: ''));
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !hub_stripe_verify($payload, (string)($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? ''), $secret)) {
    http_response_code(400);
    exit('{"error":"signature"}');
}
$event = json_decode($payload, true);
if (!is_array($event) || empty($event['id'])) {
    http_response_code(400);
    exit('{"error":"payload"}');
}
try {
    $res = hub_billing_handle($event);
    hset('stripe_last_event', hnow() . ' · ' . ($event['type'] ?? '?') . ' · ' . $res);
    echo json_encode(['ok' => true, 'result' => $res], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('[stripe] ' . $e->getMessage());
    http_response_code(500); // Stripe renverra l'événement plus tard
    echo '{"error":"internal"}';
}
