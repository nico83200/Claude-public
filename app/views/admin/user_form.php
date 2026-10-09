<?php $u ??= []; $v = fn($k, $d = '') => e($u[$k] ?? $d); $isNew = empty($u['id']); ?>
<div class="breadcrumb"><a href="<?= url('admin/users') ?>">Comptes</a> <?= icon('chevron-right', 14) ?> <?= e($title) ?></div>
<h1 class="mb-2"><?= e($title) ?> <?= ($u['status'] ?? '') === 'pending' ? '<span class="badge badge-pink">Demande en attente</span>' : '' ?></h1>
<form method="post" class="grid grid-main">
  <?= csrf_field() ?>
  <div class="card">
    <div class="card-head"><h2><?= icon('users') ?> Identité</h2></div>
    <div class="card-body form-grid">
      <div class="field"><label>Prénom *</label><input type="text" name="first_name" value="<?= $v('first_name') ?>" required></div>
      <div class="field"><label>Nom *</label><input type="text" name="last_name" value="<?= $v('last_name') ?>" required></div>
      <div class="field"><label>E-mail *</label><input type="email" name="email" value="<?= $v('email') ?>" required></div>
      <div class="field"><label>Téléphone</label><input type="tel" name="phone" value="<?= $v('phone') ?>"></div>
      <div class="field"><label>Fonction</label><select name="job"><option value="">—</option><?php foreach (job_choices() as $j): ?><option <?= ($u['job'] ?? '') === $j ? 'selected' : '' ?>><?= e($j) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label><?= $isNew ? 'Mot de passe' : 'Nouveau mot de passe' ?></label><input type="text" name="password" autocomplete="new-password" placeholder="<?= $isNew ? 'vide = généré automatiquement' : 'laisser vide pour ne pas changer' ?>"><small>Pour réinitialiser un mot de passe oublié, saisissez-en un nouveau et transmettez-le au salarié.</small></div>
    </div>
  </div>
  <div class="stack">
    <div class="card">
      <div class="card-head"><h2><?= icon('building') ?> Centres autorisés</h2></div>
      <div class="card-body">
        <?php foreach ($centers as $c): ?><label class="check"><input type="checkbox" name="centers[]" value="<?= (int)$c['id'] ?>" <?= in_array((int)$c['id'], $selected, true) ? 'checked' : '' ?>> <?= e($c['name']) ?> <small><?= e($c['city']) ?></small></label><?php endforeach; ?>
        <small class="muted">Un salarié peut être validé sur plusieurs sites et passe de l'un à l'autre via le sélecteur de centre.</small>
      </div>
    </div>
    <div class="card card-body">
      <div class="field"><label class="rh-label">Rôle <?php partial('roles_help'); ?></label>
        <select name="role"><option value="user" <?= ($u['role'] ?? 'user') === 'user' ? 'selected' : '' ?>>Salarié (commandes &amp; réceptions)</option><option value="manager" <?= ($u['role'] ?? '') === 'manager' ? 'selected' : '' ?>>Responsable de centre (valide les demandes de ses centres)</option><option value="buyer" <?= ($u['role'] ?? '') === 'buyer' ? 'selected' : '' ?>>Acheteur (service achats, sans l'organisation ni les paramètres)</option><option value="admin" <?= ($u['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Administrateur (service achats, organisation et paramètres)</option></select>
        <small class="muted">L'acheteur traite les demandes, les commandes, le catalogue, les fournisseurs, les factures et les budgets. Seul l'administrateur gère les centres, les comptes, les paramètres, le journal d'audit et les mises à jour.</small>
      </div>
      <div class="field"><label>Statut</label>
        <select name="status">
          <option value="active" <?= in_array($u['status'] ?? 'active', ['active', 'pending'], true) ? 'selected' : '' ?>>Actif (validé)</option>
          <option value="pending">En attente</option>
          <option value="disabled" <?= ($u['status'] ?? '') === 'disabled' ? 'selected' : '' ?>>Désactivé</option>
        </select>
      </div>
      <button class="btn btn-primary btn-lg" type="submit"><?= icon('check', 18) ?> <?= ($u['status'] ?? '') === 'pending' ? 'Valider le compte' : 'Enregistrer' ?></button>
    </div>
  </div>
</form>
<?php if (!empty($u['id']) && (int)$u['id'] !== $me && user_has_2fa($u)): ?>
<form method="post" class="card card-body mt-2">
  <?= csrf_field() ?><input type="hidden" name="action" value="reset_2fa">
  <div class="row" style="justify-content:space-between;flex-wrap:wrap;gap:1rem">
    <div><strong><?= icon('lock', 16) ?> Double authentification activée</strong><br><small class="muted">Si l'utilisateur a perdu son téléphone, réinitialisez-la : il se connectera avec son seul mot de passe puis la reconfigurera.</small></div>
    <button class="btn" type="submit" data-confirm="Réinitialiser la double authentification de ce compte ?">Réinitialiser</button>
  </div>
</form>
<?php endif; ?>
<?php if (!empty($u['id']) && (int)$u['id'] !== $me): ?>
<form method="post" action="<?= url('admin/users/delete') ?>" class="card card-body mt-2 danger-zone">
  <?= csrf_field() ?><input type="hidden" name="ids[]" value="<?= (int)$u['id'] ?>">
  <div class="row" style="justify-content:space-between;flex-wrap:wrap;gap:1rem">
    <div><strong>Supprimer ce compte</strong><br><small class="muted"><?= $hasHistory
        ? 'Ce compte a passé ' . (int)$hasHistory . ' demande(s) : il sera anonymisé (nom, e-mail et téléphone effacés, connexion impossible) et l\'historique des commandes restera consultable.'
        : 'Ce compte n\'a passé aucune demande : il sera effacé définitivement.' ?></small></div>
    <button class="btn btn-danger" type="submit" data-confirm="Supprimer le compte de <?= e(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? '')) ?> ?"><?= icon('trash', 18) ?> Supprimer le compte</button>
  </div>
</form>
<?php endif; ?>
<?php partial('roles_help', ['dialog' => true]); ?>
