<p><a href="<?= e(url('admin/produits')) ?>">← Produits</a></p>
<section class="card">
  <h2>Trouver un ID variante du catalogue Printful</h2>
  <p class="muted small">Les articles personnalisés (nom, photo, portrait IA) sont commandés à Printful avec un <strong>ID variante catalogue</strong> et le fichier d'impression composé par la boutique. Cherchez le support (en anglais, ex. « framed poster », « mug », « hoodie »), ouvrez-le, puis recopiez l'ID de chaque variante dans la colonne « ID variante catalogue » du produit.</p>
  <form method="get" action="<?= e(url('admin/produits/catalogue-printful')) ?>" class="inline-form">
    <input name="q" value="<?= e($search) ?>" placeholder="framed poster" aria-label="Rechercher dans le catalogue Printful">
    <button class="btn btn-primary">Rechercher</button>
  </form>
  <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

  <?php if ($products): ?>
    <table class="table">
      <thead><tr><th>ID</th><th>Produit</th><th>Type</th><th>Variantes</th><th></th></tr></thead>
      <?php foreach ($products as $p): ?>
        <tr>
          <td>#<?= (int) $p['id'] ?></td>
          <td><strong><?= e($p['title'] ?? '') ?></strong></td>
          <td class="muted small"><?= e($p['type_name'] ?? '') ?></td>
          <td><?= (int) ($p['variant_count'] ?? 0) ?></td>
          <td class="right"><a class="btn btn-sm" href="<?= e(url('admin/produits/catalogue-printful', ['produit' => (int) $p['id'], 'q' => $search])) ?>">Voir les variantes</a></td>
        </tr>
      <?php endforeach; ?>
    </table>
  <?php elseif ($search !== '' && !$product && !$error): ?>
    <p class="muted">Aucun produit ne correspond à « <?= e($search) ?> ».</p>
  <?php endif; ?>

  <?php if ($product): ?>
    <h3><?= e($product['product']['title'] ?? '') ?> <span class="muted small">#<?= (int) ($product['product']['id'] ?? 0) ?></span></h3>
    <?php if ($search !== ''): ?><p><a href="<?= e(url('admin/produits/catalogue-printful', ['q' => $search])) ?>">← Résultats pour « <?= e($search) ?> »</a></p><?php endif; ?>
    <table class="table">
      <thead><tr><th>ID variante</th><th>Variante</th><th>Taille</th><th>Couleur</th><th class="right">Prix Printful</th><th></th></tr></thead>
      <?php foreach ($product['variants'] ?? [] as $v): ?>
        <tr>
          <td><code><?= (int) $v['id'] ?></code> <button type="button" class="btn btn-sm" data-copy="<?= (int) $v['id'] ?>">Copier</button></td>
          <td><?= e($v['name'] ?? '') ?></td>
          <td><?= e($v['size'] ?? '') ?></td>
          <td><?php if (!empty($v['color_code'])): ?><span class="swatch-dot" style="background: <?= e($v['color_code']) ?>"></span><?php endif; ?> <?= e($v['color'] ?? '') ?></td>
          <td class="right"><?= e((string) ($v['price'] ?? '')) ?> $</td>
          <td class="muted small"><?= isset($v['in_stock']) && !$v['in_stock'] ? 'Rupture' : '' ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
  <?php endif; ?>
</section>
