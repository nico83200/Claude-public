<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Panier en session. Les prix sont toujours relus en base (jamais depuis le client).
 */
final class Cart
{
    public const MAX_QTY = 20;

    private static function &store(): array
    {
        if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }
        return $_SESSION['cart'];
    }

    /**
     * @param array{year?:string, portrait?:string} $extra année de naissance et jeton du portrait IA
     */
    public static function add(int $variantId, int $qty, string $text = '', ?string $photo = null, array $extra = []): void
    {
        $cart = &self::store();
        $year = (string) ($extra['year'] ?? '');
        $portrait = $extra['portrait'] ?? null;
        $key = $variantId . ':' . substr(sha1($text . '|' . $photo . '|' . $year . '|' . $portrait), 0, 12);
        $current = $cart[$key]['qty'] ?? 0;
        $cart[$key] = [
            'variant_id' => $variantId, 'qty' => min(self::MAX_QTY, $current + max(1, $qty)),
            'text' => $text, 'photo' => $photo, 'year' => $year, 'portrait' => $portrait,
        ];
    }

    /** Vrai si la photo appartient à une ligne du panier de cette session (accès à la vignette). */
    public static function ownsPhoto(string $photo): bool
    {
        foreach (self::store() as $line) {
            if (($line['photo'] ?? null) === $photo) {
                return true;
            }
        }
        return false;
    }

    public static function update(string $key, int $qty): void
    {
        $cart = &self::store();
        if (!isset($cart[$key])) {
            return;
        }
        if ($qty <= 0) {
            unset($cart[$key]);
            return;
        }
        $cart[$key]['qty'] = min(self::MAX_QTY, $qty);
    }

    public static function remove(string $key): void
    {
        $cart = &self::store();
        unset($cart[$key]);
    }

    public static function clear(): void
    {
        $_SESSION['cart'] = [];
    }

    public static function count(): int
    {
        return array_sum(array_column(self::store(), 'qty'));
    }

    /** Lignes du panier enrichies depuis la base ; les articles devenus indisponibles sont retirés. */
    public static function items(): array
    {
        $cart = &self::store();
        return self::itemsFor($cart);
    }

    /** Lignes enrichies pour un panier donné (achat express : un seul article, hors panier). */
    public static function itemsFor(array &$cart): array
    {
        if (!$cart) {
            return [];
        }
        $ids = array_values(array_unique(array_map(static fn ($l) => (int) $l['variant_id'], $cart)));
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $rows = Database::all(
            "SELECT v.id, v.name AS variant_name, v.sku, v.price AS variant_price, v.image AS variant_image,
                    p.id AS product_id, p.name AS product_name, p.slug, p.price AS base_price, p.images,
                    p.personalizable, p.personalization
             FROM product_variants v JOIN products p ON p.id = v.product_id
             WHERE v.id IN ($placeholders) AND v.active = 1 AND p.active = 1",
            $ids
        );
        $byId = array_column($rows, null, 'id');

        $items = [];
        foreach ($cart as $key => $line) {
            $row = $byId[$line['variant_id']] ?? null;
            // Portrait IA expiré (supprimé après 30 jours sans commande) : la ligne n'est plus valable
            $portrait = $line['portrait'] ?? null;
            $portraitRow = $portrait ? AiPortrait::find($portrait) : null;
            if (!$row || ($portrait && ($portraitRow['status'] ?? '') !== 'ready')) {
                unset($cart[$key]);
                continue;
            }
            $unit = (int) ($row['variant_price'] ?? $row['base_price']);
            $images = json_field($row['images']);
            $cfg = $row['personalizable'] ? Personalizer::config($row['personalization']) : null;
            $items[] = [
                'key'          => $key,
                'variant_id'   => (int) $row['id'],
                'product_id'   => (int) $row['product_id'],
                'product_name' => $row['product_name'],
                'variant_name' => $row['variant_name'],
                'sku'          => $row['sku'],
                'slug'         => $row['slug'],
                'image'        => $row['variant_image'] ?: ($images[0] ?? null),
                'unit_price'   => $unit,
                'qty'          => (int) $line['qty'],
                'line_total'   => $unit * (int) $line['qty'],
                'text'         => (string) ($line['text'] ?? ''),
                'photo'        => $line['photo'] ?? null,
                'year'         => (string) ($line['year'] ?? ''),
                'portrait'     => $line['portrait'] ?? null,
                'portrait_style' => $portraitRow ? AiPortrait::label($portraitRow) : null,
                'mode'         => $cfg['mode'] ?? null,
                'personalization_label' => ($cfg['mode'] ?? null) === 'ai_manga' ? 'Sous l\'illustration' : ($cfg['label'] ?? null),
            ];
        }
        return $items;
    }

    public static function subtotal(?array $items = null): int
    {
        return array_sum(array_column($items ?? self::items(), 'line_total'));
    }

    /** Options de livraison proposées (montants TTC en centimes). */
    public static function shippingOptions(int $subtotal): array
    {
        $threshold = (int) setting('shipping_free_threshold', 0);
        $standard = (int) setting('shipping_standard_price', 0);
        $options = [[
            'code'  => 'STANDARD',
            'label' => (string) setting('shipping_standard_label'),
            'price' => ($threshold > 0 && $subtotal >= $threshold) ? 0 : $standard,
            'min'   => (int) setting('shipping_standard_min', 4),
            'max'   => (int) setting('shipping_standard_max', 8),
        ]];
        $express = (int) setting('shipping_express_price', 0);
        if ($express > 0) {
            $options[] = [
                'code'  => 'EXPRESS',
                'label' => (string) setting('shipping_express_label'),
                'price' => $express,
                'min'   => (int) setting('shipping_express_min', 2),
                'max'   => (int) setting('shipping_express_max', 4),
            ];
        }
        return $options;
    }

    public static function remainingForFreeShipping(int $subtotal): int
    {
        $threshold = (int) setting('shipping_free_threshold', 0);
        return $threshold > 0 ? max(0, $threshold - $subtotal) : 0;
    }
}
