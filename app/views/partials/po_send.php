<?php
/**
 * Passage de la commande au fournisseur selon son mode : site en ligne, PDF par e-mail
 * (par l'application, ou par la messagerie de l'ordinateur si l'envoi est désactivé), téléphone.
 * Variables : $sup (fournisseur), $q (['id' => …] ou ['ref' => …]), $copyText (liste à copier), $created, $isGroup.
 */
$method = supplier_order_method($sup);
$orderUrl = supplier_order_url($sup);
$draft = po_mail_draft(isset($q['ref']) ? array_map('intval', array_column(group_or_fail($q['ref']), 'id')) : [(int)$q['id']]);
$mailto = 'mailto:' . rawurlencode($draft['to']) . '?subject=' . rawurlencode($draft['subject'])
    . '&body=' . rawurlencode($draft['body'] . "\n\n(Bon de commande " . $draft['filename'] . ' en pièce jointe)');
$appMail = mail_case_enabled('supplier_po');
?>
<?php if ($method === 'online' && $orderUrl): ?>
  <div class="send-block send-online">
    <div class="send-title"><?= icon('cart', 18) ?> Commande en ligne</div>
    <?php if (!empty($created)): ?><p class="mb-1" style="font-size:.9rem"><strong>Bon créé.</strong> Passez maintenant la commande sur le site de <?= e($sup['supplier_name']) ?>.</p><?php endif; ?>
    <a class="btn btn-primary" style="width:100%" href="<?= e($orderUrl) ?>" target="_blank" rel="noopener" data-open-site <?= !empty($created) ? 'data-autofocus' : '' ?>><?= icon('send', 16) ?> Ouvrir le site de <?= e($sup['supplier_name']) ?></a>
    <div class="send-meta">
      <?php if (!empty($sup['customer_number'])): ?><span>N° client : <strong><?= e($sup['customer_number']) ?></strong> <button type="button" class="btn btn-ghost btn-xs" data-copy="<?= e($sup['customer_number']) ?>">copier</button></span><?php endif; ?>
      <?php if (!empty($copyText)): ?><button type="button" class="btn btn-sm" data-copy="<?= e($copyText) ?>"><?= icon('clipboard', 15) ?> Copier les références et quantités</button><?php endif; ?>
      <?php if (!empty($sup['order_note'])): ?><small class="muted"><?= e($sup['order_note']) ?></small><?php endif; ?>
    </div>
    <small class="muted">Une fois la commande validée sur le site, notez son numéro ci-dessous et passez le bon en « Commandé ».</small>
  </div>
<?php endif; ?>

<?php if ($method === 'online' && !$orderUrl): ?>
  <div class="flash flash-info" style="font-size:.88rem"><?= icon('info', 16) ?><div>Commande en ligne prévue, mais l'adresse du site n'est pas renseignée : <a href="<?= url('admin/supplier', ['id' => $sup['supplier_id']]) ?>">compléter la fiche fournisseur</a>.</div></div>
<?php endif; ?>

<?php if ($method === 'phone'): ?>
  <div class="send-block">
    <div class="send-title"><?= icon('phone', 18) ?> Commande par téléphone</div>
    <?php if (!empty($sup['supplier_phone'])): ?><a class="btn" style="width:100%" href="tel:<?= e(preg_replace('/[^\d+]/', '', $sup['supplier_phone'])) ?>"><?= icon('phone', 16) ?> <?= e($sup['supplier_phone']) ?></a><?php endif; ?>
    <div class="send-meta"><?php if (!empty($sup['customer_number'])): ?><span>N° client : <strong><?= e($sup['customer_number']) ?></strong></span><?php endif; ?>
      <a class="btn btn-sm" href="<?= url('admin/order/pdf', $q) ?>" target="_blank"><?= icon('file', 15) ?> Bon à lire (PDF)</a></div>
    <?php if (!empty($sup['order_note'])): ?><small class="muted"><?= e($sup['order_note']) ?></small><?php endif; ?>
  </div>
<?php elseif ($method === 'other' && !empty($sup['order_note'])): ?>
  <div class="flash flash-info" style="font-size:.88rem"><?= icon('info', 16) ?><div><strong>Mode de commande :</strong> <?= e($sup['order_note']) ?></div></div>
<?php endif; ?>

<?php $emailFirst = $method === 'email' || ($method === 'other' && $draft['to']) || ($method === 'online' && !$orderUrl); ?>
<?php if ($draft['to'] || $method === 'email'): ?>
<details class="send-block" <?= $emailFirst ? 'open' : '' ?>>
  <summary class="send-title"><?= icon('mail', 18) ?> <?= $emailFirst ? 'Envoyer le bon (PDF) par e-mail' : 'Autre possibilité : envoyer le PDF par e-mail' ?></summary>
  <?php if ($appMail): ?>
    <form method="post" action="<?= url('admin/order/send', $q) ?>" class="mt-1">
      <?= csrf_field() ?>
      <input type="email" name="to" value="<?= e($draft['to']) ?>" required placeholder="adresse de commande du fournisseur" class="mb-1">
      <label class="check" style="font-weight:500"><input type="checkbox" name="mark_ordered" value="1" checked> et passer <?= !empty($isGroup) ? 'les bons' : 'le bon' ?> en « Commandé »</label>
      <?php if (mail_case_enabled('supplier_copy')): ?><label class="check" style="font-weight:500"><input type="checkbox" name="cc_me" value="1"> m'envoyer une copie</label><?php endif; ?>
      <button class="btn btn-blue" style="width:100%" type="submit"><?= icon('send', 16) ?> Envoyer par l'application</button>
    </form>
  <?php else: ?>
    <p class="muted mt-1 mb-1" style="font-size:.85rem">L'envoi automatique est désactivé dans l'application : préparez l'e-mail dans votre messagerie, le texte est déjà rédigé.</p>
    <a class="btn btn-blue" style="width:100%" href="<?= url('admin/order/eml', $q) ?>"><?= icon('mail', 16) ?> E-mail prêt, PDF joint</a>
    <small class="muted" style="display:block;text-align:center;margin-top:.3rem">S'ouvre dans Outlook ou Courrier (Windows), prêt à envoyer.</small>
    <div class="send-or">ou, avec une autre messagerie</div>
    <div class="send-steps">
      <a class="btn btn-sm" href="<?= url('admin/order/pdf', $q + ['dl' => 1]) ?>"><?= icon('download', 15) ?> 1. Télécharger le PDF</a>
      <a class="btn btn-sm" href="<?= e($mailto) ?>"><?= icon('mail', 15) ?> 2. Ouvrir l'e-mail pré-rempli</a>
    </div>
    <small class="muted">Joignez ensuite le PDF téléchargé (<?= e($draft['filename']) ?>) avant d'envoyer.<?= !$draft['to'] ? ' Adresse du fournisseur non renseignée dans sa fiche.' : '' ?></small>
  <?php endif; ?>
</details>
<?php endif; ?>
