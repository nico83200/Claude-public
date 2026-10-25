<section class="card">
  <div class="card-head">
    <h2>Version installée : <?= e($version) ?></h2>
  </div>
  <?php if (!$zipAvailable): ?>
    <div class="alert alert-error">L'extension PHP « zip » n'est pas active : activez-la dans hPanel › Avancé › Configuration PHP › Extensions.</div>
  <?php endif; ?>
  <p>Déposez le fichier ZIP d'une nouvelle version. Avant toute modification, le site :</p>
  <ul>
    <li>vérifie que le ZIP est bien une version de la boutique, sans chemin dangereux ni erreur de syntaxe PHP ;</li>
    <li>sauvegarde les fichiers qui vont être remplacés (5 dernières sauvegardes conservées) ;</li>
    <li>ne touche jamais à votre configuration (<code>config/config.php</code>), à vos données (<code>storage/</code>) ni à vos images (<code>public/uploads/</code>) ;</li>
    <li>met ensuite la base de données à jour si la version l'exige.</li>
  </ul>
  <form method="post" action="<?= e(url('admin/mise-a-jour')) ?>" enctype="multipart/form-data" data-confirm="Installer cette version ? Le site sera indisponible quelques secondes.">
    <?= csrf_field() ?>
    <label>Fichier ZIP de la nouvelle version (max. <?= e($maxUpload) ?>)
      <input type="file" name="package" accept=".zip,application/zip" required></label>
    <details class="mb"><summary class="small muted">Options avancées</summary>
      <label class="checkbox mt"><input type="checkbox" name="downgrade" value="1"> Autoriser l'installation d'une version plus ancienne</label>
    </details>
    <button class="btn btn-primary" <?= $zipAvailable ? '' : 'disabled' ?>>Installer la mise à jour</button>
  </form>
  <p class="muted small mt">Conseil : avant une mise à jour importante, faites aussi une sauvegarde de la base dans hPanel › Bases de données › phpMyAdmin › Exporter (ou utilisez les sauvegardes automatiques Hostinger).</p>
</section>

<?php if ($pending || $migrationError): ?>
<section class="card">
  <h2>Base de données</h2>
  <?php if ($migrationError): ?><div class="alert alert-error"><?= e($migrationError) ?></div><?php endif; ?>
  <?php if ($pending): ?>
    <p><?= count($pending) ?> migration(s) en attente : <code><?= e(implode(', ', $pending)) ?></code></p>
    <form method="post" action="<?= e(url('admin/mise-a-jour/migrations')) ?>"><?= csrf_field() ?><button class="btn btn-primary">Appliquer les migrations</button></form>
  <?php endif; ?>
</section>
<?php endif; ?>

<section class="card" id="base">
  <div class="card-head">
    <h2>Sauvegardes de la base de données</h2>
    <form method="post" action="<?= e(url('admin/mise-a-jour/sauvegarde-base')) ?>"><?= csrf_field() ?><button class="btn">Sauvegarder maintenant</button></form>
  </div>
  <p class="muted small">Commandes, clientes, produits, réglages… Une copie est faite automatiquement avant chaque mise à jour et chaque semaine ; les 8 dernières sont conservées. Fichier .sql.gz à importer dans phpMyAdmin (Hostinger) en cas de besoin. Il contient vos clés d'intégration : gardez-le en lieu sûr.</p>
  <?php if (!$dbBackups): ?><p class="muted">Aucune sauvegarde pour l'instant.</p><?php endif; ?>
  <table class="table">
    <?php foreach ($dbBackups as $b): ?>
      <tr>
        <td><code><?= e($b['name']) ?></code></td>
        <td><?= e(date('d/m/Y à H:i', $b['date'])) ?></td>
        <td><?= e(number_format($b['size'] / 1024, 0, ',', ' ')) ?> Ko</td>
        <td class="right"><a class="btn" href="<?= e(url('admin/mise-a-jour/sauvegarde-base/' . rawurlencode($b['name']))) ?>">Télécharger</a></td>
      </tr>
    <?php endforeach; ?>
  </table>
</section>

<section class="card">
  <h2>Sauvegardes des fichiers</h2>
  <?php if (!$backups): ?><p class="muted">Aucune sauvegarde : elles sont créées automatiquement à chaque mise à jour.</p><?php endif; ?>
  <table class="table">
    <?php foreach ($backups as $b): ?>
      <tr>
        <td><code><?= e($b['name']) ?></code></td>
        <td><?= e(date('d/m/Y à H:i', $b['date'])) ?></td>
        <td><?= e(number_format($b['size'] / 1024, 0, ',', ' ')) ?> Ko</td>
        <td class="right">
          <form method="post" action="<?= e(url('admin/mise-a-jour/restaurer/' . rawurlencode($b['name']))) ?>" data-confirm="Restaurer les fichiers de cette sauvegarde ? Les modifications de la base de données ne sont pas annulées.">
            <?= csrf_field() ?><button class="btn">Restaurer</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>
</section>
