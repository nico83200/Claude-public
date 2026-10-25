<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Installe un lot d'articles préparés (fichier JSON de database/) : hors ligne, à vérifier puis publier.
 * Un article dont l'adresse existe déjà n'est jamais modifié.
 */
final class CatalogPack
{
    public static function install(string $file): int
    {
        $items = json_decode((string) file_get_contents($file), true) ?: [];
        $categories = array_column(Database::all('SELECT id, slug FROM categories'), 'id', 'slug');
        $fallback = (int) (Database::value('SELECT id FROM categories WHERE active = 1 ORDER BY position, id LIMIT 1') ?: 0);
        $count = 0;
        foreach ($items as $item) {
            if (Database::value('SELECT id FROM products WHERE slug = ?', [$item['slug']])) {
                continue;
            }
            $cfg = $item['personalization'] ?? null;
            $id = Database::insert('products', [
                'name'              => $item['name'],
                'slug'              => $item['slug'],
                'short_description' => $item['short_description'],
                'description'       => $item['description'],
                'price'             => (int) $item['price'],
                'images'            => json_encode($item['images'], JSON_UNESCAPED_SLASHES),
                'featured'          => 0,
                'active'            => 0, // brouillon : visuels à créer avant la mise en ligne
                'audience'          => $item['audience'] ?? 'tous',
                'personalizable'    => $cfg ? 1 : 0,
                'personalization'   => $cfg ? json_encode($cfg, JSON_UNESCAPED_UNICODE) : null,
                'print_placement'   => $item['print_placement'] ?? 'default',
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);
            $principal = (int) ($categories[$item['category']] ?? $fallback) ?: null;
            $extra = array_map(static fn ($slug) => (int) ($categories[$slug] ?? 0), $item['extra_categories'] ?? []);
            Catalog::setCategories($id, $principal, $extra);
            foreach ($item['variants'] as $i => $v) {
                Database::insert('product_variants', [
                    'product_id'          => $id,
                    'name'                => trim(($v['color'] ? $v['color'] . ' / ' : '') . $v['size'], ' /') ?: $item['name'],
                    'color'               => $v['color'] ?: null,
                    'color_hex'           => $v['color_hex'] ?: null,
                    'size'                => $v['size'] ?: null,
                    'price'               => $v['price'],
                    'cost'                => $v['cost'],
                    'printful_variant_id' => $v['printful_variant_id'],
                    'image'               => $v['image'] ?: null,
                    'position'            => $i,
                    'active'              => 1,
                ]);
            }
            $count++;
        }
        return $count;
    }
}
