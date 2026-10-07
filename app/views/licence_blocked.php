<?php $c = support_contact(); $expired = ($status ?? '') === 'expired'; ?>
<div>
  <div class="card-body" style="text-align:center">
    <div style="color:var(--red)"><?= icon('alert', 40) ?></div>
    <h1 style="font-size:1.35rem"><?= $expired ? 'Licence expirée' : 'Accès suspendu' ?></h1>
    <p class="muted"><?= $expired ? 'L\'abonnement à ' . e(app_name()) . ' n\'a pas été renouvelé : l\'accès au logiciel est coupé et les utilisateurs ont été déconnectés.' : 'L\'accès à ' . e(app_name()) . ' est suspendu par ' . e($c['editor']) . ', l\'éditeur du logiciel.' ?> Vos données sont conservées et seront de nouveau accessibles dès le rétablissement.</p>
    <p>Responsable des achats : contactez <?= e($c['editor']) ?><br><a href="mailto:<?= e($c['email']) ?>"><?= e($c['email']) ?></a><?= $c['phone'] ? ' · ' . e($c['phone']) : '' ?></p>
    <a class="btn btn-primary" href="<?= url('login') ?>"><?= icon('refresh', 16) ?> Vérifier à nouveau</a>
    <p class="muted mt-2" style="font-size:.82rem">Une fois l'abonnement renouvelé par <?= e($c['editor']) ?>, l'accès est rétabli automatiquement : cliquez sur « Vérifier à nouveau ».</p>
  </div>
</div>
