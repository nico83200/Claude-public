<div class="card"><div class="empty">
  <?= icon($code === 403 ? 'lock' : 'search') ?>
  <h2><?= $code === 403 ? 'Accès refusé' : 'Introuvable' ?></h2>
  <p><?= e($message) ?></p>
  <a class="btn btn-primary" href="index.php"><?= icon('home', 18) ?> Retour à l'accueil</a>
</div></div>
