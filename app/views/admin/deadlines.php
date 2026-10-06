<div class="page-head">
  <div><h1>Dates limites de commande</h1><p>Affichées sur le tableau de bord des centres concernés, avec un compte à rebours.</p></div>
  <a class="btn" href="<?= url('admin/deadlines', ['past' => $showPast ? null : 1]) ?>"><?= icon('clock', 16) ?> <?= $showPast ? 'Voir les prochaines' : 'Voir les passées' ?></a>
</div>
<div class="grid grid-main">
  <div class="card">
    <div class="card-head"><h2><?= icon('calendar') ?> <?= $showPast ? 'Dates passées' : 'À venir' ?></h2></div>
    <?php if ($deadlines): foreach ($deadlines as $d): $cd = countdown($d['deadline_at']); ?>
      <div class="deadline">
        <div class="deadline-date lvl-<?= e($cd['level']) ?>"><b><?= date('d', strtotime($d['deadline_at'])) ?></b><span><?= e(month_short_fr((int)date('n', strtotime($d['deadline_at'])))) ?></span></div>
        <div style="flex:1;min-width:0">
          <div class="strong"><?= e($d['title']) ?></div>
          <small><?= e(date_long_fr($d['deadline_at'])) ?> à <?= date('H\hi', strtotime($d['deadline_at'])) ?></small>
          <div class="chips mt-1">
            <?= $d['center_name'] ? '<span class="badge" style="background:' . e($d['center_color']) . '22;color:' . e($d['center_color']) . '">' . e($d['center_name']) . '</span>' : '<span class="badge badge-blue">Tous les centres</span>' ?>
            <?= $d['supplier_name'] ? '<span class="badge badge-gray">' . icon('truck', 12) . ' ' . e($d['supplier_name']) . '</span>' : '' ?>
          </div>
          <?php if ($d['description']): ?><small class="muted"><?= e($d['description']) ?></small><?php endif; ?>
        </div>
        <span class="countdown <?= e($cd['level']) ?>"><?= e($cd['label']) ?></span>
        <form method="post" onsubmit="return confirm('Supprimer cette date ?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$d['id'] ?>"><button class="btn btn-ghost btn-sm btn-danger" type="submit"><?= icon('trash', 15) ?></button></form>
      </div>
    <?php endforeach; else: ?><div class="empty"><?= icon('calendar') ?><p>Aucune date.</p></div><?php endif; ?>
  </div>
  <form method="post" class="card">
    <?= csrf_field() ?>
    <div class="card-head"><h2><?= icon('plus') ?> Nouvelle date limite</h2></div>
    <div class="card-body">
      <div class="field"><label>Intitulé *</label><input type="text" name="title" required placeholder="ex : Commande mensuelle fournitures médicales"></div>
      <div class="form-grid">
        <div class="field"><label>Date *</label><input type="date" name="date" required value="<?= date('Y-m-d', strtotime('+7 days')) ?>"></div>
        <div class="field"><label>Heure</label><input type="time" name="time" value="12:00"></div>
      </div>
      <div class="field"><label>Fournisseur concerné</label><select name="supplier_id"><option value="">Tous / général</option><?php foreach ($suppliers as $s): ?><option value="<?= $s['id'] ?>"><?= e($s['name']) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label>Centres (aucun coché = tous)</label><?php foreach ($centers as $c): ?><label class="check"><input type="checkbox" name="centers[]" value="<?= (int)$c['id'] ?>"> <?= e($c['name']) ?></label><?php endforeach; ?></div>
      <div class="field"><label>Message</label><textarea name="description" rows="2" placeholder="ex : pensez à vérifier vos stocks de gants"></textarea></div>
      <div class="form-grid">
        <div class="field"><label>Répéter</label><select name="every"><option value="month">Tous les mois</option><option value="2weeks">Toutes les 2 semaines</option><option value="week">Toutes les semaines</option></select></div>
        <div class="field"><label>Nombre d'occurrences</label><input type="number" name="repeat" min="1" max="12" value="1"></div>
      </div>
      <button class="btn btn-primary" type="submit"><?= icon('calendar', 18) ?> Programmer</button>
    </div>
  </form>
</div>
