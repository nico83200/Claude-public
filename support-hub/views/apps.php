<?php
/** Applications dont les utilisateurs écrivent au chat (nom et couleur dans la boîte de réception). Leur gestion (clients, abonnements…) se fait dans leur propre console. */
defined('HUB') || exit;

$stats = [];
foreach (hub_apps() as $a) {
    $stats[$a['slug']] = [
        'clients' => (int)hone('SELECT COUNT(*) n FROM clients WHERE app = ? AND active = 1', [$a['slug']])['n'],
    ];
}
?>
<main class="wrap">
  <?= $flashHtml ?>
  <h1>Applications</h1>
  <p class="muted">Les conversations des utilisateurs de toutes les applications NLapps arrivent dans une seule boîte de réception, avec un filtre par application. Clients, abonnements, versions, vidéos et FAQ se gèrent dans la console de chaque application.</p>

  <details class="card edit" <?= count(hub_apps()) ? '' : 'open' ?>>
    <summary><b>＋ Ajouter une application</b></summary>
    <form method="post">
      <?= csrf_input() ?><input type="hidden" name="action" value="app_save">
      <div class="grid2">
        <div><label>Nom</label><input type="text" name="name" required maxlength="60" placeholder="ex : Planning Soins"></div>
        <div><label>Identifiant technique <small class="muted">(envoyé par l'application ; déduit du nom si vide)</small></label><input type="text" name="slug" placeholder="ex : planning-soins" autocapitalize="none"></div>
        <div><label>Couleur</label><input type="color" name="color" value="#0ea5e9" style="height:42px;padding:4px"></div>
      </div>
      <button class="btn primary" style="margin-top:.8rem">Ajouter</button>
    </form>
  </details>

  <?php foreach (hub_apps() as $a): $s = $stats[$a['slug']]; ?>
    <div class="card">
      <div class="row">
        <span class="dot" style="background:<?= h($a['color']) ?>;width:14px;height:14px"></span>
        <b style="flex:1"><?= h($a['name']) ?> <small class="muted">· identifiant <code><?= h($a['slug']) ?></code></small></b>
        <?php if (!empty($a['console_url'])): ?><a class="btn sm" href="<?= h($a['console_url']) ?>" target="_blank" rel="noopener">Console ↗</a><?php endif; ?>
      </div>
      <small class="muted"><?= $s['clients'] ?> accès actif(s) au chat<?= !empty($a['console_url']) ? ' · gérée par sa console' : '' ?></small>
      <details class="edit"><summary class="muted" style="margin-top:.4rem"><small>Modifier</small></summary>
        <form method="post"><?= csrf_input() ?><input type="hidden" name="action" value="app_save"><input type="hidden" name="orig" value="<?= h($a['slug']) ?>">
          <div class="grid2">
            <div><label>Nom</label><input type="text" name="name" value="<?= h($a['name']) ?>" required></div>
            <div><label>Couleur</label><input type="color" name="color" value="<?= h($a['color']) ?>" style="height:42px;padding:4px"></div>
            <div><label>Ordre dans le menu</label><input type="number" name="position" value="<?= (int)$a['position'] ?>"></div>
          </div>
          <div class="row" style="margin-top:.8rem"><button class="btn primary sm">Enregistrer</button></div>
        </form>
        <?php if (!$s['clients'] && count(hub_apps()) > 1): ?>
          <form method="post" style="margin-top:.5rem" onsubmit="return confirm('Supprimer cette application de la console ?')"><?= csrf_input() ?><input type="hidden" name="action" value="app_delete"><input type="hidden" name="slug" value="<?= h($a['slug']) ?>"><button class="btn sm danger">Supprimer l'application</button></form>
        <?php endif; ?>
      </details>
    </div>
  <?php endforeach; ?>
  <p class="muted"><small>Pour brancher une nouvelle application : intégrez le kit <code>sdk/</code> (conversations), puis créez ses accès dans « Accès au chat ». L'identifiant technique est celui que l'application envoie au centre d'assistance.</small></p>
</main>
