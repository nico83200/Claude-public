<?php /** @var array $b */ $lvl = budget_level($b); $sp = $b['amount'] > 0 ? min(100, $b['spent'] / $b['amount'] * 100) : 0; $cm = $b['amount'] > 0 ? min(100 - $sp, $b['committed'] / $b['amount'] * 100) : 0; ?>
<?php if ($b['defined']): ?>
  <div class="row" style="justify-content:space-between;font-size:.85rem;margin-bottom:.35rem">
    <span><strong><?= money($b['spent']) ?></strong> <span class="muted">/ <?= money($b['amount']) ?></span></span>
    <span class="badge badge-<?= $lvl === 'over' ? 'red' : ($lvl === 'warn' ? 'amber' : 'green') ?>"><?= $b['pct'] ?> %</span>
  </div>
  <div class="gauge <?= $lvl === 'over' ? 'over' : '' ?>"><span class="g-spent" style="width:<?= $sp ?>%"></span><span class="g-committed" style="width:<?= $cm ?>%"></span></div>
  <div class="legend"><span><i style="background:#6366f1"></i>Commandé</span><span><i style="background:#f59e0b"></i>Engagé <?= money($b['committed']) ?></span><?php if (!empty($b['pending'])): ?><span>Demandes en attente <?= money($b['pending']) ?></span><?php endif; ?><span>Reste <strong style="color:<?= $b['remaining'] < 0 ? 'var(--red)' : 'var(--text)' ?>"><?= money($b['remaining']) ?></strong></span></div>
<?php else: ?>
  <small class="muted">Aucun budget défini<?= is_admin() ? ' — <a href="' . url('admin/budgets') . '">définir</a>' : '' ?>. Commandé : <?= money($b['spent']) ?></small>
<?php endif; ?>
