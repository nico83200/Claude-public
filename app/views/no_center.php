<div class="card"><div class="empty">
  <?= icon('building') ?>
  <h2>Aucun centre associé à votre compte</h2>
  <p>Votre compte n'est encore rattaché à aucun centre. Contactez le service achats pour qu'il vous donne accès à votre site.</p>
  <?php if (is_superadmin()): ?><a class="btn btn-primary" href="<?= url('admin/center') ?>"><?= icon('plus', 18) ?> Créer un premier centre</a><?php endif; ?>
</div></div>
