<?php
/** Réglages : disponibilité et horaires, notifications, réponses rapides, IA, sécurité. */
defined('HUB') || exit;

$days = [1 => 'Lundi', 2 => 'Mardi', 3 => 'Mercredi', 4 => 'Jeudi', 5 => 'Vendredi', 6 => 'Samedi', 7 => 'Dimanche'];
$sched = hub_schedule();
$mode = hsetting('availability_mode', 'manual');
$subs = hall('SELECT * FROM push_subs ORDER BY created_at DESC');
$quick = hall('SELECT * FROM quick_replies ORDER BY position, id');
$key = (string)hsetting('anthropic_api_key', '');
?>
<main class="wrap">
  <?= $flashHtml ?>
  <h1>Réglages</h1>

  <form method="post" class="card" id="availability">
    <?= csrf_input() ?><input type="hidden" name="action" value="schedule_save">
    <h2>Disponibilité</h2>
    <label class="check"><input type="radio" name="mode" value="manual" <?= $mode !== 'auto' ? 'checked' : '' ?> style="width:auto"> Manuelle : le bouton en haut de la console indique si vous êtes disponible</label>
    <label class="check"><input type="radio" name="mode" value="auto" <?= $mode === 'auto' ? 'checked' : '' ?> style="width:auto"> Selon mes horaires : « Disponible » pendant les plages ci-dessous, « Absent » en dehors</label>
    <table class="sched">
      <?php foreach ($days as $d => $label): ?>
        <tr><td style="width:110px"><?= $label ?></td><td><input name="day[<?= $d ?>]" value="<?= h(implode(', ', array_map(fn($s) => $s[0] . '-' . $s[1], $sched[$d] ?? []))) ?>" placeholder="fermé (ex : 09:00-12:30, 14:00-18:00)"></td></tr>
      <?php endforeach; ?>
    </table>
    <label>Message affiché quand vous êtes absent</label>
    <textarea name="away_message" rows="2"><?= h($st['away_message']) ?></textarea>
    <button class="btn primary">Enregistrer</button>
  </form>

<?php if ($hubUser['role'] === 'admin'): ?>
  <form method="post" class="card" id="licences">
    <?= csrf_input() ?><input type="hidden" name="action" value="grace_save">
    <h2>Licences</h2>
    <p class="muted" style="margin-top:0">À l'échéance d'un abonnement non renouvelé, l'accès au logiciel du client est coupé et tous ses utilisateurs sont déconnectés (page avec vos coordonnées). Vous pouvez laisser un délai de grâce.</p>
    <div class="row"><label style="margin:0">Délai de grâce après l'échéance</label><input type="number" name="grace_days" min="0" max="60" value="<?= hub_grace_days() ?>" style="max-width:90px"><span>jour(s) — 0 = coupure immédiate</span><button class="btn">Enregistrer</button></div>
  </form>
<?php endif; ?>

  <div class="card" id="install">
    <h2>Application sur vos appareils</h2>
    <p class="muted" style="margin-top:0">La console s'installe comme une application (icône sur l'écran d'accueil ou le bureau, fenêtre dédiée, notifications) :</p>
    <ul style="margin:.3rem 0 .8rem;padding-left:1.2rem;line-height:1.6">
      <li><b>Ordinateur (Chrome, Edge)</b> : bouton ci-dessous, ou icône ⊕ dans la barre d'adresse.</li>
      <li><b>Android</b> : bouton ci-dessous, ou menu ⋮ → « Installer l'application ».</li>
      <li><b>iPhone / iPad</b> : dans Safari, bouton Partager → « Sur l'écran d'accueil ».</li>
    </ul>
    <div class="row"><button class="btn primary" type="button" data-install-btn>⬇ Installer sur cet appareil</button><span class="muted" data-install-state></span>
      <a class="btn" href="index.php?p=update">Mise à jour du centre (v<?= h(hub_version()) ?>)</a></div>
  </div>

  <div class="card" id="notif">
    <h2>Notifications</h2>
    <p style="margin-top:0">Installez cette console sur votre téléphone (menu du navigateur → « Ajouter à l'écran d'accueil »), puis activez les notifications : vous êtes prévenu à chaque nouveau message, même console fermée.</p>
    <div class="row">
      <button class="btn primary" type="button" data-push-enable>🔔 Activer sur cet appareil</button>
      <form method="post"><?= csrf_input() ?><input type="hidden" name="action" value="push_test"><button class="btn">Envoyer un test</button></form>
      <span class="muted" data-push-state></span>
    </div>
    <?php if ($subs): ?>
      <table style="margin-top:.6rem"><?php foreach ($subs as $s): ?>
        <tr><td><?= h($s['label'] ?: 'Appareil') ?><br><small class="muted">activé le <?= date('d/m/Y', strtotime($s['created_at'])) ?><?= $s['last_ok'] ? ' · dernière notification ' . date('d/m H:i', strtotime($s['last_ok'])) : '' ?></small></td>
          <td style="text-align:right"><form method="post"><?= csrf_input() ?><input type="hidden" name="action" value="push_unsubscribe"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><button class="btn sm">Retirer</button></form></td></tr>
      <?php endforeach; ?></table>
    <?php endif; ?>
    <p class="muted" style="margin-bottom:0"><small>Aussi : e-mail à <b><?= h((string)hcfg('notify_email') ?: 'désactivé') ?></b><?= hcfg('ntfy_url') ? ' et ntfy (' . h((string)hcfg('ntfy_url')) . ')' : '' ?> (réglages dans <code>config.php</code>). Une alerte au plus toutes les 3 minutes par conversation.</small></p>
  </div>

  <div class="card" id="quick">
    <h2>Réponses rapides</h2>
    <p class="muted" style="margin-top:0">Insérées en un clic dans une conversation. <code>{prenom}</code> est remplacé par le prénom de l'utilisateur.</p>
    <?php foreach ($quick as $q): ?>
      <details class="edit"><summary><b><?= h($q['title']) ?></b> <small class="muted"><?= h(mb_substr($q['body'], 0, 80)) ?>…</small></summary>
        <form method="post"><?= csrf_input() ?><input type="hidden" name="action" value="quick_save"><input type="hidden" name="id" value="<?= (int)$q['id'] ?>">
          <input name="title" value="<?= h($q['title']) ?>"><textarea name="body" rows="3" style="margin-top:.4rem"><?= h($q['body']) ?></textarea>
          <div class="row" style="margin-top:.4rem"><button class="btn sm primary">Enregistrer</button></div></form>
        <form method="post" style="margin-top:.4rem"><?= csrf_input() ?><input type="hidden" name="action" value="quick_delete"><input type="hidden" name="id" value="<?= (int)$q['id'] ?>"><button class="btn sm danger">Supprimer</button></form>
      </details>
    <?php endforeach; ?>
    <form method="post" style="margin-top:.8rem"><?= csrf_input() ?><input type="hidden" name="action" value="quick_save">
      <div class="grid2"><div><label>Titre</label><input name="title" placeholder="ex : Vider le cache" required></div></div>
      <label>Texte</label><textarea name="body" rows="3" required placeholder="Bonjour {prenom}, …"></textarea>
      <button class="btn">＋ Ajouter</button></form>
  </div>

<?php if ($hubUser['role'] === 'admin'): ?>
  <form method="post" class="card" id="ai">
    <?= csrf_input() ?><input type="hidden" name="action" value="ai_save">
    <h2>Suggestion de réponse par l'IA</h2>
    <p class="muted" style="margin-top:0">Le bouton « ✨ Suggérer une réponse » rédige une proposition à partir de la conversation, du contexte technique, de la FAQ et de vos réponses rapides. Vous relisez avant d'envoyer.
      <?= hub_ai_available() ? '<span class="tag green">active</span>' : '<span class="tag">inactive</span>' ?></p>
    <div class="grid2">
      <div><label>Clé API Claude</label><input type="password" name="api_key" autocomplete="new-password" placeholder="<?= $key ? h(substr($key, 0, 10)) . '… (enregistrée)' : 'sk-ant-…' ?>"></div>
      <div><label>Modèle</label><input name="model" value="<?= h((string)hsetting('anthropic_model', 'claude-opus-5-5')) ?>"></div>
    </div>
    <?php if (!is_file(HUB . '/vendor/autoload.php')): ?><p class="err">Dossier <code>vendor/</code> absent : utilisez le paquet complet du centre d'assistance.</p><?php endif; ?>
    <div class="row" style="margin-top:.8rem"><button class="btn primary">Enregistrer</button><?php if ($key): ?><label class="check" style="margin:0"><input type="checkbox" name="remove_key" value="1"> supprimer la clé</label><?php endif; ?></div>
  </form>
<?php endif; ?>

  <div class="card" id="security">
    <h2>Sécurité</h2>
    <p class="muted" style="margin-top:0">Identifiant, mot de passe et double authentification se règlent dans <a href="index.php?p=account">Mon compte</a><?= $hubUser['role'] === 'admin' ? ', les accès de votre équipe dans <a href="index.php?p=users">Comptes</a>' : '' ?>. Les clés des clients se renouvellent depuis le parc clients de chaque application.</p>
  </div>
</main>
