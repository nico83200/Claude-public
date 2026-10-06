<div class="page-head"><div><h1>Journal d'audit</h1><p>Qui a modifié quoi, et quand : prix, budgets, comptes, statuts des bons, paramètres, mises à jour…</p></div></div>
<form class="filters" method="get">
  <input type="hidden" name="r" value="admin/audit">
  <input type="search" name="q" value="<?= e($q) ?>" placeholder="Rechercher (action, article, e-mail…)" style="min-width:280px">
  <select name="user"><option value="">Tous les utilisateurs</option><?php foreach ($users as $u): ?><option value="<?= $u['id'] ?>" <?= $user === (int)$u['id'] ? 'selected' : '' ?>><?= e($u['first_name'] . ' ' . $u['last_name']) ?></option><?php endforeach; ?></select>
  <button class="btn" type="submit"><?= icon('filter', 16) ?> Filtrer</button>
</form>
<div class="card">
  <?php if ($rows): ?><div class="table-wrap"><table class="table">
    <thead><tr><th>Date</th><th>Utilisateur</th><th>Action</th><th>Détail</th><th>IP</th></tr></thead>
    <tbody><?php foreach ($rows as $a): ?>
      <tr><td class="nowrap"><small><?= date_fr($a['created_at'], true) ?></small></td><td class="nowrap"><?= e(trim(($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? ''))) ?: '<small class="muted">système</small>' ?></td>
        <td class="strong"><?= e($a['action']) ?></td><td><small style="word-break:break-word"><?= e(mb_substr((string)$a['details'], 0, 300)) ?></small></td><td><small class="muted"><?= e($a['ip']) ?></small></td></tr>
    <?php endforeach; ?></tbody>
  </table></div><?php else: ?><div class="empty"><?= icon('shield') ?><p>Aucune entrée.</p></div><?php endif; ?>
</div>
