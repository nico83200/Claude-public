<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Services\AiPortrait;
use App\Services\Catalog;
use App\Services\Personalizer;
use App\Services\PortraitStyles;
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
        $category = Catalog::category($slug);
        if (!$category) {
            $target = Catalog::categoryRedirect($slug) ?? abort(404);
            redirect('categorie/' . $target . (($q = $_SERVER['QUERY_STRING'] ?? '') !== '' ? '?' . $q : ''), 301);
        }
        $this->listing($category);
    }

    private function listing(?array $category): void
    {
        $filters = [
            'category_id'    => $category['id'] ?? null,
            'q'              => mb_substr((string) input('q', ''), 0, 80),
            'sort'           => (string) input('tri', 'featured'),
            'personalizable' => input('personnalisable') === '1',
            'audience'       => in_array(input('pour'), ['elle', 'lui'], true) ? (string) input('pour') : null,
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
        } elseif (!$category && $filters['audience']) {
            $title = $filters['audience'] === 'lui' ? 'Pour lui : idées pour les hommes de cavalières' : 'Pour elle : la boutique des cavalières';
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

    /**
     * Assistant cadeau : pour qui, quel budget, personnalisé ou non → une sélection.
     * Si rien ne correspond, les critères sont élargis un à un (personnalisation, puis budget).
     */
    public function giftFinder(): void
    {
        $who = in_array(input('pour'), ['elle', 'lui'], true) ? (string) input('pour') : '';
        $budget = in_array((int) input('budget'), [20, 30, 50, 80], true) ? (int) input('budget') : 0;
        $perso = in_array(input('perso'), ['oui', 'non'], true) ? (string) input('perso') : '';
        $done = input('pour') !== null || input('budget') !== null || input('perso') !== null;
        $results = [];
        $relaxed = null;
        if ($done) {
            $filters = ['sort' => 'bestsellers', 'audience' => $who ?: null, 'max_price' => $budget ? $budget * 100 : null];
            $filters += $perso === 'oui' ? ['personalizable' => true] : ($perso === 'non' ? ['not_personalizable' => true] : []);
            $results = Catalog::products($filters, 12);
            if (!$results && $perso !== '') {
                unset($filters['personalizable'], $filters['not_personalizable']);
                $results = Catalog::products($filters, 12);
                $relaxed = 'personnalisés ou non';
            }
            if (!$results && $budget) {
                $filters['max_price'] = null;
                $results = Catalog::products($filters, 12);
                $relaxed = 'tous budgets confondus';
            }
        }
        view('shop/gift-finder', [
            'pageTitle'       => 'Idées cadeaux pour cavalière (et pour son conjoint !)',
            'metaDescription' => 'Trouve le cadeau idéal pour une cavalière ou pour l\'homme d\'une cavalière en 3 questions : vêtements, accessoires et cadeaux personnalisés avec le nom ou le portrait de son cheval.',
            'canonical'       => url('idees-cadeaux'),
            'who'             => $who,
            'budget'          => $budget,
            'perso'           => $perso,
            'done'            => $done,
            'results'         => $results,
            'relaxed'         => $relaxed,
            'deadline'        => self::christmasDeadline(),
        ]);
    }

    /** Recherche instantanée (JSON) : suggestions au fil de la frappe. */
    public function suggest(): void
    {
        $q = mb_substr(trim((string) input('q', '')), 0, 80);
        header('Cache-Control: public, max-age=300');
        json_response([
            'q'       => $q,
            'items'   => mb_strlen($q) >= 2 ? Catalog::suggest($q) : [],
            'total'   => mb_strlen($q) >= 2 ? Catalog::count(['q' => $q]) : 0,
            'all_url' => url('boutique', ['q' => $q]),
        ]);
    }

    public function product(string $slug): void
    {
        $product = Catalog::product($slug) ?? abort(404);
        $data = self::productData($product);
        $related = $product['category_id']
            ? Catalog::products(['category_id' => $product['category_id'], 'exclude' => $product['id'], 'sort' => 'bestsellers'], 4)
            : [];
        if (count($related) < 4) {
            $exclude = array_merge([$product['id']], array_column($related, 'id'));
            $related = array_merge($related, Catalog::products(['exclude' => $exclude, 'sort' => 'bestsellers'], 4 - count($related)));
        }
        $isAi = ($data['personalization']['mode'] ?? null) === 'ai_portrait';

        view('shop/product', $data + [
            'pageTitle'       => $product['seo_title'] ?: $product['name'],
            'metaDescription' => $product['seo_description'] ?: ($product['short_description'] ?: excerpt($product['description'])),
            'ogImage'         => image_url($product['main_image']),
            'canonical'       => url('produit/' . $product['slug']),
            'related'         => $related,
            'complete'        => Catalog::completeTheLook($product, 3),
            'landingUrl'      => $isAi ? url('portrait-aquarelle') : null,
        ]);
    }

    /**
     * Page dédiée au portrait aquarelle par IA : exemples, explication du style, créateur intégré, FAQ.
     */
    /** Page d'un style (/portrait-cheval/pop-art) : l'aquarelle garde son adresse historique. */
    public function portraitStyle(string $slug): void
    {
        $key = PortraitStyles::bySlug($slug) ?? abort(404);
        if ($key === PortraitStyles::DEFAULT) {
            redirect('portrait-aquarelle', 301);
        }
        $this->portraitLanding($key);
    }

    public function portraitLanding(?string $styleKey = null): void
    {
        $styleKey = isset(PortraitStyles::enabled()[$styleKey ?? PortraitStyles::DEFAULT]) ? ($styleKey ?? PortraitStyles::DEFAULT) : PortraitStyles::selected();
        $page = PortraitStyles::PAGES[$styleKey];
        $product = self::aiPortraitProduct() ?? abort(404);
        $data = self::productData($product);
        $variants = $data['variants'];
        $cfg = $data['personalization'];

        $formats = [];
        $frames = [];
        foreach ($variants as $v) {
            $price = (int) ($v['price'] ?? $product['price']);
            if ($v['size']) {
                $formats[$v['size']] = min($formats[$v['size']] ?? PHP_INT_MAX, $price);
            }
            if ($v['color']) {
                $frames[$v['color']] = $v['color_hex'];
            }
        }
        $minPrice = $variants ? min(array_map(static fn ($v) => (int) ($v['price'] ?? $product['price']), $variants)) : (int) $product['price'];
        $threshold = (int) setting('shipping_free_threshold', 0);
        $global = Reviews::enabled() ? Reviews::global() : ['avg' => 0, 'count' => 0];
        $created = (int) Database::value("SELECT COUNT(*) FROM ai_portraits WHERE status = 'ready'");
        $handDrawn = null;
        foreach (Catalog::products(['personalizable' => true], 50) as $p) {
            if (Personalizer::config($p['personalization'])['mode'] === 'portrait') {
                $handDrawn = $p;
                break;
            }
        }

        $faq = self::portraitFaq($cfg, $formats, $frames, $data['delivery']);
        $jsonLd = [
            '@context' => 'https://schema.org',
            '@graph'   => [
                array_diff_key($data['jsonLd'], ['@context' => 1]),
                [
                    '@type'      => 'FAQPage',
                    'mainEntity' => array_map(static fn ($q) => [
                        '@type'          => 'Question',
                        'name'           => $q[0],
                        'acceptedAnswer' => ['@type' => 'Answer', 'text' => strip_tags($q[1])],
                    ], $faq),
                ],
            ],
        ];

        view('shop/portrait-aquarelle', [
            'pageTitle'       => $page['title'],
            'metaDescription' => $page['meta'],
            'ogImage'         => ($example = PortraitStyles::example($styleKey)) && $styleKey !== PortraitStyles::DEFAULT ? url($example) : image_url($product['main_image']),
            'canonical'       => $styleKey === PortraitStyles::DEFAULT ? url('portrait-aquarelle') : url('portrait-cheval/' . PortraitStyles::STYLES[$styleKey]['slug']),
            'styleKey'        => $styleKey,
            'stylePage'       => $page,
            'jsonLd'          => $jsonLd,
            'formats'         => $formats,
            'frames'          => $frames,
            'minPrice'        => $minPrice,
            'freeShipping'    => $threshold > 0 && $minPrice >= $threshold,
            'globalRating'    => $global,
            'portraitsCount'  => $created,
            'handDrawn'       => $handDrawn,
            'faq'             => $faq,
            'christmas'       => self::christmasDeadline(),
            'perDay'          => max(1, (int) config('ai.daily_per_visitor', 3)),
        ] + $data); // les clés ci-dessus (dont jsonLd) priment sur les données de la fiche
    }

    /** Premier produit actif proposant le portrait IA (null si la fonctionnalité n'est pas installée). */
    public static function aiPortraitProduct(): ?array
    {
        if (!AiPortrait::enabled()) {
            return null;
        }
        foreach (Catalog::products(['personalizable' => true], 50) as $p) {
            if (Personalizer::config($p['personalization'])['mode'] === 'ai_portrait') {
                return Catalog::product($p['slug']);
            }
        }
        return null;
    }

    /** Données communes à la fiche produit et à la page dédiée. */
    private static function productData(array $product): array
    {
        $variants = Catalog::variants((int) $product['id']);
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
        $personalization = $product['personalizable'] ? Personalizer::config($product['personalization']) : null;

        return [
            'product'         => $product,
            'variants'        => $variants,
            'personalization' => $personalization,
            'reviewSummary'   => $reviewSummary,
            'reviews'         => $reviews,
            'sizeGuide'       => $sizeGuide,
            'delivery'        => delivery_window('standard', $personalization ? (int) $personalization['extra_days'] : 0),
            'jsonLd'          => $jsonLd,
        ];
    }

    /** Questions fréquentes du portrait IA (affichées sur la page et transmises à Google). */
    private static function portraitFaq(?array $cfg, array $formats, array $frames, array $delivery): array
    {
        $perDay = max(1, (int) config('ai.daily_per_visitor', 3));
        $formatList = implode(', ', array_map(static fn ($size, $price) => $size . ' (' . money($price) . ')', array_keys($formats), $formats));
        $frameList = mb_strtolower(implode(', ', array_keys($frames)));
        return [
            ['Quelle photo choisir ?', 'Une photo nette et bien éclairée, où la tête de ton cheval est bien visible, de profil ou de trois quarts. Une photo de téléphone suffit. Évite le contre-jour et les photos où il est très loin. Pour un résultat parfait, choisis une photo où il est seul.'],
            ['Quels styles puis-je choisir ?', 'Avec la même photo, tu peux essayer ' . implode(', ', array_map(static fn ($s) => mb_strtolower($s['title']), PortraitStyles::enabled())) . '. Chaque style garde sa robe, ses marques et son regard : tu compares les aperçus et tu commandes celui que tu préfères, au même prix.'],
            ['Combien de temps faut-il pour créer le portrait ?', 'En général moins d\'une minute : tu suis l\'avancement en direct, puis l\'aperçu apparaît dans son cadre.'],
            ['Puis-je essayer avant de commander ?', 'Oui, l\'aperçu est offert et sans engagement. Tu peux créer jusqu\'à ' . $perDay . ' versions par jour et ne commander que celle qui te fait craquer.'],
            ['Le nom et l\'année sont-ils obligatoires ?', (!empty($cfg['required']) ? 'Le nom de ton cheval est inscrit sous le portrait, en écriture manuscrite.' : 'Le nom est facultatif.') . (!empty($cfg['year_enabled']) ? ' L\'année de naissance est facultative : sans elle, le nom est simplement centré un peu plus bas.' : '')],
            ['Quels formats et quels cadres ?', 'Formats : ' . $formatList . '. Cadres : ' . $frameList . '. Le portrait est imprimé en haute définition sur papier d\'art mat, sans filigrane, et livré encadré, prêt à accrocher.'],
            ['Quand vais-je le recevoir ?', 'Commandé aujourd\'hui, il arrive en général entre le ' . $delivery['from'] . ' et le ' . $delivery['to'] . ' : fabrication à la commande puis livraison suivie. Tu reçois le numéro de suivi par e-mail.'],
            ['Que devient la photo de mon cheval ?', 'Elle sert uniquement à créer le dessin et à fabriquer ton article. Si tu ne commandes pas, la photo et le portrait sont supprimés automatiquement après 30 jours.'],
            ['Et si mon tableau arrive abîmé ?', 'Chaque portrait étant créé spécialement pour toi, il n\'est pas soumis au droit de rétractation. En revanche, en cas de défaut ou de casse pendant le transport, nous le réimprimons gratuitement : envoie-nous simplement une photo.'],
        ];
    }

    /** Date limite de commande pour une livraison avant Noël (affichée du 15 octobre à cette date). */
    private static function christmasDeadline(): ?string
    {
        $deadline = christmas_deadline();
        return $deadline ? date_short_fr($deadline) : null;
    }
}
