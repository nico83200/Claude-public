<?php
declare(strict_types=1);

/**
 * Abonnements en ligne de la plateforme Centriva (Stripe), sans connexion :
 *  - centriva.fr/?paiement=<jeton>  page de paiement personnelle d'un client : souscription par carte ou prélèvement SEPA,
 *                                   ou espace client Stripe (moyen de paiement, factures). Aucune donnée bancaire ne transite ici.
 *  - centriva.fr/?webhook=stripe    notifications de Stripe (signature vérifiée), à déclarer dans Stripe → Développeurs → Webhooks
 *                                   avec les événements checkout.session.completed, invoice.paid, invoice.payment_failed,
 *                                   customer.subscription.updated et customer.subscription.deleted.
 */
defined('APP') || exit; // servi par la page d'accueil de la plateforme (app/bootstrap.php), sans espace client chargé

if (isset($_GET['webhook'])) {
    header('Content-Type: application/json; charset=utf-8');
    $payload = (string)file_get_contents('php://input');
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'
        || !platform_stripe_verify($payload, (string)($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? ''), trim((string)platform_setting('stripe_webhook_secret')))) {
        http_response_code(400);
        exit('{"error":"signature"}');
    }
    $event = json_decode($payload, true);
    if (!is_array($event) || empty($event['id'])) {
        http_response_code(400);
        exit('{"error":"payload"}');
    }
    try {
        $res = platform_billing_handle($event);
        platform_settings_save(['stripe_last_event' => date('Y-m-d H:i:s') . ' · ' . ($event['type'] ?? '?') . ' · ' . $res]);
        echo json_encode(['ok' => true, 'result' => $res], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        error_log('[stripe] ' . $e->getMessage());
        http_response_code(500); // Stripe renverra l'événement plus tard
        echo '{"error":"internal"}';
    }
    exit;
}

header('Cache-Control: no-store');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');

$slug = platform_slug_by_token((string)($_GET['paiement'] ?? ''));
$operator = (string)platform_setting('operator_name');
$contact = (string)platform_setting('operator_email');
$error = null;
if ($slug && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        header('Location: ' . platform_billing_session_url($slug), true, 303);
        exit;
    } catch (Throwable $e) {
        error_log('[paiement] ' . $e->getMessage());
        $error = 'Le paiement en ligne est momentanément indisponible. Réessayez dans quelques minutes ou contactez ' . $operator . '.';
    }
}
$c = $slug ? platform_licence_row($slug) : null;
$lic = $slug ? platform_licence($slug) : null;
$lines = $slug ? platform_billing_lines($slug) : [];
$ht = array_sum(array_column($lines, 'amount'));
$disc = $slug ? platform_discount($slug) : null;
$ht -= $slug ? platform_discount_amount($slug, $ht) : 0;
$vat = platform_vat();
$eur = fn(int $cents) => number_format($cents / 100, 2, ',', ' ') . ' €';
$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$dir = instance_web_dir();
?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title>Abonnement Centriva</title>
<link rel="icon" type="image/svg+xml" href="<?= $dir ?>/assets/brand/centriva-mark.svg">
<style>
:root { --ink:#0f172a; --muted:#64748b; --line:#e2e8f0; --bg:#f5f7fb; --card:#fff; }
@media (prefers-color-scheme: dark) { :root { --ink:#e2e8f0; --muted:#94a3b8; --line:#2a3055; --bg:#0e1122; --card:#171b33; } }
* { box-sizing:border-box; }
body { margin:0; min-height:100vh; display:grid; place-items:center; padding:1.5rem 1rem; background:var(--bg); color:var(--ink); font-family:Inter,system-ui,-apple-system,"Segoe UI",sans-serif; }
.box { width:100%; max-width:480px; background:var(--card); border:1px solid var(--line); border-radius:18px; padding:1.6rem 1.5rem; box-shadow:0 10px 40px rgba(15,23,42,.08); }
h1 { font-size:1.35rem; margin:1.1rem 0 .3rem; } p { line-height:1.5; } .muted { color:var(--muted); }
table { width:100%; border-collapse:collapse; margin:1rem 0; font-size:.95rem; } td { padding:.45rem 0; border-bottom:1px solid var(--line); } td:last-child { text-align:right; white-space:nowrap; }
tr.total td { font-weight:800; border-bottom:0; font-size:1.05rem; }
.btn { display:block; width:100%; padding:.85rem 1rem; border:0; border-radius:12px; background:linear-gradient(135deg,#0a9cf7,#2a1fc4 55%,#ff3d9a); color:#fff; font:inherit; font-weight:700; cursor:pointer; text-align:center; text-decoration:none; }
.ok { background:#ecfdf5; color:#065f46; padding:.8rem 1rem; border-radius:12px; } .err { background:#fef2f2; color:#991b1b; padding:.8rem 1rem; border-radius:12px; }
@media (prefers-color-scheme: dark) { .ok { background:#064e3b; color:#d1fae5; } .err { background:#7f1d1d; color:#fee2e2; } }
.methods { display:flex; gap:.5rem; justify-content:center; margin-top:.8rem; font-size:.82rem; color:var(--muted); }
.methods span { border:1px solid var(--line); border-radius:8px; padding:.2rem .55rem; }
</style>
</head>
<body>
<div class="box">
  <img src="<?= $dir ?>/assets/brand/centriva-logo.svg" alt="Centriva" height="36">
<?php if (!$slug): ?>
  <h1>Lien invalide</h1>
  <p class="muted">Ce lien de paiement n'existe pas ou n'est plus actif. Contactez <?= $h($operator) ?><?= $contact ? ' (' . $h($contact) . ')' : '' ?>.</p>
<?php elseif (!platform_stripe_ready()): ?>
  <h1><?= $h(instances_registry()[$slug]['name']) ?></h1>
  <p class="muted">Le paiement en ligne n'est pas encore ouvert. Contactez <?= $h($operator) ?><?= $contact ? ' (' . $h($contact) . ')' : '' ?>.</p>
<?php else: ?>
  <h1><?= $h(instances_registry()[$slug]['name']) ?></h1>
  <p class="muted" style="margin-top:0">Abonnement mensuel, sans engagement · licence <?= $lic['paid_until'] ? 'valable jusqu\'au ' . date('d/m/Y', strtotime($lic['paid_until'])) : 'active' ?></p>
  <?php if ($error): ?><p class="err"><?= $h($error) ?></p><?php endif; ?>
  <?php if (isset($_GET['done'])): ?><p class="ok"><b>Merci !</b> Votre abonnement est enregistré : les paiements se feront automatiquement chaque mois et prolongeront votre licence. La confirmation peut prendre une minute (prélèvement SEPA : quelques jours).</p><?php endif; ?>
  <table>
    <?php foreach ($lines as $l): ?><tr><td><?= $h($l['label']) ?></td><td><?= $eur($l['amount']) ?> HT</td></tr><?php endforeach; ?>
    <?php if ($disc): ?><tr><td>Réduction (code <?= $h($disc['code']) ?>) · <?= $h(platform_discount_label($disc)) ?></td><td>−<?= $eur(platform_discount_amount($slug, array_sum(array_column($lines, 'amount')))) ?> HT</td></tr><?php endif; ?>
    <?php if ($vat > 0): ?><tr><td class="muted">TVA <?= $h(rtrim(rtrim(number_format($vat, 2, ',', ''), '0'), ',')) ?> %</td><td class="muted"><?= $eur((int)round($ht * $vat / 100)) ?></td></tr><?php endif; ?>
    <tr class="total"><td>Total par mois</td><td><?= $eur((int)round($ht * (1 + $vat / 100))) ?> TTC</td></tr>
  </table>
  <?php if (platform_billing_active($slug)): ?>
    <p>Paiements automatiques <b><?= $h(platform_billing_status_label($c['billing_status'])) ?></b><?= $c['billing_method'] ? ' (' . ($c['billing_method'] === 'sepa_debit' ? 'prélèvement SEPA' : 'carte bancaire') . ')' : '' ?><?= $c['billing_next'] ? ', prochaine échéance le ' . date('d/m/Y', strtotime($c['billing_next'])) : '' ?>.</p>
    <form method="post"><button class="btn">Gérer mon abonnement · moyen de paiement et factures</button></form>
  <?php else: ?>
    <?php if ($lic['paid_until'] && strtotime($lic['paid_until']) > time() + 2 * 86400): ?><p class="muted" style="font-size:.9rem">La période déjà réglée est conservée : le premier paiement aura lieu le <?= date('d/m/Y', strtotime($lic['paid_until'])) ?>.</p><?php endif; ?>
    <form method="post"><button class="btn">Mettre en place le paiement automatique</button></form>
    <div class="methods"><span>Carte bancaire</span><span>Prélèvement SEPA</span></div>
  <?php endif; ?>
  <p class="muted" style="font-size:.8rem;margin-bottom:0">Paiement sécurisé par Stripe : <?= $h($operator) ?> n'a jamais accès à vos numéros de carte ni à votre IBAN. Factures disponibles dans l'espace de gestion.</p>
<?php endif; ?>
</div>
</body>
</html>
