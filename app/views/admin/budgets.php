<div class="page-head">
  <div><h1>Budgets annuels</h1><p>Budget achats par centre. « Commandé » = bons passés chez les fournisseurs ; « Engagé » = bons à commander.</p></div>
  <div class="chips">
    <?php foreach ([$year - 1, $year, $year + 1] as $y): ?><a class="chip <?= $y === $year ? 'active' : '' ?>" href="<?= url('admin/budgets', ['year' => $y]) ?>"><?= $y ?></a><?php endforeach; ?>
  </div>
</div>
<form method="post" class="card">
  <?= csrf_field() ?><input type="hidden" name="year" value="<?= $year ?>">
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Centre</th><th style="width:170px">Budget <?= $year ?> (€ HT)</th><th style="width:120px">Alerte à (%)</th><th style="min-width:340px">Consommation</th></tr></thead>
    <tbody>
    <?php foreach ($centers as $c): $b = $c['budget']; ?>
      <tr>
        <td><span class="dot" style="background:<?= e($c['color']) ?>"></span> <strong><?= e($c['name']) ?></strong></td>
        <td><input type="text" name="amount[<?= (int)$c['id'] ?>]" value="<?= $b['amount'] > 0 ? e(number_format($b['amount'], 2, ',', ' ')) : '' ?>" placeholder="0,00" style="text-align:right"></td>
        <td><input type="number" name="alert_pct[<?= (int)$c['id'] ?>]" value="<?= (int)$b['alert_pct'] ?>" min="1" max="100"></td>
        <td><?php partial('budget_gauge', ['b' => $b]); ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <div class="card-foot row"><small class="muted">Une notification est envoyée aux administrateurs quand le seuil d'alerte est franchi (une fois par an et par centre).</small><span class="spacer"></span><button class="btn btn-primary" type="submit"><?= icon('check', 18) ?> Enregistrer</button></div>
</form>
