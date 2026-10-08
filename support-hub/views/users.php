<?php
/** Comptes de la console : chaque personne a son identifiant, son mot de passe et sa double authentification. */
defined('HUB') || exit;

$users = hall('SELECT * FROM users ORDER BY active DESC, name');
$roles = ['admin' => 'Administrateur', 'agent' => 'Conseiller'];
?>
<main class="wrap">
  <?= $flashHtml ?>
  <h1>Comptes</h1>
  <p class="muted">Chaque personne qui répond aux utilisateurs a son propre compte : ses réponses sont signées de son nom. Un <b>administrateur</b> accède à toute la console ; un <b>conseiller</b> aux conversations, à la FAQ, aux vidéos et à sa disponibilité.</p>

  <details class="card edit">
    <summary><b>＋ Nouveau compte</b></summary>
    <form method="post">
      <?= csrf_input() ?><input type="hidden" name="action" value="user_add">
      <div class="grid2">
        <div><label>Nom</label><input type="text" name="name" required maxlength="80" placeholder="ex : Julie Martin"></div>
        <div><label>Identifiant</label><input type="text" name="username" required autocapitalize="none" spellcheck="false" placeholder="ex : julie"></div>
        <div><label>E-mail <small class="muted">(facultatif)</small></label><input type="email" name="email"></div>
        <div><label>Rôle</label><select name="role"><option value="agent">Conseiller</option><option value="admin">Administrateur</option></select></div>
        <div><label>Mot de passe provisoire (10 caractères min.)</label><input type="text" name="password" required minlength="10" autocomplete="off" value="<?= h(substr(str_replace(['+', '/', '='], '', base64_encode(random_bytes(12))), 0, 14)) ?>"></div>
      </div>
      <button class="btn primary" style="margin-top:.8rem">Créer le compte</button>
    </form>
  </details>

  <div class="card table-wrap" style="padding:.4rem .8rem">
    <table class="cards">
      <tr><th>Personne</th><th>Rôle</th><th class="hide-sm">Dernière connexion</th><th></th></tr>
      <?php foreach ($users as $u): ?>
        <tr class="<?= $u['active'] ? '' : 'dim' ?>">
          <td><b><?= h($u['name']) ?></b><?= (int)$u['id'] === (int)$hubUser['id'] ? ' <span class="tag blue">vous</span>' : '' ?><br><small class="muted">identifiant : <?= h($u['username']) ?><?= $u['email'] ? ' · ' . h($u['email']) : '' ?></small></td>
          <td><span class="tag <?= $u['role'] === 'admin' ? 'violet' : '' ?>"><?= h($roles[$u['role']] ?? $u['role']) ?></span> <?= $u['totp_secret'] ? '<span class="tag green" title="Double authentification activée">2FA</span>' : '' ?><?= $u['active'] ? '' : ' <span class="tag red">désactivé</span>' ?></td>
          <td class="hide-sm"><small><?= $u['last_login'] ? date('d/m/Y H:i', strtotime($u['last_login'])) : 'jamais' ?></small></td>
          <td>
            <details class="edit"><summary class="btn sm">Gérer</summary>
              <form method="post"><?= csrf_input() ?><input type="hidden" name="action" value="user_save"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                <label>Nom</label><input type="text" name="name" value="<?= h($u['name']) ?>">
                <label>E-mail</label><input type="email" name="email" value="<?= h((string)$u['email']) ?>">
                <label>Rôle</label><select name="role"><?php foreach ($roles as $k => $l): ?><option value="<?= $k ?>" <?= $u['role'] === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select>
                <label class="check"><input type="checkbox" name="active" value="1" <?= $u['active'] ? 'checked' : '' ?>> Compte actif</label>
                <button class="btn sm primary">Enregistrer</button>
              </form>
              <form method="post" class="row" style="margin-top:.6rem"><?= csrf_input() ?><input type="hidden" name="action" value="user_password"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                <input type="text" name="password" placeholder="Nouveau mot de passe provisoire" minlength="10" required autocomplete="off"><button class="btn sm">Changer le mot de passe</button></form>
              <?php if ($u['totp_secret']): ?>
                <form method="post" style="margin-top:.4rem" onsubmit="return confirm('Réinitialiser la double authentification (téléphone perdu) ?')"><?= csrf_input() ?><input type="hidden" name="action" value="user_totp_reset"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>"><button class="btn sm danger">Réinitialiser la double authentification</button></form>
              <?php endif; ?>
            </details>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>
</main>
