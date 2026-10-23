<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\Catalog;

final class HomeController
{
    public function index(): void
    {
        $featured = Catalog::products(['featured' => true], 8);
        if (count($featured) < 4) {
            $featured = Catalog::products(['sort' => 'new'], 8);
        }
        view('shop/home', [
            'pageTitle'       => setting('shop_name') . ' — ' . setting('shop_tagline'),
            'metaDescription' => setting('home_meta_description'),
            'categories'      => Catalog::categories(),
            'featured'        => $featured,
            'personalized'    => Catalog::products(['personalizable' => true], 4),
        ]);
    }
}
