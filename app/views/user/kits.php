<div class="page-head">
  <div><h1>Listes types</h1><p>Vos commandes habituelles en un clic : listes partagées par le service achats et listes personnelles.</p></div>
  <a class="btn btn-primary" href="<?= url('kit') ?>"><?= icon('plus', 18) ?> Nouvelle liste</a>
</div>
<?php if (!$kits): ?>
  <div class="card"><div class="empty"><?= icon('clipboard') ?><h3>Aucune liste pour l'instant</h3><p>Créez une liste, ou enregistrez votre panier comme liste depuis la page « Mon panier ».</p></div></div>
<?php endif; ?>
<div class="grid grid-3">
<?php foreach ($kits as $k): ?>
  <div class="card" style="border-top:5px solid <?= $k['shared'] ? 'var(--primary)' : '#ec4899' ?>">
    <div class="card-body">
      <div class="row" style="align-items:flex-start">
        <div class="stat-icon <?= $k['shared'] ? 'g-indigo' : 'g-pink' ?>" style="width:42px;height:42px"><?= icon($k['shared'] ? 'users' : 'star', 20) ?></div>
        <div style="flex:1;min-width:0"><h3 class="mb-0"><?= e($k['name']) ?></h3><small><?= $k['shared'] ? 'Liste partagée' . ($k['center_id'] ? '' : ' (tous les centres)') : 'Liste personnelle' ?> · <?= plural((int)$k['nb'], 'article', 'articles') ?></small></div>
      </div>
      <?php if ($k['description']): ?><p class="muted mt-1 mb-0" style="font-size:.88rem"><?= e($k['description']) ?></p><?php endif; ?>
    </div>
    <div class="card-foot row">
      <form method="post" action="<?= url('kit/to-cart', ['id' => $k['id']]) ?>"><?= csrf_field() ?><button class="btn btn-primary btn-sm" type="submit"><?= icon('cart', 15) ?> Tout ajouter au panier</button></form>
      <a class="btn btn-sm btn-ghost" href="<?= url('kit', ['id' => $k['id']]) ?>"><?= icon(kit_can_edit($k) ? 'edit' : 'eye', 15) ?> <?= kit_can_edit($k) ? 'Modifier' : 'Voir' ?></a>
    </div>
  </div>
<?php endforeach; ?>
</div>
