<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\View;
use App\Services\AiPortrait;
use App\Services\Cart;
use App\Services\Catalog;
use App\Services\Personalizer;
use App\Services\Upload;

final class CartController
{
    /** Données communes au panier et au mini-panier. */
    public static function summary(int $suggestions = 3): array
    {
        $items = Cart::items();
        $subtotal = Cart::subtotal($items);
        $remaining = Cart::remainingForFreeShipping($subtotal);
        $threshold = (int) setting('shipping_free_threshold', 0);
        return [
            'items'     => $items,
            'subtotal'  => $subtotal,
            'shipping'  => Cart::shippingOptions($subtotal),
            'remaining' => $remaining,
            'progress'  => $threshold > 0 ? min(100, (int) round($subtotal * 100 / $threshold)) : 100,
            'boosters'  => $items && $remaining > 0 && $suggestions > 0
                ? Catalog::shippingBoosters($remaining, array_column($items, 'product_id'), $suggestions)
                : [],
        ];
    }

    public function show(): void
    {
        view('shop/cart', self::summary() + [
            'pageTitle'   => 'Mon panier',
            'noindex'     => true,
            'stripeReady' => (string) config('stripe.secret_key') !== '',
        ]);
    }

    /** Contenu du mini-panier latéral (chargé en AJAX). */
    public function drawer(): void
    {
        header('Cache-Control: no-store');
        echo View::capture('partials/cart-drawer', self::summary(2));
    }

    public function add(): void
    {
        $ajax = input('ajax') === '1';
        $fail = static function (string $message, string $back) use ($ajax): never {
            if ($ajax) {
                json_response(['ok' => false, 'message' => $message], 422);
            }
            flash('error', $message);
            back($back);
        };

        $variantId = (int) input('variant_id');
        $row = Database::one(
            'SELECT v.id, p.personalizable, p.personalization, p.slug FROM product_variants v
             JOIN products p ON p.id = v.product_id WHERE v.id = ? AND v.active = 1 AND p.active = 1',
            [$variantId]
        );
        if (!$row) {
            $fail('Cet article n\'est plus disponible.', '/boutique');
        }

        $text = '';
        $photo = null;
        $extra = [];
        if ($row['personalizable']) {
            $cfg = Personalizer::config($row['personalization']);
            $text = Personalizer::sanitize((string) input('personalization', ''), (int) $cfg['max_length']);
            if ($text === '' && !empty($cfg['required'])) {
                $fail('Merci de renseigner : ' . $cfg['label'] . '.', '/produit/' . $row['slug']);
            }
            if ($cfg['mode'] === 'ai_portrait') {
                $token = (string) input('ai_portrait');
                $portrait = AiPortrait::owns($token) ? AiPortrait::find($token) : null;
                if (!$portrait || $portrait['status'] !== 'ready') {
                    $fail('Créez d\'abord le portrait de votre cheval à partir de sa photo.', '/produit/' . $row['slug']);
                }
                try {
                    $year = !empty($cfg['year_enabled']) ? AiPortrait::sanitizeYear(input('year', '')) : '';
                } catch (\InvalidArgumentException $e) {
                    $fail($e->getMessage(), '/produit/' . $row['slug']);
                }
                $extra = ['portrait' => $token, 'year' => $year];
            } elseif (Personalizer::needsPhoto($cfg)) {
                $file = Upload::files('photo')[0] ?? null;
                if (!$file) {
                    $fail('Ajoutez la photo de votre cheval pour personnaliser cet article.', '/produit/' . $row['slug']);
                }
                try {
                    $photo = Upload::customerPhoto($file);
                } catch (\RuntimeException $e) {
                    $fail($e->getMessage(), '/produit/' . $row['slug']);
                }
            }
        }

        Cart::add($variantId, (int) input('qty', 1), $text, $photo, $extra);
        if ($ajax) {
            json_response(['ok' => true, 'count' => Cart::count(), 'html' => View::capture('partials/cart-drawer', self::summary(2) + ['justAdded' => true])]);
        }
        flash('success', 'Article ajouté au panier.');
        redirect('panier');
    }

    /** Vignette de la photo d'une cliente, visible seulement depuis son propre panier. */
    public function photo(string $name): void
    {
        $path = Upload::customerPhotoPath($name);
        if (!$path || !Cart::ownsPhoto($name)) {
            abort(404);
        }
        self::sendThumbnail($path);
    }

    public static function sendThumbnail(string $path, int $size = 400): never
    {
        $img = imagecreatefromjpeg($path);
        $ratio = $size / max(imagesx($img), imagesy($img));
        $thumb = $ratio < 1 ? imagescale($img, (int) round(imagesx($img) * $ratio), -1, IMG_BICUBIC) : $img;
        header('Content-Type: image/jpeg');
        header('Cache-Control: private, max-age=3600');
        imagejpeg($thumb, null, 82);
        exit;
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
