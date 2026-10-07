<?php
/** Mise à jour du centre d'assistance par paquet ZIP (comme dans Approvia) : sauvegarde automatique et retour arrière. */
defined('HUB') || exit;

$last = json_decode((string)hsetting('last_update', ''), true);
$backups = hub_backups();
?>
<main class="wrap">
  <?= $flashHtml ?>
  <h1>Mise à jour du centre d'assistance</h1>
  <div class="stats">
    <div class="stat"><b>v<?= h(hub_version()) ?></b><span>version installée</span></div>
    <div class="stat"><b><?= $last ? date('d/m/Y', strtotime($last['at'])) : '—' ?></b><span>dernière mise à jour<?= $last ? ' (' . h($last['from']) . ' → ' . h($last['to']) . ')' : '' ?></span></div>
    <div class="stat"><b>PHP <?= h(PHP_VERSION) ?></b><span><?= class_exists(ZipArchive::class) ? 'extension zip présente' : 'extension zip absente' ?></span></div>
  </div>
  <form method="post" enctype="multipart/form-data" class="card">
    <?= csrf_input() ?><input type="hidden" name="action" value="hub_update">
    <h2>Installer une nouvelle version</h2>
    <p class="muted" style="margin-top:0">Déposez le paquet <code>nlapps-assistance.zip</code>. Seuls les fichiers du logiciel sont remplacés : <b>config.php</b> et le dossier <b>data/</b> (conversations, clients, licences, versions publiées, images) ne sont jamais touchés. Une sauvegarde est faite avant ; en cas d'échec, l'ancienne version est restaurée automatiquement.</p>
    <div class="grid2">
      <div><label>Paquet (.zip)</label><input type="file" name="package" accept=".zip,application/zip" required></div>
      <div><label>Votre mot de passe (confirmation)</label><input type="password" name="password" required autocomplete="current-password"></div>
    </div>
    <label class="check"><input type="checkbox" name="allow_same" value="1"> Réinstaller même si la version n'est pas plus récente</label>
    <button class="btn primary">Mettre à jour</button>
    <p class="muted"><small>Taille maximale acceptée par le serveur : <?= h((string)ini_get('upload_max_filesize')) ?>. Si le paquet complet (avec vendor/) est trop lourd, utilisez le paquet allégé <code>nlapps-assistance-maj.zip</code>.</small></p>
  </form>
  <div class="card table-wrap" style="padding:.6rem .9rem">
    <h2>Sauvegardes (5 dernières)</h2>
    <table>
      <tr><th>Date</th><th>Version</th><th class="hide-sm">Motif</th><th></th></tr>
      <?php foreach ($backups as $bk): ?>
        <tr><td><?= date('d/m/Y H:i', strtotime($bk['at'])) ?><br><small class="muted"><?= round($bk['size'] / 1024) ?> Ko</small></td><td>v<?= h($bk['version']) ?></td><td class="hide-sm"><small><?= h($bk['reason']) ?></small></td>
          <td><details class="edit"><summary class="btn sm">Restaurer</summary>
            <form method="post" class="row" style="margin-top:.4rem"><?= csrf_input() ?><input type="hidden" name="action" value="hub_rollback"><input type="hidden" name="backup" value="<?= h($bk['file']) ?>">
              <input type="password" name="password" placeholder="Mot de passe" required style="min-width:140px"><button class="btn sm danger" onclick="return confirm('Revenir à la version <?= h($bk['version']) ?> ?')">Confirmer</button></form></details></td></tr>
      <?php endforeach; ?>
      <?php if (!$backups): ?><tr><td colspan="4" class="muted">Aucune sauvegarde pour l'instant (une est créée à chaque mise à jour).</td></tr><?php endif; ?>
    </table>
  </div>
</main>
