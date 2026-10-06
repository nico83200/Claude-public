<div class="page-head"><div><h1>Mon profil</h1><p><?= e($u['email']) ?></p></div></div>
<div class="grid grid-2">
  <form method="post" class="card">
    <?= csrf_field() ?><input type="hidden" name="action" value="profile">
    <div class="card-head"><h2><?= icon('users') ?> Informations</h2></div>
    <div class="card-body form-grid">
      <div class="field"><label>Prénom</label><input type="text" name="first_name" value="<?= e($u['first_name']) ?>"></div>
      <div class="field"><label>Nom</label><input type="text" name="last_name" value="<?= e($u['last_name']) ?>"></div>
      <div class="field"><label>Fonction</label><select name="job"><?php foreach (job_choices() as $j): ?><option <?= $u['job'] === $j ? 'selected' : '' ?>><?= e($j) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label>Téléphone</label><input type="tel" name="phone" value="<?= e($u['phone']) ?>"></div>
      <div class="full">
        <label>Centres accessibles</label>
        <div class="chips"><?php foreach (user_centers() as $c): ?><span class="chip"><span class="dot" style="background:<?= e($c['color']) ?>"></span><?= e($c['name']) ?></span><?php endforeach; ?></div>
      </div>
    </div>
    <div class="card-foot"><button class="btn btn-primary" type="submit">Enregistrer</button></div>
  </form>
  <form method="post" class="card">
    <?= csrf_field() ?><input type="hidden" name="action" value="password">
    <div class="card-head"><h2><?= icon('lock') ?> Mot de passe</h2></div>
    <div class="card-body">
      <div class="field"><label>Mot de passe actuel</label><input type="password" name="current" required autocomplete="current-password"></div>
      <div class="field"><label>Nouveau mot de passe</label><input type="password" name="new" minlength="8" required autocomplete="new-password"></div>
      <div class="field"><label>Confirmation</label><input type="password" name="confirm" minlength="8" required autocomplete="new-password"></div>
    </div>
    <div class="card-foot"><button class="btn btn-primary" type="submit">Changer le mot de passe</button></div>
  </form>
  <form method="post" class="card" style="grid-column:1/-1">
    <?= csrf_field() ?><input type="hidden" name="action" value="notifications">
    <div class="card-head"><h2><?= icon('bell') ?> Notifications</h2></div>
    <div class="card-body">
      <label class="check"><input type="checkbox" name="notify_email" value="1" <?= (int)($u['notify_email'] ?? 1) ? 'checked' : '' ?>> Recevoir aussi les notifications par e-mail (<?= e($u['email']) ?>)</label>
      <?php if (setting('mail_enabled', '0') !== '1'): ?><small class="muted">L'envoi d'e-mails n'est pas encore activé par le service achats : seules les notifications dans l'application sont actives.</small><?php endif; ?>
      <hr>
      <p class="muted">Événements qui vous intéressent :</p>
      <div class="check-grid">
        <?php $prefs = user_notify_prefs($u); foreach (NOTIFY_EVENTS as $k => $ev): if ($ev['for'] === 'admin' && $u['role'] !== 'admin') continue; if (!notify_event_enabled($k)) continue; ?>
          <label class="check"><input type="checkbox" name="events[<?= $k ?>]" value="1" <?= ($prefs[$k] ?? true) ? 'checked' : '' ?>> <?= e($ev['label']) ?></label>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="card-foot"><button class="btn btn-primary" type="submit">Enregistrer mes préférences</button></div>
  </form>
</div>
