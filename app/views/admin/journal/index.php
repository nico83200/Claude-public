<div class="card-head">
  <p class="muted">Conseils, idées cadeaux, entretien… Des articles réguliers aident votre boutique à être trouvée sur Google et donnent envie d'acheter.</p>
  <a class="btn btn-primary" href="<?= e(url('admin/journal/nouveau')) ?>">Nouvel article</a>
</div>
<section class="card">
  <table class="table">
    <thead><tr><th>Article</th><th>Thème</th><th>Statut</th><th>Date</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($posts as $p): ?>
      <tr>
        <td><a href="<?= e(url('admin/journal/' . $p['id'])) ?>"><strong><?= e($p['title']) ?></strong></a><br><small class="muted">/journal/<?= e($p['slug']) ?></small></td>
        <td><?= e($p['tag'] ?: '—') ?></td>
        <td><?= $p['published'] ? ($p['published_at'] > now() ? 'Programmé' : 'Publié') : 'Brouillon' ?></td>
        <td><?= e(date_fr($p['published_at'] ?: $p['created_at'], false)) ?></td>
        <td class="right"><a class="btn" href="<?= e(url('journal/' . $p['slug'])) ?>" target="_blank">Voir ↗</a></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$posts): ?><tr><td colspan="5" class="muted">Aucun article pour l'instant.</td></tr><?php endif; ?>
    </tbody>
  </table>
</section>
