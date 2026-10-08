<?php
/** Versions publiées : les installations clientes les proposent en un clic à leur administrateur. */
defined('HUB') || exit;

$releases = hall('SELECT r.*, (SELECT COUNT(*) FROM clients c WHERE c.app = r.app AND c.app_version = r.version) AS installs FROM releases r WHERE r.app = ? ORDER BY r.created_at DESC', [$app['slug']]);
usort($releases, fn($a, $b) => strcmp($a['app'], $b['app']) ?: version_compare($b['version'], $a['version']));
$maxUpload = ini_get('upload_max_filesize');
?>
<main class="wrap">
  <?= $flashHtml ?>
  <h1><span class="dot" style="background:<?= h($app['color']) ?>;width:14px;height:14px"></span> Versions · <?= h($app['name']) ?></h1>
  <form method="post" enctype="multipart/form-data" class="card">
    <?= csrf_input() ?><input type="hidden" name="action" value="release_upload">
    <h2>Publier une nouvelle version</h2>
    <p class="muted" style="margin-top:0">Déposez le paquet de mise à jour (ex : <code><?= h($app['slug']) ?>-1.12.0.zip</code>). La version et les notes sont lues dans le paquet. Les installations à jour de leur licence la proposeront aussitôt à leur administrateur, qui l'installe en un clic (sauvegarde automatique et retour arrière possible).</p>
    <div class="row">
      <input type="file" name="package" accept=".zip,application/zip" required>
      <input type="hidden" name="app" value="<?= h($app['slug']) ?>">
      <label class="check" style="margin:0"><input type="checkbox" name="publish" value="1" checked> publier tout de suite</label>
      <button class="btn primary">Envoyer</button>
    </div>
    <small class="muted">Taille maximale acceptée par le serveur : <?= h((string)$maxUpload) ?>.</small>
  </form>
  <div class="card table-wrap" style="padding:.4rem .8rem">
    <table class="cards">
      <tr><th>Version</th><th>Notes</th><th class="hide-sm">Installée chez</th><th></th></tr>
      <?php foreach ($releases as $r): ?>
        <tr class="<?= $r['published'] ? '' : 'dim' ?>">
          <td><b><?= h($r['version']) ?></b><br><small class="muted"><?= date('d/m/Y', strtotime($r['created_at'])) ?> · <?= round($r['size'] / 1024) ?> Ko</small><br><?= $r['published'] ? '<span class="tag green">publiée</span>' : '<span class="tag">brouillon</span>' ?></td>
          <td><details><summary><small><?= h(mb_substr(strtok((string)$r['notes'], "\n") ?: '—', 0, 110)) ?></small></summary><pre style="white-space:pre-wrap;font-size:.82rem"><?= h((string)$r['notes']) ?></pre></details></td>
          <td class="hide-sm"><?= (int)$r['installs'] ?> client(s)</td>
          <td class="row" style="flex-wrap:nowrap">
            <form method="post"><?= csrf_input() ?><input type="hidden" name="action" value="release_toggle"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn sm"><?= $r['published'] ? 'Retirer' : 'Publier' ?></button></form>
            <form method="post" onsubmit="return confirm('Supprimer définitivement cette version ?')"><?= csrf_input() ?><input type="hidden" name="action" value="release_delete"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn sm danger">Supprimer</button></form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$releases): ?><tr><td colspan="4" class="muted">Aucune version publiée.</td></tr><?php endif; ?>
    </table>
  </div>
</main>
