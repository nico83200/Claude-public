<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Cycle de vie d'une commande :
 * pending (panier envoyé vers Stripe) → paid (webhook Stripe) → in_production (transmise à Printful)
 * → shipped (webhook Printful). Branches : canceled, refunded.
 */
final class OrderService
{
    private const MAX_FULFILLMENT_ATTEMPTS = 5;

    public static function find(int $id): ?array
    {
        return Database::one('SELECT * FROM orders WHERE id = ?', [$id]);
    }

    public static function findByNumber(string $number): ?array
    {
        return Database::one('SELECT * FROM orders WHERE number = ?', [$number]);
    }

    public static function items(int $orderId): array
    {
        return Database::all('SELECT * FROM order_items WHERE order_id = ? ORDER BY id', [$orderId]);
    }

    public static function events(int $orderId): array
    {
        return Database::all('SELECT * FROM order_events WHERE order_id = ? ORDER BY id DESC', [$orderId]);
    }

    public static function event(int $orderId, string $type, string $message): void
    {
        Database::insert('order_events', ['order_id' => $orderId, 'type' => $type, 'message' => $message, 'created_at' => now()]);
    }

    public static function trackingUrl(array $order): string
    {
        return url('commande/' . rawurlencode($order['number']), ['t' => $order['access_token']]);
    }

    /** Crée une commande « en attente de paiement » à partir des lignes du panier. */
    public static function createFromCart(array $items, bool $newsletter = false): array
    {
        if (!$items) {
            throw new \InvalidArgumentException('Panier vide.');
        }
        $id = Database::transaction(static function () use ($items, $newsletter): int {
            $subtotal = Cart::subtotal($items);
            $id = Database::insert('orders', [
                'number'       => 'TMP-' . bin2hex(random_bytes(6)),
                'access_token' => bin2hex(random_bytes(24)),
                'status'       => 'pending',
                'fulfillment_status' => 'none',
                'subtotal'     => $subtotal,
                'total'        => $subtotal,
                'currency'     => 'EUR',
                'newsletter_optin' => $newsletter ? 1 : 0,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
            $number = strtoupper((string) setting('order_prefix', 'CC')) . '-' . (1000 + $id);
            Database::update('orders', ['number' => $number], 'id = :id', ['id' => $id]);

            foreach ($items as $item) {
                Database::insert('order_items', [
                    'order_id'     => $id,
                    'product_id'   => $item['product_id'],
                    'variant_id'   => $item['variant_id'],
                    'product_name' => $item['product_name'],
                    'variant_name' => $item['variant_name'],
                    'sku'          => $item['sku'],
                    'image'        => $item['image'],
                    'unit_price'   => $item['unit_price'],
                    'quantity'     => $item['qty'],
                    'personalization_text' => $item['text'] !== '' ? $item['text'] : null,
                ]);
            }
            return $id;
        });
        self::event($id, 'created', 'Commande créée, redirection vers le paiement.');
        return self::find($id);
    }

    /**
     * Paiement confirmé par Stripe (webhook checkout.session.completed ou page de retour).
     * Idempotent : seul le premier appel fait passer la commande à « paid ».
     */
    public static function handleCheckoutCompleted(array $session): ?array
    {
        $order = self::orderForSession($session);
        if (!$order) {
            log_message('stripe', 'Session sans commande associée', ['session' => $session['id'] ?? null]);
            return null;
        }
        if (($session['payment_status'] ?? '') !== 'paid') {
            if ($order['status'] === 'pending') {
                self::event((int) $order['id'], 'payment', 'Paiement en cours de validation (moyen de paiement différé).');
            }
            return $order;
        }

        $claimed = Database::update(
            'orders',
            ['status' => 'paid', 'paid_at' => now(), 'updated_at' => now()],
            "id = :id AND status = 'pending'",
            ['id' => $order['id']]
        );
        if ($claimed === 0) {
            return self::find((int) $order['id']); // déjà traitée
        }

        $customer = $session['customer_details'] ?? [];
        $shipping = $session['shipping_details'] ?? $session['collected_information']['shipping_details'] ?? null;
        $address = $shipping['address'] ?? $customer['address'] ?? [];
        $totals = $session['total_details'] ?? [];

        Database::update('orders', [
            'email'            => mb_strtolower((string) ($customer['email'] ?? $session['customer_email'] ?? '')),
            'customer_name'    => (string) ($shipping['name'] ?? $customer['name'] ?? ''),
            'phone'            => (string) ($customer['phone'] ?? ''),
            'shipping_address' => json_encode([
                'name'        => (string) ($shipping['name'] ?? $customer['name'] ?? ''),
                'line1'       => (string) ($address['line1'] ?? ''),
                'line2'       => (string) ($address['line2'] ?? ''),
                'postal_code' => (string) ($address['postal_code'] ?? ''),
                'city'        => (string) ($address['city'] ?? ''),
                'state'       => (string) ($address['state'] ?? ''),
                'country'     => (string) ($address['country'] ?? ''),
            ], JSON_UNESCAPED_UNICODE),
            'shipping_method'  => self::shippingMethodFromSession($session),
            'subtotal'         => (int) ($session['amount_subtotal'] ?? $order['subtotal']),
            'discount'         => (int) ($totals['amount_discount'] ?? 0),
            'tax'              => (int) ($totals['amount_tax'] ?? 0),
            'shipping'         => (int) ($session['shipping_cost']['amount_total'] ?? $totals['amount_shipping'] ?? 0),
            'total'            => (int) ($session['amount_total'] ?? $order['total']),
            'currency'         => strtoupper((string) ($session['currency'] ?? 'eur')),
            'stripe_payment_intent' => is_array($session['payment_intent'] ?? null) ? $session['payment_intent']['id'] : ($session['payment_intent'] ?? null),
            'fulfillment_status' => 'pending',
        ], 'id = :id', ['id' => $order['id']]);

        $order = self::find((int) $order['id']);
        self::event((int) $order['id'], 'payment', 'Paiement reçu : ' . money($order['total']) . '.');

        $items = self::items((int) $order['id']);
        $mailData = ['order' => $order, 'items' => $items, 'address' => json_field($order['shipping_address'])];
        Mailer::sendTemplate($order['email'], 'Confirmation de votre commande ' . $order['number'], 'order_confirmation', $mailData);
        Mailer::notifyAdmin('Nouvelle commande ' . $order['number'] . ' — ' . money($order['total']), 'admin_new_order', $mailData);

        if ($order['newsletter_optin'] && $order['email']) {
            Newsletter::subscribe($order['email'], 'checkout');
        }

        self::fulfill((int) $order['id']);
        return self::find((int) $order['id']);
    }

    private static function orderForSession(array $session): ?array
    {
        $orderId = (int) ($session['metadata']['order_id'] ?? 0);
        $order = $orderId ? self::find($orderId) : null;
        if (!$order && !empty($session['id'])) {
            $order = Database::one('SELECT * FROM orders WHERE stripe_session_id = ?', [$session['id']]);
        }
        if ($order && $order['stripe_session_id'] && !empty($session['id']) && $order['stripe_session_id'] !== $session['id']) {
            log_message('stripe', 'Session ne correspondant pas à la commande', ['order' => $order['number'], 'session' => $session['id']]);
            return null;
        }
        return $order;
    }

    private static function shippingMethodFromSession(array $session): string
    {
        $rate = $session['shipping_cost']['shipping_rate'] ?? null;
        if (is_array($rate)) {
            return (string) ($rate['metadata']['code'] ?? 'STANDARD');
        }
        if (is_string($rate) && config('stripe.secret_key')) {
            try {
                $data = Stripe::fromConfig()->request('GET', 'shipping_rates/' . rawurlencode($rate));
                return (string) ($data['metadata']['code'] ?? 'STANDARD');
            } catch (\Throwable) {
                // repli ci-dessous
            }
        }
        $amount = (int) ($session['shipping_cost']['amount_total'] ?? 0);
        $express = (int) setting('shipping_express_price', 0);
        return ($express > 0 && $amount === $express && $amount !== (int) setting('shipping_standard_price')) ? 'EXPRESS' : 'STANDARD';
    }

    /** Transmet la commande à Printful. Retourne true si la transmission a réussi. */
    public static function fulfill(int $orderId): bool
    {
        $order = self::find($orderId);
        if (!$order || !in_array($order['status'], ['paid', 'in_production'], true)
            || !in_array($order['fulfillment_status'], ['pending', 'error'], true)) {
            return false;
        }

        try {
            if (!Printful::configured()) {
                throw new \RuntimeException('Printful n\'est pas configuré (jeton API manquant).');
            }
            $payload = self::buildPrintfulPayload($order);
            $confirm = (bool) config('printful.auto_confirm');
            $result = Printful::fromConfig()->createOrder($payload, $confirm);

            $isDraft = ($result['status'] ?? 'draft') === 'draft';
            Database::update('orders', [
                'printful_order_id'  => (int) $result['id'],
                'status'             => 'in_production',
                'fulfillment_status' => $isDraft ? 'draft' : 'submitted',
                'fulfillment_attempts' => (int) $order['fulfillment_attempts'] + 1,
                'last_error'         => null,
                'updated_at'         => now(),
            ], 'id = :id', ['id' => $orderId]);
            self::event($orderId, 'fulfillment', $isDraft
                ? 'Commande créée en brouillon chez Printful (#' . $result['id'] . ') : à confirmer depuis Printful ou l\'administration.'
                : 'Commande transmise à Printful (#' . $result['id'] . '), mise en production.');
            return true;
        } catch (\Throwable $e) {
            $attempts = (int) $order['fulfillment_attempts'] + 1;
            Database::update('orders', [
                'fulfillment_status'   => 'error',
                'fulfillment_attempts' => $attempts,
                'last_error'           => $e->getMessage(),
                'updated_at'           => now(),
            ], 'id = :id', ['id' => $orderId]);
            self::event($orderId, 'error', 'Échec de transmission à Printful (tentative ' . $attempts . ') : ' . $e->getMessage());
            log_message('fulfillment', 'Échec', ['order' => $order['number'], 'error' => $e->getMessage()]);
            if ($attempts === 1 || $attempts >= self::MAX_FULFILLMENT_ATTEMPTS) {
                Mailer::notifyAdmin('⚠ Commande ' . $order['number'] . ' non transmise à Printful', 'admin_alert', [
                    'title'   => 'Transmission à Printful impossible',
                    'message' => $e->getMessage() . ($attempts >= self::MAX_FULFILLMENT_ATTEMPTS
                        ? ' — Nombre maximal de tentatives automatiques atteint, intervention manuelle requise.'
                        : ' — Une nouvelle tentative sera faite automatiquement par la tâche planifiée.'),
                    'order'   => self::find($orderId),
                ]);
            }
            return false;
        }
    }

    public static function buildPrintfulPayload(array $order): array
    {
        $address = json_field($order['shipping_address']);
        $rows = Database::all(
            'SELECT i.*, v.printful_variant_id, v.printful_sync_variant_id,
                    p.design_file, p.print_placement, p.personalization
             FROM order_items i
             LEFT JOIN product_variants v ON v.id = i.variant_id
             LEFT JOIN products p ON p.id = i.product_id
             WHERE i.order_id = ? ORDER BY i.id',
            [$order['id']]
        );

        $items = [];
        foreach ($rows as $row) {
            $line = [
                'external_id'  => 'item-' . $row['id'],
                'quantity'     => (int) $row['quantity'],
                'retail_price' => number_format($row['unit_price'] / 100, 2, '.', ''),
                'name'         => trim($row['product_name'] . ' — ' . $row['variant_name'], ' —'),
            ];
            $text = (string) $row['personalization_text'];

            if ($text !== '' && $row['design_file'] && $row['printful_variant_id']) {
                $file = Personalizer::render($row['design_file'], $text, Personalizer::config($row['personalization']));
                $fileUrl = url($file);
                Database::update('order_items', ['print_file_url' => $fileUrl], 'id = :id', ['id' => $row['id']]);
                $line['variant_id'] = (int) $row['printful_variant_id'];
                $line['files'] = [['type' => $row['print_placement'] ?: 'front', 'url' => $fileUrl]];
            } elseif ($text !== '') {
                throw new \RuntimeException("« {$row['product_name']} » est personnalisé mais n'a pas de visuel d'impression ou d'ID variante Printful catalogue.");
            } elseif ($row['printful_sync_variant_id']) {
                $line['sync_variant_id'] = (int) $row['printful_sync_variant_id'];
            } elseif ($row['printful_variant_id'] && $row['design_file']) {
                $fileUrl = url(Personalizer::publish($row['design_file']));
                $line['variant_id'] = (int) $row['printful_variant_id'];
                $line['files'] = [['type' => $row['print_placement'] ?: 'front', 'url' => $fileUrl]];
            } else {
                throw new \RuntimeException("La variante « {$row['product_name']} — {$row['variant_name']} » n'est reliée à aucun produit Printful.");
            }
            $items[] = $line;
        }

        $country = strtoupper((string) ($address['country'] ?? 'FR'));
        $recipient = [
            'name'         => $address['name'] ?: $order['customer_name'],
            'address1'     => $address['line1'] ?? '',
            'address2'     => $address['line2'] ?? '',
            'city'         => $address['city'] ?? '',
            'zip'          => $address['postal_code'] ?? '',
            'country_code' => $country,
            'email'        => $order['email'],
            'phone'        => $order['phone'],
        ];
        if (in_array($country, ['US', 'CA', 'AU', 'JP'], true) && !empty($address['state'])) {
            $recipient['state_code'] = $address['state'];
        }

        $packingSlip = array_filter([
            'email'           => (string) setting('contact_email'),
            'phone'           => (string) setting('contact_phone'),
            'message'         => mb_substr((string) setting('packing_slip_message'), 0, 200),
            'store_name'      => (string) setting('shop_name'),
            'custom_order_id' => $order['number'],
        ]);

        return [
            'external_id'  => $order['number'],
            'shipping'     => $order['shipping_method'] ?: 'STANDARD',
            'recipient'    => $recipient,
            'items'        => $items,
            'retail_costs' => [
                'currency' => $order['currency'] ?: 'EUR',
                'subtotal' => number_format($order['subtotal'] / 100, 2, '.', ''),
                'discount' => number_format($order['discount'] / 100, 2, '.', ''),
                'shipping' => number_format($order['shipping'] / 100, 2, '.', ''),
                'total'    => number_format($order['total'] / 100, 2, '.', ''),
            ],
            'packing_slip' => $packingSlip,
        ];
    }

    /** Événements Printful (webhook) */
    public static function handlePrintfulEvent(array $payload): void
    {
        $type = (string) ($payload['type'] ?? '');
        $data = $payload['data'] ?? [];
        $pfOrder = $data['order'] ?? [];
        $order = null;
        if (!empty($pfOrder['external_id'])) {
            $order = self::findByNumber((string) $pfOrder['external_id']);
        }
        if (!$order && !empty($pfOrder['id'])) {
            $order = Database::one('SELECT * FROM orders WHERE printful_order_id = ?', [(int) $pfOrder['id']]);
        }
        if (!$order) {
            log_message('printful', 'Webhook sans commande correspondante', ['type' => $type]);
            return;
        }
        $id = (int) $order['id'];

        switch ($type) {
            case 'package_shipped':
                self::markShipped($order, $data['shipment'] ?? []);
                break;
            case 'order_failed':
                $reason = (string) ($data['reason'] ?? 'raison non précisée');
                Database::update('orders', ['fulfillment_status' => 'error', 'last_error' => $reason, 'updated_at' => now()], 'id = :id', ['id' => $id]);
                self::event($id, 'error', 'Printful signale un échec : ' . $reason);
                Mailer::notifyAdmin('⚠ Échec Printful — commande ' . $order['number'], 'admin_alert', ['title' => 'Commande en échec chez Printful', 'message' => $reason, 'order' => $order]);
                break;
            case 'order_canceled':
                Database::update('orders', ['fulfillment_status' => 'canceled', 'updated_at' => now()], 'id = :id', ['id' => $id]);
                self::event($id, 'fulfillment', 'Commande annulée chez Printful.');
                Mailer::notifyAdmin('Commande ' . $order['number'] . ' annulée chez Printful', 'admin_alert', ['title' => 'Annulation Printful', 'message' => 'Pensez à rembourser le client si nécessaire (bouton « Rembourser » dans l\'administration).', 'order' => $order]);
                break;
            case 'order_put_hold':
                $reason = (string) ($data['reason'] ?? '');
                self::event($id, 'fulfillment', 'Commande mise en attente par Printful. ' . $reason);
                Mailer::notifyAdmin('Commande ' . $order['number'] . ' en attente chez Printful', 'admin_alert', ['title' => 'Commande en attente', 'message' => $reason ?: 'Action requise dans votre tableau de bord Printful.', 'order' => $order]);
                break;
            case 'order_remove_hold':
                self::event($id, 'fulfillment', 'Commande relancée par Printful.');
                break;
            case 'package_returned':
                self::event($id, 'fulfillment', 'Colis retourné à Printful : ' . (string) ($data['reason'] ?? ''));
                Mailer::notifyAdmin('Colis retourné — commande ' . $order['number'], 'admin_alert', ['title' => 'Colis retourné', 'message' => (string) ($data['reason'] ?? 'Contactez le client pour vérifier son adresse.'), 'order' => $order]);
                break;
        }
    }

    public static function markShipped(array $order, array $shipment): void
    {
        if ($order['status'] === 'shipped' && $order['tracking_number'] === ($shipment['tracking_number'] ?? null)) {
            return;
        }
        Database::update('orders', [
            'status'             => 'shipped',
            'fulfillment_status' => 'shipped',
            'carrier'            => trim(($shipment['carrier'] ?? '') . ' ' . ($shipment['service'] ?? '')) ?: null,
            'tracking_number'    => $shipment['tracking_number'] ?? null,
            'tracking_url'       => $shipment['tracking_url'] ?? null,
            'last_error'         => null,
            'shipped_at'         => now(),
            'updated_at'         => now(),
        ], 'id = :id', ['id' => $order['id']]);
        $order = self::find((int) $order['id']);
        self::event((int) $order['id'], 'shipped', 'Colis expédié' . ($order['tracking_number'] ? ' — suivi ' . $order['tracking_number'] : '') . '.');
        if ($order['email']) {
            Mailer::sendTemplate($order['email'], 'Votre commande ' . $order['number'] . ' est en route 🐎', 'order_shipped', ['order' => $order, 'items' => self::items((int) $order['id'])]);
        }
    }

    /** Rattrapage si un webhook Printful a été manqué (appelé par la tâche planifiée). */
    public static function syncFromPrintful(array $order): void
    {
        $pf = Printful::fromConfig()->getOrder('@' . $order['number']);
        $status = (string) ($pf['status'] ?? '');
        if (in_array($status, ['fulfilled', 'partial'], true) && !empty($pf['shipments'])) {
            self::markShipped($order, end($pf['shipments']) ?: []);
        } elseif ($status === 'canceled' && $order['fulfillment_status'] !== 'canceled') {
            self::handlePrintfulEvent(['type' => 'order_canceled', 'data' => ['order' => $pf]]);
        } elseif ($status === 'failed' && $order['fulfillment_status'] !== 'error') {
            self::handlePrintfulEvent(['type' => 'order_failed', 'data' => ['order' => $pf, 'reason' => 'Statut Printful : failed']]);
        } elseif ($order['fulfillment_status'] === 'draft' && in_array($status, ['pending', 'inprocess'], true)) {
            Database::update('orders', ['fulfillment_status' => 'submitted', 'updated_at' => now()], 'id = :id', ['id' => $order['id']]);
            self::event((int) $order['id'], 'fulfillment', 'Brouillon confirmé chez Printful, production lancée.');
        }
    }

    public static function handleSessionExpired(array $session): void
    {
        $order = self::orderForSession($session);
        if ($order && $order['status'] === 'pending') {
            Database::update('orders', ['status' => 'canceled', 'updated_at' => now()], "id = :id AND status = 'pending'", ['id' => $order['id']]);
            self::event((int) $order['id'], 'canceled', 'Session de paiement expirée sans règlement (panier abandonné).');
        }
    }

    public static function handleRefund(array $charge): void
    {
        $pi = (string) ($charge['payment_intent'] ?? '');
        $order = $pi !== '' ? Database::one('SELECT * FROM orders WHERE stripe_payment_intent = ?', [$pi]) : null;
        if (!$order) {
            return;
        }
        $refunded = (int) ($charge['amount_refunded'] ?? 0);
        if (!empty($charge['refunded'])) {
            Database::update('orders', ['status' => 'refunded', 'updated_at' => now()], 'id = :id', ['id' => $order['id']]);
            self::event((int) $order['id'], 'refund', 'Commande intégralement remboursée (' . money($refunded) . ').');
        } else {
            self::event((int) $order['id'], 'refund', 'Remboursement partiel : ' . money($refunded) . ' au total.');
        }
    }

    /** Annule les commandes restées « en attente de paiement » au-delà du délai configuré. */
    public static function cancelAbandoned(): int
    {
        $hours = max(25, (int) setting('pending_order_ttl_hours', 48));
        $limit = date('Y-m-d H:i:s', time() - $hours * 3600);
        $rows = Database::all("SELECT id FROM orders WHERE status = 'pending' AND created_at < ?", [$limit]);
        foreach ($rows as $row) {
            Database::update('orders', ['status' => 'canceled', 'updated_at' => now()], "id = :id AND status = 'pending'", ['id' => $row['id']]);
            self::event((int) $row['id'], 'canceled', 'Annulée automatiquement : paiement non finalisé après ' . $hours . ' h.');
        }
        return count($rows);
    }
}
