<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Avis clients vérifiés : seuls les clients ayant reçu leur commande peuvent noter les articles achetés
 * (lien personnel envoyé par e-mail). Publication après modération (contenus illicites uniquement).
 */
final class Reviews
{
    private static ?bool $tableExists = null;

    /** Faux si désactivé dans les réglages ou si la migration n'est pas encore passée. */
    public static function enabled(): bool
    {
        if (self::$tableExists === null) {
            try {
                Database::value('SELECT 1 FROM reviews LIMIT 1');
                self::$tableExists = true;
            } catch (\Throwable) {
                self::$tableExists = false;
            }
        }
        return self::$tableExists && setting('reviews_enabled', '1') === '1';
    }

    /** @return array{avg:float, count:int, dist:array<int,int>} */
    public static function summary(int $productId): array
    {
        $dist = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
        foreach (Database::all("SELECT rating, COUNT(*) AS n FROM reviews WHERE product_id = ? AND status = 'approved' GROUP BY rating", [$productId]) as $row) {
            $dist[(int) $row['rating']] = (int) $row['n'];
        }
        $count = array_sum($dist);
        $avg = $count ? array_sum(array_map(static fn ($r, $n) => $r * $n, array_keys($dist), $dist)) / $count : 0.0;
        return ['avg' => round($avg, 1), 'count' => $count, 'dist' => $dist];
    }

    public static function forProduct(int $productId, int $limit = 20): array
    {
        return Database::all(
            "SELECT * FROM reviews WHERE product_id = ? AND status = 'approved' ORDER BY created_at DESC LIMIT " . (int) $limit,
            [$productId]
        );
    }

    /** Note moyenne de toute la boutique (affichée seulement à partir de quelques avis). */
    public static function global(): array
    {
        $row = Database::one("SELECT AVG(rating) AS avg, COUNT(*) AS n FROM reviews WHERE status = 'approved'");
        return ['avg' => round((float) ($row['avg'] ?? 0), 1), 'count' => (int) ($row['n'] ?? 0)];
    }

    public static function latest(int $limit = 6, int $minRating = 4): array
    {
        return Database::all(
            "SELECT r.*, p.name AS product_name, p.slug AS product_slug, p.images AS product_images
             FROM reviews r JOIN products p ON p.id = r.product_id
             WHERE r.status = 'approved' AND r.rating >= ? AND p.active = 1 AND r.body IS NOT NULL AND r.body <> ''
             ORDER BY r.created_at DESC LIMIT " . (int) $limit,
            [$minRating]
        );
    }

    public static function pendingCount(): int
    {
        try {
            return (int) Database::value("SELECT COUNT(*) FROM reviews WHERE status = 'pending'");
        } catch (\Throwable) {
            return 0;
        }
    }

    /** Produits d'une commande, avec l'avis déjà laissé le cas échéant. */
    public static function orderProducts(array $order): array
    {
        return Database::all(
            'SELECT DISTINCT i.product_id, MAX(i.product_name) AS product_name, MAX(i.image) AS image,
                    (SELECT r.id FROM reviews r WHERE r.order_id = ? AND r.product_id = i.product_id) AS review_id
             FROM order_items i JOIN products p ON p.id = i.product_id
             WHERE i.order_id = ? GROUP BY i.product_id',
            [$order['id'], $order['id']]
        );
    }

    public static function submit(array $order, int $productId, int $rating, string $author, string $title, string $body, ?string $photo = null): bool
    {
        if ($rating < 1 || $rating > 5) {
            return false;
        }
        $bought = Database::value('SELECT COUNT(*) FROM order_items WHERE order_id = ? AND product_id = ?', [$order['id'], $productId]);
        $already = Database::value('SELECT COUNT(*) FROM reviews WHERE order_id = ? AND product_id = ?', [$order['id'], $productId]);
        if (!$bought || $already) {
            HomeImages::discard($photo);
            return false;
        }
        Database::insert('reviews', [
            'product_id'  => $productId,
            'order_id'    => $order['id'],
            'author_name' => mb_substr(trim($author), 0, 80) ?: 'Cliente',
            'rating'      => $rating,
            'title'       => mb_substr(trim($title), 0, 120) ?: null,
            'body'        => mb_substr(trim($body), 0, 2000) ?: null,
            'verified'    => 1,
            'photo'       => $photo,
            'status'      => setting('reviews_auto_publish', '0') === '1' ? 'approved' : 'pending',
            'created_at'  => now(),
        ]);
        return true;
    }

    /** Photos des clientes pour un produit (avis publiés). */
    public static function photos(int $productId, int $limit = 8): array
    {
        try {
            return Database::all("SELECT id, photo, author_name, rating FROM reviews WHERE product_id = ? AND status = 'approved' AND photo IS NOT NULL ORDER BY created_at DESC LIMIT " . (int) $limit, [$productId]);
        } catch (\PDOException) {
            return [];
        }
    }

    /** Prénom + initiale : « Marie D. » */
    public static function displayName(?string $fullName): string
    {
        $parts = preg_split('/\s+/', trim((string) $fullName)) ?: [];
        $first = $parts[0] ?? '';
        $last = count($parts) > 1 ? mb_strtoupper(mb_substr(end($parts), 0, 1)) . '.' : '';
        return trim(mb_convert_case($first, MB_CASE_TITLE) . ' ' . $last);
    }

    public static function reviewUrl(array $order): string
    {
        return url('avis/' . rawurlencode($order['number']), ['t' => $order['access_token']]);
    }

    /** Tâche planifiée : invite les clientes à donner leur avis quelques jours après l'expédition. */
    public static function sendRequests(int $limit = 20): int
    {
        if (!self::enabled()) {
            return 0;
        }
        $days = max(1, (int) setting('review_request_days', 10));
        $orders = Database::all(
            "SELECT * FROM orders WHERE status = 'shipped' AND review_requested_at IS NULL AND email IS NOT NULL
             AND shipped_at < ? ORDER BY shipped_at LIMIT " . (int) $limit,
            [date('Y-m-d H:i:s', time() - $days * 86400)]
        );
        foreach ($orders as $order) {
            Database::update('orders', ['review_requested_at' => now()], 'id = :id', ['id' => $order['id']]);
            Mailer::sendTemplate($order['email'], 'Ton avis compte pour nous 💕', 'review_request', [
                'order' => $order,
                'items' => self::orderProducts($order),
                'link'  => self::reviewUrl($order),
            ]);
            OrderService::event((int) $order['id'], 'review', 'Demande d\'avis envoyée.');
        }
        return count($orders);
    }
}
