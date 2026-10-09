<?php
/** Console : abonnements de tous les clients, paiements reçus, réglages (tarifs, TVA, Stripe). */
$ps = platform_settings();
$rows = [];
$mrr = $auto = $late = $soon = 0;
foreach ($registry as $slug => $i) {
    if (!empty($i['demo'])) {
        continue;
    }
    $lic = platform_licence((string)$slug);
    $lr = platform_licence_row((string)$slug);
    $ttc = platform_monthly_ttc((string)$slug);
    $rows[$slug] = compact('i', 'lic', 'lr', 'ttc');
    if (in_array($lic['status'], ['active', 'grace'], true)) {
        $mrr += $ttc;
    }
    $auto += platform_billing_active((string)$slug) ? 1 : 0;
    $late += in_array($lr['billing_status'], ['past_due', 'unpaid'], true) || in_array($lic['status'], ['grace', 'expired'], true) ? 1 : 0;
    $soon += $lic['days_left'] !== null && $lic['days_left'] >= 0 && $lic['days_left'] <= 15 && !platform_billing_active((string)$slug) ? 1 : 0;
}
$events = platform_billing_events();
$month = date('Y-m');
$cashed = array_sum(array_map(fn($e) => $e['type'] === 'invoice.paid' && str_starts_with((string)$e['created_at'], $month) ? (int)$e['amount'] : 0, $events));
$eur = fn(int $c) => number_format($c / 100, 2, ',', ' ') . ' €';
$editSlug = (string)($_GET['edit'] ?? '');
?>
<h1>Abonnements</h1>
<div class="stats">
  <div class="card"><small class="muted">Revenu mensuel</small><b><?= $eur($mrr) ?></b><small class="muted">TTC, licences actives</small></div>
  <div class="card"><small class="muted">Paiement automatique</small><b><?= $auto ?> / <?= count($rows) ?></b><small class="muted">clients</small></div>
  <div class="card"><small class="muted">Encaissé ce mois</small><b><?= $eur($cashed) ?></b><small class="muted">paiements en ligne</small></div>
  <div class="card"><small class="muted">À surveiller</small><b><?= $late + $soon ?></b><small class="muted"><?= $late ?> en retard · <?= $soon ?> échéance(s) sous 15 j</small></div>
</div>
<?php if (!platform_stripe_ready()): ?><div class="flash error">Paiement en ligne non configuré : renseignez la clé Stripe ci-dessous pour envoyer des liens de paiement (carte ou prélèvement SEPA) et prolonger les licences automatiquement. Les licences se gèrent déjà à la main.</div><?php endif; ?>
<div class="card scroll">
  <table class="list">
    <tr><th>Client</th><th>Licence</th><th>Échéance</th><th>Mensuel TTC</th><th>Paiement</th><th></th></tr>
    <?php foreach ($rows as $slug => $r): ?>
      <tr>
        <td><b><?= e($r['i']['name']) ?></b><br><small class="muted"><?= e($slug) ?><?= $r['lr']['contact_email'] ? ' · ' . e($r['lr']['contact_email']) : '' ?></small></td>
        <td><?= console_licence_tag($r['lic']['status']) ?><br><small class="muted"><?= e($r['lic']['plan']) ?><?= $r['lic']['ai'] ? ' + IA' : '' ?></small></td>
        <td><?= $r['lic']['paid_until'] ? e(date('d/m/Y', strtotime($r['lic']['paid_until']))) : '—' ?></td>
        <td><?= $eur($r['ttc']) ?></td>
        <td><small><?= e(platform_billing_active((string)$slug) ? ($r['lr']['billing_method'] === 'sepa_debit' ? 'Prélèvement SEPA' : ($r['lr']['billing_method'] === 'card' ? 'Carte' : 'Automatique')) . ' · ' . platform_billing_status_label($r['lr']['billing_status']) : ($r['lr']['billing_status'] === 'canceled' ? 'Résilié' : 'Manuel')) ?>
          <?= $r['lr']['billing_next'] ? '<br><span class="muted">prochain : ' . e(date('d/m/Y', strtotime($r['lr']['billing_next']))) . '</span>' : '' ?></small></td>
        <td><a class="btn sm" href="console.php?p=billing&amp;edit=<?= e(rawurlencode((string)$slug)) ?>#fiche">Gérer</a></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="6" class="muted">Aucun client (hors démonstrations).</td></tr><?php endif; ?>
  </table>
</div>
<?php if (isset($rows[$editSlug])): $slug = $editSlug; $i = $rows[$slug]['i']; $lr = $rows[$slug]['lr']; $lic = $rows[$slug]['lic']; $back = 'billing'; ?>
  <div class="card" id="fiche"><h2>Licence et abonnement · <?= e($i['name']) ?></h2><?php require __DIR__ . '/licence_form.php'; ?>
    <?php $ev = platform_billing_events($slug); if ($ev): ?><h2 style="margin-top:1rem">Historique</h2>
      <?php foreach (array_slice($ev, 0, 12) as $e): ?><div><small class="muted"><?= e(date('d/m/Y H:i', strtotime((string)$e['created_at']))) ?></small> <?= e($e['label']) ?><?= $e['url'] ? ' · <a href="' . e($e['url']) . '" target="_blank" rel="noopener">facture</a>' : '' ?></div><?php endforeach; ?>
    <?php endif; ?></div>
<?php endif; ?>
<div class="card">
  <h2>Derniers paiements</h2>
  <?php foreach (array_slice($events, 0, 25) as $e): ?>
    <div style="padding:.35rem 0;border-bottom:1px solid var(--border)"><small class="muted"><?= e(date('d/m/Y H:i', strtotime((string)$e['created_at']))) ?> · <?= e($registry[$e['slug']]['name'] ?? $e['slug']) ?></small><br><?= e($e['label']) ?><?= !empty($e['url']) ? ' · <a href="' . e($e['url']) . '" target="_blank" rel="noopener">facture</a>' : '' ?></div>
  <?php endforeach; ?>
  <?php if (!$events): ?><p class="muted">Aucun paiement enregistré.</p><?php endif; ?>
</div>
<form method="post" class="card" id="reglages" autocomplete="off"><?= csrf_field() ?><input type="hidden" name="action" value="billing_settings">
  <h2>Tarifs et paiement en ligne</h2>
  <div class="grid2">
    <div><label>Abonnement HT / mois (par défaut)</label><input name="price_base" inputmode="decimal" value="<?= e((string)$ps['price_base']) ?>"></div>
    <div><label>Option assistant IA HT / mois</label><input name="price_ai" inputmode="decimal" value="<?= e((string)$ps['price_ai']) ?>"></div>
    <div><label>TVA (%)</label><input name="vat" inputmode="decimal" value="<?= e((string)$ps['vat']) ?>"></div>
    <div><label>Délai de grâce après l'échéance (jours, 0 = coupure le lendemain)</label><input type="number" name="grace_days" min="0" max="60" value="<?= (int)$ps['grace_days'] ?>"></div>
    <div><label>Nom affiché (pages de paiement, licences)</label><input name="operator_name" value="<?= e((string)$ps['operator_name']) ?>"></div>
    <div><label>E-mail de contact et des alertes de paiement</label><input type="email" name="operator_email" value="<?= e((string)$ps['operator_email']) ?>"></div>
  </div>
  <h2 style="margin-top:1.2rem">Stripe <?= platform_stripe_ready() ? '<span class="tag green">' . (platform_stripe_test_mode() ? 'mode test' : 'reliée') . '</span>' : '<span class="tag amber">non reliée</span>' ?></h2>
  <div class="grid2">
    <div><label>Clé secrète (sk_live_… ou sk_test_…)</label><input type="password" name="stripe_secret_key" placeholder="<?= platform_stripe_ready() ? e(substr(platform_stripe_key(), 0, 8)) . '… — saisir pour remplacer' : '' ?>" autocomplete="new-password"></div>
    <div><label>Secret du webhook (whsec_…)</label><input type="password" name="stripe_webhook_secret" placeholder="<?= $ps['stripe_webhook_secret'] ? 'enregistré — saisir pour remplacer' : '' ?>" autocomplete="new-password"></div>
  </div>
  <p class="muted"><small>Dans Stripe → Développeurs → Webhooks, ajoutez l'adresse <code><?= e(platform_webhook_url()) ?></code> avec les événements <code>checkout.session.completed</code>, <code>invoice.paid</code>, <code>invoice.payment_failed</code>, <code>customer.subscription.updated</code>, <code>customer.subscription.deleted</code>, puis collez son secret ci-dessus.<?= !empty($ps['stripe_last_event']) ? ' Dernier événement reçu : ' . e($ps['stripe_last_event']) . '.' : '' ?></small></p>
  <?php if (platform_stripe_ready()): ?><label class="check"><input type="checkbox" name="stripe_remove" value="1"> Déconnecter Stripe</label><?php endif; ?>
  <div class="row"><button class="btn primary">Enregistrer</button></div>
</form>
<?php if (platform_stripe_ready()): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="stripe_test"><button class="btn sm">Tester la connexion à Stripe</button></form><?php endif; ?>
