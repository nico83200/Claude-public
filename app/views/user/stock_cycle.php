<div class="breadcrumb"><a href="<?= url('stock') ?>">Inventaire</a> <?= icon('chevron-right', 14) ?> Inventaire tournant</div>
<div class="page-head">
  <div><h1>Inventaire tournant</h1><p><?= e($center['name']) ?> · semaine <?= e(substr($week, -2)) ?> · comptez ces <?= count($items) ?> articles : ceux qui n'ont pas été comptés depuis le plus longtemps, en priorité les plus coûteux et les plus consommés. Quelques minutes par semaine suffisent pour garder un stock juste.</p></div>
  <?php if ($items): ?><button class="btn" type="button" onclick="window.print()"><?= icon('printer', 18) ?> Imprimer la liste</button><?php endif; ?>
</div>
<div class="stack">
  <form method="post" class="card">
    <?= csrf_field() ?>
    <?php if ($items): ?>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>Article</th><th>Emplacement</th><th class="num">Stock théorique</th><th class="num">Compté</th><th class="num">Écart</th></tr></thead>
      <tbody>
      <?php foreach ($items as $it): $d = $done[(int)$it['product_id']] ?? null; $price = effective_price($it); ?>
        <tr class="<?= $d ? 'dim' : '' ?>">
          <td><div class="row"><?php partial('thumb', ['p' => $it, 'size' => 34]); ?><div><a class="strong" href="<?= url('stock/history', ['product_id' => $it['product_id']]) ?>"><?= e($it['name']) ?></a><div><small class="muted"><?= e($it['unit']) ?><?= $it['counted_at'] ? ' · compté le ' . date_fr($it['counted_at']) : ' · jamais compté' ?></small></div></div></div></td>
          <td><small><?= e($it['location'] ?: '—') ?></small></td>
          <td class="num"><?= (int)$it['qty'] ?></td>
          <td class="num"><?php if ($d): ?><strong><?= (int)$d['qty_after'] ?></strong> <span class="badge badge-green">fait</span>
            <?php else: ?><input class="count-input" type="number" min="0" name="counted[<?= (int)$it['product_id'] ?>]" placeholder="—" inputmode="numeric" data-expected="<?= (int)$it['qty'] ?>" data-price="<?= $price ?>"><?php endif; ?></td>
          <td class="num"><?php if ($d): $g = (int)$d['delta']; ?><span class="cycle-gap <?= $g > 0 ? 'pos' : ($g < 0 ? 'neg' : '') ?>"><?= $g > 0 ? '+' : '' ?><?= $g ?><?= $g ? '<br><small>' . money($g * $price) . '</small>' : '' ?></span><?php else: ?><span class="cycle-gap" data-gap></span><?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <div class="card-foot row">
      <small class="muted">Saisissez la quantité réellement présente. L'écart est enregistré dans l'historique et chiffré en euros.</small>
      <span class="spacer"></span>
      <?php if (count($done) < count($items)): ?><button class="btn btn-primary" type="submit"><?= icon('check', 18) ?> Enregistrer les comptages</button><?php else: ?><span class="badge badge-green"><?= icon('check', 14) ?> Inventaire de la semaine terminé</span><?php endif; ?>
    </div>
    <?php else: ?>
      <div class="empty"><?= icon('clipboard') ?><p>Aucun article suivi en stock dans ce centre.</p><a class="btn" href="<?= url('stock') ?>">Suivre des articles</a></div>
    <?php endif; ?>
  </form>
  <div class="card">
    <div class="card-head"><h3><?= icon('chart', 18) ?> Écarts des dernières semaines</h3></div>
    <?php if ($history): ?>
      <ul class="list"><?php foreach ($history as $h): ?>
        <li><div class="grow"><strong>Semaine <?= e(substr($h['week'], -2)) ?></strong><div><small class="muted"><?= (int)$h['counted'] ?> compté(s) · <?= (int)$h['gaps'] ?> écart(s)</small></div></div>
          <span class="cycle-gap <?= $h['value'] > 0 ? 'pos' : ($h['value'] < 0 ? 'neg' : '') ?> strong"><?= $h['value'] > 0 ? '+' : '' ?><?= money($h['value']) ?></span></li>
      <?php endforeach; ?></ul>
    <?php else: ?><div class="empty"><p>Pas encore d'inventaire tournant.</p></div><?php endif; ?>
  </div>
</div>
