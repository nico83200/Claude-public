<?php
/** Tutoriels vidéo : publiés une fois ici, ils arrivent dans toutes les installations (menu « Tutoriels vidéo » et aide en ligne). */
defined('HUB') || exit;

$videos = hall("SELECT * FROM videos WHERE app IN (?, '*') ORDER BY position, id", [$app['slug']]);
$maxUpload = ini_get('upload_max_filesize');
$postMax = ini_get('post_max_size');
$help = "Un chapitre par ligne : minutes:secondes, titre, puis | et des mots-clés facultatifs.\nEx. : 4:12 Réceptionner une livraison | réception colis livré";

function video_form(array $v, bool $new): void
{
    global $help;
    ?>
    <label>Titre</label><input name="title" value="<?= h($v['title'] ?? '') ?>" required maxlength="150" placeholder="ex : Tutoriel salarié">
    <label>Description</label><textarea name="description" rows="2" maxlength="2000"><?= h($v['description'] ?? '') ?></textarea>
    <div class="grid2">
      <div><label>Mots-clés <small class="muted">(aident le chatbot à proposer la vidéo)</small></label><input name="keywords" value="<?= h($v['keywords'] ?? '') ?>" maxlength="400" placeholder="ex : commander réceptionner scanner"></div>
      <div><label>Visible dans</label><select name="app"><option value="<?= h($GLOBALS['app']['slug']) ?>"><?= h($GLOBALS['app']['name']) ?> uniquement</option><option value="*" <?= ($v['app'] ?? '') === '*' ? 'selected' : '' ?>>Toutes les applications</option></select></div>
      <div><label>Visible par</label><select name="audience"><option value="all">Tous les utilisateurs</option><option value="admin" <?= ($v['audience'] ?? '') === 'admin' ? 'selected' : '' ?>>Administrateurs seulement</option></select></div>
      <div><label>Ordre d'affichage</label><input type="number" name="position" value="<?= (int)($v['position'] ?? 0) ?>"></div>
    </div>
    <label class="check"><input type="checkbox" name="welcome" value="1" <?= !empty($v['welcome']) ? 'checked' : '' ?>> Vidéo d'accueil des salariés : s'ouvre en fenêtre à leur première connexion (jusqu'à « Ne plus afficher »)</label>
    <label>Chapitres <small class="muted">(le chatbot ouvre la vidéo directement au bon chapitre)</small></label>
    <textarea name="chapters" rows="<?= $new ? 6 : 10 ?>" placeholder="<?= h($help) ?>"><?= h(isset($v['chapters']) ? hub_chapters_text(json_decode((string)$v['chapters'], true) ?: []) : '') ?></textarea>
    <?php
}
?>
<main class="wrap">
  <?= $flashHtml ?>
  <h1><span class="dot" style="background:<?= h($app['color']) ?>;width:14px;height:14px"></span> Vidéos · <?= h($app['name']) ?></h1>
  <p class="muted">Une vidéo publiée ici est transmise à toutes les installations à jour de leur licence (à la prochaine synchronisation, moins de 10 minutes, puis téléchargée en arrière-plan). Les utilisateurs la retrouvent dans le menu « Tutoriels vidéo », et l'aide en ligne la propose — au bon chapitre — quand une question s'y rapporte.</p>
  <form method="post" enctype="multipart/form-data" class="card" data-video-upload>
    <?= csrf_input() ?><input type="hidden" name="action" value="video_upload"><input type="hidden" name="duration" value="">
    <h2>Publier une vidéo</h2>
    <label>Fichier MP4 (H.264, lisible sur tous les appareils)</label>
    <input type="file" name="video" accept="video/mp4,.mp4,.m4v" required>
    <small class="muted">Taille maximale acceptée par le serveur : <?= h((string)$maxUpload) ?> (envoi) / <?= h((string)$postMax) ?> (formulaire). Au-delà, demandez à l'hébergeur d'augmenter upload_max_filesize et post_max_size.</small>
    <?php video_form(['app' => $app['slug']], true); ?>
    <label class="check"><input type="checkbox" name="publish" value="1" checked> Publier tout de suite</label>
    <button class="btn primary">Envoyer la vidéo</button>
  </form>

  <?php foreach ($videos as $v): $ch = json_decode((string)$v['chapters'], true) ?: []; ?>
    <div class="card <?= $v['published'] ? '' : 'dim' ?>">
      <div class="row">
        <b style="flex:1"><?= h($v['title']) ?></b>
        <?= $v['published'] ? '<span class="tag green">publiée</span>' : '<span class="tag">brouillon</span>' ?>
        <?= $v['app'] === '*' ? '<span class="tag blue">toutes les applications</span>' : '' ?>
        <?= $v['audience'] === 'admin' ? '<span class="tag violet">admin</span>' : '' ?><?= !empty($v['welcome']) ? '<span class="tag blue">accueil</span>' : '' ?>
        <form method="post"><?= csrf_input() ?><input type="hidden" name="action" value="video_toggle"><input type="hidden" name="id" value="<?= (int)$v['id'] ?>"><button class="btn sm"><?= $v['published'] ? 'Retirer' : 'Publier' ?></button></form>
        <form method="post" onsubmit="return confirm('Supprimer cette vidéo ? Elle disparaîtra aussi des installations.')"><?= csrf_input() ?><input type="hidden" name="action" value="video_delete"><input type="hidden" name="id" value="<?= (int)$v['id'] ?>"><button class="btn sm danger">Supprimer</button></form>
      </div>
      <small class="muted"><?= $v['duration'] ? sprintf('%d:%02d', intdiv((int)$v['duration'], 60), (int)$v['duration'] % 60) . ' · ' : '' ?><?= round($v['size'] / 1048576, 1) ?> Mo · <?= count($ch) ?> chapitre(s) · mise à jour le <?= date('d/m/Y', strtotime($v['updated_at'])) ?></small>
      <?php if ($v['description']): ?><p style="margin:.4rem 0 0;white-space:pre-line"><?= h($v['description']) ?></p><?php endif; ?>
      <details class="edit"><summary class="muted" style="margin-top:.4rem"><small>Modifier le titre, les mots-clés ou les chapitres</small></summary>
        <form method="post"><?= csrf_input() ?><input type="hidden" name="action" value="video_save"><input type="hidden" name="id" value="<?= (int)$v['id'] ?>">
          <?php video_form($v, false); ?>
          <button class="btn primary">Enregistrer</button>
        </form>
      </details>
    </div>
  <?php endforeach; ?>
  <?php if (!$videos): ?><div class="card muted">Aucune vidéo publiée.</div><?php endif; ?>
</main>
<script>
// Durée lue par le navigateur au choix du fichier
document.querySelectorAll('[data-video-upload] input[type=file]').forEach((inp) => inp.addEventListener('change', () => {
  const f = inp.files[0]; if (!f) return;
  const form = inp.form, v = document.createElement('video'); v.preload = 'metadata';
  v.onloadedmetadata = () => { form.duration.value = Math.round(v.duration || 0); URL.revokeObjectURL(v.src); };
  v.src = URL.createObjectURL(f);
  if (!form.title.value) form.title.value = f.name.replace(/\.[^.]+$/, '').replace(/[-_]+/g, ' ');
}));
</script>
