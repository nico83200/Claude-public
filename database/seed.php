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
}

function seed_demo_catalog(): void
{
    $categories = [
        ['Sweats & hoodies', 'sweats-hoodies', 'Des sweats doux et chauds pour les matinées à l\'écurie comme pour les soirées après concours.'],
        ['T-shirts', 't-shirts', 'Des t-shirts en coton bio, imprimés à la demande, pour afficher votre discipline préférée.'],
        ['Accessoires', 'accessoires', 'Casquettes, tote bags et indispensables du cavalier, à personnaliser au nom de votre cheval ou de votre écurie.'],
        ['Déco & maison', 'deco-maison', 'Affiches, mugs et coussins pour faire entrer le monde du cheval à la maison.'],
    ];
    $catIds = [];
    foreach ($categories as $pos => [$name, $slug, $desc]) {
        $catIds[$slug] = Database::insert('categories', [
            'name' => $name, 'slug' => $slug, 'description' => $desc, 'position' => $pos, 'active' => 1,
        ]);
    }

    $design = seed_demo_design();
    $apparelSizes = ['S', 'M', 'L', 'XL', '2XL'];

    $products = [
        [
            'cat' => 'sweats-hoodies', 'name' => 'Sweat à capuche « Fer à cheval » personnalisé', 'price' => 5490, 'compare' => null,
            'image' => 'hoodie-navy.svg', 'featured' => 1, 'personalizable' => 1, 'placement' => 'front',
            'short' => 'Le sweat signature, brodé au nom de votre cheval.',
            'desc' => '<p>Notre best-seller : un sweat à capuche épais (coton bio 80 % / polyester recyclé 20 %), avec le fer à cheval porte-bonheur et <strong>le nom de votre cheval</strong> imprimé sous le motif.</p><ul><li>Coupe unisexe, capuche doublée</li><li>Poche kangourou</li><li>Lavage à 30 °C sur l\'envers</li></ul><p>Fabriqué à la demande dans un atelier européen de notre partenaire d\'impression : pas de stock, pas de gaspillage.</p>',
            'colors' => [['Bleu marine', '#1f2a44'], ['Sable', '#d9c7a7']], 'sizes' => $apparelSizes,
            'pers' => ['label' => 'Nom de votre cheval', 'max_length' => 16, 'preview_y' => 0.6, 'preview_size' => 0.05, 'preview_color' => '#f5efe3'],
        ],
        [
            'cat' => 'sweats-hoodies', 'name' => 'Sweat col rond « Horse Girl »', 'price' => 4490, 'compare' => 4990,
            'image' => 'sweat-sand.svg', 'featured' => 1, 'personalizable' => 0, 'placement' => 'front',
            'short' => 'Le sweat cosy pour toutes celles qui vivent au rythme des chevaux.',
            'desc' => '<p>Sweat col rond molletonné, imprimé « Horse Girl » façon college. Parfait pour les journées de concours ou les dimanches au pré.</p><ul><li>Molleton gratté intérieur</li><li>Bords côtes aux poignets</li></ul>',
            'colors' => [['Sable', '#d9c7a7'], ['Vert sapin', '#2f4a3a']], 'sizes' => $apparelSizes,
        ],
        [
            'cat' => 't-shirts', 'name' => 'T-shirt « Dressage Club »', 'price' => 2790, 'compare' => null,
            'image' => 'tshirt-cream.svg', 'featured' => 1, 'personalizable' => 0, 'placement' => 'front',
            'short' => 'Un t-shirt en coton bio au style vintage pour les amoureux du dressage.',
            'desc' => '<p>T-shirt 100 % coton biologique, coupe droite, impression DTG aux encres à base d\'eau.</p>',
            'colors' => [['Crème', '#f3ead8'], ['Noir', '#1d1d1b']], 'sizes' => ['XS', 'S', 'M', 'L', 'XL'],
        ],
        [
            'cat' => 't-shirts', 'name' => 'T-shirt enfant « Mon poney et moi »', 'price' => 2290, 'compare' => null,
            'image' => 'tshirt-kid.svg', 'featured' => 0, 'personalizable' => 1, 'placement' => 'front',
            'short' => 'Le t-shirt des petits cavaliers, avec le prénom de leur poney.',
            'desc' => '<p>T-shirt enfant en coton doux, avec le nom du poney imprimé sous l\'illustration. Idéal pour un cadeau d\'anniversaire ou de passage de Galop.</p>',
            'colors' => [['Rose poudré', '#e8c4c0'], ['Bleu ciel', '#b9d3e6']], 'sizes' => ['4 ans', '6 ans', '8 ans', '10 ans', '12 ans'],
            'pers' => ['label' => 'Prénom du poney', 'max_length' => 14, 'preview_y' => 0.58, 'preview_size' => 0.05, 'preview_color' => '#1f2a44'],
        ],
        [
            'cat' => 'accessoires', 'name' => 'Casquette brodée « Écurie »', 'price' => 2990, 'compare' => null,
            'image' => 'cap.svg', 'featured' => 1, 'personalizable' => 1, 'placement' => 'embroidery_front',
            'short' => 'Une casquette brodée au nom de votre écurie ou de votre cheval.',
            'desc' => '<p>Casquette 6 pans en sergé de coton, fermeture réglable en laiton. Broderie fine sur le devant.</p>',
            'colors' => [['Bleu marine', '#1f2a44'], ['Kaki', '#6b6b4a']], 'sizes' => ['Taille unique'],
            'pers' => ['label' => 'Texte brodé (écurie, cheval…)', 'max_length' => 14, 'preview_y' => 0.5, 'preview_size' => 0.045, 'preview_color' => '#f5efe3'],
        ],
        [
            'cat' => 'accessoires', 'name' => 'Tote bag « Équitation »', 'price' => 2190, 'compare' => null,
            'image' => 'tote.svg', 'featured' => 0, 'personalizable' => 0, 'placement' => 'front',
            'short' => 'Le sac idéal pour transporter brosses, carottes et bombe.',
            'desc' => '<p>Tote bag en toile de coton épaisse, anses longues, impression durable.</p>',
            'colors' => [['Naturel', '#efe6d2']], 'sizes' => ['Unique'],
        ],
        [
            'cat' => 'deco-maison', 'name' => 'Mug personnalisé « Propriété de… »', 'price' => 1890, 'compare' => null,
            'image' => 'mug.svg', 'featured' => 1, 'personalizable' => 1, 'placement' => 'default',
            'short' => 'Le mug du café avant la monte, au nom de votre cheval.',
            'desc' => '<p>Mug en céramique 33 cl, compatible lave-vaisselle et micro-ondes, impression par sublimation.</p>',
            'colors' => [['Blanc', '#ffffff']], 'sizes' => ['33 cl'],
            'pers' => ['label' => 'Nom de votre cheval', 'max_length' => 16, 'preview_y' => 0.62, 'preview_size' => 0.05, 'preview_color' => '#1f2a44'],
        ],
        [
            'cat' => 'deco-maison', 'name' => 'Affiche « Anatomie du cheval »', 'price' => 2490, 'compare' => null,
            'image' => 'poster.svg', 'featured' => 1, 'personalizable' => 0, 'placement' => 'default',
            'short' => 'Une planche d\'inspiration naturaliste sur papier mat premium.',
            'desc' => '<p>Affiche imprimée sur papier mat 200 g/m², livrée roulée dans un tube protecteur. Cadre non inclus.</p>',
            'colors' => [], 'sizes' => ['30 × 40 cm', '50 × 70 cm'], 'size_prices' => [2490, 3490],
        ],
        [
            'cat' => 'deco-maison', 'name' => 'Coussin « Galop »', 'price' => 3490, 'compare' => null,
            'image' => 'cushion.svg', 'featured' => 0, 'personalizable' => 0, 'placement' => 'front',
            'short' => 'Un coussin tout doux pour un salon aux couleurs de l\'écurie.',
            'desc' => '<p>Housse en polyester effet daim et garnissage inclus, impression recto verso.</p>',
            'colors' => [], 'sizes' => ['45 × 45 cm'],
        ],
    ];

    foreach ($products as $p) {
        $id = Database::insert('products', [
            'category_id'       => $catIds[$p['cat']],
            'name'              => $p['name'],
            'slug'              => slugify($p['name']),
            'short_description' => $p['short'],
            'description'       => $p['desc'],
            'price'             => $p['price'],
            'compare_at_price'  => $p['compare'],
            'images'            => json_encode(['assets/img/products/' . $p['image']]),
            'featured'          => $p['featured'],
            'active'            => 1,
            'personalizable'    => $p['personalizable'],
            'personalization'   => isset($p['pers']) ? json_encode($p['pers'], JSON_UNESCAPED_UNICODE) : null,
            'design_file'       => $design,
            'print_placement'   => $p['placement'],
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        $colors = $p['colors'] ?: [[null, null]];
        $position = 0;
        foreach ($colors as [$color, $hex]) {
            foreach ($p['sizes'] as $i => $size) {
                Database::insert('product_variants', [
                    'product_id' => $id,
                    'name'       => trim(($color ? $color . ' / ' : '') . $size),
                    'color'      => $color,
                    'color_hex'  => $hex,
                    'size'       => $size,
                    'sku'        => 'DEMO-' . $id . '-' . (++$position),
                    'price'      => $p['size_prices'][$i] ?? null,
                    'position'   => $position,
                    'active'     => 1,
                ]);
            }
        }
    }
}

/** Visuel d'impression de démonstration (fer à cheval) généré avec GD — remplacez-le par vos créations. */
function seed_demo_design(): ?string
{
    if (!function_exists('imagecreatetruecolor')) {
        return null;
    }
    $w = 3600;
    $h = 3600;
    $img = imagecreatetruecolor($w, $h);
    imagesavealpha($img, true);
    imagefill($img, 0, 0, imagecolorallocatealpha($img, 0, 0, 0, 127));
    $gold = imagecolorallocate($img, 184, 134, 74);
    $clear = imagecolorallocatealpha($img, 0, 0, 0, 127);
    [$cx, $cy, $outer, $inner, $legEnd] = [1800, 1350, 950, 640, 2150];
    // Fer à cheval : anneau supérieur + deux branches arrondies, trous de clous.
    imagefilledellipse($img, $cx, $cy, $outer * 2, $outer * 2, $gold);
    imagefilledrectangle($img, $cx - $outer, $cy, $cx - $inner, $legEnd, $gold);
    imagefilledrectangle($img, $cx + $inner, $cy, $cx + $outer, $legEnd, $gold);
    $legWidth = $outer - $inner;
    imagefilledellipse($img, $cx - $inner - (int) ($legWidth / 2), $legEnd, $legWidth, $legWidth, $gold);
    imagefilledellipse($img, $cx + $inner + (int) ($legWidth / 2), $legEnd, $legWidth, $legWidth, $gold);
    imagealphablending($img, false);
    imagefilledellipse($img, $cx, $cy, $inner * 2, $inner * 2, $clear);
    imagefilledrectangle($img, $cx - $inner, $cy, $cx + $inner, $h - 1, $clear);
    $mid = (int) (($outer + $inner) / 2);
    foreach ([195, 225, 255, 285, 315, 345] as $angle) {
        $rad = deg2rad($angle);
        imagefilledellipse($img, (int) ($cx + cos($rad) * $mid), (int) ($cy + sin($rad) * $mid), 80, 80, $clear);
    }
    imagealphablending($img, true);
    $font = STORAGE_PATH . '/fonts/LiberationSerif-Bold.ttf';
    if (function_exists('imagettftext') && is_file($font)) {
        $navy = imagecolorallocate($img, 31, 42, 68);
        $box = imagettfbbox(150, 0, $font, 'EST. ÉCURIE');
        imagettftext($img, 150, 0, (int) ($w / 2 - ($box[2] - $box[0]) / 2), 2650, $navy, $font, 'EST. ÉCURIE');
    }
    $name = 'demo-fer-a-cheval.png';
    imagepng($img, STORAGE_PATH . '/designs/' . $name, 6);
    imagedestroy($img);
    return $name;
}

function seed_pages(): array
{
    return [
        [
            'slug' => 'a-propos', 'title' => 'Notre histoire', 'in_footer' => 1,
            'meta_description' => 'Une boutique née aux écuries : vêtements et accessoires équestres imprimés à la demande.',
            'content' => <<<'HTML'
<p class="lead">{{shop_name}} est né d'une évidence : les cavalières et cavaliers méritent mieux que des produits génériques.</p>
<h2>Imprimé pour vous, pas pour un entrepôt</h2>
<p>Chaque article est fabriqué <strong>uniquement après votre commande</strong>, dans les ateliers européens de notre partenaire d'impression. Pas de stock dormant, pas d'invendus détruits : seulement ce qui est réellement porté.</p>
<h2>Votre cheval au cœur du design</h2>
<p>Parce que chaque cheval est unique, la plupart de nos créations se personnalisent avec son nom, celui de votre poney ou de votre écurie.</p>
<h2>Une petite équipe passionnée</h2>
<p>Nous dessinons nos motifs entre deux séances de travail à pied et deux concours. Une question ? <a href="/contact">Écrivez-nous</a>, on répond toujours.</p>
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
<p>Nous livrons en France métropolitaine, en Belgique, en Suisse, au Luxembourg, à Monaco et dans plusieurs pays de l'Union européenne. Un e-mail avec le lien de suivi vous est envoyé dès l'expédition. Une commande contenant plusieurs articles peut être expédiée en plusieurs colis.</p>
<h2>Article défectueux ou erreur</h2>
<p>Un défaut d'impression, une erreur de taille de notre part, un colis abîmé ? Contactez-nous dans les <strong>30 jours</strong> suivant la réception avec une photo : nous réimprimons ou remboursons l'article, sans frais.</p>
<h2>Droit de rétractation</h2>
<p>Pour les articles <strong>non personnalisés</strong>, vous disposez de 14 jours à compter de la réception pour exercer votre droit de rétractation (frais de retour à votre charge). Contactez-nous avant tout renvoi pour obtenir l'adresse de retour.</p>
<p>Conformément à l'article L221-28 3° du Code de la consommation, le droit de rétractation <strong>ne s'applique pas aux articles personnalisés</strong> (nom de cheval, texte…), confectionnés selon vos spécifications.</p>
HTML,
        ],
        [
            'slug' => 'faq', 'title' => 'Questions fréquentes', 'in_footer' => 1,
            'meta_description' => 'Tailles, personnalisation, délais, entretien : toutes les réponses à vos questions.',
            'content' => <<<'HTML'
<h2>Comment fonctionne la personnalisation ?</h2>
<p>Sur les articles marqués « Personnalisable », saisissez le nom de votre cheval dans le champ prévu : un aperçu s'affiche instantanément. Le texte est intégré au fichier d'impression et imprimé exactement comme saisi (pensez à vérifier l'orthographe !).</p>
<h2>Comment choisir ma taille ?</h2>
<p>Nos coupes sont unisexes et plutôt standard. En cas de doute entre deux tailles, prenez la plus grande pour un style confortable.</p>
<h2>Quand vais-je recevoir ma commande ?</h2>
<p>Comptez 2 à 5 jours de fabrication puis le délai de livraison choisi. Vous pouvez suivre votre commande à tout moment depuis la page <a href="/suivi">Suivi de commande</a>.</p>
<h2>Quels moyens de paiement acceptez-vous ?</h2>
<p>Carte bancaire (CB, Visa, Mastercard, American Express), Apple Pay, Google Pay et d'autres moyens selon votre pays. Le paiement est entièrement sécurisé par Stripe : nous n'avons jamais accès à vos données bancaires.</p>
<h2>Comment entretenir mes vêtements ?</h2>
<p>Lavage à 30 °C sur l'envers, pas de sèche-linge, repassage sur l'envers. Votre impression restera éclatante longtemps.</p>
<h2>Proposez-vous des tarifs pour les clubs et écuries ?</h2>
<p>Oui ! Pour équiper votre club, votre équipe de concours ou vos cavaliers, <a href="/contact">contactez-nous</a> pour un devis.</p>
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
</ul>
<h2>Destinataires</h2>
<p>Stripe (paiement), Printful (fabrication et expédition — reçoit vos nom, adresse et téléphone), Hostinger (hébergement), Brevo (newsletter, le cas échéant). Certains prestataires peuvent transférer des données hors UE dans le cadre de clauses contractuelles types.</p>
<h2>Cookies</h2>
<p>Nous utilisons un cookie de session indispensable au panier. Les cookies de mesure d'audience ou publicitaires ne sont déposés qu'avec votre accord, via le bandeau affiché lors de votre première visite. Vous pouvez modifier votre choix à tout moment avec le lien « Gérer les cookies » en bas de page.</p>
<h2>Vos droits</h2>
<p>Vous disposez d'un droit d'accès, de rectification, d'effacement, d'opposition, de limitation et de portabilité. Écrivez-nous à {{contact_email}}. Vous pouvez également introduire une réclamation auprès de la CNIL (www.cnil.fr).</p>
HTML,
        ],
    ];
}
