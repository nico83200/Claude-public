<div class="page-head">
  <div><h1>Export et import</h1><p>Transférez l'intégralité de ce Centriva vers une autre installation : centres, comptes, catalogue, fournisseurs, demandes, commandes, stock, contrats, budgets, réglages et fichiers.</p></div>
</div>

<?php if ($pending): $m = $pending['manifest']; ?>
<form method="post" class="card mb-2" style="border:2px solid var(--amber, #f59e0b)" data-busy="Import en cours… (ne fermez pas la page)">
  <?= csrf_field() ?><input type="hidden" name="action" value="import">
  <div class="card-head"><h2><?= icon('alert') ?> Confirmer l'import</h2></div>
  <div class="card-body">
    <p style="margin-top:0">Archive de <strong><?= e($m['name'] ?? '?') ?></strong>, exportée le <?= e(date_fr((string)($m['exported_at'] ?? ''), true)) ?> (Centriva <?= e($m['app_version'] ?? '?') ?>)<?= !empty($m['files']) ? ', avec ' . (int)$m['files'] . ' fichier(s)' : ', sans fichiers' ?>.</p>
    <div class="table-wrap"><table class="table">
      <thead><tr><th></th><th class="num">Archive (à importer)</th><th class="num">Ce Centriva (remplacé)</th></tr></thead>
      <tbody><?php foreach ($counts as $k => $v): ?><tr><td><?= e(ucfirst($k)) ?></td><td class="num strong"><?= (int)($m['counts'][$k] ?? 0) ?></td><td class="num muted"><?= (int)$v ?></td></tr><?php endforeach; ?></tbody>
    </table></div>
    <div class="flash flash-error mt-1"><?= icon('alert') ?><div><strong>Toutes les données de ce Centriva seront remplacées</strong> par celles de l'archive<?= !empty($m['files']) ? ', photos, logo, factures et contrats compris' : '' ?>. Restent celles de cette installation : licence et accès au centre d'assistance NLapps, tutoriels vidéo, historique des mises à jour. Une sauvegarde de la base actuelle est faite juste avant. Vous serez ensuite déconnecté : reconnectez-vous avec un compte de la base importée.</div></div>
    <div class="form-grid mt-1">
      <div class="field"><label>Saisissez <strong>REMPLACER</strong></label><input type="text" name="confirm_word" required autocomplete="off"></div>
      <div class="field"><label>Votre mot de passe</label><input type="password" name="admin_password" required autocomplete="current-password"></div>
    </div>
  </div>
  <div class="card-foot row"><button class="btn btn-danger" type="submit"><?= icon('download', 18) ?> Remplacer les données par l'archive</button>
    <button class="btn" type="submit" form="transfer-cancel">Annuler</button></div>
</form>
<form method="post" id="transfer-cancel"><?= csrf_field() ?><input type="hidden" name="action" value="cancel"></form>
<?php endif; ?>

<div class="grid grid-2">
  <form method="post" class="card" autocomplete="off">
    <?= csrf_field() ?><input type="hidden" name="action" value="export">
    <div class="card-head"><h2><?= icon('download') ?> Exporter</h2></div>
    <div class="card-body">
      <div class="chips mb-2"><?php foreach ($counts as $k => $v): ?><span class="badge badge-gray"><?= (int)$v ?> <?= e($k) ?></span><?php endforeach; ?></div>
      <p class="muted" style="margin-top:0">L'archive contient des données personnelles (comptes, mots de passe chiffrés…) : elle est <strong>chiffrée</strong> par le mot de passe choisi ci-dessous, qui sera demandé à l'import. Conservez-le à part.</p>
      <div class="form-grid">
        <div class="field"><label>Mot de passe de l'archive</label><input type="password" name="password" minlength="10" required autocomplete="new-password"><small>10 caractères minimum.</small></div>
        <div class="field"><label>Confirmation</label><input type="password" name="confirm" minlength="10" required autocomplete="new-password"></div>
      </div>
      <label class="check"><input type="checkbox" name="files" value="1" checked> Inclure les fichiers : photos des articles, logo, factures et contrats (<?= e(number_format($filesSize / 1048576, 1, ',', ' ')) ?> Mo)</label>
    </div>
    <div class="card-foot"><button class="btn btn-primary" type="submit"><?= icon('download', 18) ?> Télécharger l'export</button></div>
  </form>

  <form method="post" class="card" enctype="multipart/form-data" autocomplete="off" data-busy="Vérification de l'archive…">
    <?= csrf_field() ?><input type="hidden" name="action" value="upload">
    <div class="card-head"><h2><?= icon('refresh') ?> Importer</h2></div>
    <div class="card-body">
      <p class="muted" style="margin-top:0">Choisissez l'archive exportée d'un autre Centriva (même version ou plus ancienne). Rien n'est modifié avant votre confirmation, après un aperçu de son contenu.</p>
      <div class="field"><label>Archive d'export (.zip)</label><input type="file" name="archive" accept=".zip,application/zip" required>
        <small>Taille maximale acceptée par le serveur : <?= round($limit / 1048576) ?> Mo.</small></div>
      <div class="field"><label>Mot de passe de l'archive</label><input type="password" name="password" required autocomplete="off"></div>
    </div>
    <div class="card-foot"><button class="btn" type="submit"><?= icon('search', 18) ?> Vérifier l'archive</button></div>
  </form>
</div>
