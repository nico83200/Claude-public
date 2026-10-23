<section class="card">
  <?php if (!$messages): ?><p class="muted">Aucun message.</p><?php endif; ?>
  <?php foreach ($messages as $m): ?>
    <article class="message <?= $m['is_read'] ? '' : 'unread' ?>">
      <header><strong><?= e($m['name']) ?></strong> · <a href="mailto:<?= e($m['email']) ?>?subject=<?= rawurlencode('Re: ' . $m['subject']) ?>"><?= e($m['email']) ?></a> · <span class="muted"><?= e(date_fr($m['created_at'])) ?></span></header>
      <p class="muted small"><?= e($m['subject']) ?><?= $m['order_number'] ? ' · Commande ' . e($m['order_number']) : '' ?></p>
      <p><?= nl2br(e($m['message'])) ?></p>
    </article>
  <?php endforeach; ?>
</section>
