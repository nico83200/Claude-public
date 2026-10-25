<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Liste d'envies : gardée dans le navigateur (localStorage) pour toutes les visiteuses,
 * et enregistrée sur le compte une fois connectée (retrouvée sur tous les appareils).
 */
final class Wishlist
{
    public const MAX = 60;

    /** @return list<int> */
    public static function ids(int $customerId): array
    {
        try {
            return array_map('intval', array_column(Database::all('SELECT product_id FROM wishlist_items WHERE customer_id = ? ORDER BY id DESC', [$customerId]), 'product_id'));
        } catch (\PDOException) {
            return []; // table pas encore créée : mise à jour en cours (migrations à appliquer)
        }
    }

    /** Ajoute les produits (existants et en ligne) à la liste du compte ; renvoie la liste complète. */
    public static function merge(int $customerId, array $productIds): array
    {
        $productIds = self::sanitize($productIds);
        if ($productIds) {
            $valid = array_column(Database::all('SELECT id FROM products WHERE active = 1 AND id IN (' . implode(',', $productIds) . ')'), 'id');
            $current = self::ids($customerId);
            foreach (array_diff(array_map('intval', $valid), $current) as $id) {
                if (count($current) >= self::MAX) {
                    break;
                }
                Database::insert('wishlist_items', ['customer_id' => $customerId, 'product_id' => $id, 'created_at' => now()]);
                $current[] = $id;
            }
        }
        return self::ids($customerId);
    }

    public static function remove(int $customerId, int $productId): void
    {
        Database::query('DELETE FROM wishlist_items WHERE customer_id = ? AND product_id = ?', [$customerId, $productId]);
    }

    /** @return list<int> identifiants positifs, uniques, limités */
    public static function sanitize(array|string $ids): array
    {
        if (is_string($ids)) {
            $ids = explode(',', $ids);
        }
        $clean = array_values(array_unique(array_filter(array_map('intval', $ids), static fn ($i) => $i > 0)));
        return array_slice($clean, 0, self::MAX);
    }

    /** Produits de la liste, dans l'ordre donné. */
    public static function products(array $ids): array
    {
        $ids = self::sanitize($ids);
        if (!$ids) {
            return [];
        }
        $byId = [];
        foreach (Catalog::products(['ids' => $ids], self::MAX) as $p) {
            $byId[(int) $p['id']] = $p;
        }
        return array_values(array_filter(array_map(static fn ($id) => $byId[$id] ?? null, $ids)));
    }
}
