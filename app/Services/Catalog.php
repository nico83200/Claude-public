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

    /** Nouvelle adresse d'une catégorie renommée ou fusionnée (redirection 301), sinon null. */
    public static function categoryRedirect(string $slug): ?string
    {
        $map = json_decode((string) setting('category_redirects', ''), true) ?: [];
        for ($i = 0; $i < 5 && isset($map[$slug]); $i++) {
            $slug = $map[$slug];
            if (self::category($slug)) {
                return $slug;
            }
        }
        return null;
    }

    /** Mémorise l'ancienne adresse d'une catégorie pour que ses liens restent valables. */
    public static function rememberSlug(string $old, string $new): void
    {
        if ($old === '' || $old === $new) {
            return;
        }
        $map = json_decode((string) setting('category_redirects', ''), true) ?: [];
        unset($map[$new]); // l'adresse redevient celle d'une vraie catégorie
        $map[$old] = $new;
        \App\Core\Settings::set('category_redirects', json_encode($map, JSON_UNESCAPED_SLASHES));
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
            // Chaque mot doit apparaître quelque part : nom, accroche, description ou collection
            foreach (self::searchTerms((string) $filters['q']) as $term) {
                $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $term) . '%';
                $where[] = '(p.name LIKE ? OR p.short_description LIKE ? OR p.description LIKE ? OR p.category_id IN (SELECT id FROM categories WHERE name LIKE ?))';
                array_push($params, $like, $like, $like, $like);
            }
        }
        if (!empty($filters['personalizable'])) {
            $where[] = 'p.personalizable = 1';
        }
        if (!empty($filters['not_personalizable'])) {
            $where[] = 'p.personalizable = 0';
        }
        if (!empty($filters['audience']) && isset(self::AUDIENCES[$filters['audience']])) {
            // « Pour elle » : articles pour elle + articles pour tous
            $where[] = "(p.audience = ? OR p.audience = 'tous' OR p.audience IS NULL)";
            $params[] = $filters['audience'];
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
        if (!empty($filters['ids'])) {
            $where[] = 'p.id IN (' . implode(',', array_map('intval', (array) $filters['ids'])) . ')';
        }
        if (!empty($filters['exclude'])) {
            $ids = array_map('intval', (array) $filters['exclude']);
            $where[] = 'p.id NOT IN (' . implode(',', $ids) . ')';
        }
        return [implode(' AND ', $where), $params];
    }

    /**
     * « Complète le look » : accessoires d'autres collections, sans taille à choisir (une seule variante
     * ou une seule taille), ajoutables en un clic. Chaque produit reçoit la variante proposée.
     */
    public static function completeTheLook(array $product, int $limit = 3): array
    {
        $out = [];
        foreach (self::products(['exclude' => [(int) $product['id']], 'sort' => 'bestsellers'], 40) as $p) {
            if ($p['personalizable'] || ((int) $p['category_id'] === (int) $product['category_id'] && $product['category_id'])) {
                continue;
            }
            $variants = self::variants((int) $p['id']);
            $sizes = array_unique(array_filter(array_column($variants, 'size'), static fn ($s) => $s !== null && $s !== ''));
            if (!$variants || count($sizes) > 1) {
                continue;
            }
            $out[] = $p + ['variant_id' => (int) $variants[0]['id']];
            if (count($out) >= $limit) {
                break;
            }
        }
        return $out;
    }

    public const AUDIENCES = ['elle' => 'Pour elle', 'lui' => 'Pour lui'];

    /** « elle », « lui » ou « tous », déduit du nom du produit et de sa collection. */
    public static function guessAudience(string $name, string $category = ''): string
    {
        $text = mb_strtolower($name . ' ' . $category);
        if (preg_match('/\b(homme|hommes|lui|mari|conjoint|conjoints|papa|monsieur)\b/u', $text)) {
            return 'lui';
        }
        if (preg_match('/\b(femme|femmes|elle|cavalière|cavalières|maman|madame|fille)\b/u', $text)) {
            return 'elle';
        }
        return 'tous';
    }

    /** Synonymes courants (« tee-shirt » trouve les t-shirts, « tasse » les mugs…). */
    private const SYNONYMS = [
        'tshirt' => 't-shirt', 'tee-shirt' => 't-shirt', 'teeshirt' => 't-shirt', 'tee' => 't-shirt',
        'hoodie' => 'capuche', 'sweatshirt' => 'sweat', 'sweat-shirt' => 'sweat', 'pull' => 'sweat',
        'tasse' => 'mug', 'poster' => 'affiche', 'tableau' => 'affiche', 'cadre' => 'encadr', 'sac' => 'tote',
        'bouteille' => 'gourde', 'gobelet' => 'gourde', 'carnet' => 'carnet', 'agenda' => 'carnet',
        'aquarelle' => 'aquarelle', 'dessin' => 'portrait', 'illustration' => 'portrait', 'nom' => 'nom',
        'cheval' => 'cheval', 'chevaux' => 'cheval', 'jument' => 'cheval', 'poney' => 'cheval',
    ];
    private const STOP_WORDS = ['de', 'du', 'des', 'la', 'le', 'les', 'un', 'une', 'pour', 'mon', 'ma', 'mes', 'son', 'sa', 'ses', 'avec', 'en', 'et', 'au', 'aux', 'à'];

    /** Mots utiles d'une recherche : minuscules, sans mots vides, pluriels simples, synonymes. */
    public static function searchTerms(string $q): array
    {
        $words = preg_split('/[\s,;\/]+/u', mb_strtolower(trim($q)), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $terms = [];
        foreach ($words as $w) {
            $w = trim($w, ".!?'\"«»()");
            if ($w === '' || in_array($w, self::STOP_WORDS, true)) {
                continue;
            }
            $w = self::SYNONYMS[$w] ?? $w;
            if (mb_strlen($w) > 4 && preg_match('/[^s]s$/u', $w)) {
                $w = mb_substr($w, 0, -1); // sweats → sweat, affiches → affiche
            }
            if (mb_strlen($w) >= 2) {
                $terms[$w] = $w;
            }
        }
        return array_slice(array_values($terms), 0, 6) ?: [mb_strtolower(trim($q))];
    }

    /** Suggestions de la recherche instantanée. */
    public static function suggest(string $q, int $limit = 6): array
    {
        $out = [];
        foreach (self::products(['q' => $q, 'sort' => 'bestsellers'], $limit) as $p) {
            $out[] = [
                'name'     => typo($p['name']),
                'url'      => url('produit/' . $p['slug']),
                'price'    => money($p['price']),
                'image'    => thumb($p['main_image'], 160),
                'category' => (string) ($p['category_name'] ?? ''),
                'custom'   => (bool) $p['personalizable'],
            ];
        }
        return $out;
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
        // Images introuvables sur le serveur (fichier supprimé) ignorées : pas de vignette cassée
        $p['images_list'] = array_values(array_filter(json_field($p['images'] ?? null), static fn ($img) => is_string($img) && $img !== ''
            && (preg_match('#^https?://#', $img) || is_file(PUBLIC_PATH . '/' . ltrim($img, '/')))));
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
