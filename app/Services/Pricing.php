<?php
declare(strict_types=1);

namespace App\Services;

/** Prix de vente à partir du coût Printful : TVA 20 %, puis marge 50 %, arrondi aux 10 centimes inférieurs. */
final class Pricing
{
    public const VAT = 0.20;
    public const MARGIN = 0.50;

    /** @param int $cost coût HT en centimes */
    public static function retail(int $cost): int
    {
        // coût × 1,2 × 1,5 = coût × 1,8 (en entiers pour éviter les arrondis flottants)
        return intdiv($cost * 18, 100) * 10;
    }

    /**
     * Applique la formule aux variantes qui ont un coût : prix de base = le moins cher, prix de variante seulement s'il diffère.
     * @return int|null nouveau prix de base (null si aucune variante n'a de coût)
     */
    public static function applyToProduct(int $productId): ?int
    {
        $variants = \App\Core\Database::all('SELECT id, cost FROM product_variants WHERE product_id = ? AND cost IS NOT NULL AND cost > 0', [$productId]);
        if (!$variants) {
            return null;
        }
        $prices = array_map(static fn ($v) => self::retail((int) $v['cost']), $variants);
        $base = min($prices);
        foreach ($variants as $i => $v) {
            \App\Core\Database::update('product_variants', ['price' => $prices[$i] !== $base ? $prices[$i] : null], 'id = :id', ['id' => $v['id']]);
        }
        \App\Core\Database::update('products', ['price' => $base, 'updated_at' => now()], 'id = :id', ['id' => $productId]);
        return $base;
    }
}
