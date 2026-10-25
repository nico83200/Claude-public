<?php $linked = array_map('intval', json_field($post['product_ids'])); ?>
<p><a href="<?= e(url('admin/journal')) ?>">← Journal</a><?php if ($post['id']): ?> · <a href="<?= e(url('journal/' . $post['slug'])) ?>" target="_blank">Voir l'article ↗</a><?php endif; ?></p>
<form method="post" action="<?= e(url('admin/journal/' . ($post['id'] ?: 'nouveau'))) ?>" enctype="multipart/form-data">
  <?= csrf_field() ?>
  <div class="grid-main">
    <div>
      <section class="card">
        <label>Titre <input name="title" required maxlength="190" value="<?= e($post['title']) ?>" placeholder="Ex. : 10 idées cadeaux pour une cavalière"></label>
        <label>Chapeau (résumé affiché dans la liste, 2 phrases) <textarea name="excerpt" rows="2" maxlength="400"><?= e($post['excerpt']) ?></textarea></label>
        <label for="content">Contenu</label>
        <textarea id="content" name="content" rows="22" data-rich-editor><?= e(rich_text($post['content'])) ?></textarea>
        <p class="muted small">Astuce : utilisez des intertitres (« Titre ») et des listes, c'est plus agréable à lire sur téléphone et mieux compris par Google.</p>
      </section>
      <section class="card">
        <h2>Articles de la boutique cités</h2>
        <p class="muted small">Affichés en fin d'article (« Pour aller plus loin »).</p>
        <div class="check-list">
          <?php foreach ($products as $p): ?>
            <label class="checkbox"><input type="checkbox" name="product_ids[]" value="<?= (int) $p['id'] ?>" <?= in_array((int) $p['id'], $linked, true) ? 'checked' : '' ?>> <?= e($p['name']) ?></label>
          <?php endforeach; ?>
        </div>
      </section>
    </div>
    <div>
      <section class="card">
        <h2>Publication</h2>
        <label class="checkbox"><input type="checkbox" name="published" value="1" <?= $post['published'] ? 'checked' : '' ?>> Publié sur le site</label>
        <label>Date de publication (laisser vide = maintenant) <input type="datetime-local" name="published_at" value="<?= $post['published_at'] ? e(date('Y-m-d\TH:i', strtotime($post['published_at']))) : '' ?>"></label>
        <label>Thème <select name="tag"><?php foreach (App\Services\Journal::TAGS as $t): ?><option <?= $post['tag'] === $t ? 'selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?></select></label>
        <button class="btn btn-primary btn-block">Enregistrer</button>
      </section>
      <section class="card">
        <h2>Image de couverture</h2>
        <?php if ($post['cover_image']): ?>
          <div class="home-slot-preview" style="aspect-ratio:4/3"><img src="<?= e(thumb($post['cover_image'], 400)) ?>" alt=""></div>
          <label class="checkbox"><input type="checkbox" name="remove_cover" value="1"> Retirer l'image</label>
        <?php endif; ?>
        <label>Nouvelle image (paysage, 1600 × 1200 px conseillé) <input type="file" name="cover" accept="image/jpeg,image/png,image/webp"></label>
      </section>
      <section class="card">
        <h2>Référencement</h2>
        <label>Adresse <input name="slug" value="<?= e($post['slug']) ?>" placeholder="générée depuis le titre"></label>
        <label>Méta-description (Google, 160 car.) <textarea name="meta_description" rows="3" maxlength="300"><?= e($post['meta_description']) ?></textarea></label>
      </section>
    </div>
  </div>
</form>
<?php if ($post['id']): ?>
  <form method="post" action="<?= e(url('admin/journal/' . $post['id'] . '/supprimer')) ?>" data-confirm="Supprimer définitivement cet article ?" class="mt"><?= csrf_field() ?><button class="btn btn-danger">Supprimer l'article</button></form>
<?php endif; ?>
