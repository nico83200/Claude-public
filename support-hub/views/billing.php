<?php
/** Abonnements : tous les clients, montant mensuel, mode de paiement, échéances, impayés et journal des paiements. */
defined('HUB') || exit;

$stripeOn = hub_stripe_ready();
$apps = [];
foreach (hub_apps() as $a) {
    $apps[$a['slug']] = $a;
}
$clients = hall('SELECT * FROM clients ORDER BY active DESC, name');
$eur = fn(int $cents) => number_format($cents / 100, 2, ',', ' ') . ' €';
$k = ['mrr' => 0, 'auto' => 0, 'autoAmount' => 0, 'late' => 0, 'soon' => 0, 'active' => 0];
foreach ($clients as &$c) {
    $c['lic'] = hub_licence($c);
    $c['sum'] = hub_billing_summary($c);
    $c['auto'] = hub_billing_active($c);
    $live = in_array($c['lic']['status'], ['active', 'grace'], true) && (int)$c['active'];
    if ($live) {
        $k['active']++;
        $k['mrr'] += $c['sum']['monthly_ttc'];
        if ($c['auto']) {
            $k['auto']++;
            $k['autoAmount'] += $c['sum']['monthly_ttc'];
        }
    }
    if (in_array($c['lic']['status'], ['grace', 'expired'], true) || in_array((string)$c['billing_status'], ['past_due', 'unpaid'], true)) {
        $k['late']++;
    }
    if (!$c['auto'] && $c['lic']['status'] === 'active' && $c['lic']['days_left'] !== null && $c['lic']['days_left'] <= 30) {
        $k['soon']++;
    }
}
unset($c);
$month = hone("SELECT COALESCE(SUM(amount), 0) s, COUNT(*) n FROM billing_events WHERE type = 'invoice.paid' AND amount > 0 AND created_at >= ?", [date('Y-m-01')]);
$events = hall('SELECT e.*, c.name AS client_name, c.app FROM billing_events e LEFT JOIN clients c ON c.id = e.client_id ORDER BY e.id DESC LIMIT 40');
$licTag = ['active' => ['Active', 'green'], 'grace' => ['Échue · grâce', 'amber'], 'expired' => ['Expirée', 'red'], 'suspended' => ['Suspendue', 'red']];
?>
<main class="wrap">
  <?= $flashHtml ?>
  <h1>💳 Abonnements</h1>

  <?php if (!$stripeOn): ?>
    <div class="card" style="border:2px solid #c7d2fe">
      <b>Paiement en ligne pas encore activé</b>
      <p class="muted" style="margin:.4rem 0 .8rem">Les abonnements ci-dessous sont suivis « à la main » (échéance prolongée depuis le parc clients). Reliez votre compte Stripe pour que vos clients paient par carte ou prélèvement SEPA, et que chaque paiement prolonge leur licence automatiquement.</p>
      <a class="btn primary" href="index.php?p=settings#paiement">Configurer le paiement en ligne</a>
    </div>
  <?php endif; ?>

  <div class="stats">
    <div class="stat"><b><?= $eur($k['mrr']) ?></b><span>revenu mensuel TTC (<?= $k['active'] ?> licence<?= $k['active'] > 1 ? 's' : '' ?> active<?= $k['active'] > 1 ? 's' : '' ?>)</span></div>
    <div class="stat"><b><?= $k['auto'] ?> / <?= $k['active'] ?></b><span>en paiement automatique (<?= $eur($k['autoAmount']) ?> / mois)</span></div>
    <div class="stat"><b><?= $eur((int)$month['s']) ?></b><span>encaissé en ligne ce mois (<?= (int)$month['n'] ?> paiement<?= (int)$month['n'] > 1 ? 's' : '' ?>)</span></div>
    <div class="stat"><b style="color:<?= $k['late'] ? 'var(--red)' : 'inherit' ?>"><?= $k['late'] ?></b><span>impayés / licences échues</span></div>
    <div class="stat"><b style="color:<?= $k['soon'] ? 'var(--amber)' : 'inherit' ?>"><?= $k['soon'] ?></b><span>échéance &lt; 30 j sans paiement automatique</span></div>
  </div>

  <div class="card table-wrap" style="padding:.4rem .8rem">
    <table class="cards">
      <tr><th>Client</th><th>Montant</th><th>Paiement</th><th>Licence</th><th></th></tr>
      <?php foreach ($clients as $c): $lt = $licTag[$c['lic']['status']]; $a = $apps[$c['app']] ?? null; $bs = (string)$c['billing_status']; ?>
        <tr class="<?= $c['active'] ? '' : 'dim' ?>">
          <td><b><?= h($c['name']) ?></b><br><small class="muted"><?php if ($a): ?><span class="dot" style="background:<?= h($a['color']) ?>"></span> <?= h($a['name']) ?><?php else: ?><?= h($c['app']) ?><?php endif; ?> · <?= h($c['plan']) ?><?= (int)$c['ai_option'] ? ' + IA' : '' ?></small></td>
          <td><b><?= $eur($c['sum']['monthly_ttc']) ?></b><br><small class="muted">TTC / mois</small></td>
          <td><?php if ($c['auto']): ?>
              <span class="tag <?= in_array($bs, ['past_due', 'unpaid'], true) ? 'red' : 'green' ?>"><?= $c['billing_method'] === 'sepa_debit' ? 'Prélèvement SEPA' : ($c['billing_method'] === 'card' ? 'Carte' : 'Automatique') ?></span>
              <br><small class="muted"><?= h(hub_billing_status_label($bs)) ?><?= $c['billing_next'] ? ' · prochain le ' . date('d/m/Y', strtotime($c['billing_next'])) : '' ?></small>
            <?php elseif ($bs === 'canceled'): ?><span class="tag amber">Résilié</span><br><small class="muted">paiements arrêtés</small>
            <?php else: ?><span class="tag">Manuel</span><br><small class="muted"><?= $stripeOn ? 'lien de paiement non utilisé' : 'facturation hors ligne' ?></small><?php endif; ?></td>
          <td><span class="tag <?= $lt[1] ?>"><?= h($lt[0]) ?></span><br><small class="muted"><?= $c['paid_until'] ? 'jusqu\'au ' . date('d/m/Y', strtotime($c['paid_until'])) : 'sans échéance' ?></small></td>
          <td>
            <?php if ($stripeOn): ?>
              <details class="edit"><summary class="btn sm">Lien de paiement</summary>
                <div style="min-width:260px">
                  <input readonly value="<?= h(hub_pay_url($c)) ?>" onclick="this.select()">
                  <form method="post" style="margin-top:.4rem"><?= csrf_input() ?><input type="hidden" name="action" value="client_paylink"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><input type="hidden" name="send" value="1"><input type="hidden" name="back" value="billing">
                    <button class="btn sm" <?= $c['contact_email'] ? '' : 'disabled title="E-mail du contact manquant"' ?>>Envoyer à <?= h($c['contact_email'] ?: '—') ?></button></form>
                </div></details>
            <?php endif; ?>
            <a class="btn sm" href="index.php?p=clients&app=<?= h(rawurlencode($c['app'])) ?>#client-<?= (int)$c['id'] ?>">Gérer</a>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$clients): ?><tr><td colspan="5" class="muted">Aucun client : créez-les depuis le parc clients de chaque application.</td></tr><?php endif; ?>
    </table>
  </div>

  <div class="card">
    <h2>Journal des paiements</h2>
    <?php if ($events): ?>
      <?php foreach ($events as $e): ?>
        <div style="padding:.35rem 0;border-bottom:1px solid var(--line)"><small class="muted"><?= date('d/m/Y H:i', strtotime($e['created_at'])) ?></small> · <b><?= h($e['client_name'] ?: '—') ?></b> · <?= h($e['label']) ?><?= $e['url'] ? ' · <a href="' . h($e['url']) . '" target="_blank" rel="noopener">facture</a>' : '' ?></div>
      <?php endforeach; ?>
    <?php else: ?><p class="muted" style="margin:0"><?= $stripeOn ? 'Aucun paiement en ligne reçu pour l\'instant.' : 'Les paiements en ligne apparaîtront ici dès que Stripe sera relié.' ?></p><?php endif; ?>
  </div>
</main>
