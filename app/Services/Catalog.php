<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

final class Catalog
{
    public const SORTS = [
        'featured'   => 'p.featured DESC, p.id DESC',
        'bestsellers' => 'sold DESC, p.featured DESC, p.id DESC',
        'new'        => 'p.created_at DESC',
        'price_asc'  => 'p.price ASC',
        'price_desc' => 'p.price DESC',
        'rating'     => 'rating_avg DESC, rating_count DESC',
        'name'       => 'p.name ASC',
    ];

    /** Fenêtre utilisée pour les « meilleures ventes ». */
    private const BESTSELLER_DAYS = 90;

    private static ?bool $hasReviews = null;
    private static ?array $bestSellerIds = null;

    public static function categories(bool $activeOnly = true): array
    {
        return Database::all(
            'SELECT c.*,
                (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id AND p.active = 1) AS product_count,
                (SELECT p.images FROM products p WHERE p.category_id = c.id AND p.active = 1 ORDER BY p.featured DESC, p.id LIMIT 1) AS cover_images
             FROM categories c' . ($activeOnly ? ' WHERE c.active = 1' : '') . ' ORDER BY c.position, c.name'
        );
    }

    /** Visuel d'une catégorie : image dédiée, sinon celle de son produit phare. */
    public static function categoryImage(array $category): ?string
    {
        return $category['image'] ?: (json_field($category['cover_images'] ?? null)[0] ?? null);
    }

    public static function category(string $slug): ?array
    {
        return Database::one('SELECT * FROM categories WHERE slug = ? AND active = 1', [$slug]);
    }

    /** Colonnes calculées : note moyenne, nombre d'avis, ventes récentes. */
    private static function computedColumns(): string
    {
        $since = date('Y-m-d H:i:s', time() - self::BESTSELLER_DAYS * 86400);
        $sold = "(SELECT COALESCE(SUM(i.quantity), 0) FROM order_items i JOIN orders o ON o.id = i.order_id
                  WHERE i.product_id = p.id AND o.paid_at IS NOT NULL AND o.status <> 'refunded' AND o.paid_at >= '$since') AS sold";
        if (self::hasReviews()) {
            return "$sold,
                (SELECT AVG(r.rating) FROM reviews r WHERE r.product_id = p.id AND r.status = 'approved') AS rating_avg,
                (SELECT COUNT(*) FROM reviews r WHERE r.product_id = p.id AND r.status = 'approved') AS rating_count";
        }
        return "$sold, NULL AS rating_avg, 0 AS rating_count";
    }

    /** La table des avis peut manquer brièvement pendant une mise à jour (migration pas encore passée). */
    private static function hasReviews(): bool
    {
        if (self::$hasReviews === null) {
            try {
                Database::value('SELECT 1 FROM reviews LIMIT 1');
                self::$hasReviews = true;
            } catch (\Throwable) {
                self::$hasReviews = false;
            }
        }
        return self::$hasReviews;
    }

    public static function products(array $filters = [], int $limit = 48, int $offset = 0): array
    {
        [$where, $params] = self::where($filters);
        $order = self::SORTS[$filters['sort'] ?? 'featured'] ?? self::SORTS['featured'];
        $rows = Database::all(
            'SELECT p.*, c.name AS category_name, c.slug AS category_slug, ' . self::computedColumns() . "
             FROM products p LEFT JOIN categories c ON c.id = p.category_id
             WHERE $where ORDER BY $order LIMIT " . (int) $limit . ' OFFSET ' . (int) $offset,
            $params
        );
        return array_map([self::class, 'hydrate'], $rows);
    }

    /** Meilleures ventes réelles ; complétées par les produits mis en avant tant qu'il y a peu de ventes. */
    public static function bestSellers(int $limit = 8): array
    {
        $products = array_values(array_filter(self::products(['sort' => 'bestsellers'], $limit), static fn ($p) => (int) $p['sold'] > 0));
        if (count($products) < $limit) {
            $ids = array_column($products, 'id');
            foreach (self::products(['featured' => true], $limit) as $p) {
                if (count($products) < $limit && !in_array($p['id'], $ids, true)) {
                    $products[] = $p;
                }
            }
        }
        return $products;
    }

    public static function count(array $filters = []): int
    {
        [$where, $params] = self::where($filters);
        return (int) Database::value("SELECT COUNT(*) FROM products p WHERE $where", $params);
    }

    private static function where(array $filters): array
    {
        $where = ['p.active = 1'];
        $params = [];
        if (!empty($filters['category_id'])) {
            $where[] = 'p.category_id = ?';
            $params[] = (int) $filters['category_id'];
        }
        if (!empty($filters['q'])) {
            $where[] = '(p.name LIKE ? OR p.short_description LIKE ?)';
            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], (string) $filters['q']) . '%';
            array_push($params, $like, $like);
        }
        if (!empty($filters['personalizable'])) {
            $where[] = 'p.personalizable = 1';
        }
        if (!empty($filters['featured'])) {
            $where[] = 'p.featured = 1';
        }
        if (!empty($filters['max_price'])) {
            $where[] = 'p.price <= ?';
            $params[] = (int) $filters['max_price'];
        }
        if (!empty($filters['min_price'])) {
            $where[] = 'p.price >= ?';
            $params[] = (int) $filters['min_price'];
        }
        if (!empty($filters['exclude'])) {
            $ids = array_map('intval', (array) $filters['exclude']);
            $where[] = 'p.id NOT IN (' . implode(',', $ids) . ')';
        }
        return [implode(' AND ', $where), $params];
    }

    public static function product(string $slug): ?array
    {
        $row = Database::one(
            'SELECT p.*, c.name AS category_name, c.slug AS category_slug, ' . self::computedColumns() . '
             FROM products p LEFT JOIN categories c ON c.id = p.category_id
             WHERE p.slug = ? AND p.active = 1',
            [$slug]
        );
        return $row ? self::hydrate($row) : null;
    }

    public static function variants(int $productId, bool $activeOnly = true): array
    {
        return Database::all(
            'SELECT * FROM product_variants WHERE product_id = ?' . ($activeOnly ? ' AND active = 1' : '') . ' ORDER BY position, id',
            [$productId]
        );
    }

    /**
     * Suggestions pour atteindre la livraison offerte : produits dont le prix couvre le montant manquant,
     * du moins cher au plus cher (à défaut, les moins chers).
     */
    public static function shippingBoosters(int $remaining, array $excludeIds, int $limit = 3): array
    {
        $filters = ['exclude' => $excludeIds ?: [0], 'sort' => 'price_asc'];
        $covering = self::products($filters + ['min_price' => $remaining], $limit);
        return $covering ?: self::products($filters, $limit);
    }

    private static function bestSellerIds(): array
    {
        if (self::$bestSellerIds === null) {
            $since = date('Y-m-d H:i:s', time() - self::BESTSELLER_DAYS * 86400);
            self::$bestSellerIds = array_map('intval', array_column(Database::all(
                "SELECT i.product_id, SUM(i.quantity) AS qty FROM order_items i JOIN orders o ON o.id = i.order_id
                 WHERE o.paid_at IS NOT NULL AND o.status <> 'refunded' AND o.paid_at >= ? AND i.product_id IS NOT NULL
                 GROUP BY i.product_id HAVING SUM(i.quantity) >= 3 ORDER BY qty DESC LIMIT 3",
                [$since]
            ), 'product_id'));
        }
        return self::$bestSellerIds;
    }

    public static function hydrate(array $p): array
    {
        $p['images_list'] = json_field($p['images'] ?? null);
        $p['main_image'] = $p['images_list'][0] ?? null;
        $p['hover_image'] = $p['images_list'][1] ?? null;
        $p['rating_avg'] = isset($p['rating_avg']) ? round((float) $p['rating_avg'], 1) : 0.0;
        $p['rating_count'] = (int) ($p['rating_count'] ?? 0);

        // Un seul badge principal, du plus convaincant au plus informatif.
        $p['badge'] = null;
        if (in_array((int) $p['id'], self::bestSellerIds(), true)) {
            $p['badge'] = 'Best-seller';
        } elseif (!empty($p['created_at']) && strtotime($p['created_at']) > time() - 21 * 86400) {
            $p['badge'] = 'Nouveau';
        }
        $p['discount'] = ($p['compare_at_price'] ?? 0) > $p['price']
            ? (int) round(100 - $p['price'] * 100 / $p['compare_at_price'])
            : 0;
        return $p;
    }
}
