<section class="section">
  <div class="container narrow center">
    <p class="error-code"><?= (int) $code ?></p>
    <h1><?= e($title) ?></h1>
    <?php if ($message): ?><p class="lead"><?= e($message) ?></p><?php elseif ($code === 404): ?><p class="lead">Ce cheval s'est échappé du paddock… La page demandée n'existe pas ou plus.</p><?php endif; ?>
    <a class="btn btn-primary" href="<?= e(url('/')) ?>">Retour à l'accueil</a>
  </div>
</section>
