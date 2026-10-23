<div class="toolbar">
  <form method="get" class="inline"><input type="search" name="q" value="<?= e($q) ?>" placeholder="Rechercher un produit"><button class="btn">OK</button></form>
  <div class="inline">
    <a class="btn" href="<?= e(url('admin/produits/printful')) ?>">Importer depuis Printful</a>
    <a class="btn btn-primary" href="<?= e(url('admin/produits/nouveau')) ?>">+ Nouveau produit</a>
  </div>
</div>
<section class="card">
  <table class="table">
    <thead><tr><th></th><th>Produit</th><th>Catégorie</th><th>Prix</th><th>Variantes</th><th>État</th></tr></thead>
    <tbody>
    <?php foreach ($products as $p): ?>
      <tr>
        <td style="width:64px"><img class="thumb" src="<?= e(image_url($p['main_image'])) ?>" alt="" width="56" height="56"></td>
        <td><a href="<?= e(url('admin/produits/' . $p['id'])) ?>"><strong><?= e($p['name']) ?></strong></a>
          <?php if ($p['personalizable']): ?><span class="tag">Personnalisable</span><?php endif; ?>
          <?php if ($p['featured']): ?><span class="tag">Mis en avant</span><?php endif; ?></td>
        <td><?= e($p['category_name'] ?? '—') ?></td>
        <td><?= e(money($p['price'])) ?></td>
        <td><?= (int) $p['variant_count'] ?>
          <?php if ($p['unlinked']): ?><br><span class="status status-error" title="Variantes sans ID Printful : elles ne pourront pas être produites"><?= (int) $p['unlinked'] ?> non reliée(s)</span><?php endif; ?></td>
        <td><?= $p['active'] ? '<span class="status status-shipped">En ligne</span>' : '<span class="status status-pending">Brouillon</span>' ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$products): ?><tr><td colspan="6" class="muted">Aucun produit. Importez vos produits depuis Printful ou créez-en un.</td></tr><?php endif; ?>
    </tbody>
  </table>
</section>
