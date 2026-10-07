<div class="page-head">
  <div><h1>Nettoyage des données</h1><p>Retirez les données de démonstration ou de test avant la mise en service. Une sauvegarde complète est faite automatiquement avant chaque nettoyage.</p></div>
</div>

<div class="grid grid-2">
  <div class="card">
    <div class="card-head"><h2><?= icon('sparkles') ?> Données de démonstration</h2></div>
    <div class="card-body">
      <?php if (array_sum($demo) === 0): ?>
        <div class="flash flash-success mb-0"><?= icon('check') ?><div>Aucune donnée de démonstration n'est présente.</div></div>
      <?php else: ?>
        <p>Supprime le jeu d'essai installé avec l'application, avec tout ce qui s'y rattache :</p>
        <ul class="cleanup-list">
          <?php foreach ($demo as $label => $n): ?><?php if ($n): ?><li><strong><?= (int)$n ?></strong> <?= e($label) ?></li><?php endif; ?><?php endforeach; ?>
        </ul>
        <p class="muted" style="font-size:.88rem">Les comptes de démonstration sont ceux en <code>@demo.fr</code>. Vos propres centres, fournisseurs, articles, comptes, votre logo et vos paramètres sont conservés.</p>
        <form method="post" class="mt-1">
          <?= csrf_field() ?><input type="hidden" name="action" value="demo">
          <div class="field"><label>Confirmez avec votre mot de passe</label><input type="password" name="password" required autocomplete="current-password"></div>
          <label class="check" style="font-size:.85rem"><input type="checkbox" name="without_backup" value="1"> Continuer même si la sauvegarde préalable échoue</label>
          <button class="btn btn-danger" type="submit" data-confirm="Supprimer définitivement toutes les données de démonstration ?"><?= icon('trash', 18) ?> Supprimer les données de démonstration</button>
        </form>
      <?php endif; ?>
    </div>
  </div>

  <div class="card">
    <div class="card-head"><h2><?= icon('refresh') ?> Repartir de zéro (activité de test)</h2></div>
    <div class="card-body">
      <p>Efface toute l'activité saisie pendant vos essais :</p>
      <ul class="cleanup-list">
        <?php foreach ($activity as $label => $n): ?><li><strong><?= (int)$n ?></strong> <?= e($label) ?></li><?php endforeach; ?>
        <li>articles hors catalogue proposés et stocks des centres</li>
      </ul>
      <p class="muted" style="font-size:.88rem"><strong>Conservés :</strong> centres, fournisseurs, catalogue et prix, comptes, budgets, listes types, dates limites, logo et paramètres.</p>
      <form method="post" class="mt-1">
        <?= csrf_field() ?><input type="hidden" name="action" value="activity">
        <div class="field"><label>Confirmez avec votre mot de passe</label><input type="password" name="password" required autocomplete="current-password"></div>
        <label class="check" style="font-size:.85rem"><input type="checkbox" name="without_backup" value="1"> Continuer même si la sauvegarde préalable échoue</label>
        <button class="btn btn-danger" type="submit" data-confirm="Effacer toutes les demandes, tous les bons de commande, les stocks et les notifications ? Le catalogue et les comptes sont conservés."><?= icon('trash', 18) ?> Effacer toute l'activité</button>
      </form>
    </div>
  </div>
</div>
<p class="muted mt-2" style="font-size:.88rem"><?= icon('info', 16) ?> En cas d'erreur, la sauvegarde faite juste avant se restaure depuis <a href="<?= url('admin/updates') ?>">Mises à jour → Sauvegardes</a> (« restaurer aussi les données »). Pour supprimer des comptes un par un ou en lot, utilisez <a href="<?= url('admin/users') ?>">Comptes utilisateurs</a>.</p>
