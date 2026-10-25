<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Services\Account;
use App\Services\Cart;
use App\Services\OrderService;
use App\Services\Stripe;

final class CheckoutController
{
    public function start(): void
    {
        $items = Cart::items();
        if (!$items) {
            flash('error', 'Ton panier est vide.');
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
        $this->checkout($items, input('newsletter') === '1', url('panier') . '?annule=1');
    }

    /**
     * Achat express depuis la fiche produit (articles sans personnalisation) : paiement Stripe direct
     * (Apple Pay, Google Pay, carte) pour cet article seul ; le panier n'est pas modifié.
     */
    public function express(): void
    {
        $variantId = (int) input('variant_id');
        $row = Database::one('SELECT v.id, p.slug, p.personalizable FROM product_variants v JOIN products p ON p.id = v.product_id
            WHERE v.id = ? AND v.active = 1 AND p.active = 1', [$variantId]);
        if (!$row || $row['personalizable']) {
            flash('error', 'Cet article ne peut pas être acheté en express.');
            redirect($row ? 'produit/' . $row['slug'] : 'boutique');
        }
        if ((string) config('stripe.secret_key') === '') {
            flash('error', 'Le paiement en ligne n\'est pas encore activé sur cette boutique.');
            redirect('produit/' . $row['slug']);
        }
        $lines = ['express' => ['variant_id' => $variantId, 'qty' => max(1, min(20, (int) input('qty', 1))), 'text' => '', 'photo' => null, 'year' => '', 'portrait' => null]];
        $items = Cart::itemsFor($lines);
        if (!$items) {
            flash('error', 'Cet article n\'est plus disponible.');
            redirect('produit/' . $row['slug']);
        }
        $_SESSION['express_checkout'] = true;
        $this->checkout($items, false, url('produit/' . $row['slug']), true);
    }

    private function checkout(array $items, bool $newsletter, string $cancelUrl, bool $express = false): never
    {
        $order = OrderService::createFromCart($items, $newsletter);
        $customer = Account::current();
        $extra = [];
        if ($customer) {
            // E-mail connu dès maintenant : commande rattachée, et relance possible si le paiement n'aboutit pas
            $extra += ['customer_id' => $customer['id'], 'email' => $customer['email']];
        }
        $giftMessage = trim(str_replace(["\r\n", "\r"], "\n", (string) input('gift_message', '')));
        if (input('gift') === '1') {
            $extra += [
                'is_gift'      => 1,
                'gift_to'      => mb_substr(trim((string) input('gift_to', '')), 0, 60) ?: null,
                'gift_message' => $giftMessage !== '' ? mb_substr($giftMessage, 0, 200) : null,
            ];
        }
        if ($extra) {
            Database::update('orders', $extra, 'id = :id', ['id' => $order['id']]);
            $order = OrderService::find((int) $order['id']);
        }
        $_SESSION['want_account'] = !$customer && input('create_account') === '1';
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
            'cancel_url'           => $cancelUrl,
            'line_items'           => $lineItems,
            'shipping_address_collection' => ['allowed_countries' => array_values((array) config('stripe.allowed_countries'))],
            'shipping_options'     => $shippingOptions,
            'phone_number_collection' => ['enabled' => 'true'],
            'billing_address_collection' => 'auto',
            'allow_promotion_codes' => 'true',
            'metadata'             => ['order_id' => (string) $order['id'], 'order_number' => $order['number']] + ($order['is_gift'] ? ['cadeau' => 'oui'] : []),
            'payment_intent_data'  => [
                'metadata'    => ['order_id' => (string) $order['id'], 'order_number' => $order['number']],
                'description' => 'Commande ' . $order['number'],
            ],
            'expires_at'           => time() + 23 * 3600,
        ];
        if ($customer) {
            $params['customer_email'] = $customer['email'];
        }

        try {
            $session = Stripe::fromConfig()->createCheckoutSession($params, 'order-' . $order['number']);
        } catch (\Throwable $e) {
            OrderService::event((int) $order['id'], 'error', 'Création de la session Stripe impossible : ' . $e->getMessage());
            Database::update('orders', ['status' => 'canceled', 'updated_at' => now()], 'id = :id', ['id' => $order['id']]);
            flash('error', 'Le paiement est momentanément indisponible. Réessaie dans quelques instants.');
            redirect('panier');
        }

        Database::update('orders', ['stripe_session_id' => $session['id'], 'updated_at' => now()], 'id = :id', ['id' => $order['id']]);
        $_SESSION['last_order_id'] = (int) $order['id'];
        $_SESSION['express_order'] = $express ? (int) $order['id'] : null;
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

        // Achat express : le panier (autres articles) est conservé
        if ($order['status'] !== 'pending' && (int) ($_SESSION['express_order'] ?? 0) !== (int) $order['id']) {
            Cart::clear();
        }

        view('shop/success', [
            'pageTitle' => 'Merci pour ta commande',
            'noindex'   => true,
            'order'     => $order,
            'items'     => OrderService::items((int) $order['id']),
            'customer'  => Account::current(),
            'accountExists' => $order['email'] ? (bool) Account::findByEmail((string) $order['email']) : false,
            'wantAccount'   => !empty($_SESSION['want_account']),
        ]);
    }
}
