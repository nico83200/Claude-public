<div class="breadcrumb"><a href="<?= url('videos') ?>">Tutoriels vidéo</a> <?= icon('chevron-right', 14) ?> Gestion</div>
<div class="page-head">
  <div><h1>Tutoriels vidéo</h1><p>Les vidéos publiées par <?= e(support_contact()['editor']) ?> arrivent automatiquement. Vous pouvez aussi ajouter les vôtres (procédures internes, matériel du centre…).</p></div>
  <?php if ($managed): ?>
    <form method="post" data-busy="Récupération des vidéos <?= e(support_contact()['editor']) ?>…"><?= csrf_field() ?><input type="hidden" name="action" value="fetch"><button class="btn" type="submit"><?= icon('download', 18) ?> Récupérer les vidéos <?= e(support_contact()['editor']) ?></button></form>
  <?php endif; ?>
</div>

<?php
$chapHelp = 'Un chapitre par ligne : minutes:secondes, titre, puis | et des mots-clés facultatifs (synonymes que les salariés pourraient employer). Ex. : 4:12 Réceptionner une livraison | réception colis livré';
?>
<div class="grid grid-main">
  <div class="stack">
    <?php if (!$videos): ?><div class="card"><div class="empty"><?= icon('play') ?><p>Aucune vidéo pour l'instant.</p></div></div><?php endif; ?>
    <?php foreach ($videos as $v): $hub = $v['source'] === 'hub'; ?>
      <div class="card <?= $v['active'] ? '' : 'is-done' ?>">
        <div class="card-head">
          <div>
            <h3 class="mb-0"><?= e($v['title']) ?></h3>
            <small class="muted">
              <?= $hub ? 'Publiée par ' . e(support_contact()['editor']) : 'Ajoutée par votre établissement' ?>
              <?= $v['duration'] ? ' · ' . e(video_time((int)$v['duration'])) : '' ?><?= $v['size'] ? ' · ' . round($v['size'] / 1048576, 1) . ' Mo' : '' ?>
              · <?= count($v['chapters']) ?> chapitre(s) · <?= $v['audience'] === 'admin' ? 'administrateurs' : 'tous les utilisateurs' ?>
            </small>
          </div>
          <div class="row">
            <?php if (!$v['ready']): ?><span class="badge badge-amber"><?= $hub ? 'Téléchargement en attente' : 'Fichier manquant' ?></span>
            <?php elseif (!$v['active']): ?><span class="badge">Masquée</span>
            <?php else: ?><a class="btn btn-sm" href="<?= url('videos', ['v' => $v['uid']]) ?>"><?= icon('play', 16) ?> Voir</a><?php endif; ?>
          </div>
        </div>
        <details class="card-body" style="padding-top:0">
          <summary class="muted" style="cursor:pointer"><small>Modifier</small></summary>
          <form method="post" class="mt-1">
            <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)$v['id'] ?>">
            <?php if ($hub): ?>
              <p class="muted"><small>Titre, description et chapitres sont gérés par <?= e(support_contact()['editor']) ?>. Vous pouvez masquer la vidéo ou changer son ordre d'affichage.</small></p>
            <?php else: ?>
              <div class="field"><label>Titre</label><input type="text" name="title" value="<?= e($v['title']) ?>" required maxlength="150"></div>
              <div class="field"><label>Description</label><textarea name="description" rows="2" maxlength="2000"><?= e($v['description']) ?></textarea></div>
              <div class="field"><label>Mots-clés <small class="muted">(aident l'aide en ligne à proposer la vidéo)</small></label><input type="text" name="keywords" value="<?= e($v['keywords']) ?>" maxlength="400"></div>
              <div class="field"><label>Chapitres</label><textarea name="chapters" rows="6" placeholder="<?= e($chapHelp) ?>"><?= e(video_chapters_text($v['chapters'])) ?></textarea></div>
              <div class="field"><label>Visible par</label><select name="audience"><option value="all">Tous les utilisateurs</option><option value="admin" <?= $v['audience'] === 'admin' ? 'selected' : '' ?>>Administrateurs seulement</option></select></div>
            <?php endif; ?>
            <div class="row row-wrap">
              <label class="mb-0">Ordre</label><input class="qty-input" type="number" name="position" value="<?= (int)$v['position'] ?>">
              <label class="check mb-0"><input type="checkbox" name="active" value="1" <?= $v['active'] ? 'checked' : '' ?>> Visible</label>
              <span class="spacer"></span>
              <button class="btn btn-primary btn-sm" type="submit">Enregistrer</button>
            </div>
          </form>
          <?php if (!$hub): ?>
            <form method="post" class="mt-1" onsubmit="return confirm('Supprimer définitivement cette vidéo ?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$v['id'] ?>"><button class="btn btn-ghost btn-sm btn-danger" type="submit"><?= icon('trash', 16) ?> Supprimer la vidéo</button></form>
          <?php endif; ?>
        </details>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="stack">
  <form method="post" class="card" id="welcome">
    <?= csrf_field() ?><input type="hidden" name="action" value="welcome">
    <div class="card-head"><h2><?= icon('play') ?> Vidéo d'accueil des salariés</h2></div>
    <div class="card-body">
      <p class="muted" style="margin-top:0">S'ouvre en fenêtre à la connexion des salariés, jusqu'à ce qu'ils cochent « Ne plus afficher ». Elle reste disponible dans « Tutoriels vidéo ».</p>
      <div class="field"><label>Vidéo</label>
        <select name="welcome_video">
          <option value="">Automatique<?= $welcomeAuto ? ' : « ' . e($welcomeAuto['title']) . ' » (choisie par ' . e(support_contact()['editor']) . ')' : ' (aucune vidéo désignée pour l\'instant)' ?></option>
          <?php foreach (array_filter($videos, fn($v) => $v['ready'] && $v['active'] && $v['audience'] !== 'admin') as $v): ?>
            <option value="<?= e($v['uid']) ?>" <?= $welcome === $v['uid'] ? 'selected' : '' ?>><?= e($v['title']) ?></option>
          <?php endforeach; ?>
          <option value="none" <?= $welcome === 'none' ? 'selected' : '' ?>>Aucune fenêtre d'accueil</option>
        </select></div>
      <label class="check"><input type="checkbox" name="reset" value="1"> La montrer à nouveau à tous les salariés (y compris ceux qui l'ont masquée)</label>
      <button class="btn btn-primary" type="submit">Enregistrer</button>
    </div>
  </form>
  <form method="post" enctype="multipart/form-data" class="card" data-busy="Envoi de la vidéo… (cela peut prendre quelques minutes)" data-video-upload>
    <?= csrf_field() ?><input type="hidden" name="action" value="upload"><input type="hidden" name="duration" value="">
    <div class="card-head"><h2><?= icon('plus') ?> Ajouter une vidéo</h2></div>
    <div class="card-body">
      <div class="field"><label>Fichier vidéo (MP4)</label><input type="file" name="video" accept="video/mp4,.mp4,.m4v" required>
        <small class="muted">Limite d'envoi du serveur : <?= round($limit / 1048576) ?> Mo<?= $limit < 100 * 1048576 ? ' (votre hébergeur peut l\'augmenter : upload_max_filesize et post_max_size)' : '' ?>.</small></div>
      <div class="field"><label>Titre</label><input type="text" name="title" required maxlength="150" placeholder="ex : Faire l'inventaire de la réserve"></div>
      <div class="field"><label>Description</label><textarea name="description" rows="2" maxlength="2000"></textarea></div>
      <div class="field"><label>Mots-clés</label><input type="text" name="keywords" maxlength="400" placeholder="ex : inventaire stock compter réserve"></div>
      <div class="field"><label>Chapitres <small class="muted">(facultatif)</small></label><textarea name="chapters" rows="5" placeholder="<?= e($chapHelp) ?>"></textarea></div>
      <div class="field"><label>Visible par</label><select name="audience"><option value="all">Tous les utilisateurs</option><option value="admin">Administrateurs seulement</option></select></div>
      <input type="hidden" name="position" value="<?= count($videos) * 10 ?>">
      <button class="btn btn-primary" type="submit"><?= icon('download', 18) ?> Publier la vidéo</button>
    </div>
  </form>
</div>
</div>
