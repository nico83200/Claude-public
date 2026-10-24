<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Services\Catalog;
use App\Services\Personalizer;
use App\Services\Reviews;

final class ShopController
{
    private const PER_PAGE = 24;

    public function catalog(): void
    {
        $this->listing(null);
    }

    public function category(string $slug): void
    {
        $category = Catalog::category($slug) ?? abort(404);
        $this->listing($category);
    }

    private function listing(?array $category): void
    {
        $filters = [
            'category_id'    => $category['id'] ?? null,
            'q'              => mb_substr((string) input('q', ''), 0, 80),
            'sort'           => (string) input('tri', 'featured'),
            'personalizable' => input('personnalisable') === '1',
            'max_price'      => input('prix_max') ? max(0, (int) input('prix_max')) * 100 : null,
        ];
        if (!array_key_exists($filters['sort'], Catalog::SORTS)) {
            $filters['sort'] = 'featured';
        }
        $title = $category ? $category['name'] : 'La boutique';
        if ($filters['q']) {
            $title = 'Recherche : ' . $filters['q'];
        } elseif (!$category && $filters['max_price']) {
            $title = 'Idées cadeaux à moins de ' . ($filters['max_price'] / 100) . ' €';
        } elseif (!$category && $filters['personalizable']) {
            $title = 'À personnaliser';
        }
        $page = max(1, (int) input('page', 1));
        $total = Catalog::count($filters);

        view('shop/catalog', [
            'pageTitle'       => $title,
            'metaDescription' => $category ? excerpt($category['description']) : 'Tous nos articles équestres imprimés à la demande.',
            'category'        => $category,
            'categories'      => Catalog::categories(),
            'products'        => Catalog::products($filters, self::PER_PAGE, ($page - 1) * self::PER_PAGE),
            'filters'         => $filters,
            'page'            => $page,
            'pages'           => (int) ceil($total / self::PER_PAGE),
            'total'           => $total,
            'canonical'       => $category ? url('categorie/' . $category['slug']) : url('boutique'),
        ]);
    }

    public function product(string $slug): void
    {
        $product = Catalog::product($slug) ?? abort(404);
        $variants = Catalog::variants((int) $product['id']);
        $related = $product['category_id']
            ? Catalog::products(['category_id' => $product['category_id'], 'exclude' => $product['id'], 'sort' => 'bestsellers'], 4)
            : [];
        if (count($related) < 4) {
            $exclude = array_merge([$product['id']], array_column($related, 'id'));
            $related = array_merge($related, Catalog::products(['exclude' => $exclude, 'sort' => 'bestsellers'], 4 - count($related)));
        }
        $reviewSummary = Reviews::enabled() ? Reviews::summary((int) $product['id']) : ['avg' => 0, 'count' => 0, 'dist' => []];
        $reviews = $reviewSummary['count'] ? Reviews::forProduct((int) $product['id']) : [];

        // Guide des tailles : affiché pour les articles textiles (plusieurs tailles de type S, M, L…)
        $sizes = array_filter(array_unique(array_column($variants, 'size')));
        $isApparel = count($sizes) > 1 && (bool) array_intersect($sizes, ['XS', 'S', 'M', 'L', 'XL', '2XL']);
        $sizeGuide = $isApparel ? Database::one("SELECT title, content FROM pages WHERE slug = 'guide-des-tailles'") : null;

        $prices = array_map(static fn ($v) => (int) ($v['price'] ?? $product['price']), $variants) ?: [(int) $product['price']];
        $jsonLd = [
            '@context'    => 'https://schema.org',
            '@type'       => 'Product',
            'name'        => $product['name'],
            'description' => excerpt($product['description'] ?: $product['short_description'], 500),
            'image'       => array_map('image_url', $product['images_list']),
            'sku'         => 'P' . $product['id'],
            'brand'       => ['@type' => 'Brand', 'name' => setting('shop_name')],
            'offers'      => [
                '@type'         => 'AggregateOffer',
                'priceCurrency' => 'EUR',
                'lowPrice'      => number_format(min($prices) / 100, 2, '.', ''),
                'highPrice'     => number_format(max($prices) / 100, 2, '.', ''),
                'offerCount'    => count($prices),
                'availability'  => $variants ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                'url'           => url('produit/' . $product['slug']),
            ],
        ];

        if ($reviewSummary['count'] > 0) {
            $jsonLd['aggregateRating'] = [
                '@type'       => 'AggregateRating',
                'ratingValue' => number_format($reviewSummary['avg'], 1, '.', ''),
                'reviewCount' => $reviewSummary['count'],
                'bestRating'  => 5,
            ];
            $jsonLd['review'] = array_map(static fn ($r) => [
                '@type'         => 'Review',
                'author'        => ['@type' => 'Person', 'name' => $r['author_name']],
                'datePublished' => substr($r['created_at'], 0, 10),
                'reviewRating'  => ['@type' => 'Rating', 'ratingValue' => (int) $r['rating'], 'bestRating' => 5],
                'reviewBody'    => (string) $r['body'],
            ], array_slice($reviews, 0, 5));
        }

        view('shop/product', [
            'reviewSummary'   => $reviewSummary,
            'reviews'         => $reviews,
            'sizeGuide'       => $sizeGuide,
            'delivery'        => delivery_window('standard', $product['personalizable'] ? (int) Personalizer::config($product['personalization'])['extra_days'] : 0),
            'jsonLd'          => $jsonLd,
            'pageTitle'       => $product['seo_title'] ?: $product['name'],
            'metaDescription' => $product['seo_description'] ?: ($product['short_description'] ?: excerpt($product['description'])),
            'ogImage'         => image_url($product['main_image']),
            'canonical'       => url('produit/' . $product['slug']),
            'product'         => $product,
            'variants'        => $variants,
            'personalization' => $product['personalizable'] ? Personalizer::config($product['personalization']) : null,
            'related'         => $related,
        ]);
    }
}
