<!doctype html>
<html lang="fr">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= !empty($reconnected) ? 'Boutique reconnectée' : 'Installation terminée' ?></title>
<meta name="robots" content="noindex"><link rel="stylesheet" href="<?= e(url('assets/css/admin.css')) ?>"></head>
<body class="install">
<main class="install-card">
  <?php if (!empty($reconnected)): ?>
    <h1>✅ Boutique reconnectée</h1>
    <p>Vos produits, commandes et réglages ont été conservés<?= !empty($restored) ? ', et vos clés Stripe, Printful et e-mail restaurées' : '' ?>. Les adresses de webhooks ci-dessous sont inchangées<?= empty($restored) ? ' — sauf si aucune sauvegarde n\'existait : vérifiez alors la page Intégrations' : '' ?>.</p>
  <?php else: ?>
    <h1>🎉 Installation terminée</h1>
    <p>Votre boutique est installée. Notez précieusement les informations ci-dessous :</p>
  <?php endif; ?>
  <dl class="kv">
    <dt>URL du webhook Stripe</dt><dd><code><?= e($config['app']['url']) ?>/webhooks/stripe</code></dd>
    <dt>URL du webhook Printful</dt><dd><code><?= e($config['app']['url']) ?>/webhooks/printful/<?= e($config['printful']['webhook_token']) ?></code><br><small>Enregistrable en un clic depuis Administration › Intégrations.</small></dd>
    <dt>Tâche Cron (hPanel)</dt><dd><code>/usr/bin/php <?= e(BASE_PATH) ?>/cron/cron.php</code><br><small>Toutes les 15 minutes.</small></dd>
  </dl>
  <p>
    <a class="btn btn-primary" href="<?= e($config['app']['url']) ?>/admin">Accéder à l'administration</a>
    <a class="btn" href="<?= e($config['app']['url']) ?>/">Voir la boutique</a>
  </p>
</main>
</body>
</html>
