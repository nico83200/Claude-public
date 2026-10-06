<div class="page-head">
  <div><h1>Mises à jour</h1><p>Version installée : <span class="version-pill">v<?= e(APP_VERSION) ?></span></p></div>
  <form method="post" action="<?= url('admin/updates/backup') ?>" class="row"><?= csrf_field() ?>
    <label class="check mb-0" style="font-size:.85rem"><input type="checkbox" name="vendor" value="1"> inclure vendor/</label>
    <button class="btn" type="submit"><?= icon('archive', 18) ?> Créer une sauvegarde</button>
  </form>
</div>
<?php if (!$zipOk || !$writable): ?>
  <div class="flash flash-error"><?= icon('alert') ?><div><?= !$zipOk ? 'L\'extension PHP « zip » est absente : demandez son activation à votre hébergeur. ' : '' ?><?= !$writable ? 'Les dossiers app/ et storage/ doivent être accessibles en écriture par PHP pour appliquer une mise à jour.' : '' ?></div></div>
<?php endif; ?>

<div class="grid grid-main">
  <div class="stack">
    <?php if ($preview): ?>
    <div class="card" style="border:2px solid var(--primary)">
      <div class="card-head"><h2><?= icon('sparkles') ?> Paquet prêt : <span class="version-pill">v<?= e($preview['version']) ?></span></h2><small class="muted"><?= e($preview['name']) ?></small></div>
      <div class="card-body">
        <?php if (!$preview['newer']): ?><div class="flash flash-error"><?= icon('alert') ?><div>Cette version n'est pas plus récente que la version installée (v<?= e(APP_VERSION) ?>).</div></div><?php endif; ?>
        <p><strong><?= (int)$preview['count'] ?></strong> fichiers à installer<?= $preview['vendor'] ? ' (dépendances vendor/ incluses)' : '' ?><?= $preview['date'] ? ' · publié le ' . e($preview['date']) : '' ?>.</p>
        <?php if ($preview['notes']): ?><div class="card card-body mb-2" style="background:var(--surface-2)"><strong>Notes de version</strong><div style="white-space:pre-line;font-size:.9rem"><?= e($preview['notes']) ?></div></div><?php endif; ?>
        <?php if ($preview['skipped']): ?><small class="muted">Fichiers ignorés (protégés : configuration, photos, données) : <?= e(implode(', ', $preview['skipped'])) ?></small><?php endif; ?>
        <form method="post" action="<?= url('admin/updates/upload') ?>" class="mt-2">
          <?= csrf_field() ?><input type="hidden" name="step" value="apply">
          <ol style="margin:0 0 1rem;padding-left:1.2rem;color:var(--muted);font-size:.9rem">
            <li>Sauvegarde automatique du code et de la base de données actuels.</li>
            <li>Mise en maintenance de quelques secondes et installation des fichiers.</li>
            <li>Mise à jour automatique de la base au premier chargement.</li>
          </ol>
          <?php if (!$preview['newer']): ?><label class="check"><input type="checkbox" name="allow_downgrade" value="1"> Installer quand même (réinstallation / version antérieure)</label><?php endif; ?>
          <div class="field"><label>Confirmez avec votre mot de passe</label><input type="password" name="password" required autocomplete="current-password"></div>
          <div class="row"><button class="btn btn-primary" type="submit"><?= icon('refresh', 18) ?> Installer la version <?= e($preview['version']) ?></button>
          <button class="btn btn-ghost" type="submit" name="step" value="cancel" formnovalidate>Annuler</button></div>
        </form>
      </div>
    </div>
    <?php endif; ?>

    <form method="post" action="<?= url('admin/updates/upload') ?>" enctype="multipart/form-data" class="card">
      <?= csrf_field() ?><input type="hidden" name="step" value="upload">
      <div class="card-head"><h2><?= icon('upload') ?> Installer une mise à jour</h2></div>
      <div class="card-body">
        <p class="muted">Chargez le fichier <code>.zip</code> de mise à jour fourni. Il est d'abord analysé ; rien n'est modifié avant votre confirmation. La configuration (<code>config.php</code>), les photos et les données ne sont jamais touchées.</p>
        <div class="field"><input type="file" name="package" accept=".zip,application/zip" required><small>Taille maximale acceptée par le serveur : <?= round($maxUpload / 1048576, 1) ?> Mo.</small></div>
        <button class="btn btn-primary" type="submit" <?= $zipOk ? '' : 'disabled' ?>><?= icon('search', 18) ?> Analyser le paquet</button>
      </div>
    </form>

    <div class="card">
      <div class="card-head"><h2><?= icon('archive') ?> Sauvegardes &amp; retour arrière</h2></div>
      <?php if ($backups): ?>
      <div class="table-wrap"><table class="table">
        <thead><tr><th>Version</th><th>Date</th><th>Motif</th><th class="num">Taille</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($backups as $b): ?>
          <tr>
            <td><span class="version-pill">v<?= e($b['version'] ?? '?') ?></span></td>
            <td class="nowrap"><?= date_fr($b['created_at'] ?? date('Y-m-d H:i:s', $b['mtime']), true) ?></td>
            <td><small><?= e($b['reason'] ?? '') ?><?= !empty($b['by']) ? ' · ' . e($b['by']) : '' ?></small></td>
            <td class="num"><small><?= round($b['size'] / 1048576, 1) ?> Mo</small></td>
            <td class="nowrap text-right">
              <button class="btn btn-sm btn-amber" type="button" data-rollback="<?= e($b['file']) ?>" data-version="<?= e($b['version'] ?? '?') ?>"><?= icon('repeat', 15) ?> Restaurer</button>
              <a class="btn btn-sm btn-ghost btn-icon" href="<?= url('admin/updates/download', ['file' => $b['file']]) ?>" title="Télécharger"><?= icon('download', 15) ?></a>
              <form method="post" action="<?= url('admin/updates/delete') ?>" style="display:inline" onsubmit="return confirm('Supprimer définitivement cette sauvegarde ?')"><?= csrf_field() ?><input type="hidden" name="file" value="<?= e($b['file']) ?>"><button class="btn btn-sm btn-ghost btn-icon btn-danger" type="submit" title="Supprimer"><?= icon('trash', 15) ?></button></form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
      <?php else: ?><div class="empty"><?= icon('archive') ?><p>Aucune sauvegarde. Une sauvegarde est créée automatiquement avant chaque mise à jour.</p></div><?php endif; ?>
    </div>

    <form method="post" action="<?= url('admin/updates/rollback') ?>" class="card hidden" id="rollback-form">
      <?= csrf_field() ?><input type="hidden" name="file" value="">
      <div class="card-head"><h2><?= icon('repeat') ?> Revenir à la version <span class="version-pill" data-rb-version></span></h2></div>
      <div class="card-body">
        <p>Le code de l'application sera remplacé par celui de la sauvegarde. L'état actuel est lui-même sauvegardé avant l'opération, vous pourrez donc annuler ce retour arrière.</p>
        <label class="check"><input type="checkbox" name="restore_db" value="1"> Restaurer aussi la base de données de cette date</label>
        <small class="muted" style="display:block;margin:-.2rem 0 1rem 1.7rem">⚠️ Les demandes, commandes et inventaires saisis depuis seront perdus. À ne cocher qu'en cas de problème de données : le code d'une version antérieure fonctionne avec la base actuelle.</small>
        <div class="field"><label>Confirmez avec votre mot de passe</label><input type="password" name="password" required autocomplete="current-password"></div>
        <div class="row"><button class="btn btn-amber" type="submit"><?= icon('repeat', 18) ?> Confirmer le retour arrière</button><button class="btn btn-ghost" type="button" onclick="this.closest('form').classList.add('hidden')">Annuler</button></div>
      </div>
    </form>
  </div>

  <div class="stack">
    <div class="card">
      <div class="card-head"><h3><?= icon('clock', 18) ?> Historique</h3></div>
      <?php if ($history): ?><ul class="timeline">
        <?php foreach ($history as $h): ?>
          <li>
            <div class="strong"><?= ['update' => 'Mise à jour', 'rollback' => 'Retour arrière', 'backup' => 'Sauvegarde'][$h['action']] ?? e($h['action']) ?> <?= $h['action'] !== 'backup' ? 'v' . e($h['from_version']) . ' → v' . e($h['to_version']) : 'v' . e($h['to_version']) ?></div>
            <?php if ($h['notes']): ?><small style="white-space:pre-line"><?= e(mb_substr($h['notes'], 0, 300)) ?></small><?php endif; ?>
            <div class="when"><?= date_fr($h['created_at'], true) ?><?= $h['first_name'] ? ' · ' . e($h['first_name'] . ' ' . $h['last_name']) : '' ?></div>
          </li>
        <?php endforeach; ?>
      </ul><?php else: ?><div class="empty"><p>Aucune opération.</p></div><?php endif; ?>
    </div>
    <div class="card card-body">
      <h3><?= icon('info', 18) ?> Bon à savoir</h3>
      <ul style="margin:.4rem 0 0;padding-left:1.1rem;color:var(--muted);font-size:.88rem">
        <li>Les mises à jour de la base sont uniquement additives (nouvelles tables ou colonnes) : revenir au code précédent ne nécessite pas de restaurer les données.</li>
        <li>Les sauvegardes sont stockées dans <code>storage/backups/</code>, inaccessible depuis le web. Téléchargez-en une copie de temps en temps.</li>
        <li>Paquet trop gros pour le serveur ? Envoyez les fichiers par FTP : la base se met à jour toute seule au chargement suivant.</li>
      </ul>
    </div>
  </div>
</div>
