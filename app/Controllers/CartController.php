<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Services\Cart;
use App\Services\Personalizer;

final class CartController
{
    public function show(): void
    {
        $items = Cart::items();
        $subtotal = Cart::subtotal($items);
        view('shop/cart', [
            'pageTitle' => 'Mon panier',
            'noindex'   => true,
            'items'     => $items,
            'subtotal'  => $subtotal,
            'shipping'  => Cart::shippingOptions($subtotal),
            'remaining' => Cart::remainingForFreeShipping($subtotal),
            'stripeReady' => (string) config('stripe.secret_key') !== '',
        ]);
    }

    public function add(): void
    {
        $variantId = (int) input('variant_id');
        $row = Database::one(
            'SELECT v.id, p.personalizable, p.personalization, p.slug FROM product_variants v
             JOIN products p ON p.id = v.product_id WHERE v.id = ? AND v.active = 1 AND p.active = 1',
            [$variantId]
        );
        if (!$row) {
            flash('error', 'Cet article n\'est plus disponible.');
            back('/boutique');
        }

        $text = '';
        if ($row['personalizable']) {
            $cfg = Personalizer::config($row['personalization']);
            $text = Personalizer::sanitize((string) input('personalization', ''), (int) $cfg['max_length']);
            if ($text === '' && !empty($cfg['required'])) {
                flash('error', 'Merci de renseigner : ' . $cfg['label'] . '.');
                back('/produit/' . $row['slug']);
            }
        }

        Cart::add($variantId, (int) input('qty', 1), $text);
        if (input('ajax') === '1') {
            json_response(['ok' => true, 'count' => Cart::count()]);
        }
        flash('success', 'Article ajouté au panier.');
        redirect('panier');
    }

    public function update(): void
    {
        foreach ((array) ($_POST['qty'] ?? []) as $key => $qty) {
            Cart::update((string) $key, (int) $qty);
        }
        redirect('panier');
    }

    public function remove(): void
    {
        Cart::remove((string) input('key'));
        flash('success', 'Article retiré du panier.');
        redirect('panier');
    }
}
