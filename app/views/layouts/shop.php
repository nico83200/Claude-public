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
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?></title>
<meta name="description" content="<?= e(excerpt($description, 160)) ?>">
<?php if (!empty($noindex)): ?><meta name="robots" content="noindex, nofollow"><?php endif; ?>
<?php if (!empty($canonical)): ?><link rel="canonical" href="<?= e($canonical) ?>"><?php endif; ?>
<meta property="og:site_name" content="<?= e($shopName) ?>">
<meta property="og:title" content="<?= e($pageTitle ?? $shopName) ?>">
<meta property="og:description" content="<?= e(excerpt($description, 200)) ?>">
<meta property="og:type" content="<?= isset($product) ? 'product' : 'website' ?>">
<?php if (!empty($ogImage)): ?><meta property="og:image" content="<?= e($ogImage) ?>"><?php endif; ?>
<meta name="theme-color" content="#1f2a44">
<link rel="icon" href="<?= e(url('assets/img/favicon.svg')) ?>" type="image/svg+xml">
<link rel="stylesheet" href="<?= e(asset('assets/css/app.css')) ?>">
<?php if (!empty($jsonLd)): ?><script type="application/ld+json"><?= json_encode($jsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></script><?php endif; ?>
</head>
<body data-ga4="<?= e(config('analytics.ga4_id')) ?>" data-pixel="<?= e(config('analytics.meta_pixel_id')) ?>">
<a class="skip-link" href="#contenu">Aller au contenu</a>

<?php if (setting('announcement')): ?>
<div class="announcement"><?= e(setting('announcement')) ?></div>
<?php endif; ?>

<header class="site-header">
  <div class="container header-inner">
    <button class="nav-toggle" aria-expanded="false" aria-controls="main-nav" aria-label="Ouvrir le menu">
      <span></span><span></span><span></span>
    </button>
    <a class="brand" href="<?= e(url('/')) ?>" aria-label="<?= e($shopName) ?> — accueil">
      <?php partial('logo') ?>
      <span class="brand-name"><?= e($shopName) ?></span>
    </a>
    <nav id="main-nav" class="main-nav" aria-label="Navigation principale">
      <a href="<?= e(url('boutique')) ?>" class="<?= is_active('/boutique') ? 'active' : '' ?>">Tout voir</a>
      <?php foreach ($navCategories as $c): ?>
        <a href="<?= e(url('categorie/' . $c['slug'])) ?>" class="<?= is_active('/categorie/' . $c['slug']) ? 'active' : '' ?>"><?= e($c['name']) ?></a>
      <?php endforeach; ?>
      <a href="<?= e(url('boutique', ['personnalisable' => 1])) ?>" class="nav-highlight">Personnalisés</a>
    </nav>
    <div class="header-actions">
      <form class="search" action="<?= e(url('boutique')) ?>" method="get" role="search">
        <label class="sr-only" for="q">Rechercher</label>
        <input id="q" type="search" name="q" placeholder="Rechercher…" value="<?= e($_GET['q'] ?? '') ?>">
      </form>
      <a class="cart-link" href="<?= e(url('panier')) ?>" aria-label="Panier (<?= $cartCount ?> article<?= $cartCount > 1 ? 's' : '' ?>)">
        <svg viewBox="0 0 24 24" width="24" height="24" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="1.6" d="M5 8h14l-1.2 11.2a1 1 0 0 1-1 .8H7.2a1 1 0 0 1-1-.8L5 8Zm4 0V6a3 3 0 0 1 6 0v2"/></svg>
        <span class="cart-count" data-cart-count <?= $cartCount ? '' : 'hidden' ?>><?= $cartCount ?></span>
      </a>
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

<section class="reassurance">
  <div class="container reassurance-grid">
    <div><strong>Imprimé à la demande</strong><span>Fabriqué pour vous, zéro stock gaspillé</span></div>
    <div><strong>Personnalisable</strong><span>Au nom de votre cheval ou de votre écurie</span></div>
    <div><strong>Paiement sécurisé</strong><span>CB, Apple Pay, Google Pay via Stripe</span></div>
    <div><strong>Satisfait ou réimprimé</strong><span>Un défaut ? On réimprime sans frais</span></div>
  </div>
</section>

<footer class="site-footer">
  <div class="container footer-grid">
    <div>
      <a class="brand brand-footer" href="<?= e(url('/')) ?>"><?php partial('logo') ?><span class="brand-name"><?= e($shopName) ?></span></a>
      <p><?= e(setting('shop_tagline')) ?></p>
      <?php if ($socials): ?>
      <p class="socials"><?php foreach ($socials as $label => $href): ?><a href="<?= e($href) ?>" rel="noopener" target="_blank"><?= e($label) ?></a><?php endforeach; ?></p>
      <?php endif; ?>
    </div>
    <div>
      <h3>Boutique</h3>
      <ul>
        <?php foreach ($navCategories as $c): ?><li><a href="<?= e(url('categorie/' . $c['slug'])) ?>"><?= e($c['name']) ?></a></li><?php endforeach; ?>
        <li><a href="<?= e(url('suivi')) ?>">Suivre ma commande</a></li>
      </ul>
    </div>
    <div>
      <h3>Informations</h3>
      <ul>
        <?php foreach ($footerPages as $p): ?><li><a href="<?= e(url('page/' . $p['slug'])) ?>"><?= e($p['title']) ?></a></li><?php endforeach; ?>
        <li><a href="<?= e(url('contact')) ?>">Contact</a></li>
        <li><button type="button" class="link-button" data-cookie-settings>Gérer les cookies</button></li>
      </ul>
    </div>
    <div>
      <h3>La newsletter du club</h3>
      <p>Nouveautés, éditions limitées et offres réservées aux abonnés.</p>
      <form class="newsletter" action="<?= e(url('newsletter')) ?>" method="post">
        <?= csrf_field() ?>
        <input type="text" name="website" class="hp" tabindex="-1" autocomplete="off" aria-hidden="true">
        <label class="sr-only" for="nl-email">Adresse e-mail</label>
        <div class="input-group">
          <input id="nl-email" type="email" name="email" required placeholder="votre@email.fr">
          <button class="btn btn-primary" type="submit">S'inscrire</button>
        </div>
        <label class="checkbox small"><input type="checkbox" name="consent" value="1" required> <span>J'accepte de recevoir la newsletter. Désinscription en un clic.</span></label>
      </form>
    </div>
  </div>
  <div class="container footer-bottom">
    <span>© <?= date('Y') ?> <?= e($shopName) ?> — Tous droits réservés</span>
    <span>Paiement sécurisé · Fabrication en Europe</span>
  </div>
</footer>

<div class="cookie-banner" data-cookie-banner hidden role="dialog" aria-live="polite" aria-label="Gestion des cookies">
  <div class="container cookie-inner">
    <p>Nous utilisons des cookies pour mesurer l'audience et améliorer votre expérience. Le cookie du panier est indispensable ; les autres ne sont déposés qu'avec votre accord. <a href="<?= e(url('page/confidentialite')) ?>">En savoir plus</a></p>
    <div class="cookie-actions">
      <button class="btn btn-ghost" type="button" data-cookie-choice="refuse">Refuser</button>
      <button class="btn btn-primary" type="button" data-cookie-choice="accept">Accepter</button>
    </div>
  </div>
</div>

<script src="<?= e(asset('assets/js/app.js')) ?>" defer></script>
</body>
</html>
