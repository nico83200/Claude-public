<div class="grid-main">
  <section class="card">
    <table class="table">
      <thead><tr><th>Ordre</th><th>Nom</th><th>Produits</th><th>État</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($categories as $c): ?>
        <tr>
          <td><?= (int) $c['position'] ?></td>
          <td><strong><?= e($c['name']) ?></strong><br><small class="muted">/categorie/<?= e($c['slug']) ?></small></td>
          <td><?= (int) $c['product_count'] ?></td>
          <td><?= $c['active'] ? 'Visible' : 'Masquée' ?></td>
          <td class="right">
            <details><summary class="btn">Modifier</summary>
              <form method="post" action="<?= e(url('admin/categories')) ?>" class="form-grid mt">
                <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                <label>Nom <input name="name" value="<?= e($c['name']) ?>" required></label>
                <label>Slug <input name="slug" value="<?= e($c['slug']) ?>"></label>
                <label>Ordre <input name="position" type="number" value="<?= (int) $c['position'] ?>"></label>
                <label class="checkbox"><input type="checkbox" name="active" value="1" <?= $c['active'] ? 'checked' : '' ?>> Visible</label>
                <label class="full">Description <textarea name="description" rows="2"><?= e($c['description']) ?></textarea></label>
                <button class="btn btn-primary">Enregistrer</button>
              </form>
              <form method="post" action="<?= e(url('admin/categories/' . $c['id'] . '/supprimer')) ?>" data-confirm="Supprimer cette catégorie ?"><?= csrf_field() ?><button class="btn btn-danger mt">Supprimer</button></form>
            </details>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </section>
  <section class="card">
    <h2>Nouvelle catégorie</h2>
    <form method="post" action="<?= e(url('admin/categories')) ?>">
      <?= csrf_field() ?>
      <label>Nom <input name="name" required></label>
      <label>Description <textarea name="description" rows="3"></textarea></label>
      <label>Ordre <input name="position" type="number" value="<?= count($categories) ?>"></label>
      <label class="checkbox"><input type="checkbox" name="active" value="1" checked> Visible</label>
      <button class="btn btn-primary">Créer</button>
    </form>
  </section>
</div>
