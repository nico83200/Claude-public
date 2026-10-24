<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\Catalog;
use App\Services\Personalizer;
use App\Services\Reviews;

final class HomeController
{
    public function index(): void
    {
        $bestSellers = Catalog::bestSellers(8);
        if (count($bestSellers) < 4) {
            $bestSellers = Catalog::products(['sort' => 'new'], 8);
        }
        // « Mon cheval, ma fierté » : prix d'appel et lien par type de personnalisation
        $modes = [];
        foreach (Catalog::products(['personalizable' => true, 'sort' => 'price_asc'], 50) as $p) {
            $mode = Personalizer::config($p['personalization'])['mode'];
            $modes[$mode] ??= ['price' => (int) $p['price'], 'slug' => $p['slug'], 'image' => json_field($p['images'])[0] ?? null];
        }
        $global = Reviews::enabled() ? Reviews::global() : ['avg' => 0, 'count' => 0];
        view('shop/home', [
            'bestSellers'     => $bestSellers,
            'modes'           => $modes,
            'newArrivals'     => Catalog::products(['sort' => 'new'], 4),
            'globalRating'    => $global,
            'testimonials'    => $global['count'] >= 3 ? Reviews::latest(6) : [],
            'giftMax'         => (int) setting('gift_max_price', 3000),
            'pageTitle'       => setting('shop_name') . ' — ' . setting('shop_tagline'),
            'metaDescription' => setting('home_meta_description'),
            'categories'      => Catalog::categories(),
            'personalized'    => Catalog::products(['personalizable' => true], 4),
        ]);
    }
}
