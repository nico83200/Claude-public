<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Journal (blog) : conseils, idées cadeaux, entretien… pour attirer des visiteuses depuis Google
 * et donner envie, avec les articles de la boutique cités en fin de texte.
 */
final class Journal
{
    public const TAGS = ['Idées cadeaux', 'Conseils', 'Entretien', 'Coulisses', 'Inspiration'];

    public static function available(): bool
    {
        static $ok = null;
        if ($ok === null) {
            try {
                $ok = (int) Database::value('SELECT COUNT(*) FROM journal_posts WHERE published = 1 AND published_at <= ?', [now()]) > 0;
            } catch (\Throwable) {
                $ok = false; // table pas encore créée (mise à jour en cours)
            }
        }
        return $ok;
    }

    public static function published(?string $tag = null, int $limit = 60, ?int $exceptId = null): array
    {
        $sql = 'SELECT * FROM journal_posts WHERE published = 1 AND published_at <= ?';
        $params = [now()];
        if ($tag !== null && $tag !== '') {
            $sql .= ' AND tag = ?';
            $params[] = $tag;
        }
        if ($exceptId) {
            $sql .= ' AND id <> ?';
            $params[] = $exceptId;
        }
        return Database::all($sql . ' ORDER BY published_at DESC, id DESC LIMIT ' . max(1, $limit), $params);
    }

    public static function find(string $slug, bool $publishedOnly = true): ?array
    {
        $post = Database::one('SELECT * FROM journal_posts WHERE slug = ?', [$slug]);
        if (!$post || ($publishedOnly && (!$post['published'] || $post['published_at'] > now()))) {
            return null;
        }
        return $post;
    }

    /** Durée de lecture estimée (200 mots par minute, rythme d'une lecture sur téléphone). */
    public static function readingTime(string $html): int
    {
        $words = preg_match_all("/[\\p{L}\\p{N}'’-]+/u", strip_tags($html));
        return max(1, (int) ceil($words / 200));
    }

    public static function products(array $post): array
    {
        $ids = array_map('intval', json_field($post['product_ids'] ?? null));
        return $ids ? Wishlist::products($ids) : [];
    }

    /** Articles de départ (installation neuve ou mise à jour), seulement si le journal est vide. */
    public static function installStarter(): int
    {
        if ((int) Database::value('SELECT COUNT(*) FROM journal_posts') > 0) {
            return 0;
        }
        $articles = require BASE_PATH . '/database/journal-articles.php';
        $count = 0;
        foreach ($articles as $i => $a) {
            $ids = [];
            foreach ($a['products'] as $slug) {
                $id = Database::value('SELECT id FROM products WHERE slug = ? AND active = 1', [$slug]);
                if ($id) {
                    $ids[] = (int) $id;
                }
            }
            Database::insert('journal_posts', [
                'slug'             => $a['slug'],
                'title'            => $a['title'],
                'tag'              => $a['tag'],
                'excerpt'          => $a['excerpt'],
                'content'          => $a['content'],
                'cover_image'      => is_file(PUBLIC_PATH . '/' . $a['cover']) ? $a['cover'] : null,
                'product_ids'      => json_encode($ids),
                'meta_description' => $a['excerpt'],
                'published'        => 1,
                'published_at'     => date('Y-m-d H:i:s', time() - ($i + 1) * 3 * 86400),
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);
            $count++;
        }
        return $count;
    }
}
