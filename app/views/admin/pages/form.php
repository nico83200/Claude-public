<p><a href="<?= e(url('admin/pages')) ?>">← Pages</a></p>
<form method="post" action="<?= e(url('admin/pages/' . ($page['id'] ?: 'nouvelle'))) ?>" class="card">
  <?= csrf_field() ?>
  <div class="form-grid">
    <label>Titre <input name="title" required value="<?= e($page['title']) ?>"></label>
    <label>Adresse (slug) <input name="slug" value="<?= e($page['slug']) ?>"></label>
  </div>
  <label>Contenu (HTML) <textarea name="content" rows="22" class="mono"><?= e($page['content']) ?></textarea></label>
  <label>Méta-description <input name="meta_description" value="<?= e($page['meta_description']) ?>" maxlength="300"></label>
  <label class="checkbox"><input type="checkbox" name="in_footer" value="1" <?= $page['in_footer'] ? 'checked' : '' ?>> Afficher dans le pied de page</label>
  <button class="btn btn-primary">Enregistrer</button>
</form>
