<?php
/** Accès au chat : une clé par installation ou espace client. Les clients des applications ayant leur propre console (Centriva) y sont créés automatiquement. */
defined('HUB') || exit;

$clients = hall('SELECT * FROM clients ORDER BY active DESC, app, name');
$apiUrl = preg_replace('/index\.php$/', 'api.php', hub_base_url());
$local = hub_apps_local();
?>
<main class="wrap">
  <?= $flashHtml ?>
  <h1>Accès au chat</h1>
  <p class="muted">Chaque application cliente se connecte au chat avec sa propre clé. Les espaces Centriva reçoivent la leur automatiquement depuis la console de la plateforme ; les licences, abonnements et paiements ne sont plus gérés ici.</p>

  <?php if ($nk = $_SESSION['new_key'] ?? null): unset($_SESSION['new_key']); ?>
    <div class="card keybox"><b><?= !empty($nk['rotated']) ? 'Nouvelle clé' : 'Clé' ?> de « <?= h($nk['name']) ?> »</b> (affichée une seule fois) :
      <pre>'support_hub_url' => '<?= h($apiUrl) ?>',
'support_hub_key' => '<?= h($nk['key']) ?>',</pre>
      <small class="muted"><?= !empty($nk['rotated']) ? 'L\'ancienne clé reste acceptée 14 jours.' : 'À coller dans la configuration de l\'application (kit NLapps).' ?></small></div>
  <?php endif; ?>

  <?php if ($local): ?>
  <details class="card edit">
    <summary><b>＋ Nouvel accès</b> <small class="muted">— pour une application sans console de gestion</small></summary>
    <form method="post">
      <?= csrf_input() ?><input type="hidden" name="action" value="client_add">
      <div class="grid2">
        <div><label>Nom du client</label><input name="name" required></div>
        <div><label>Application</label><select name="app"><?php foreach ($local as $a): ?><option value="<?= h($a['slug']) ?>"><?= h($a['name']) ?></option><?php endforeach; ?></select></div>
        <div><label>Adresse du site</label><input name="site" placeholder="https://…"></div>
      </div>
      <button class="btn primary">Créer la clé</button>
    </form>
  </details>
  <?php endif; ?>

  <div class="card table-wrap" style="padding:.4rem .8rem">
    <table class="cards">
      <tr><th>Client</th><th>Application</th><th>Dernière activité</th><th></th></tr>
      <?php foreach ($clients as $c): $console = hub_app_console((string)$c['app']); ?>
        <tr class="<?= $c['active'] ? '' : 'dim' ?>">
          <td><b><?= h($c['name']) ?></b><br><small class="muted">Clé <code><?= h($c['key_hint']) ?></code><?= $c['console_slug'] ? ' · espace <code>' . h($c['console_slug']) . '</code>' : '' ?></small></td>
          <td><?= h(hub_app_name((string)$c['app'])) ?><?= $console ? '<br><small class="muted">géré par sa console</small>' : '' ?></td>
          <td><small class="muted"><?= $c['last_seen'] ? date('d/m/Y H:i', strtotime($c['last_seen'])) : 'jamais' ?></small></td>
          <td><?php if (!$console): ?><div class="row">
            <form method="post" onsubmit="return confirm('Générer une nouvelle clé ? L\'ancienne restera acceptée 14 jours.')"><?= csrf_input() ?><input type="hidden" name="action" value="client_rotate"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><button class="btn sm">Nouvelle clé</button></form>
            <form method="post"><?= csrf_input() ?><input type="hidden" name="action" value="client_toggle"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><button class="btn sm <?= $c['active'] ? 'danger' : '' ?>"><?= $c['active'] ? 'Désactiver' : 'Réactiver' ?></button></form>
          </div><?php else: ?><span class="tag <?= $c['active'] ? 'green' : '' ?>"><?= $c['active'] ? 'actif' : 'désactivé' ?></span><?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$clients): ?><tr><td colspan="4" class="muted">Aucun accès pour l'instant.</td></tr><?php endif; ?>
    </table>
  </div>
</main>
