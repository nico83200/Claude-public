<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Services\Cart;
use App\Services\OrderService;
use App\Services\Stripe;

final class CheckoutController
{
    public function start(): void
    {
        $items = Cart::items();
        if (!$items) {
            flash('error', 'Votre panier est vide.');
            redirect('panier');
        }
        if (input('cgv') !== '1') {
            flash('error', 'Merci d\'accepter les conditions générales de vente pour continuer.');
            redirect('panier');
        }
        if ((string) config('stripe.secret_key') === '') {
            flash('error', 'Le paiement en ligne n\'est pas encore activé sur cette boutique.');
            redirect('panier');
        }

        $order = OrderService::createFromCart($items, input('newsletter') === '1');
        $subtotal = Cart::subtotal($items);

        $lineItems = [];
        foreach ($items as $item) {
            $description = trim($item['variant_name'] . ($item['text'] !== '' ? ' · ' . $item['personalization_label'] . ' : ' . $item['text'] : ''));
            $productData = ['name' => $item['product_name']];
            if ($description !== '') {
                $productData['description'] = mb_substr($description, 0, 300);
            }
            if ($item['image'] && preg_match('#^https://#', image_url($item['image'])) && !str_ends_with((string) $item['image'], '.svg')) {
                $productData['images'] = [image_url($item['image'])];
            }
            $lineItems[] = [
                'quantity'   => $item['qty'],
                'price_data' => [
                    'currency'     => config('stripe.currency', 'eur'),
                    'unit_amount'  => $item['unit_price'],
                    'product_data' => $productData,
                ],
            ];
        }

        $shippingOptions = [];
        foreach (Cart::shippingOptions($subtotal) as $option) {
            $shippingOptions[] = ['shipping_rate_data' => [
                'type'         => 'fixed_amount',
                'display_name' => $option['price'] === 0 ? $option['label'] . ' (offerte)' : $option['label'],
                'fixed_amount' => ['amount' => $option['price'], 'currency' => config('stripe.currency', 'eur')],
                'delivery_estimate' => [
                    'minimum' => ['unit' => 'business_day', 'value' => $option['min']],
                    'maximum' => ['unit' => 'business_day', 'value' => $option['max']],
                ],
                'metadata'     => ['code' => $option['code']],
            ]];
        }

        $params = [
            'mode'                 => 'payment',
            'locale'               => 'fr',
            'client_reference_id'  => $order['number'],
            'success_url'          => url('commande/merci') . '?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url'           => url('panier') . '?annule=1',
            'line_items'           => $lineItems,
            'shipping_address_collection' => ['allowed_countries' => array_values((array) config('stripe.allowed_countries'))],
            'shipping_options'     => $shippingOptions,
            'phone_number_collection' => ['enabled' => 'true'],
            'billing_address_collection' => 'auto',
            'allow_promotion_codes' => 'true',
            'metadata'             => ['order_id' => (string) $order['id'], 'order_number' => $order['number']],
            'payment_intent_data'  => [
                'metadata'    => ['order_id' => (string) $order['id'], 'order_number' => $order['number']],
                'description' => 'Commande ' . $order['number'],
            ],
            'expires_at'           => time() + 23 * 3600,
        ];

        try {
            $session = Stripe::fromConfig()->createCheckoutSession($params, 'order-' . $order['number']);
        } catch (\Throwable $e) {
            OrderService::event((int) $order['id'], 'error', 'Création de la session Stripe impossible : ' . $e->getMessage());
            Database::update('orders', ['status' => 'canceled', 'updated_at' => now()], 'id = :id', ['id' => $order['id']]);
            flash('error', 'Le paiement est momentanément indisponible. Merci de réessayer dans quelques instants.');
            redirect('panier');
        }

        Database::update('orders', ['stripe_session_id' => $session['id'], 'updated_at' => now()], 'id = :id', ['id' => $order['id']]);
        $_SESSION['last_order_id'] = (int) $order['id'];
        redirect($session['url'], 303);
    }

    public function success(): void
    {
        $sessionId = (string) input('session_id', '');
        $order = $sessionId !== '' ? Database::one('SELECT * FROM orders WHERE stripe_session_id = ?', [$sessionId]) : null;
        if (!$order) {
            redirect('/');
        }

        // Si le webhook n'est pas encore arrivé, on vérifie directement auprès de Stripe.
        if ($order['status'] === 'pending') {
            try {
                $session = Stripe::fromConfig()->retrieveCheckoutSession($sessionId);
                $order = OrderService::handleCheckoutCompleted($session) ?? $order;
            } catch (\Throwable $e) {
                log_message('stripe', 'Vérification au retour impossible', ['error' => $e->getMessage()]);
            }
        }

        if ($order['status'] !== 'pending') {
            Cart::clear();
        }

        view('shop/success', [
            'pageTitle' => 'Merci pour votre commande',
            'noindex'   => true,
            'order'     => $order,
            'items'     => OrderService::items((int) $order['id']),
        ]);
    }
}
