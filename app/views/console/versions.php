<?php
/** Console : installation d'une nouvelle version pour tous les clients et historique des versions. */
$releases = platform_releases();
?>
<h1>Versions</h1>
<div class="card">
  <h2>Installer une nouvelle version</h2>
  <p>Version en service : <strong><?= e(APP_VERSION) ?></strong>. Le code est commun : une mise à jour s'applique à tous les espaces en une fois.</p>
  <ol class="muted"><li>Sauvegarde de la base de chaque client (dans son espace) et du code.</li><li>Remplacement des fichiers ; en cas d'échec, l'ancienne version est remise automatiquement.</li><li>Migration de la base de chaque client, puis la version et ses notes rejoignent l'historique ci-dessous.</li></ol>
  <form method="post" enctype="multipart/form-data" class="row"><?= csrf_field() ?><input type="hidden" name="action" value="update">
    <input type="file" name="package" accept=".zip" required style="max-width:340px"><button class="btn primary">Installer pour tous les clients</button></form>
</div>
<div class="card">
  <h2>Historique</h2>
  <?php foreach ($releases as $r): ?>
    <div style="padding:.6rem 0;border-bottom:1px solid var(--border)">
      <div class="row" style="justify-content:space-between">
        <div><b>v<?= e($r['version']) ?></b> <?= $r['version'] === APP_VERSION ? '<span class="tag green">en service</span>' : '' ?>
          <small class="muted"><?= !empty($r['date']) ? e(date('d/m/Y', strtotime((string)$r['date']))) : '' ?><?= !empty($r['installed_at']) ? ' · installée le ' . e(date('d/m/Y H:i', strtotime((string)$r['installed_at']))) . (!empty($r['installed_by']) ? ' par ' . e($r['installed_by']) : '') : '' ?><?= ($r['source'] ?? '') === 'assistance' ? ' · reprise du centre d\'assistance' : '' ?></small></div>
        <?php if (!empty($r['file']) && is_file(platform_releases_dir() . '/' . basename((string)$r['file']))): ?><a class="btn sm" href="console.php?p=versions&amp;dl=<?= e(rawurlencode($r['version'])) ?>">Paquet (<?= ($sz = (int)($r['size'] ?? 0)) >= 1048576 ? round($sz / 1048576, 1) . ' Mo' : max(1, round($sz / 1024)) . ' Ko' ?>)</a><?php endif; ?>
      </div>
      <?php if (!empty($r['notes'])): ?><details><summary class="muted">Notes de version</summary><pre class="notes"><?= e($r['notes']) ?></pre></details><?php endif; ?>
      <details><summary class="muted">Modifier les notes</summary>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="release_notes"><input type="hidden" name="version" value="<?= e($r['version']) ?>">
          <textarea name="notes" rows="5"><?= e($r['notes'] ?? '') ?></textarea><p><button class="btn sm">Enregistrer</button></p></form></details>
    </div>
  <?php endforeach; ?>
  <?php if (!$releases): ?><p class="muted">Aucune version enregistrée. L'historique du centre d'assistance se reprend depuis le menu Assistance.</p><?php endif; ?>
</div>
<div class="card">
  <h2>Bases des clients</h2>
  <p class="muted"><small>Si un espace affiche une version de base différente du code (après un incident), relancez la migration.</small></p>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="migrate"><button class="btn">Migrer toutes les bases en v<?= e(APP_VERSION) ?></button></form>
</div>
<div class="card">
  <h2>Tâches planifiées</h2>
  <p class="muted"><small>Programmez chez l'hébergeur, toutes les 5 à 15 minutes : <code>php <?= e(ROOT) ?>/cron.php</code> — il traite chaque client à son tour (e-mails, rappels, sauvegardes…).</small></p>
  <form method="post" class="row"><?= csrf_field() ?><input type="hidden" name="action" value="cron"><label class="check" style="margin:0"><input type="checkbox" name="force" value="1"> tout forcer</label><button class="btn">Lancer maintenant pour tous</button></form>
</div>
