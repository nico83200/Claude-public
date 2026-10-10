<?php
$st = ['active' => ['Active', 'green'], 'grace' => ['Échue · délai de grâce', 'amber'], 'expired' => ['Expirée', 'red'], 'suspended' => ['Suspendue', 'red']][$lic['status']] ?? [$lic['status'], 'gray'];
$eur = fn(int $c) => number_format($c / 100, 2, ',', ' ') . ' €';
$net = max(0, $ht - $discountHt);
$editor = support_contact()['editor'];
?>
<div class="page-head"><h1>Paramètres <small class="muted" style="font-size:1rem;font-weight:500">· Abonnement</small></h1></div>
<nav class="settings-tabs"><?php foreach (settings_tabs() as $tk => [$tl, $ti]): ?><a class="<?= $tk === 'subscription' ? 'active' : '' ?>" href="<?= $tk === 'subscription' ? url('admin/subscription') : url('admin/settings', ['tab' => $tk]) ?>"><?= icon($ti, 16) ?> <?= e($tl) ?></a><?php endforeach; ?></nav>
<?php if ($lic['status'] === 'expired'): ?>
  <div class="flash flash-error"><?= icon('alert') ?><div><strong>Abonnement expiré<?= $lic['paid_until'] ? ' le ' . date_fr($lic['paid_until']) : '' ?>.</strong> Vos utilisateurs ne peuvent plus se connecter ; vos données sont conservées. Renouvelez ci-dessous pour rétablir l'accès immédiatement.</div></div>
<?php elseif ($lic['status'] === 'grace'): ?>
  <div class="flash flash-info"><?= icon('alert') ?><div>Abonnement échu le <?= date_fr($lic['paid_until']) ?> : l'accès sera coupé le <?= date_fr(date('Y-m-d', strtotime($lic['grace_until'] . ' +1 day'))) ?> sans renouvellement.</div></div>
<?php elseif ($lic['status'] === 'suspended'): ?>
  <div class="flash flash-error"><?= icon('alert') ?><div>Accès suspendu par <?= e($editor) ?>. Contactez-nous : <?= e(support_contact()['email']) ?>.</div></div>
<?php endif; ?>
<div class="grid grid-2">
  <div class="card">
    <div class="card-head"><h2><?= icon('euro') ?> Votre abonnement</h2><span class="badge badge-<?= $st[1] ?>"><?= e($st[0]) ?></span></div>
    <div class="card-body">
      <div class="licence-box mb-2">
        <div><small class="muted">Formule</small><div class="strong"><?= e($lic['plan']) ?></div></div>
        <div><small class="muted">Valable jusqu'au</small><div class="strong"><?= $lic['paid_until'] ? date_fr($lic['paid_until']) : 'Sans échéance' ?></div></div>
        <div><small class="muted">Assistant IA</small><div class="strong"><?= $lic['ai'] ? 'Inclus' : 'Non souscrit' ?></div></div>
        <div><small class="muted">Paiement</small><div class="strong"><?= $auto ? e(($row['billing_method'] === 'sepa_debit' ? 'Prélèvement SEPA' : 'Carte bancaire') . ' automatique') : 'À la demande' ?></div>
          <?php if ($auto && $row['billing_next']): ?><small class="muted">prochain le <?= date_fr($row['billing_next']) ?></small><?php endif; ?></div>
      </div>
      <table class="table">
        <?php foreach ($lines as $l): ?><tr><td><?= e($l['label']) ?></td><td class="text-right" style="white-space:nowrap"><?= $eur($l['amount']) ?> HT</td></tr><?php endforeach; ?>
        <?php if ($discount): ?><tr><td>Réduction (code <?= e($discount['code']) ?>) <small class="muted"><?= e(platform_discount_label($discount)) ?></small></td><td class="text-right" style="white-space:nowrap">−<?= $eur($discountHt) ?> HT</td></tr><?php endif; ?>
        <?php if ($vat > 0): ?><tr><td class="muted">TVA <?= e(rtrim(rtrim(number_format($vat, 2, ',', ''), '0'), ',')) ?> %</td><td class="text-right muted" style="white-space:nowrap"><?= $eur((int)round($net * $vat / 100)) ?></td></tr><?php endif; ?>
        <tr><td class="strong">Total par mois</td><td class="text-right strong" style="white-space:nowrap"><?= $eur((int)round($net * (1 + $vat / 100))) ?> TTC</td></tr>
      </table>
      <?php if ($lic['status'] !== 'suspended'): ?>
        <?php if ($online): ?>
          <form method="post" class="mt-1"><?= csrf_field() ?><input type="hidden" name="action" value="pay">
            <button class="btn btn-primary" type="submit"><?= icon('euro', 18) ?> <?= $auto ? 'Gérer mon abonnement · moyen de paiement et factures' : ($lic['status'] === 'expired' ? 'Renouveler maintenant' : 'Mettre en place le paiement automatique') ?></button></form>
          <?php if (!$auto): ?><p class="muted mb-0" style="font-size:.85rem">Carte bancaire ou prélèvement SEPA, sans engagement, sur une page sécurisée Stripe. Chaque paiement prolonge automatiquement votre licence d'un mois<?= $lic['paid_until'] && strtotime($lic['paid_until']) > time() + 2 * 86400 ? ' ; la période déjà réglée est conservée (premier paiement le ' . date_fr($lic['paid_until']) . ')' : '' ?>.</p><?php endif; ?>
        <?php else: ?>
          <p class="muted mb-0">Le paiement en ligne n'est pas encore ouvert : contactez <?= e($editor) ?> (<?= e(support_contact()['email']) ?>) pour renouveler.</p>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
  <div class="stack">
    <form method="post" class="card"><?= csrf_field() ?><input type="hidden" name="action" value="code">
      <div class="card-head"><h2><?= icon('tag') ?> Code d'accès ou de réduction</h2></div>
      <div class="card-body">
        <p class="muted" style="margin-top:0;font-size:.9rem">Vous avez reçu un code de <?= e($editor) ?> (accès gratuit, offre de lancement, réduction) ? Saisissez-le ici : il s'applique immédiatement.</p>
        <div class="row"><input type="text" name="code" required maxlength="40" placeholder="ex : BIENVENUE-2026" style="flex:1;text-transform:uppercase" autocomplete="off"><button class="btn" type="submit">Appliquer</button></div>
        <?php if ($discount): ?><p class="mb-0 mt-1" style="font-size:.9rem"><?= icon('check-circle', 16) ?> Réduction en cours : <?= e(platform_discount_label($discount)) ?> (code <?= e($discount['code']) ?>).</p><?php endif; ?>
      </div>
    </form>
    <div class="card">
      <div class="card-head"><h2><?= icon('file') ?> Historique</h2></div>
      <div class="card-body">
        <?php foreach ($events as $ev): ?>
          <div style="padding:.35rem 0;border-bottom:1px solid var(--border);font-size:.9rem"><small class="muted"><?= date_fr($ev['created_at'], true) ?></small><br><?= e(preg_replace('/ \([^()]*<[^>]+>\)$/', '', (string)$ev['label'])) ?><?= !empty($ev['url']) ? ' · <a href="' . e($ev['url']) . '" target="_blank" rel="noopener">facture</a>' : '' ?></div>
        <?php endforeach; ?>
        <?php if (!$events): ?><p class="muted mb-0">Aucun paiement enregistré pour l'instant.</p><?php endif; ?>
      </div>
    </div>
  </div>
</div>
