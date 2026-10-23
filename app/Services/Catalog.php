<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

final class Catalog
{
    public const SORTS = [
        'featured'   => 'p.featured DESC, p.id DESC',
        'new'        => 'p.created_at DESC',
        'price_asc'  => 'p.price ASC',
        'price_desc' => 'p.price DESC',
        'name'       => 'p.name ASC',
    ];

    public static function categories(bool $activeOnly = true): array
    {
        return Database::all(
            'SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id AND p.active = 1) AS product_count
             FROM categories c' . ($activeOnly ? ' WHERE c.active = 1' : '') . ' ORDER BY c.position, c.name'
        );
    }

    public static function category(string $slug): ?array
    {
        return Database::one('SELECT * FROM categories WHERE slug = ? AND active = 1', [$slug]);
    }

    public static function products(array $filters = [], int $limit = 48, int $offset = 0): array
    {
        [$where, $params] = self::where($filters);
        $order = self::SORTS[$filters['sort'] ?? 'featured'] ?? self::SORTS['featured'];
        $rows = Database::all(
            "SELECT p.*, c.name AS category_name, c.slug AS category_slug
             FROM products p LEFT JOIN categories c ON c.id = p.category_id
             WHERE $where ORDER BY $order LIMIT " . (int) $limit . ' OFFSET ' . (int) $offset,
            $params
        );
        return array_map([self::class, 'hydrate'], $rows);
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
        if (!empty($filters['exclude'])) {
            $where[] = 'p.id <> ?';
            $params[] = (int) $filters['exclude'];
        }
        return [implode(' AND ', $where), $params];
    }

    public static function product(string $slug): ?array
    {
        $row = Database::one(
            'SELECT p.*, c.name AS category_name, c.slug AS category_slug
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

    public static function hydrate(array $p): array
    {
        $p['images_list'] = json_field($p['images'] ?? null);
        $p['main_image'] = $p['images_list'][0] ?? null;
        return $p;
    }
}
