<div class="toolbar">
  <nav class="tabs">
    <?php foreach (['pending' => 'À modérer', 'approved' => 'Publiés', 'rejected' => 'Refusés'] as $k => $label): ?>
      <a href="<?= e(url('admin/avis', ['statut' => $k])) ?>" class="<?= $status === $k ? 'active' : '' ?>"><?= e($label) ?> (<?= (int) ($counts[$k] ?? 0) ?>)</a>
    <?php endforeach; ?>
  </nav>
</div>
<section class="card">
  <p class="muted small">Les avis sont demandés automatiquement par e-mail <?= (int) setting('review_request_days', 10) ?> jours après l'expédition (réglable dans Réglages). Rappel légal : ne refusez un avis que s'il est illicite, injurieux ou hors sujet — jamais parce qu'il est négatif.</p>
  <?php if (!$reviews): ?><p class="muted">Aucun avis dans cette liste.</p><?php endif; ?>
  <?php foreach ($reviews as $r): ?>
    <article class="message">
      <header>
        <strong class="stars-text"><?= str_repeat('★', (int) $r['rating']) . str_repeat('☆', 5 - (int) $r['rating']) ?></strong>
        <strong><?= e($r['title'] ?: 'Sans titre') ?></strong> — <?= e($r['author_name']) ?>
        <span class="muted small">· <a href="<?= e(url('produit/' . $r['product_slug'])) ?>" target="_blank"><?= e($r['product_name']) ?></a>
        <?= $r['order_number'] ? '· commande ' . e($r['order_number']) : '' ?> · <?= e(date_fr($r['created_at'])) ?></span>
      </header>
      <?php if ($r['body']): ?><p><?= nl2br(e($r['body'])) ?></p><?php endif; ?>
      <div class="actions">
        <?php foreach (['publier' => 'Publier', 'refuser' => 'Refuser', 'supprimer' => 'Supprimer'] as $action => $label): ?>
          <?php if (($action === 'publier' && $r['status'] === 'approved') || ($action === 'refuser' && $r['status'] === 'rejected')) continue; ?>
          <form method="post" action="<?= e(url('admin/avis/' . $r['id'] . '/' . $action)) ?>" <?= $action === 'supprimer' ? 'data-confirm="Supprimer définitivement cet avis ?"' : '' ?>>
            <?= csrf_field() ?><button class="btn btn-sm <?= $action === 'publier' ? 'btn-primary' : ($action === 'supprimer' ? 'btn-danger' : '') ?>"><?= $label ?></button>
          </form>
        <?php endforeach; ?>
      </div>
    </article>
  <?php endforeach; ?>
</section>
