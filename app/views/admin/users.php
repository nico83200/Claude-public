<div class="page-head">
  <div><h1>Comptes utilisateurs</h1><p>Validez les demandes d'accès et attribuez les centres de chaque salarié.</p></div>
  <a class="btn btn-primary" href="<?= url('admin/user') ?>"><?= icon('plus', 18) ?> Créer un compte</a>
</div>
<div class="tabs">
  <?php foreach (['' => 'Tous', 'pending' => 'À valider', 'active' => 'Actifs', 'disabled' => 'Désactivés'] as $k => $l): ?>
    <a class="<?= $status === $k ? 'active' : '' ?>" href="<?= url('admin/users', ['status' => $k]) ?>"><?= $l ?><?php if ($k && !empty($counts[$k])): ?> <span class="badge <?= $k === 'pending' ? 'badge-pink' : 'badge-gray' ?>"><?= (int)$counts[$k] ?></span><?php endif; ?></a>
  <?php endforeach; ?>
</div>
<form method="post" action="<?= url('admin/users/delete') ?>" class="card" id="users-form">
  <?= csrf_field() ?>
  <div class="bulk-bar" id="bulk-bar" hidden>
    <span><strong data-bulk-count>0</strong> compte(s) sélectionné(s)</span>
    <button class="btn btn-sm btn-danger" type="submit" data-confirm="Supprimer les comptes sélectionnés ? Les comptes qui ont déjà passé des commandes seront anonymisés pour conserver l'historique."><?= icon('trash', 16) ?> Supprimer la sélection</button>
  </div>
  <div class="table-wrap"><table class="table">
    <thead><tr><th class="col-check"><input type="checkbox" data-check-all="ids[]" title="Tout sélectionner" aria-label="Tout sélectionner"></th><th>Nom</th><th>Fonction</th><th>Centres</th><th>Rôle</th><th>Statut</th><th>Dernière connexion</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($users as $u): ?>
      <tr>
        <td class="col-check"><?php if ((int)$u['id'] !== $me): ?><input type="checkbox" name="ids[]" value="<?= (int)$u['id'] ?>" aria-label="Sélectionner <?= e($u['first_name'] . ' ' . $u['last_name']) ?>"><?php endif; ?></td>
        <td><div class="row"><div class="avatar sm"><?= e(initials($u['first_name'], $u['last_name'])) ?></div><div><div class="strong"><?= e($u['first_name'] . ' ' . $u['last_name']) ?></div><small><?= e($u['email']) ?></small></div></div></td>
        <td><?= e($u['job']) ?></td>
        <td><div class="chips">
          <?php foreach ($userCenters[(int)$u['id']] ?? [] as $c): ?><span class="badge" style="background:<?= e($c['color']) ?>22;color:<?= e($c['color']) ?>"><?= e($c['name']) ?></span><?php endforeach; ?>
          <?php if ($u['status'] === 'pending' && $u['requested_centers']): ?><small class="muted">Demandé : <?= e(implode(', ', array_filter(array_map(fn($id) => $centerNames[(int)$id] ?? null, explode(',', $u['requested_centers']))))) ?></small><?php endif; ?>
          <?php if (is_admin($u)): ?><small class="muted">Tous (<?= e(mb_strtolower(role_label($u['role']))) ?>)</small><?php endif; ?>
        </div></td>
        <td><?= ['admin' => '<span class="badge badge-violet">Administrateur</span>', 'buyer' => '<span class="badge badge-violet">Acheteur</span>', 'manager' => '<span class="badge badge-blue">Responsable</span>'][$u['role']] ?? '<span class="badge badge-gray">Salarié</span>' ?></td>
        <td><?= ['pending' => '<span class="badge badge-pink">À valider</span>', 'active' => '<span class="badge badge-green">Actif</span>', 'disabled' => '<span class="badge badge-gray">Désactivé</span>'][$u['status']] ?? e($u['status']) ?></td>
        <td class="nowrap"><small><?= date_fr($u['last_login'], true) ?></small></td>
        <td><a class="btn btn-sm <?= $u['status'] === 'pending' ? 'btn-primary' : '' ?>" href="<?= url('admin/user', ['id' => $u['id']]) ?>"><?= $u['status'] === 'pending' ? 'Valider' : 'Modifier' ?></a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php if (!$users): ?><div class="empty"><p>Aucun compte.</p></div><?php endif; ?>
</form>
