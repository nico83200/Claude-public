<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\Catalog;
use App\Services\Personalizer;

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
        ];
        $page = max(1, (int) input('page', 1));
        $total = Catalog::count($filters);

        view('shop/catalog', [
            'pageTitle'       => $category ? $category['name'] : ($filters['q'] ? 'Recherche : ' . $filters['q'] : 'La boutique'),
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
            ? Catalog::products(['category_id' => $product['category_id'], 'exclude' => $product['id']], 4)
            : [];

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

        view('shop/product', [
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
