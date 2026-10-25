<?php
use App\Core\Database;
use App\Services\Cart;
use App\Services\Catalog;

$shopName = (string) setting('shop_name');
$title = isset($pageTitle) && $pageTitle !== $shopName && !str_starts_with((string) $pageTitle, $shopName) ? $pageTitle . ' | ' . $shopName : ($pageTitle ?? $shopName);
$description = $metaDescription ?? setting('home_meta_description');
$navCategories = Catalog::categories();
$footerPages = Database::all('SELECT slug, title FROM pages WHERE in_footer = 1 ORDER BY id');
$cartCount = Cart::count();
$success = flash('success');
$error = flash('error');
$socials = array_filter(['Instagram' => setting('social_instagram'), 'Facebook' => setting('social_facebook'), 'TikTok' => setting('social_tiktok'), 'Pinterest' => setting('social_pinterest')]);
$announcements = array_values(array_filter(array_map('trim', explode('|', (string) setting('announcement')))));
$welcomeOffer = App\Services\WelcomeOffer::active() ? App\Services\WelcomeOffer::label() : '';
$customer = App\Services\Account::current();
// Popup newsletter : jamais pendant l'achat ni dans l'espace cliente
$showPopup = setting('popup_enabled', '1') === '1' && empty($noindex)
    && !preg_match('#^/(panier|commande|compte|suivi)#', request_path());
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($title) ?></title>
<meta name="description" content="<?= e(excerpt($description, 160)) ?>">
<?php if (!empty($noindex)): ?><meta name="robots" content="noindex, nofollow"><?php endif; ?>
<?php if (!empty($canonical)): ?><link rel="canonical" href="<?= e($canonical) ?>"><?php endif; ?>
<meta property="og:site_name" content="<?= e($shopName) ?>">
<meta property="og:title" content="<?= e($pageTitle ?? $shopName) ?>">
<meta property="og:description" content="<?= e(excerpt($description, 200)) ?>">
<meta property="og:type" content="<?= isset($product) ? 'product' : 'website' ?>">
<?php if (!empty($ogImage)): ?><meta property="og:image" content="<?= e($ogImage) ?>"><?php endif; ?>
<meta name="theme-color" content="#f9f7f3">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<link rel="icon" href="<?= e(url('assets/img/favicon.svg')) ?>" type="image/svg+xml">
<link rel="preload" href="<?= e(url('assets/fonts/playfair-display-latin-wght-normal.woff2')) ?>" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="<?= e(url('assets/fonts/montserrat-latin-wght-normal.woff2')) ?>" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= e(asset('assets/css/app.css')) ?>">
<?php if (!empty($jsonLd)): ?><script type="application/ld+json"><?= json_encode($jsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></script><?php endif; ?>
</head>
<body data-wishlist-url="<?= e(url('favoris')) ?>" data-wishlist-sync="<?= e(url('favoris/synchroniser')) ?>"<?= $customer ? ' data-wishlist-server="' . e(json_encode(App\Services\Wishlist::ids((int) $customer['id']))) . '"' : '' ?> data-ga4="<?= e(config('analytics.ga4_id')) ?>" data-pixel="<?= e(config('analytics.meta_pixel_id')) ?>" data-cart-url="<?= e(url('panier/apercu')) ?>">
<a class="skip-link" href="#contenu">Aller au contenu</a>

<?php if ($announcements): ?>
<div class="announcement" data-announcements='<?= e(json_encode($announcements, JSON_UNESCAPED_UNICODE)) ?>'>
  <span data-announce><?= e($announcements[0]) ?></span>
</div>
<?php endif; ?>

<header class="site-header">
  <div class="container header-inner">
    <button class="icon-btn nav-toggle" aria-expanded="false" aria-controls="main-nav" aria-label="Menu"><?= icon('menu', 22) ?></button>
    <a class="brand" href="<?= e(url('/')) ?>" aria-label="<?= e($shopName) ?> — accueil">
      <img class="logo-img" src="<?= e(url('assets/img/brand/logo.svg')) ?>" alt="<?= e($shopName) ?>" width="148" height="50">
    </a>
    <nav id="main-nav" class="main-nav" aria-label="Navigation principale">
      <div class="nav-group" data-nav-group>
        <button type="button" class="nav-parent <?= is_active('/boutique') || is_active('/categorie/') ? 'active' : '' ?>" aria-expanded="false" aria-controls="nav-shop" data-nav-parent>Boutique <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg></button>
        <div class="nav-sub" id="nav-shop">
          <a href="<?= e(url('boutique')) ?>"><strong>Toute la collection</strong></a>
          <?php foreach ($navCategories as $c): ?>
            <a href="<?= e(url('categorie/' . $c['slug'])) ?>" class="<?= is_active('/categorie/' . $c['slug']) ? 'active' : '' ?>"><?= e($c['name']) ?></a>
          <?php endforeach; ?>
          <span class="nav-sub-sep" aria-hidden="true"></span>
          <a href="<?= e(url('boutique', ['personnalisable' => 1])) ?>">Articles personnalisables</a>
          <a href="<?= e(url('boutique', ['prix_max' => (int) setting('gift_max_price', 3000) / 100])) ?>">Idées cadeaux</a>
        </div>
      </div>
      <?php if (App\Services\AiPortrait::landingAvailable()): ?>
        <a href="<?= e(url('portrait-aquarelle')) ?>" class="nav-highlight <?= is_active('/portrait-aquarelle') ? 'active' : '' ?>"><?= icon('sparkle', 15) ?>Portrait aquarelle</a>
      <?php endif; ?>
      <a href="<?= e(url('page/a-propos')) ?>" class="<?= is_active('/page/a-propos') ? 'active' : '' ?>">Notre histoire</a>
      <?php if (App\Services\Journal::available()): ?><a href="<?= e(url('journal')) ?>" class="<?= is_active('/journal') ? 'active' : '' ?>">Journal</a><?php endif; ?>
      <a href="<?= e(url('contact')) ?>" class="<?= is_active('/contact') ? 'active' : '' ?>">Contact</a>
      <a href="<?= e(url('favoris')) ?>" class="nav-account nav-wish" data-wish-link><?= icon('heart', 17) ?>Mes favoris</a>
      <a href="<?= e(url('compte')) ?>" class="nav-account <?= is_active('/compte') ? 'active' : '' ?>"><?= icon('user', 17) ?><?= $customer ? 'Mon compte · ' . e(App\Services\Account::firstName($customer)) : 'Mon compte / Connexion' ?></a>
    </nav>
    <div class="header-actions">
      <button class="icon-btn" type="button" data-search-toggle aria-expanded="false" aria-controls="search-bar" aria-label="Rechercher"><?= icon('search', 21) ?></button>
      <a class="icon-btn wish-link" href="<?= e(url('favoris')) ?>" data-wish-link aria-label="Mes favoris"><?= icon('heart', 21) ?><span class="cart-count" data-wish-count hidden>0</span></a>
      <a class="account-btn <?= $customer ? 'is-logged' : '' ?> <?= is_active('/compte') ? 'active' : '' ?>" href="<?= e(url('compte')) ?>" aria-label="<?= $customer ? 'Mon compte (connectée en tant que ' . e(App\Services\Account::firstName($customer)) . ')' : 'Mon compte — se connecter ou créer un compte' ?>">
        <span class="account-avatar" aria-hidden="true"><?= $customer ? e(mb_strtoupper(mb_substr(App\Services\Account::firstName($customer), 0, 1))) : icon('user', 21) ?></span>
        <span class="account-label"><small><?= $customer ? 'Bonjour ' . e(App\Services\Account::firstName($customer)) : 'Bonjour !' ?></small>Mon compte</span>
      </a>
      <a class="icon-btn" href="<?= e(url('panier')) ?>" data-cart-open aria-label="Panier (<?= $cartCount ?> article<?= $cartCount > 1 ? 's' : '' ?>)">
        <?= icon('bag', 22) ?>
        <span class="cart-count" data-cart-count <?= $cartCount ? '' : 'hidden' ?>><?= $cartCount ?></span>
      </a>
    </div>
  </div>
  <div class="search-bar" id="search-bar" hidden>
    <div class="container">
      <form action="<?= e(url('boutique')) ?>" method="get" role="search" class="search-form" data-search-form data-suggest-url="<?= e(url('recherche/suggestions')) ?>">
        <label class="sr-only" for="q">Rechercher un article</label>
        <input id="q" type="search" name="q" placeholder="Sweat, mug, portrait, cadeau…" value="<?= e($_GET['q'] ?? '') ?>" autocomplete="off" role="combobox" aria-expanded="false" aria-controls="search-results" aria-autocomplete="list">
        <button class="btn btn-primary" type="submit">Rechercher</button>
        <div class="search-results" id="search-results" role="listbox" hidden></div>
      </form>
      <p class="search-popular" data-search-popular>Populaire :
        <?php if (App\Services\AiPortrait::landingAvailable()): ?><a href="<?= e(url('portrait-aquarelle')) ?>">Portrait aquarelle</a><?php endif; ?>
        <a href="<?= e(url('boutique', ['personnalisable' => 1])) ?>">À personnaliser</a>
        <a href="<?= e(url('boutique', ['prix_max' => (int) setting('gift_max_price', 3000) / 100])) ?>">Idées cadeaux</a>
        <a href="<?= e(url('boutique', ['q' => 'sweat'])) ?>">Sweats</a>
      </p>
    </div>
  </div>
</header>

<?php if ($success || $error): ?>
<div class="container flash-wrap">
  <?php if ($success): ?><div class="flash flash-success" role="status"><?= e($success) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="flash flash-error" role="alert"><?= e($error) ?></div><?php endif; ?>
</div>
<?php endif; ?>

<main id="contenu">
<?= $content ?>
</main>

<section class="reassurance" aria-label="Nos engagements">
  <div class="container reassurance-grid">
    <div><span class="ic"><?= icon('sparkle', 22) ?></span><span><strong>Imprimé pour vous</strong>Fabriqué à la commande, zéro gaspillage</span></div>
    <div><span class="ic"><?= icon('heart', 22) ?></span><span><strong>Personnalisation offerte</strong>Son nom, sa photo, son portrait</span></div>
    <div><span class="ic"><?= icon('truck', 22) ?></span><span><strong>Livraison offerte</strong>dès <?= e(money(setting('shipping_free_threshold'))) ?> d'achat</span></div>
    <div><span class="ic"><?= icon('refresh', 22) ?></span><span><strong>Satisfaite ou réimprimé</strong>Un défaut ? On s'en occupe</span></div>
  </div>
</section>

<?php if (App\Core\Auth::admin()):
    // Barre d'édition rapide, visible uniquement par l'administratrice connectée
    $adminEdit = null;
    if (isset($product['id']) && (is_active('/produit/') || is_active('/portrait-aquarelle'))) {
        $adminEdit = ['admin/produits/' . (int) $product['id'], 'Modifier l\'article'];
    } elseif (request_path() === '/') {
        $adminEdit = ['admin/accueil', 'Modifier la page d\'accueil'];
    } elseif (isset($category['id'])) {
        $adminEdit = ['admin/categories', 'Modifier la catégorie'];
    } elseif (isset($post['id']) && is_active('/journal/')) {
        $adminEdit = ['admin/journal/' . (int) $post['id'], 'Modifier l\'article du journal'];
    } elseif (is_active('/journal')) {
        $adminEdit = ['admin/journal', 'Gérer le journal'];
    } elseif (is_array($page ?? null) && isset($page['id'])) {
        $adminEdit = ['admin/pages/' . (int) $page['id'], 'Modifier la page'];
    }
?>
<div class="admin-bar" role="region" aria-label="Administration">
  <?php if ($adminEdit): ?><a class="admin-bar-edit" href="<?= e(url($adminEdit[0])) ?>"><?= icon('brush', 16) ?> <?= e($adminEdit[1]) ?></a><?php endif; ?>
  <a href="<?= e(url('admin')) ?>">Administration</a>
</div>
<?php endif; ?>

<footer class="site-footer">
  <div class="container footer-grid">
    <div>
      <a class="brand brand-footer" href="<?= e(url('/')) ?>"><img class="logo-img" src="<?= e(url('assets/img/brand/logo-signature-creme.svg')) ?>" alt="<?= e($shopName) ?> — le club des passionnées" width="240" height="96"></a>
      <p>Une marque qui réunit les cavalières autour de leur passion. Dessiné avec amour, imprimé à la demande en Europe.</p>
      <?php if ($socials): ?>
      <p class="socials"><?php foreach ($socials as $label => $href): ?><a href="<?= e($href) ?>" rel="noopener" target="_blank"><?= e($label) ?></a><?php endforeach; ?></p>
      <?php endif; ?>
    </div>
    <details class="footer-col" data-footer-col open>
      <summary><h3>Boutique</h3></summary>
      <ul>
        <?php foreach ($navCategories as $c): ?><li><a href="<?= e(url('categorie/' . $c['slug'])) ?>"><?= e($c['name']) ?></a></li><?php endforeach; ?>
        <?php if (App\Services\AiPortrait::landingAvailable()): ?><li><a href="<?= e(url('portrait-aquarelle')) ?>">Portrait aquarelle de votre cheval</a></li><?php endif; ?>
        <li><a href="<?= e(url('boutique', ['personnalisable' => 1])) ?>">Articles personnalisables</a></li>
        <li><a href="<?= e(url('boutique', ['prix_max' => (int) setting('gift_max_price', 3000) / 100])) ?>">Idées cadeaux</a></li>
        <?php if (App\Services\Journal::available()): ?><li><a href="<?= e(url('journal')) ?>">Le journal : conseils &amp; idées cadeaux</a></li><?php endif; ?>
        <li><a href="<?= e(url('suivi')) ?>">Suivre ma commande</a></li>
      </ul>
    </details>
    <details class="footer-col" data-footer-col open>
      <summary><h3>Aide</h3></summary>
      <ul>
        <?php foreach ($footerPages as $p): ?><li><a href="<?= e(url('page/' . $p['slug'])) ?>"><?= e($p['title']) ?></a></li><?php endforeach; ?>
        <li><a href="<?= e(url('contact')) ?>">Contact</a></li>
        <li><button type="button" class="link-button" data-cookie-settings>Gérer les cookies</button></li>
      </ul>
    </details>
    <?php if (request_path() !== '/'): /* l'accueil a déjà son bloc « Rejoignez le club » */ ?>
    <div>
      <h3>Rejoindre le club</h3>
      <p><?= $welcomeOffer !== '' ? e($welcomeOffer) . ' sur votre première commande, puis nos nouveautés en avant-première.' : 'Nouveautés en avant-première, éditions limitées et petites surprises.' ?></p>
      <form class="newsletter" action="<?= e(url('newsletter')) ?>" method="post" data-newsletter>
        <?= csrf_field() ?>
        <input type="hidden" name="source" value="footer">
        <input type="text" name="website" class="hp" tabindex="-1" autocomplete="off" aria-hidden="true">
        <label class="sr-only" for="nl-email">Adresse e-mail</label>
        <div class="input-group">
          <input id="nl-email" type="email" name="email" required placeholder="votre@email.fr" autocomplete="email">
          <button class="btn btn-primary" type="submit">Je m'inscris</button>
        </div>
        <label class="checkbox small"><input type="checkbox" name="consent" value="1" required> <span>J'accepte de recevoir la newsletter (désinscription en un clic).</span></label>
        <p class="small" data-newsletter-message role="status"></p>
      </form>
    </div>
    <?php endif; ?>
  </div>
  <div class="container footer-bottom">
    <span>© <?= date('Y') ?> <?= e($shopName) ?> — Tous droits réservés</span>
    <span class="pay-methods" aria-label="Moyens de paiement acceptés"><span>CB</span><span>VISA</span><span>Mastercard</span><span>Apple Pay</span><span>Google Pay</span></span>
  </div>
</footer>

<!-- Mini-panier -->
<div class="drawer-backdrop" data-drawer-backdrop hidden></div>
<aside class="drawer" data-drawer aria-label="Mon panier" aria-hidden="true" tabindex="-1">
  <div class="drawer-head">
    <h2>Mon panier</h2>
    <button class="icon-btn" type="button" data-drawer-close aria-label="Fermer le panier"><?= icon('close', 22) ?></button>
  </div>
  <div class="drawer-content" data-drawer-content></div>
</aside>

<?php if ($showPopup): ?>
<!-- Popup de bienvenue -->
<dialog class="modal" data-welcome data-delay="<?= max(3, (int) setting('popup_delay', 15)) ?>" aria-labelledby="welcome-title">
  <button class="icon-btn modal-close" type="button" data-modal-close aria-label="Fermer"><?= icon('close', 20) ?></button>
  <div class="welcome">
    <div class="welcome-visual"><img src="<?= e(url('assets/img/brand/logo-stacked.svg')) ?>" alt="" width="240" height="224" loading="lazy"></div>
    <div class="welcome-body">
      <p class="script-accent">le club des passionnées</p>
      <h2 id="welcome-title"><?= e(setting('popup_title')) ?></h2>
      <?php if ($welcomeOffer !== ''): ?>
        <p class="welcome-offer"><?= e($welcomeOffer) ?></p>
        <p>sur votre première commande en rejoignant notre newsletter, + les nouveautés en avant-première.</p>
      <?php else: ?>
        <p>Recevez nos nouveautés en avant-première, les éditions limitées et nos idées cadeaux.</p>
      <?php endif; ?>
      <form action="<?= e(url('newsletter')) ?>" method="post" data-newsletter>
        <?= csrf_field() ?>
        <input type="hidden" name="source" value="popup">
        <input type="text" name="website" class="hp" tabindex="-1" autocomplete="off" aria-hidden="true">
        <div class="field"><label class="sr-only" for="welcome-email">Adresse e-mail</label><input id="welcome-email" type="email" name="email" required placeholder="votre@email.fr" autocomplete="email" autofocus></div>
        <label class="checkbox small"><input type="checkbox" name="consent" value="1" required> <span>J'accepte de recevoir la newsletter. Désinscription en un clic, <a href="<?= e(url('page/confidentialite')) ?>">données protégées</a>.</span></label>
        <button class="btn btn-primary btn-block" type="submit"><?= $welcomeOffer !== '' ? 'Je reçois mon code' : 'Je m\'inscris' ?></button>
        <p class="small" data-newsletter-message role="status"></p>
      </form>
      <button class="link-button small muted mt" type="button" data-modal-close>Non merci</button>
    </div>
  </div>
</dialog>
<?php endif; ?>

<div class="cookie-banner" data-cookie-banner hidden role="dialog" aria-live="polite" aria-label="Gestion des cookies">
  <div class="cookie-inner">
    <p>Nous utilisons des cookies pour mesurer l'audience et améliorer votre expérience. Le cookie du panier est indispensable ; les autres ne sont déposés qu'avec votre accord. <a href="<?= e(url('page/confidentialite')) ?>">En savoir plus</a></p>
    <div class="cookie-actions">
      <button class="btn btn-ghost btn-sm" type="button" data-cookie-choice="refuse">Refuser</button>
      <button class="btn btn-primary btn-sm" type="button" data-cookie-choice="accept">Accepter</button>
    </div>
  </div>
</div>

<script src="<?= e(asset('assets/js/app.js')) ?>" defer></script>
<script src="<?= e(asset('assets/js/password-toggle.js')) ?>" defer></script>
</body>
</html>
