<p><a href="<?= e(url('admin/produits')) ?>">← Produits</a></p>
<section class="card">
  <p>Créez vos produits dans votre <a href="https://www.printful.com/dashboard/store" target="_blank">boutique Printful « Manual order / API »</a> (choix du support, placement du visuel, prix de vente), puis importez-les ici : variantes, tailles, couleurs, prix et visuels sont récupérés automatiquement. Un produit déjà importé est mis à jour.</p>
  <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
  <?php if (!$error && !$items): ?><p class="muted">Aucun produit synchronisé dans votre boutique Printful.</p><?php endif; ?>
  <div class="pf-grid">
    <?php foreach ($items as $item): ?>
      <div class="pf-item">
        <img src="<?= e($item['thumbnail_url'] ?? '') ?>" alt="" loading="lazy">
        <strong><?= e($item['name']) ?></strong>
        <span class="muted small"><?= (int) ($item['variants'] ?? 0) ?> variante(s) · #<?= (int) $item['id'] ?></span>
        <form method="post" action="<?= e(url('admin/produits/printful/' . (int) $item['id'])) ?>">
          <?= csrf_field() ?>
          <?php if (isset($imported[$item['id']])): ?>
            <button class="btn">Mettre à jour</button> <a class="small" href="<?= e(url('admin/produits/' . $imported[$item['id']])) ?>">Voir</a>
          <?php else: ?>
            <select name="category_id"><option value="">Catégorie…</option><?php foreach ($categories as $c): ?><option value="<?= (int) $c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?></select>
            <button class="btn btn-primary">Importer</button>
          <?php endif; ?>
        </form>
      </div>
    <?php endforeach; ?>
  </div>
  <?php if ($total > $offset + 50): ?><p><a class="btn" href="<?= e(url('admin/produits/printful', ['offset' => $offset + 50])) ?>">Page suivante →</a></p><?php endif; ?>
</section>
