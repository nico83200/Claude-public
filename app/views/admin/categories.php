<div class="page-head"><div><h1>Catégories</h1><p>Organisent le catalogue et colorent les articles sans photo.</p></div></div>
<div class="grid grid-main">
  <div class="card">
    <div class="table-wrap"><table class="table">
      <thead><tr><th>Ordre</th><th>Catégorie</th><th>Icône</th><th>Couleur</th><th class="num">Articles</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($categories as $c): $f = 'cat' . (int)$c['id']; ?>
        <tr>
          <td><input form="<?= $f ?>" type="number" name="position" value="<?= (int)$c['position'] ?>" style="width:70px"></td>
          <td><div class="row"><div class="thumb" style="width:34px;height:34px;background:<?= e($c['color']) ?>"><?= icon($c['icon'], 18) ?></div><input form="<?= $f ?>" type="text" name="name" value="<?= e($c['name']) ?>"></div></td>
          <td><select form="<?= $f ?>" name="icon" style="width:130px"><?php foreach (icon_choices() as $ic): ?><option <?= $c['icon'] === $ic ? 'selected' : '' ?>><?= $ic ?></option><?php endforeach; ?></select></td>
          <td><input form="<?= $f ?>" type="color" name="color" value="<?= e($c['color']) ?>"></td>
          <td class="num"><?= (int)$c['nb'] ?></td>
          <td class="nowrap">
            <form method="post" id="<?= $f ?>" style="display:inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><button class="btn btn-sm" type="submit" title="Enregistrer"><?= icon('check', 15) ?></button></form>
            <form method="post" style="display:inline" onsubmit="return confirm('Supprimer cette catégorie ?')"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><input type="hidden" name="action" value="delete"><button class="btn btn-ghost btn-sm btn-danger" type="submit"><?= icon('trash', 15) ?></button></form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </div>
  <form method="post" class="card">
    <?= csrf_field() ?>
    <div class="card-head"><h2><?= icon('plus') ?> Nouvelle catégorie</h2></div>
    <div class="card-body">
      <div class="field"><label>Nom</label><input type="text" name="name" required></div>
      <div class="field"><label>Icône</label><select name="icon"><?php foreach (icon_choices() as $ic): ?><option><?= $ic ?></option><?php endforeach; ?></select></div>
      <div class="field"><label>Couleur</label><div class="swatches"><?php foreach (palette() as $i => $col): ?><input type="radio" name="color" id="cc<?= $i ?>" value="<?= $col ?>" <?= $i === 1 ? 'checked' : '' ?>><label for="cc<?= $i ?>" style="background:<?= $col ?>"></label><?php endforeach; ?></div></div>
      <div class="field"><label>Ordre d'affichage</label><input type="number" name="position" value="<?= count($categories) + 1 ?>"></div>
      <button class="btn btn-primary" type="submit">Créer</button>
    </div>
  </form>
</div>
