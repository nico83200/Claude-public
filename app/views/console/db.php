<?php
/** Console : base de données commune à tous les clients (tables préfixées par client) et transfert des clients existants. */
$sd = platform_shared_db();
?>
<h1>Base de données commune</h1>
<p class="muted">Tous les clients partagent une seule base MySQL / MariaDB : chacun y a ses propres tables, préfixées par son identifiant (<code>imss_users</code>, <code>pins_users</code>…). Les données d'un client ne sont jamais lues ni écrites avec celles d'un autre ; sa sauvegarde, son export et sa suppression ne concernent que ses tables.</p>
<form method="post" class="card" autocomplete="off"><?= csrf_field() ?><input type="hidden" name="action" value="shared_db">
  <h2>Accès à la base commune <?= $sd ? '<span class="tag green">configurée</span>' : '<span class="tag amber">à configurer</span>' ?></h2>
  <div class="grid2">
    <div><label>Serveur</label><input name="host" value="<?= e($sd['host'] ?? 'localhost') ?>"></div>
    <div><label>Port</label><input name="port" value="<?= e((string)($sd['port'] ?? 3306)) ?>"></div>
    <div><label>Nom de la base</label><input name="name" required value="<?= e($sd['name'] ?? '') ?>" placeholder="ex : centriva"></div>
    <div><label>Utilisateur</label><input name="user" value="<?= e($sd['user'] ?? '') ?>"></div>
    <div><label>Mot de passe</label><input type="password" name="pass" autocomplete="new-password" placeholder="<?= $sd ? 'enregistré — saisir pour remplacer' : '' ?>"></div>
  </div>
  <p class="muted"><small>Créez la base vide chez l'hébergeur (une seule pour tous les clients), puis enregistrez : la connexion est testée. Les nouveaux clients y sont ensuite créés par défaut.</small></p>
  <p><button class="btn primary">Tester et enregistrer</button></p>
</form>
<div class="card scroll">
  <h2>Où sont les données de chaque client</h2>
  <table class="list">
    <tr><th>Client</th><th>Stockage</th><th></th></tr>
    <?php foreach ($registry as $slug => $i): $cf = instance_paths((string)$slug)['config']; $c = is_file($cf) ? (require $cf) : []; $db = $c['db'] ?? []; ?>
      <tr>
        <td><b><?= e($i['name']) ?></b><br><small class="muted"><?= e($slug) ?></small></td>
        <td><?php if (!empty($db['prefix'])): ?><span class="tag green">base commune</span> <small class="muted">tables <?= e($db['prefix']) ?>…</small>
          <?php elseif (($db['driver'] ?? '') === 'sqlite'): ?><span class="tag">SQLite</span> <small class="muted">fichier propre au client</small>
          <?php else: ?><span class="tag">MySQL dédiée</span> <small class="muted"><?= e(($db['name'] ?? '') . ' sur ' . ($db['host'] ?? '')) ?></small><?php endif; ?></td>
        <td><?php if ($sd && empty($db['prefix'])): ?>
          <form method="post" onsubmit="this.querySelector('button').textContent='Transfert en cours…';return confirm('Copier toutes les données de ce client dans la base commune, vérifier, puis l\'y basculer ? Son ancienne base est conservée telle quelle.')"><?= csrf_field() ?><input type="hidden" name="action" value="db_move"><input type="hidden" name="slug" value="<?= e($slug) ?>"><button class="btn sm">Transférer dans la base commune</button></form>
        <?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
</div>
