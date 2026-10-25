<?php
declare(strict_types=1);

use App\Core\Database;
use App\Core\Settings;

/**
 * Données initiales : réglages, pages légales (modèles à compléter) et, en option, un catalogue de démonstration.
 */
function seed_database(string $shopName, string $contactEmail, bool $demo = true): void
{
    Settings::set('shop_name', $shopName);
    Settings::set('contact_email', $contactEmail);

    foreach (seed_pages() as $i => $page) {
        Database::insert('pages', $page + ['in_footer' => 1, 'updated_at' => now()]);
    }

    if ($demo) {
        seed_demo_catalog();
    }
    App\Services\Journal::installStarter();
    foreach (Database::all('SELECT p.id, p.name, c.name AS category FROM products p LEFT JOIN categories c ON c.id = p.category_id') as $p) {
        Database::update('products', ['audience' => App\Services\Catalog::guessAudience($p['name'], (string) $p['category'])], 'id = :id', ['id' => $p['id']]);
    }
}

function seed_demo_catalog(): void
{
    $collections = [
        ['Le Club', 'le-club', 'Vêtements et accessoires à l\'effigie de Crinière Club, le club des passionnées. À adopter tout de suite, pour l\'écurie comme pour la ville.', 'assets/img/products/club-hoodie.jpg'],
        ['Mon cheval, ma fierté', 'mon-cheval-ma-fierte', 'Son nom, sa photo ou son portrait illustré : des créations uniques, imprimées rien que pour toi et ton cheval.', 'assets/img/products/cheval-mug-photo.jpg'],
        ['Les petites attentions', 'les-petites-attentions', 'Mugs, affiches, carnets et petites douceurs : les cadeaux qui font plaisir aux cavalières.', 'assets/img/products/attentions-mug.jpg'],
    ];
    $catIds = [];
    foreach ($collections as $pos => [$name, $slug, $desc, $image]) {
        $catIds[$slug] = Database::insert('categories', [
            'name' => $name, 'slug' => $slug, 'description' => $desc, 'image' => $image, 'position' => $pos, 'active' => 1,
        ]);
    }

    $designs = seed_demo_designs();
    $sizes = ['XS', 'S', 'M', 'L', 'XL', '2XL'];
    $img = static fn (string ...$names) => array_map(static fn ($n) => 'assets/img/products/' . $n . '.jpg', $names);
    $nameCfg = static fn (array $extra) => $extra + ['mode' => 'text', 'label' => 'Nom de ton cheval', 'max_length' => 16, 'required' => true, 'font' => 'script', 'uppercase' => false, 'color' => '#5a2a3a'];
    $photoCfg = static fn (array $extra) => $extra + [
        'mode' => 'photo', 'label' => 'Nom de ton cheval', 'max_length' => 16, 'required' => false, 'font' => 'script', 'uppercase' => false, 'color' => '#5a2a3a',
        'photo_x' => 0.2, 'photo_y' => 0.08, 'photo_w' => 0.6, 'photo_h' => 0.62, 'photo_shape' => 'arch', 'x' => 0.5, 'y' => 0.84, 'size' => 0.1, 'max_width' => 0.7,
    ];

    $products = [
        // ---------------------------------------------------------------- Le Club
        ['le-club', 'Sweat à capuche « Crinière Club »', 5490, null, $img('club-hoodie'), 1, 'back', $designs['logo'], null,
            'Le sweat signature du club, logo brodé façon trait fin dans le dos.',
            '<p>Notre pièce iconique : un sweat à capuche épais et tout doux, avec le logo Crinière Club dans le dos. Celui qu\'on ne quitte plus, de la sellerie au canapé.</p><ul><li>Coton bio 80 % / polyester recyclé 20 %, molleton gratté</li><li>Coupe unisexe, légèrement oversize</li><li>Lavage à 30 °C sur l\'envers</li></ul>',
            [['Crème', '#F3EEE6'], ['Rose poudré', '#D9B8B3'], ['Bordeaux', '#5A2A3A']], $sizes],
        ['le-club', 'Sweat « Le club des passionnées »', 4990, 5490, $img('club-sweat-rose'), 1, 'front', $designs['signature'], null,
            'L\'écriture manuscrite du club sur un sweat rose poudré.',
            '<p>Un sweat col rond à porter fièrement : « Le club des passionnées », notre signature, en écriture manuscrite.</p><ul><li>Molleton doux, bords côtes</li><li>Coupe unisexe</li></ul>',
            [['Rose poudré', '#D9B8B3'], ['Crème', '#F3EEE6']], $sizes],
        ['le-club', 'T-shirt « Crinière Club »', 2990, null, $img('club-tee'), 0, 'front', $designs['cheval'], null,
            'Le cheval du club, discret, côté cœur.',
            '<p>T-shirt en coton biologique avec le cheval Crinière Club côté cœur. Simple, élégant, parfait sous une veste d\'équitation.</p>',
            [['Crème', '#F7F3EC'], ['Beige', '#E8DCCB']], ['XS', 'S', 'M', 'L', 'XL']],
        ['le-club', 'Casquette brodée Crinière Club', 2990, null, $img('club-cap'), 1, 'embroidery_front', $designs['cheval'], null,
            'Casquette délavée rose ancien, cheval brodé crème.',
            '<p>Casquette 6 pans en coton délavé, cheval Crinière Club brodé. Fermeture réglable.</p>',
            [['Rose ancien', '#B88A86'], ['Crème', '#F1EADF']], ['Taille unique']],
        ['le-club', 'Tote bag « Crinière Club »', 2290, null, $img('club-tote'), 0, 'front', $designs['logo'], null,
            'Le sac des cavalières, de l\'écurie au marché.',
            '<p>Tote bag en toile de coton épaisse, anses longues, logo Crinière Club.</p>',
            [['Naturel', '#F1EADF']], ['Unique']],
        ['le-club', 'Gourde isotherme « Better with horses »', 2790, null, $img('club-gourde'), 0, 'default', $designs['better'], null,
            'Garde l\'eau fraîche 24 h et chaude 12 h, même en concours.',
            '<p>Gourde isotherme en inox double paroi, 500 ml. Écriture manuscrite « Better with horses ».</p>',
            [], ['500 ml']],

        // ---------------------------------------------------- Mon cheval, ma fierté
        ['mon-cheval-ma-fierte', 'Sweat brodé au nom de votre cheval', 5990, null, $img('cheval-sweat-nom', 'cheval-sweat-nom-vierge'), 1, 'front', $designs['cheval'],
            $nameCfg(['preview_image' => 'assets/img/products/cheval-sweat-nom-vierge.jpg', 'x' => 0.5, 'y' => 0.6, 'size' => 0.11, 'max_width' => 0.8, 'preview_x' => 0.5, 'preview_y' => 0.535, 'preview_size' => 0.083]),
            'Son prénom en écriture manuscrite, sous le cheval du club.',
            '<p>Le sweat qui raconte ton histoire : le cheval Crinière Club et <strong>le nom de ton cheval</strong> en écriture manuscrite. Un cadeau qui fait toujours son effet.</p><ul><li>Molleton épais, coupe unisexe</li><li>Aperçu en direct avant de commander</li></ul>',
            [['Crème', '#F3EEE6'], ['Bordeaux', '#5A2A3A'], ['Beige', '#E8DCCB']], $sizes],
        ['mon-cheval-ma-fierte', 'Mug photo de votre cheval', 2490, null, $img('cheval-mug-photo', 'cheval-mug-photo-vierge'), 1, 'default', $designs['cadre'],
            $photoCfg(['preview_image' => 'assets/img/products/cheval-mug-photo-vierge.jpg', 'preview_photo_x' => 0.393, 'preview_photo_y' => 0.38, 'preview_photo_w' => 0.213, 'preview_photo_h' => 0.25, 'preview_x' => 0.5, 'preview_y' => 0.703, 'preview_size' => 0.057]),
            'Sa plus belle photo, dans une arche élégante, avec son nom.',
            '<p>Envoie ta plus belle photo : nous la cadrons dans notre arche signature, avec le nom de ton cheval en écriture manuscrite.</p><ul><li>Céramique 33 cl, lave-vaisselle et micro-ondes</li><li>Conseil : une photo nette, prise de face ou de profil</li></ul>',
            [], ['33 cl']],
        ['mon-cheval-ma-fierte', 'Portrait illustré de votre cheval', 5990, null, $img('cheval-portrait', 'cheval-portrait-vierge'), 1, 'default', null,
            ['mode' => 'portrait', 'label' => 'Nom à inscrire sous le portrait', 'max_length' => 18, 'required' => false, 'font' => 'script', 'uppercase' => false, 'extra_days' => 5,
             'preview_image' => 'assets/img/products/cheval-portrait-vierge.jpg', 'preview_x' => 0.5, 'preview_y' => 0.668, 'preview_size' => 0.073],
            'Un portrait au trait fin, dessiné à la main d\'après ta photo.',
            '<p>Notre illustratrice dessine <strong>le portrait de ton cheval</strong> au trait fin, dans le style du club, à partir de ta photo. Imprimé sur papier d\'art mat, c\'est le cadeau le plus personnel qui soit.</p><ul><li>Création à la main sous 5 jours ouvrés</li><li>Papier mat premium 200 g/m², cadre non inclus</li></ul>',
            [], ['30 × 40 cm', '50 × 70 cm'], [5990, 7990]],
        ['mon-cheval-ma-fierte', 'Coussin photo de votre cheval', 4490, null, $img('cheval-coussin-photo', 'cheval-coussin-photo-vierge'), 0, 'front', $designs['cadre'],
            $photoCfg(['preview_image' => 'assets/img/products/cheval-coussin-photo-vierge.jpg', 'preview_photo_x' => 0.37, 'preview_photo_y' => 0.327, 'preview_photo_w' => 0.26, 'preview_photo_h' => 0.327, 'preview_x' => 0.5, 'preview_y' => 0.718, 'preview_size' => 0.063]),
            'Sa photo et son nom sur un coussin tout doux.',
            '<p>Un coussin effet daim avec la photo de ton cheval dans notre arche signature. Garnissage inclus.</p>',
            [], ['45 × 45 cm']],
        ['mon-cheval-ma-fierte', 'Casquette au nom de votre cheval', 3490, null, $img('cheval-casquette-nom', 'cheval-casquette-nom-vierge'), 0, 'embroidery_front', $designs['texte'],
            $nameCfg(['preview_image' => 'assets/img/products/cheval-casquette-nom-vierge.jpg', 'max_length' => 12, 'x' => 0.5, 'y' => 0.62, 'size' => 0.32, 'max_width' => 0.9, 'preview_x' => 0.5, 'preview_y' => 0.43, 'preview_size' => 0.077]),
            'Son nom brodé en écriture manuscrite.',
            '<p>Casquette en coton, avec le nom de ton cheval brodé en écriture manuscrite. Taille réglable.</p>',
            [['Crème', '#F1EADF']], ['Taille unique']],

        // --------------------------------------------------- Les petites attentions
        ['les-petites-attentions', 'Mug « Le club des passionnées »', 1790, null, $img('attentions-mug'), 1, 'default', $designs['signature'], null,
            'Le mug du café avant la monte.',
            '<p>Mug en céramique 33 cl, écriture manuscrite « Le club des passionnées ».</p>',
            [], ['33 cl']],
        ['les-petites-attentions', 'Affiche « Anatomie du cheval »', 2490, null, $img('attentions-affiche'), 1, 'default', $designs['cheval'], null,
            'Une planche naturaliste au trait fin pour la maison.',
            '<p>Affiche sur papier mat 200 g/m², livrée roulée dans un tube. Cadre non inclus.</p>',
            [], ['30 × 40 cm', '50 × 70 cm'], [2490, 3490]],
        ['les-petites-attentions', 'Planche de stickers Crinière Club', 690, null, $img('attentions-stickers'), 0, 'default', $designs['logo'], null,
            'Pour personnaliser coffre de sellerie, gourde et ordinateur.',
            '<p>Planche de stickers vinyle résistants à l\'eau : cheval, cœur, fer à cheval, « Horse girl »…</p>',
            [], ['Planche A5']],
        ['les-petites-attentions', 'Carnet « Mes séances à cheval »', 1690, null, $img('attentions-carnet'), 0, 'default', $designs['cheval'], null,
            'Pour noter progrès, exercices et petites victoires.',
            '<p>Carnet à couverture rigide, 128 pages lignées. Le compagnon de chaque séance.</p>',
            [], ['A5']],
    ];

    foreach ($products as $p) {
        [$cat, $name, $price, $compare, $images, $featured, $placement, $design, $perso, $short, $desc, $colors, $variantSizes] = $p;
        $sizePrices = $p[13] ?? [];
        $id = Database::insert('products', [
            'category_id'       => $catIds[$cat],
            'name'              => $name,
            'slug'              => slugify($name),
            'short_description' => $short,
            'description'       => $desc,
            'price'             => $price,
            'compare_at_price'  => $compare,
            'images'            => json_encode($images),
            'featured'          => $featured,
            'active'            => 1,
            'personalizable'    => $perso ? 1 : 0,
            'personalization'   => $perso ? json_encode($perso, JSON_UNESCAPED_UNICODE) : null,
            'design_file'       => $design,
            'print_placement'   => $placement,
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        $position = 0;
        foreach ($colors ?: [[null, null]] as [$color, $hex]) {
            foreach ($variantSizes as $i => $size) {
                Database::insert('product_variants', [
                    'product_id' => $id,
                    'name'       => trim(($color ? $color . ' / ' : '') . $size),
                    'color'      => $color,
                    'color_hex'  => $hex,
                    'size'       => $size,
                    'sku'        => 'DEMO-' . $id . '-' . (++$position),
                    'price'      => $sizePrices[$i] ?? null,
                    'position'   => $position,
                    'active'     => 1,
                ]);
            }
        }
    }
    seed_ai_portrait_product($catIds['mon-cheval-ma-fierte']);
}

/**
 * Affiche encadrée « Portrait aquarelle de votre cheval » (portrait redessiné par IA, style Encre & Poudre).
 * Support Printful : « Enhanced Matte Paper Framed Poster (cm) » (produit catalogue n° 304, emplacement
 * « default ») — 30 × 40 cm en cadre noir, blanc ou chêne, 45 × 61 cm en noir ou blanc.
 * Les ID variantes catalogue sont renseignés : la boutique est prête dès que le jeton Printful est saisi.
 */
function seed_ai_portrait_product(int $categoryId): int
{
    $id = Database::insert('products', [
        'category_id'       => $categoryId,
        'name'              => 'Portrait aquarelle de votre cheval, encadré',
        'slug'              => 'portrait-aquarelle-de-votre-cheval-encadre',
        'short_description' => 'Sa photo redessinée au trait fin et à l\'aquarelle, avec son nom et son année de naissance.',
        'description'       => '<p>Envoie sa plus belle photo : en quelques secondes, notre IA la redessine dans notre style signature <strong>« Encre &amp; Poudre »</strong>, un trait fin bordeaux rehaussé d\'aquarelle aux tons poudrés. Son nom s\'inscrit dessous en écriture manuscrite, avec son année de naissance si tu le souhaites.</p><ul><li>Aperçu immédiat et gratuit, avant de commander</li><li>Papier d\'art mat épais, impression haute définition sans filigrane</li><li>Cadre noir, blanc ou chêne, prêt à accrocher</li></ul>',
        'price'             => 7900,
        'compare_at_price'  => null,
        'images'            => json_encode(['assets/img/products/portrait-ia.jpg', 'assets/img/products/portrait-ia-avant-apres.jpg', 'assets/img/products/portrait-ia-detail.jpg']),
        'featured'          => 1,
        'active'            => 1,
        'personalizable'    => 1,
        'personalization'   => json_encode([
            'mode' => 'ai_portrait', 'label' => 'Nom de ton cheval', 'max_length' => 16, 'required' => true, 'font' => 'script',
            'uppercase' => false, 'color' => '#5a2a3a', 'year_enabled' => true, 'year_label' => 'Année de naissance',
            'print_w' => 3600, 'print_h' => 4800, 'extra_days' => 0,
        ], JSON_UNESCAPED_UNICODE),
        'design_file'       => null,
        'print_placement'   => 'default',
        'created_at'        => now(),
        'updated_at'        => now(),
    ]);
    $variants = [
        // couleur, code couleur, format, prix TTC (centimes), ID variante catalogue Printful
        ['Noir', '#231f20', '30 × 40 cm', 7900, 9357],
        ['Noir', '#231f20', '45 × 61 cm', 11900, 19646],
        ['Blanc', '#f4f1ec', '30 × 40 cm', 7900, 10297],
        ['Blanc', '#f4f1ec', '45 × 61 cm', 11900, 19651],
        ['Chêne', '#dcb68f', '30 × 40 cm', 8400, 11791],
    ];
    foreach ($variants as $i => [$color, $hex, $size, $price, $printfulId]) {
        Database::insert('product_variants', [
            'product_id'          => $id,
            'name'                => $color . ' / ' . $size,
            'color'               => $color,
            'color_hex'           => $hex,
            'size'                => $size,
            'sku'                 => 'CC-PIA-' . ($i + 1),
            'price'               => $price,
            'printful_variant_id' => $printfulId,
            'position'            => $i + 1,
            'active'              => 1,
        ]);
    }
    return $id;
}

/** Copie les visuels d'impression de démonstration (PNG HD transparents) dans storage/designs. */
function seed_demo_designs(): array
{
    $files = ['logo' => 'club-logo.png', 'signature' => 'club-signature.png', 'cheval' => 'cheval-nom.png', 'cadre' => 'cadre-photo.png', 'better' => 'better-with-horses.png'];
    foreach ($files as $file) {
        if (is_file(BASE_PATH . '/database/designs/' . $file)) {
            copy(BASE_PATH . '/database/designs/' . $file, STORAGE_PATH . '/designs/' . $file);
        }
    }
    // Support transparent pour les textes seuls (broderie du nom)
    if (function_exists('imagecreatetruecolor')) {
        $img = imagecreatetruecolor(3000, 1200);
        imagesavealpha($img, true);
        imagefill($img, 0, 0, imagecolorallocatealpha($img, 0, 0, 0, 127));
        imagepng($img, STORAGE_PATH . '/designs/texte-seul.png');
        imagedestroy($img);
        $files['texte'] = 'texte-seul.png';
    }
    return $files + ['texte' => null];
}

function seed_size_guide(): array
{
    return [
        'slug' => 'guide-des-tailles', 'title' => 'Guide des tailles', 'in_footer' => 1,
        'meta_description' => 'Trouve la taille idéale pour nos sweats et t-shirts d\'équitation.',
        'content' => <<<'HTML'
<p>Nos coupes sont <strong>unisexes</strong>. Pour un tombé ajusté, prends ta taille habituelle ; pour un style <em>oversize</em> (très tendance avec un legging d'équitation), prends une taille au-dessus.</p>
<h2>Sweats & t-shirts — mesures indicatives</h2>
<table>
<thead><tr><th>Taille</th><th>Demi-tour de poitrine (à plat)</th><th>Longueur</th><th>Équivalence femme</th></tr></thead>
<tbody>
<tr><td>XS</td><td>44 cm</td><td>66 cm</td><td>34</td></tr>
<tr><td>S</td><td>48 cm</td><td>69 cm</td><td>36</td></tr>
<tr><td>M</td><td>53 cm</td><td>71 cm</td><td>38–40</td></tr>
<tr><td>L</td><td>58 cm</td><td>74 cm</td><td>42</td></tr>
<tr><td>XL</td><td>63 cm</td><td>76 cm</td><td>44–46</td></tr>
<tr><td>2XL</td><td>68 cm</td><td>79 cm</td><td>48</td></tr>
</tbody>
</table>
<p>Astuce : mesure à plat un sweat que tu aimes porter et compare. Une hésitation ? <a href="/contact">Écris-nous</a>, on te conseille avec plaisir.</p>
HTML,
    ];
}

function seed_pages(): array
{
    return [
        seed_size_guide(),
        [
            'slug' => 'a-propos', 'title' => 'Notre histoire', 'in_footer' => 1,
            'meta_description' => 'Crinière Club, le club des passionnées : une marque qui réunit les cavalières autour de leur passion.',
            'content' => <<<'HTML'
<p class="lead">{{shop_name}} est né d'une évidence : les cavalières forment une vraie communauté, et elles méritaient une marque qui leur ressemble.</p>
<h2>Le club des passionnées</h2>
<p>Un esprit lifestyle et communautaire, pour celles qui passent leurs week-ends en concours, leurs soirées à l'écurie et parlent de leur cheval comme d'un membre de la famille. Nos créations se portent à cheval comme en ville.</p>
<h2>Trois collections</h2>
<ul>
<li><strong>Le Club</strong> : vêtements et accessoires à l'effigie de la marque, à adopter tout de suite.</li>
<li><strong>Mon cheval, ma fierté</strong> : son nom, sa photo ou son portrait illustré sur des pièces uniques.</li>
<li><strong>Les petites attentions</strong> : mugs, affiches et cadeaux pour faire plaisir aux cavalières.</li>
</ul>
<h2>Imprimé pour toi, pas pour un entrepôt</h2>
<p>Chaque article est fabriqué <strong>uniquement après ta commande</strong>, dans les ateliers européens de notre partenaire d'impression. Pas de stock dormant, pas d'invendus détruits.</p>
<p>Une question, une idée de création ? <a href="/contact">Écris-nous</a>, on répond toujours.</p>
HTML,
        ],
        [
            'slug' => 'livraison-retours', 'title' => 'Livraison & retours', 'in_footer' => 1,
            'meta_description' => 'Délais de fabrication, de livraison et conditions de retour de nos articles imprimés à la demande.',
            'content' => <<<'HTML'
<h2>Fabrication</h2>
<p>Nos articles sont imprimés à la demande : comptez <strong>2 à 5 jours ouvrés</strong> de fabrication avant l'expédition.</p>
<h2>Livraison</h2>
<ul>
<li><strong>Standard suivie</strong> : 4 à 8 jours ouvrés après expédition — offerte dès le seuil indiqué dans le panier.</li>
<li><strong>Express</strong> : 2 à 4 jours ouvrés après expédition.</li>
</ul>
<p>Nous livrons en France métropolitaine, en Belgique, en Suisse, au Luxembourg, à Monaco et dans plusieurs pays de l'Union européenne. Un e-mail avec le lien de suivi t'est envoyé dès l'expédition. Une commande contenant plusieurs articles peut être expédiée en plusieurs colis.</p>
<h2>Article défectueux ou erreur</h2>
<p>Un défaut d'impression, une erreur de taille de notre part, un colis abîmé ? Contacte-nous dans les <strong>30 jours</strong> suivant la réception avec une photo : nous réimprimons ou remboursons l'article, sans frais.</p>
<h2>Droit de rétractation</h2>
<p>Pour les articles <strong>non personnalisés</strong>, tu disposes de 14 jours à compter de la réception pour exercer ton droit de rétractation (frais de retour à ta charge). Contacte-nous avant tout renvoi pour obtenir l'adresse de retour.</p>
<p>Conformément à l'article L221-28 3° du Code de la consommation, le droit de rétractation <strong>ne s'applique pas aux articles personnalisés</strong> (nom de cheval, texte…), confectionnés selon tes indications.</p>
HTML,
        ],
        [
            'slug' => 'faq', 'title' => 'Questions fréquentes', 'in_footer' => 1,
            'meta_description' => 'Tailles, personnalisation, délais, entretien : toutes les réponses à tes questions.',
            'content' => <<<'HTML'
<h2>Comment fonctionne la personnalisation ?</h2>
<p>Dans la collection « Mon cheval, ma fierté », saisis le nom de ton cheval et/ou ajoute sa photo : un aperçu s'affiche instantanément sur l'article. Le texte est imprimé exactement comme saisi (pense à vérifier l'orthographe !).</p>
<h2>Quelle photo choisir ?</h2>
<p>Une photo nette et bien éclairée, prise de face ou de trois quarts, où la tête de ton cheval est bien visible. Les photos de téléphone conviennent parfaitement (format JPG ou PNG, 20 Mo maximum).</p>
<h2>Comment fonctionne le portrait aquarelle par IA ?</h2>
<p>Sur la fiche du portrait encadré, ajoute une photo de ton cheval et clique sur « Créer mon portrait » : notre IA le redessine en quelques secondes dans notre style « Encre &amp; Poudre » (trait fin bordeaux et aquarelle poudrée). Tu vois l'aperçu dans son cadre, avec son nom et son année de naissance, avant de commander. Tu peux créer plusieurs versions et garder ta préférée.</p>
<h2>Comment se passe un portrait illustré ?</h2>
<p>Notre illustratrice dessine le portrait de ton cheval à la main, au trait fin, d'après ta photo. Comptez 5 jours ouvrés de création en plus de la fabrication et de la livraison.</p>
<h2>Comment choisir ma taille ?</h2>
<p>Nos coupes sont unisexes et plutôt standard. En cas de doute entre deux tailles, prends la plus grande pour un style confortable.</p>
<h2>Quand vais-je recevoir ma commande ?</h2>
<p>Comptez 2 à 5 jours de fabrication puis le délai de livraison choisi. Tu peux suivre ta commande à tout moment depuis la page <a href="/suivi">Suivi de commande</a>.</p>
<h2>Quels moyens de paiement acceptez-vous ?</h2>
<p>Carte bancaire (CB, Visa, Mastercard, American Express), Apple Pay, Google Pay et d'autres moyens selon ton pays. Le paiement est entièrement sécurisé par Stripe : nous n'avons jamais accès à tes données bancaires.</p>
<h2>Comment entretenir mes vêtements ?</h2>
<p>Lavage à 30 °C sur l'envers, pas de sèche-linge, repassage sur l'envers. Ton impression restera éclatante longtemps.</p>
<h2>Proposez-vous des tarifs pour les clubs et écuries ?</h2>
<p>Oui ! Pour équiper ton club, ton équipe de concours ou tes cavaliers, <a href="/contact">contacte-nous</a> pour un devis.</p>
HTML,
        ],
        [
            'slug' => 'cgv', 'title' => 'Conditions générales de vente', 'in_footer' => 1,
            'meta_description' => 'Conditions générales de vente de la boutique.',
            'content' => <<<'HTML'
<p><em>Modèle à faire valider par un professionnel du droit et à compléter avec vos informations. Dernière mise à jour : {{date}}.</em></p>
<h2>Article 1 — Objet</h2>
<p>Les présentes conditions régissent les ventes conclues sur le site {{site_url}} entre {{company_name}}, {{company_address}}, SIRET {{company_siret}} (ci-après « le Vendeur ») et toute personne physique agissant en qualité de consommateur (ci-après « le Client »).</p>
<h2>Article 2 — Produits</h2>
<p>Les produits sont fabriqués à la demande après la commande. Les photographies et aperçus sont aussi fidèles que possible ; de légères variations de teinte peuvent exister entre l'écran et l'article imprimé.</p>
<h2>Article 3 — Prix</h2>
<p>Les prix sont indiqués en euros toutes taxes comprises (TTC), hors frais de livraison indiqués avant la validation de la commande.</p>
<h2>Article 4 — Commande</h2>
<p>La commande est ferme après acceptation des présentes CGV et paiement complet. Un e-mail de confirmation récapitulant la commande est adressé au Client.</p>
<h2>Article 5 — Paiement</h2>
<p>Le paiement s'effectue en ligne par l'intermédiaire de la société Stripe. Les données bancaires sont chiffrées et ne transitent jamais par les serveurs du Vendeur.</p>
<h2>Article 6 — Fabrication et livraison</h2>
<p>Les délais de fabrication et de livraison sont indiqués sur la page « Livraison & retours ». Le transfert des risques intervient à la remise du colis au Client. En cas de retard supérieur à 30 jours, le Client peut annuler sa commande dans les conditions de l'article L216-6 du Code de la consommation.</p>
<h2>Article 7 — Droit de rétractation</h2>
<p>Le Client dispose d'un délai de 14 jours à compter de la réception pour se rétracter, sans motif, pour les produits non personnalisés. Les frais de retour sont à sa charge. Le remboursement intervient dans les 14 jours suivant la réception du retour.</p>
<p>Conformément à l'article L221-28 3° du Code de la consommation, le droit de rétractation ne peut être exercé pour les biens confectionnés selon les spécifications du Client ou nettement personnalisés.</p>
<h2>Article 8 — Garanties légales</h2>
<p>Le Client bénéficie de la garantie légale de conformité (articles L217-3 et suivants du Code de la consommation) et de la garantie des vices cachés (articles 1641 et suivants du Code civil).</p>
<h2>Article 9 — Données personnelles</h2>
<p>Voir la <a href="/page/confidentialite">politique de confidentialité</a>.</p>
<h2>Article 10 — Médiation et litiges</h2>
<p>En cas de litige, le Client peut recourir gratuitement au médiateur de la consommation : {{mediator}}. Le Client peut également utiliser la plateforme européenne de règlement en ligne des litiges : https://ec.europa.eu/consumers/odr. Les présentes CGV sont soumises au droit français.</p>
HTML,
        ],
        [
            'slug' => 'mentions-legales', 'title' => 'Mentions légales', 'in_footer' => 1,
            'meta_description' => 'Mentions légales du site.',
            'content' => <<<'HTML'
<h2>Éditeur du site</h2>
<p>{{company_name}}<br>{{company_address}}<br>SIRET : {{company_siret}} — TVA : {{company_vat}}<br>Contact : {{contact_email}}<br>Directeur de la publication : {{publisher_name}}</p>
<h2>Hébergement</h2>
<p>Hostinger International Ltd., 61 Lordou Vironos Street, 6023 Larnaca, Chypre — https://www.hostinger.fr</p>
<h2>Prestataires</h2>
<p>Paiement : Stripe Payments Europe Ltd. (Irlande). Fabrication et expédition : Printful (ateliers en Europe et dans le monde).</p>
<h2>Propriété intellectuelle</h2>
<p>L'ensemble des créations, textes et visuels présents sur ce site est la propriété de {{company_name}}. Toute reproduction sans autorisation est interdite.</p>
HTML,
        ],
        [
            'slug' => 'confidentialite', 'title' => 'Politique de confidentialité & cookies', 'in_footer' => 1,
            'meta_description' => 'Comment nous protégeons vos données personnelles.',
            'content' => <<<'HTML'
<h2>Responsable du traitement</h2>
<p>{{company_name}}, {{company_address}} — {{contact_email}}.</p>
<h2>Données collectées et finalités</h2>
<ul>
<li><strong>Commandes</strong> : nom, adresse, e-mail, téléphone, contenu de la commande — exécution du contrat (fabrication, livraison, service client). Conservation : 10 ans (obligations comptables).</li>
<li><strong>Newsletter</strong> : e-mail — avec votre consentement, retirable à tout moment via le lien de désinscription.</li>
<li><strong>Formulaire de contact</strong> : nom, e-mail, message — réponse à votre demande. Conservation : 3 ans.</li>
<li><strong>Photos de votre cheval et portraits IA</strong> : la photo que vous envoyez sert uniquement à fabriquer votre article. Pour le portrait aquarelle, elle est transmise, avec votre accord, à notre prestataire de dessin par IA (OpenAI ou Replicate, selon le service activé), uniquement pour créer le dessin. Photos et portraits non commandés sont supprimés automatiquement après 30 jours ; ceux des articles commandés sont conservés avec la commande.</li>
</ul>
<h2>Destinataires</h2>
<p>Stripe (paiement), Printful (fabrication et expédition — reçoit vos nom, adresse et téléphone), Hostinger (hébergement), Brevo (newsletter, le cas échéant), OpenAI ou Replicate (dessin du portrait par IA, uniquement si vous créez un portrait). Certains prestataires peuvent transférer des données hors UE dans le cadre de clauses contractuelles types.</p>
<h2>Cookies</h2>
<p>Nous utilisons un cookie de session indispensable au panier. Les cookies de mesure d'audience ou publicitaires ne sont déposés qu'avec votre accord, via le bandeau affiché lors de votre première visite. Vous pouvez modifier votre choix à tout moment avec le lien « Gérer les cookies » en bas de page.</p>
<h2>Vos droits</h2>
<p>Vous disposez d'un droit d'accès, de rectification, d'effacement, d'opposition, de limitation et de portabilité. Écrivez-nous à {{contact_email}}. Vous pouvez également introduire une réclamation auprès de la CNIL (www.cnil.fr).</p>
HTML,
        ],
    ];
}
