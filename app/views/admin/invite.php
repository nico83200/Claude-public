<div class="breadcrumb"><a href="<?= url('admin/users') ?>">Comptes</a> <?= icon('chevron-right', 14) ?> Inviter des salariés</div>
<div class="page-head">
  <div><h1>Inviter des salariés</h1><p>Collez la liste de vos salariés : leurs comptes sont créés et chacun reçoit un lien pour choisir son mot de passe (valable 7 jours).</p></div>
</div>

<?php if ($results): ?>
<div class="card mb-2">
  <div class="card-head"><h2><?= icon('check-circle') ?> Invitations</h2>
    <?php if (!$results['mail'] && array_filter($results['rows'], fn($r) => $r['link'])): ?><button type="button" class="btn btn-sm" data-copy-invites><?= icon('clipboard', 15) ?> Copier tous les liens</button><?php endif; ?></div>
  <?php if (!$results['mail']): ?><div class="card-body" style="padding-bottom:0"><div class="flash flash-info mb-0"><?= icon('info') ?><div>Les e-mails ne sont pas activés : transmettez à chacun son lien personnel (par messagerie interne, Teams…). Ces liens ne sont affichés qu'une fois.</div></div></div><?php endif; ?>
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>Salarié</th><th>État</th><th>Lien personnel</th></tr></thead>
      <tbody>
      <?php foreach ($results['rows'] as $r): ?>
        <tr data-invite="<?= e($r['name'] . ' (' . $r['email'] . ') : ' . ($r['link'] ?? '')) ?>">
          <td><strong><?= e($r['name']) ?></strong><br><small class="muted"><?= e($r['email']) ?></small></td>
          <td><span class="badge <?= $r['link'] ? 'badge-green' : 'badge-gray' ?>"><?= e($r['status']) ?></span><?php if (!empty($r['mailed'])): ?> <span class="badge badge-blue">e-mail envoyé</span><?php endif; ?></td>
          <td><?php if ($r['link']): ?><input type="text" readonly value="<?= e($r['link']) ?>" onclick="this.select()" style="font-size:.8rem"><?php else: ?><small class="muted">Compte déjà actif : il se connecte normalement (ou « Mot de passe oublié »).</small><?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if ($results['bad']): ?><div class="card-body"><small class="muted">Lignes ignorées (aucune adresse e-mail) : <?= e(implode(' · ', $results['bad'])) ?></small></div><?php endif; ?>
</div>
<?php endif; ?>

<div class="grid grid-main">
  <form method="post" class="card">
    <?= csrf_field() ?>
    <div class="card-head"><h2><?= icon('users') ?> Liste des salariés</h2></div>
    <div class="card-body">
      <div class="field"><label>Une personne par ligne</label>
        <textarea name="people" rows="9" required placeholder="claire.dubois@centre-sante.fr&#10;Antoine Morel <a.morel@centre-sante.fr>&#10;Léa;Fabre;lea.fabre@centre-sante.fr"><?= e($draft) ?></textarea>
        <small class="muted">Formats acceptés : adresse seule, « Prénom Nom &lt;adresse&gt; », « Prénom ; Nom ; adresse » ou trois colonnes copiées depuis Excel. Sans nom, il est déduit de l'adresse (prenom.nom@…).</small></div>
      <div class="form-grid">
        <div class="field"><label>Rôle</label><select name="role"><option value="user">Salarié (commandes &amp; réceptions)</option><option value="manager">Responsable de centre</option><option value="buyer">Acheteur (service achats)</option></select></div>
        <div class="field"><label>Fonction <small class="muted">(facultatif)</small></label><select name="job"><option value="">—</option><?php foreach ($jobs as $j): ?><option><?= e($j) ?></option><?php endforeach; ?></select></div>
      </div>
      <div class="field"><label>Centres <small class="muted">(un salarié peut en avoir plusieurs)</small></label>
        <?php if ($centers): ?><div class="chips"><?php foreach ($centers as $c): ?><label class="check" style="margin:0 .75rem .3rem 0"><input type="checkbox" name="centers[]" value="<?= (int)$c['id'] ?>" <?= count($centers) === 1 ? 'checked' : '' ?>> <?= e($c['name']) ?></label><?php endforeach; ?></div>
        <?php else: ?><p class="muted mb-0">Aucun centre : <a href="<?= url('admin/center') ?>">créez d'abord vos centres</a>.</p><?php endif; ?></div>
      <label class="check"><input type="checkbox" name="send" value="1" <?= $mailOn ? 'checked' : 'disabled' ?>> Envoyer l'invitation par e-mail<?= $mailOn ? '' : ' <small class="muted">(e-mails non activés : <a href="' . url('admin/settings') . '#mail">les activer</a> — sinon, les liens s\'afficheront ici)</small>' ?></label>
    </div>
    <div class="card-foot"><button class="btn btn-primary" type="submit" <?= $centers ? '' : 'disabled' ?>><?= icon('send', 18) ?> Inviter</button></div>
  </form>
  <div class="stack">
    <?php if ($registerUrl): ?>
    <div class="card card-body">
      <h3 class="mt-0"><?= icon('send', 18) ?> Ou partagez le lien d'inscription</h3>
      <p class="muted"><small>Chacun demande son accès lui-même ; vous validez les demandes dans « Comptes ».</small></p>
      <input type="text" readonly value="<?= e($registerUrl) ?>" onclick="this.select()">
    </div>
    <?php endif; ?>
    <div class="card card-body">
      <h3 class="mt-0"><?= icon('info', 18) ?> Bon à savoir</h3>
      <p class="muted mb-0"><small>Un compte déjà actif n'est pas modifié. Une demande d'accès en attente est validée avec les centres choisis. Les rôles se changent ensuite depuis la fiche de chaque compte.</small></p>
    </div>
  </div>
</div>
<script>
document.querySelector('[data-copy-invites]')?.addEventListener('click', (e) => {
  const txt = [...document.querySelectorAll('[data-invite]')].map((r) => r.dataset.invite).filter((t) => !t.endsWith(': ')).join('\n');
  navigator.clipboard.writeText(txt).then(() => { e.target.closest('button').textContent = 'Liens copiés ✓'; });
});
</script>
