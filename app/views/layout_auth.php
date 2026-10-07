<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(app_name()) ?></title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
<link rel="stylesheet" href="assets/css/app.css?v=<?= e(defined('APP_VERSION') ? APP_VERSION : '1') ?>">
<link rel="manifest" href="manifest.webmanifest">
<meta name="theme-color" content="#2a1fc4">
<link rel="icon" type="image/svg+xml" href="assets/brand/approvia-mark.svg">
</head>
<body>
<div class="auth-wrap">
  <section class="auth-side">
    <?php if (function_exists('brand_logo_url') && ($logo = brand_logo_url())): ?>
    <div class="auth-logo"><img src="<?= e($logo) ?>" alt="<?= e(setting('company_name') ?: app_name()) ?>"></div>
    <?php else: ?>
    <div class="brand" style="padding:0;position:relative;z-index:1">
      <img class="brand-mark" src="assets/brand/approvia-mark.svg" alt="" width="40" height="40">
      <div><?= e(app_name()) ?></div>
    </div>
    <?php endif; ?>
    <div>
      <h1>Les commandes de vos centres, simplement.</h1>
      <p style="opacity:.9;max-width:440px;position:relative;z-index:1">Trouvez le bon produit en quelques secondes, suivez vos demandes et confirmez vos livraisons.</p>
      <div class="mt-3">
        <div class="auth-feature"><div class="ic-wrap"><?= icon('sparkles') ?></div><div>Recherche assistée par intelligence artificielle</div></div>
        <div class="auth-feature"><div class="ic-wrap"><?= icon('building') ?></div><div>Navigation par centre, un compte pour plusieurs sites</div></div>
        <div class="auth-feature"><div class="ic-wrap"><?= icon('package-check') ?></div><div>Suivi de la demande jusqu'à la réception</div></div>
      </div>
    </div>
    <small style="opacity:.75;position:relative;z-index:1;color:#fff">Outil interne — service achats</small>
  </section>
  <main class="auth-main">
    <div class="auth-card">
      <?php if (function_exists('brand_logo_url') && ($logo = brand_logo_url())): ?><img class="auth-logo-mobile" src="<?= e($logo) ?>" alt=""><?php endif; ?>
      <?php foreach (flashes() as $f): ?>
        <div class="flash flash-<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
      <?php endforeach; ?>
      <?= $content ?>
    </div>
  </main>
</div>
</body>
</html>
