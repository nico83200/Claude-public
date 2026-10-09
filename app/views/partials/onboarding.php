<?php
/** Guide de démarrage (pilotage de l'administrateur). */
$steps = onboarding_steps();
$pct = onboarding_progress($steps);
$main = array_filter($steps, fn($s) => !$s['optional']);
$extra = array_filter($steps, fn($s) => $s['optional']);
$next = array_values(array_filter($main, fn($s) => !$s['done']))[0] ?? null;
?>
<div class="card onboarding mb-2">
  <div class="card-head">
    <h2><?= icon('sparkles') ?> <?= $pct === 100 ? 'Votre espace est prêt' : 'Démarrage : ' . $pct . ' %' ?></h2>
    <form method="post" action="<?= url('admin/onboarding') ?>"><?= csrf_field() ?><input type="hidden" name="hide" value="1"><button class="btn btn-ghost btn-sm" type="submit"><?= $pct === 100 ? 'Fermer' : 'Masquer le guide' ?></button></form>
  </div>
  <div class="card-body">
    <div class="progress <?= $pct === 100 ? 'ok' : '' ?>"><span style="width:<?= max(4, $pct) ?>%"></span></div>
    <p class="muted" style="margin:.5rem 0 1rem"><?= $pct === 100 ? 'Bravo : vos salariés peuvent commander. Quelques réglages facultatifs pour aller plus loin :' : ($next ? 'Prochaine étape : <strong>' . e($next['title']) . '</strong>.' : '') ?></p>
    <ol class="onboarding-steps">
      <?php foreach ($pct === 100 ? $extra : $main as $s): $isNext = $next && $s['key'] === $next['key']; ?>
        <li class="<?= $s['done'] ? 'done' : '' ?> <?= $isNext ? 'next' : '' ?>">
          <span class="ob-check"><?= $s['done'] ? icon('check', 16) : '' ?></span>
          <div><strong><?= e($s['title']) ?></strong><small><?= e($s['text']) ?></small></div>
          <?php if (!$s['done']): ?><a class="btn btn-sm <?= $isNext ? 'btn-primary' : '' ?>" href="<?= e($s['link']) ?>"><?= e($s['cta']) ?></a><?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ol>
    <?php if ($pct < 100 && $extra): ?>
      <details class="mt-1"><summary class="muted" style="cursor:pointer">Pour aller plus loin (facultatif)</summary>
        <ol class="onboarding-steps mt-1">
          <?php foreach ($extra as $s): ?>
            <li class="<?= $s['done'] ? 'done' : '' ?>"><span class="ob-check"><?= $s['done'] ? icon('check', 16) : '' ?></span><div><strong><?= e($s['title']) ?></strong><small><?= e($s['text']) ?></small></div><?php if (!$s['done']): ?><a class="btn btn-sm" href="<?= e($s['link']) ?>"><?= e($s['cta']) ?></a><?php endif; ?></li>
          <?php endforeach; ?>
        </ol>
      </details>
    <?php endif; ?>
  </div>
</div>
