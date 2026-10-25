<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Services\Catalog;

final class SeoController
{
    public function sitemap(): void
    {
        header('Content-Type: application/xml; charset=utf-8');
        $urls = [['loc' => url('/'), 'priority' => '1.0'], ['loc' => url('boutique'), 'priority' => '0.9'], ['loc' => url('contact'), 'priority' => '0.3'], ['loc' => url('idees-cadeaux'), 'priority' => '0.8']];
        if (\App\Services\AiPortrait::landingAvailable()) {
            $urls[] = ['loc' => url('portrait-aquarelle'), 'priority' => '0.9'];
        }
        foreach (Catalog::categories() as $c) {
            $urls[] = ['loc' => url('categorie/' . $c['slug']), 'priority' => '0.8'];
        }
        foreach (Database::all('SELECT slug, updated_at FROM products WHERE active = 1') as $p) {
            $urls[] = ['loc' => url('produit/' . $p['slug']), 'priority' => '0.7', 'lastmod' => date('Y-m-d', strtotime($p['updated_at']))];
        }
        if (\App\Services\Journal::available()) {
            $urls[] = ['loc' => url('journal'), 'priority' => '0.6'];
            foreach (\App\Services\Journal::published() as $p) {
                $urls[] = ['loc' => url('journal/' . $p['slug']), 'priority' => '0.6', 'lastmod' => date('Y-m-d', strtotime($p['updated_at']))];
            }
        }
        foreach (Database::all('SELECT slug, updated_at FROM pages') as $p) {
            $urls[] = ['loc' => url('page/' . $p['slug']), 'priority' => '0.3', 'lastmod' => date('Y-m-d', strtotime($p['updated_at']))];
        }
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($urls as $u) {
            echo '  <url><loc>' . e($u['loc']) . '</loc>' . (isset($u['lastmod']) ? '<lastmod>' . $u['lastmod'] . '</lastmod>' : '') . '<priority>' . $u['priority'] . "</priority></url>\n";
        }
        echo '</urlset>';
    }

    public function robots(): void
    {
        header('Content-Type: text/plain; charset=utf-8');
        echo "User-agent: *\nDisallow: /admin\nDisallow: /panier\nDisallow: /commande\nDisallow: /suivi\nDisallow: /uploads/print/\n\nSitemap: " . url('sitemap.xml') . "\n";
    }

    /** Flux produits pour Google Merchant Center (fiches gratuites Google Shopping). */
    public function merchantFeed(): void
    {
        header('Content-Type: application/xml; charset=utf-8');
        $rows = Database::all(
            'SELECT p.*, v.id AS variant_id, v.name AS variant_name, v.color, v.size, v.price AS variant_price, v.image AS variant_image, v.sku, c.name AS category_name
             FROM product_variants v JOIN products p ON p.id = v.product_id LEFT JOIN categories c ON c.id = p.category_id
             WHERE p.active = 1 AND v.active = 1 ORDER BY p.id, v.position'
        );
        $shipping = (int) setting('shipping_standard_price', 0);
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0"><channel>' . "\n";
        echo '<title>' . e(setting('shop_name')) . '</title><link>' . e(url('/')) . '</link><description>' . e(setting('shop_tagline')) . "</description>\n";
        foreach ($rows as $r) {
            $images = json_field($r['images']);
            $image = $r['variant_image'] ?: ($images[0] ?? null);
            echo "<item>\n";
            echo '  <g:id>' . e($r['sku'] ?: 'V' . $r['variant_id']) . "</g:id>\n";
            echo '  <g:item_group_id>P' . (int) $r['id'] . "</g:item_group_id>\n";
            echo '  <g:title>' . e(trim($r['name'] . ' - ' . $r['variant_name'], ' -')) . "</g:title>\n";
            echo '  <g:description>' . e(excerpt($r['description'] ?: $r['short_description'], 4000)) . "</g:description>\n";
            echo '  <g:link>' . e(url('produit/' . $r['slug'])) . "</g:link>\n";
            echo '  <g:image_link>' . e(image_url($image)) . "</g:image_link>\n";
            echo "  <g:availability>in_stock</g:availability>\n";
            echo '  <g:price>' . number_format(((int) ($r['variant_price'] ?? $r['price'])) / 100, 2, '.', '') . " EUR</g:price>\n";
            echo "  <g:condition>new</g:condition>\n  <g:identifier_exists>no</g:identifier_exists>\n";
            echo '  <g:brand>' . e(setting('shop_name')) . "</g:brand>\n";
            if ($r['category_name']) {
                echo '  <g:product_type>' . e($r['category_name']) . "</g:product_type>\n";
            }
            if ($r['color']) {
                echo '  <g:color>' . e($r['color']) . "</g:color>\n";
            }
            if ($r['size']) {
                echo '  <g:size>' . e($r['size']) . "</g:size>\n";
            }
            echo '  <g:shipping><g:country>FR</g:country><g:price>' . number_format($shipping / 100, 2, '.', '') . " EUR</g:price></g:shipping>\n";
            echo "</item>\n";
        }
        echo '</channel></rss>';
    }
}
