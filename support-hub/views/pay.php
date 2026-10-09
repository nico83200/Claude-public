<?php
declare(strict_types=1);

/**
 * Page de paiement d'un client (lien personnel index.php?pay=…, public) : souscription de l'abonnement par carte ou
 * prélèvement SEPA, ou accès à l'espace client Stripe (moyen de paiement, factures). Aucune donnée bancaire ne transite ici.
 */
defined('HUB') || exit;

header('Cache-Control: no-store');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');

$c = hub_client_by_billing_token((string)($_GET['pay'] ?? ''));
$error = null;
if ($c && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        header('Location: ' . hub_billing_session_url($c), true, 303);
        exit;
    } catch (Throwable $e) {
        error_log('[paiement] ' . $e->getMessage());
        $error = 'Le paiement en ligne est momentanément indisponible. Réessayez dans quelques minutes ou contactez ' . hcfg('operator_name') . '.';
    }
}
$c = $c ? hone('SELECT * FROM clients WHERE id = ?', [$c['id']]) : null; // état à jour (retour de Stripe)
$lines = $c ? hub_billing_lines($c) : [];
$ht = array_sum(array_column($lines, 'amount'));
$vat = hub_vat_rate();
$eur = fn(int $cents) => number_format($cents / 100, 2, ',', ' ') . ' €';
$done = isset($_GET['done']);
$lic = $c ? hub_licence($c) : null;
?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title>Abonnement · <?= h((string)hcfg('operator_name')) ?></title>
<style>
:root { --ink:#0f172a; --muted:#64748b; --line:#e2e8f0; --bg:#f5f7fb; --card:#fff; --indigo:#4f46e5; --green:#059669; }
@media (prefers-color-scheme: dark) { :root { --ink:#e2e8f0; --muted:#94a3b8; --line:#2a3055; --bg:#0e1122; --card:#171b33; } }
* { box-sizing:border-box; }
body { margin:0; min-height:100vh; display:grid; place-items:center; padding:1.5rem 1rem; background:var(--bg); color:var(--ink); font-family:Inter,system-ui,-apple-system,"Segoe UI",sans-serif; }
.box { width:100%; max-width:480px; background:var(--card); border:1px solid var(--line); border-radius:18px; padding:1.6rem 1.5rem; box-shadow:0 10px 40px rgba(15,23,42,.08); }
.brand { display:flex; align-items:center; gap:.6rem; font-weight:800; margin-bottom:1.2rem; } .brand img { width:30px; height:30px; }
h1 { font-size:1.35rem; margin:0 0 .3rem; } p { line-height:1.5; } .muted { color:var(--muted); }
table { width:100%; border-collapse:collapse; margin:1rem 0; font-size:.95rem; } td { padding:.45rem 0; border-bottom:1px solid var(--line); } td:last-child { text-align:right; white-space:nowrap; }
tr.total td { font-weight:800; border-bottom:0; font-size:1.05rem; }
.btn { display:block; width:100%; padding:.85rem 1rem; border:0; border-radius:12px; background:linear-gradient(135deg,#0ea5e9,#4f46e5 55%,#7c3aed); color:#fff; font:inherit; font-weight:700; cursor:pointer; text-align:center; text-decoration:none; }
.ok { background:#ecfdf5; color:#065f46; padding:.8rem 1rem; border-radius:12px; } .err { background:#fef2f2; color:#991b1b; padding:.8rem 1rem; border-radius:12px; }
@media (prefers-color-scheme: dark) { .ok { background:#064e3b; color:#d1fae5; } .err { background:#7f1d1d; color:#fee2e2; } }
.methods { display:flex; gap:.5rem; justify-content:center; margin-top:.8rem; font-size:.82rem; color:var(--muted); }
.methods span { border:1px solid var(--line); border-radius:8px; padding:.2rem .55rem; }
</style>
</head>
<body>
<div class="box">
  <div class="brand"><img src="assets/nlapps-mark.svg" alt=""> <?= h((string)hcfg('operator_name')) ?></div>
<?php if (!$c): ?>
  <h1>Lien invalide</h1>
  <p class="muted">Ce lien de paiement n'existe pas ou n'est plus actif. Contactez <?= h((string)hcfg('operator_name')) ?> (<?= h((string)hcfg('notify_email')) ?>).</p>
<?php elseif (!hub_stripe_ready()): ?>
  <h1><?= h($c['name']) ?></h1>
  <p class="muted">Le paiement en ligne n'est pas encore ouvert. Contactez <?= h((string)hcfg('operator_name')) ?> (<?= h((string)hcfg('notify_email')) ?>).</p>
<?php else: ?>
  <h1><?= h($c['name']) ?></h1>
  <p class="muted" style="margin-top:0">Abonnement mensuel, sans engagement · licence <?= $lic['paid_until'] ? 'valable jusqu\'au ' . date('d/m/Y', strtotime($lic['paid_until'])) : 'active' ?></p>
  <?php if ($error): ?><p class="err"><?= h($error) ?></p><?php endif; ?>
  <?php if ($done): ?><p class="ok"><b>Merci !</b> Votre abonnement est enregistré : les paiements se feront automatiquement chaque mois et prolongeront votre licence. La confirmation peut prendre une minute (prélèvement SEPA : quelques jours).</p><?php endif; ?>
  <table>
    <?php foreach ($lines as $l): ?><tr><td><?= h($l['label']) ?></td><td><?= $eur($l['amount']) ?> HT</td></tr><?php endforeach; ?>
    <?php if ($vat > 0): ?><tr><td class="muted">TVA <?= h(rtrim(rtrim(number_format($vat, 2, ',', ''), '0'), ',')) ?> %</td><td class="muted"><?= $eur((int)round($ht * $vat / 100)) ?></td></tr><?php endif; ?>
    <tr class="total"><td>Total par mois</td><td><?= $eur((int)round($ht * (1 + $vat / 100))) ?> TTC</td></tr>
  </table>
  <?php if (hub_billing_active($c)): ?>
    <p>Paiements automatiques <b><?= h(hub_billing_status_label((string)$c['billing_status'])) ?></b><?= $c['billing_method'] ? ' (' . ($c['billing_method'] === 'sepa_debit' ? 'prélèvement SEPA' : 'carte bancaire') . ')' : '' ?><?= $c['billing_next'] ? ', prochaine échéance le ' . date('d/m/Y', strtotime($c['billing_next'])) : '' ?>.</p>
    <form method="post"><button class="btn">Gérer mon abonnement · moyen de paiement et factures</button></form>
  <?php else: ?>
    <?php if ($lic['paid_until'] && strtotime($lic['paid_until']) > time() + 2 * 86400): ?><p class="muted" style="font-size:.9rem">La période déjà réglée est conservée : le premier paiement aura lieu le <?= date('d/m/Y', strtotime($lic['paid_until'])) ?>.</p><?php endif; ?>
    <form method="post"><button class="btn">Mettre en place le paiement automatique</button></form>
    <div class="methods"><span>Carte bancaire</span><span>Prélèvement SEPA</span></div>
  <?php endif; ?>
  <p class="muted" style="font-size:.8rem;margin-bottom:0">Paiement sécurisé par Stripe : <?= h((string)hcfg('operator_name')) ?> n'a jamais accès à vos numéros de carte ni à votre IBAN. Factures disponibles dans l'espace de gestion.</p>
<?php endif; ?>
</div>
</body>
</html>
