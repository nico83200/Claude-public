<?php
/** Parc clients : installations, versions, usage et licences (abonnement, option IA, échéance). */
defined('HUB') || exit;

$clients = hall('SELECT * FROM clients WHERE app = ? ORDER BY active DESC, name', [$app['slug']]);
$latestByApp = [];
$kpi = ['active' => 0, 'ai' => 0, 'soon' => 0, 'late' => 0, 'outdated' => 0];
foreach ($clients as &$c) {
    $c['lic'] = hub_licence($c);
    $latestByApp[$c['app']] ??= hub_latest_release($c['app']);
    $c['latest'] = $latestByApp[$c['app']]['version'] ?? null;
    $c['outdated'] = $c['latest'] && $c['app_version'] && version_compare($c['app_version'], $c['latest'], '<');
    $c['silent'] = $c['last_check'] && strtotime($c['last_check']) < time() - 3 * 86400;
    if (in_array($c['lic']['status'], ['active', 'grace'], true)) {
        $kpi['active']++;
        $kpi['ai'] += $c['lic']['ai'] ? 1 : 0;
    }
    if ($c['lic']['status'] === 'active' && $c['lic']['days_left'] !== null && $c['lic']['days_left'] <= 30) {
        $kpi['soon']++;
    }
    if (in_array($c['lic']['status'], ['grace', 'expired'], true)) {
        $kpi['late']++;
    }
    $kpi['outdated'] += $c['outdated'] ? 1 : 0;
}
unset($c);
$licTag = ['active' => ['Active', 'green'], 'grace' => ['Échue · délai de grâce', 'amber'], 'expired' => ['Expirée', 'red'], 'suspended' => ['Suspendue', 'red']];
$apiUrl = preg_replace('/index\.php$/', 'api.php', hub_base_url());
$stripeOn = hub_stripe_ready();
$kpi['auto'] = count(array_filter($clients, fn($c) => hub_billing_active($c)));
?>
<main class="wrap">
  <?= $flashHtml ?>
  <h1><span class="dot" style="background:<?= h($app['color']) ?>;width:14px;height:14px"></span> Parc clients · <?= h($app['name']) ?></h1>
  <div class="stats">
    <div class="stat"><b><?= $kpi['active'] ?></b><span>licences actives</span></div>
    <div class="stat"><b><?= $kpi['ai'] ?></b><span>avec option IA</span></div>
    <div class="stat"><b><?= number_format($kpi['active'] * (float)$app['price_base'] + $kpi['ai'] * (float)$app['price_ai'], 0, ',', ' ') ?> €</b><span>revenu mensuel estimé (HT)</span></div>
    <div class="stat"><b style="color:<?= $kpi['soon'] + $kpi['late'] ? 'var(--amber)' : 'inherit' ?>"><?= $kpi['soon'] ?> / <?= $kpi['late'] ?></b><span>échéance &lt; 30 j / impayés</span></div>
    <div class="stat"><b style="color:<?= $kpi['outdated'] ? 'var(--amber)' : 'inherit' ?>"><?= $kpi['outdated'] ?></b><span>installation(s) à mettre à jour</span></div>
    <?php if ($stripeOn): ?><div class="stat"><b><?= $kpi['auto'] ?> / <?= count($clients) ?></b><span>en paiement automatique</span></div><?php endif; ?>
  </div>

  <?php if ($nk = $_SESSION['new_key'] ?? null): unset($_SESSION['new_key']); ?>
    <div class="card keybox"><b><?= !empty($nk['rotated']) ? 'Nouvelle clé' : 'Clé' ?> de « <?= h($nk['name']) ?> »</b> (affichée une seule fois) :
      <pre>'support_hub_url' => '<?= h($apiUrl) ?>',
'support_hub_key' => '<?= h($nk['key']) ?>',</pre>
      <small class="muted">À coller dans <?= h($app['name']) ?><?= $app['slug'] === 'approvia' ? ' : <b>Administration → Paramètres → Licence et assistance NLapps</b>' : ' (configuration du kit NLapps)' ?>. <?= !empty($nk['rotated']) ? 'L\'ancienne clé reste acceptée 14 jours, le temps que le client colle la nouvelle.' : 'Elle sert à la fois de licence, d\'accès aux mises à jour et à l\'assistance en direct.' ?></small></div>
  <?php endif; ?>

  <details class="card edit" <?= $clients ? '' : 'open' ?>>
    <summary><b>＋ Nouveau client</b> <small class="muted">— crée sa clé (licence + mises à jour + assistance)</small></summary>
    <form method="post">
      <?= csrf_input() ?><input type="hidden" name="action" value="client_add">
      <div class="grid2">
        <div><label>Nom du client</label><input name="name" placeholder="ex : Groupe IMSS" required></div>
        <input type="hidden" name="app" value="<?= h($app['slug']) ?>">
        <div><label>Adresse du site</label><input name="site" placeholder="https://achats.client.fr"></div>
        <div><label>E-mail du contact</label><input type="email" name="contact_email"></div>
        <div><label>Formule</label><input name="plan" value="Abonnement"></div>
        <div><label>Payé jusqu'au</label><input type="date" name="paid_until" value="<?= date('Y-m-d', strtotime('+1 month')) ?>"></div>
      </div>
      <label class="check"><input type="checkbox" name="ai_option" value="1"> Option assistant IA (<?= h(number_format((float)$app['price_ai'], 0, ',', ' ')) ?> € / mois)</label>
      <button class="btn primary">Créer le client et sa clé</button>
    </form>
  </details>

  <div class="card table-wrap" style="padding:.4rem .8rem">
    <table class="cards">
      <tr><th>Client</th><th>Version</th><th class="hide-sm">Usage</th><th>Licence</th><th></th></tr>
      <?php foreach ($clients as $c): $lt = $licTag[$c['lic']['status']]; $stats = json_decode((string)$c['stats'], true) ?: []; ?>
        <tr class="<?= $c['active'] ? '' : 'dim' ?>" id="client-<?= (int)$c['id'] ?>">
          <td><b><?= h($c['name']) ?></b><br>
            <small class="muted"><?= $c['instance_url'] ? '<a href="' . h($c['instance_url']) . '" target="_blank" rel="noopener">' . h(preg_replace('#^https?://#', '', $c['instance_url'])) . '</a>' : h($c['site'] ?: '—') ?></small><br>
            <small class="muted">Clé <code><?= h($c['key_hint']) ?></code></small></td>
          <td><?php if ($c['app_version']): ?><b><?= h($c['app_version']) ?></b> <?= $c['outdated'] ? '<span class="tag amber">→ ' . h($c['latest']) . '</span>' : '<span class="tag green">à jour</span>' ?><?php else: ?><span class="muted">—</span><?php endif; ?><br>
            <small class="<?= $c['silent'] ? '' : 'muted' ?>" style="<?= $c['silent'] ? 'color:var(--red)' : '' ?>"><?= $c['last_check'] ? 'vu le ' . date('d/m H:i', strtotime($c['last_check'])) : 'jamais connecté' ?></small></td>
          <td class="hide-sm"><small><?= isset($stats['users']) ? (int)$stats['users'] . ' utilisateurs<br>' . (int)($stats['centers'] ?? 0) . ' centre(s)' : '—' ?><?= isset($stats['orders30']) ? '<br>' . (int)$stats['orders30'] . ' bons / 30 j' : '' ?></small></td>
          <td><span class="tag <?= $lt[1] ?>"><?= h($lt[0]) ?></span><?= $c['lic']['ai'] ? ' <span class="tag violet">IA</span>' : '' ?><br>
            <small class="muted"><?= h($c['plan']) ?><?= $c['paid_until'] ? ' · jusqu\'au ' . date('d/m/Y', strtotime($c['paid_until'])) : ' · sans échéance' ?></small>
            <?php if ($stripeOn): $bs = (string)$c['billing_status']; ?><br><span class="tag <?= ['active' => 'green', 'trialing' => 'green', 'past_due' => 'red', 'unpaid' => 'red', 'canceled' => 'amber'][$bs] ?? '' ?>" title="Paiement en ligne"><?= $bs ? ($bs === 'active' || $bs === 'trialing' ? ($c['billing_method'] === 'sepa_debit' ? 'Prélèvement SEPA' : ($c['billing_method'] === 'card' ? 'Carte' : 'Paiement auto')) . ($c['billing_next'] ? ' · ' . date('d/m', strtotime($c['billing_next'])) : '') : h(hub_billing_status_label($bs))) : 'Paiement manuel' ?></span><?php endif; ?></td>
          <td>
            <details class="edit"><summary class="btn sm">Gérer</summary>
              <form method="post" style="min-width:260px">
                <?= csrf_input() ?><input type="hidden" name="action" value="client_save"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                <label>Nom</label><input name="name" value="<?= h($c['name']) ?>">
                <label>Site</label><input name="site" value="<?= h($c['site']) ?>">
                <label>E-mail du contact</label><input type="email" name="contact_email" value="<?= h($c['contact_email']) ?>">
                <label>Formule</label><input name="plan" value="<?= h($c['plan']) ?>">
                <label>Payé jusqu'au <small class="muted">(vide = sans échéance)</small></label><input type="date" name="paid_until" value="<?= h($c['paid_until']) ?>">
                <label class="check"><input type="checkbox" name="ai_option" value="1" <?= $c['ai_option'] ? 'checked' : '' ?>> Option assistant IA</label>
                <label>État</label><select name="status"><option value="active">Active</option><option value="suspended" <?= $c['status'] === 'suspended' ? 'selected' : '' ?>>Suspendue (accès bloqué)</option></select>
                <label>Message affiché à l'administrateur du client <small class="muted">(facultatif)</small></label><input name="licence_note" value="<?= h($c['licence_note']) ?>" placeholder="ex : facture du mois en attente">
                <div class="row" style="margin-top:.8rem"><button class="btn primary sm">Enregistrer</button></div>
              </form>
              <form method="post" class="row" style="margin-top:.6rem">
                <?= csrf_input() ?><input type="hidden" name="action" value="client_extend"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                <select name="months" style="width:auto"><option value="1">1 mois</option><option value="3">3 mois</option><option value="12">12 mois</option></select>
                <button class="btn sm">Prolonger</button>
              </form>
              <?php if ($stripeOn): $ev = hall('SELECT * FROM billing_events WHERE client_id = ? ORDER BY id DESC LIMIT 5', [$c['id']]); ?>
              <div style="margin-top:.8rem;border-top:1px solid var(--line);padding-top:.6rem">
                <b>Paiement en ligne</b> <small class="muted">· <?= h(number_format(hub_billing_summary($c)['monthly_ttc'] / 100, 2, ',', ' ')) ?> € TTC / mois</small>
                <label>Lien de paiement du client</label><input readonly value="<?= h(hub_pay_url($c)) ?>" onclick="this.select()">
                <div class="row" style="margin-top:.4rem">
                  <form method="post"><?= csrf_input() ?><input type="hidden" name="action" value="client_paylink"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><input type="hidden" name="send" value="1"><button class="btn sm" <?= $c['contact_email'] ? '' : 'disabled title="E-mail du contact manquant"' ?>>Envoyer le lien par e-mail</button></form>
                  <?php if ($c['stripe_customer']): ?><a class="btn sm" href="https://dashboard.stripe.com/<?= hub_stripe_test_mode() ? 'test/' : '' ?>customers/<?= h($c['stripe_customer']) ?>" target="_blank" rel="noopener">Ouvrir dans Stripe</a><?php endif; ?>
                </div>
                <?php foreach ($ev as $e): ?><div><small class="muted"><?= date('d/m/Y', strtotime($e['created_at'])) ?> · <?= h($e['label']) ?><?= $e['url'] ? ' · <a href="' . h($e['url']) . '" target="_blank" rel="noopener">facture</a>' : '' ?></small></div><?php endforeach; ?>
              </div>
              <?php endif; ?>
              <div class="row" style="margin-top:.6rem">
                <form method="post" onsubmit="return confirm('Générer une nouvelle clé ? L\'ancienne restera acceptée 14 jours.')"><?= csrf_input() ?><input type="hidden" name="action" value="client_rotate"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><button class="btn sm">Renouveler la clé</button></form>
                <form method="post"><?= csrf_input() ?><input type="hidden" name="action" value="client_toggle"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><button class="btn sm <?= $c['active'] ? 'danger' : '' ?>"><?= $c['active'] ? 'Désactiver la clé' : 'Réactiver' ?></button></form>
              </div>
            </details>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$clients): ?><tr><td colspan="5" class="muted">Aucun client pour l'instant.</td></tr><?php endif; ?>
    </table>
  </div>
  <p class="muted"><small><?= hub_grace_days() ? 'Licence échue : l\'application reste utilisable pendant ' . hub_grace_days() . ' jour(s) (délai de grâce, bandeau d\'alerte), puis' : 'Licence échue :' ?> l'accès au logiciel est coupé immédiatement et tous les utilisateurs sont déconnectés (message avec vos coordonnées). « Suspendue » a le même effet, à tout moment. Délai de grâce réglable dans Réglages.</small></p>
</main>
