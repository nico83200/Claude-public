<div class="page-head">
  <div><h1>Mon panier</h1><p>Demande pour <strong><?= e($center['name']) ?></strong> — les articles sont automatiquement classés par fournisseur.</p></div>
  <a class="btn" href="<?= url('catalog') ?>"><?= icon('plus', 18) ?> Ajouter des articles</a>
</div>

<?php if (!$groups): ?>
  <div class="card"><div class="empty">
    <?= icon('cart') ?><h3>Votre panier est vide</h3>
    <p>Utilisez la recherche pour trouver ce dont vous avez besoin.</p>
    <a class="btn btn-primary" href="<?= url('catalog') ?>"><?= icon('search', 18) ?> Parcourir le catalogue</a>
  </div></div>
<?php else: ?>
<form method="post" action="<?= url('cart/update') ?>">
  <?= csrf_field() ?>
  <div class="grid grid-main">
    <div class="stack">
      <?php foreach ($groups as $sid => $g): $dl = $deadlinesBySupplier[$sid] ?? null; ?>
      <div class="card supplier-block" style="border-left-color:<?= e($g['color']) ?>">
        <div class="card-head">
          <h3><?= icon('truck', 18) ?> <?= e($g['name']) ?></h3>
          <div class="row">
            <?php if ($dl): $cd = countdown($dl); ?><span class="countdown <?= e($cd['level']) ?>" title="Date limite"><?= icon('clock', 14) ?> <?= date_fr($dl) ?></span><?php endif; ?>
            <?php if (show_prices()): ?><strong><?= money($g['total']) ?></strong><?php endif; ?>
          </div>
        </div>
        <div class="table-wrap"><table class="table">
          <tbody>
          <?php foreach ($g['items'] as $it): ?>
            <tr>
              <td style="width:56px"><?php partial('thumb', ['p' => $it, 'size' => 44]); ?></td>
              <td>
                <a class="strong" href="<?= url('product', ['id' => $it['product_id']]) ?>"><?= e($it['name']) ?></a>
                <div><small><?= $it['reference'] ? 'Réf. ' . e($it['reference']) . ' · ' : '' ?><?= e($it['unit']) ?></small></div>
                <input type="text" name="comment[<?= (int)$it['id'] ?>]" value="<?= e($it['comment']) ?>" placeholder="Précision (taille, couleur…)" style="margin-top:.35rem;padding:.35rem .6rem;font-size:.85rem">
              </td>
              <td class="nowrap"><input class="qty-input" type="number" min="0" max="9999" name="qty[<?= (int)$it['id'] ?>]" value="<?= (int)$it['qty'] ?>"></td>
              <?php if (show_prices()): ?><td class="num"><?= money(effective_price($it) * $it['qty']) ?><div><small><?= money(effective_price($it)) ?> / u.</small></div></td><?php endif; ?>
              <td><button class="btn btn-ghost btn-icon btn-danger" type="submit" formaction="<?= url('cart/remove', ['id' => $it['id']]) ?>" title="Retirer"><?= icon('trash', 18) ?></button></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table></div>
      </div>
      <?php endforeach; ?>
      <button class="btn" type="submit"><?= icon('repeat', 18) ?> Mettre à jour les quantités</button>
    </div>

    <div>
      <div class="card" style="position:sticky;top:90px">
        <div class="card-head"><h2>Récapitulatif</h2></div>
        <div class="card-body">
          <div class="row mb-1"><span class="muted">Lignes</span><span class="spacer"></span><strong><?= $count ?></strong></div>
          <div class="row mb-1"><span class="muted">Fournisseurs</span><span class="spacer"></span><strong><?= count($groups) ?></strong></div>
          <?php if (show_prices()): $bud = budget_status((int)$center['id']); if ($bud['defined']): ?>
            <div class="mb-2" style="padding:.7rem;border-radius:12px;background:var(--surface-2)"><small class="muted"><?= icon('wallet', 14) ?> Budget <?= date('Y') ?> du centre</small><?php partial('budget_gauge', ['b' => $bud]); ?>
            <?php if ($bud['remaining'] < $total): ?><small style="color:var(--red)">Cette demande dépasse le budget restant : le service achats la validera au cas par cas.</small><?php endif; ?></div>
          <?php endif; endif; ?>
          <?php if (show_prices()): ?><div class="row mb-2"><span class="muted">Total estimé HT</span><span class="spacer"></span><strong style="font-size:1.3rem"><?= money($total) ?></strong></div><?php endif; ?>
          <div class="field">
            <label for="rc">Commentaire pour le service achats</label>
            <textarea id="rc" name="request_comment" placeholder="Ex : besoin avant la vacation de jeudi"></textarea>
          </div>
          <label class="check"><input type="checkbox" name="urgent" value="1"> <span>Demande <strong>urgente</strong></span></label>
          <button class="btn btn-primary btn-lg mt-1" style="width:100%" type="submit" name="then" value="submit"><?= icon('send', 18) ?> Envoyer ma demande</button>
          <p class="muted mt-1" style="font-size:.82rem">Votre demande sera regroupée avec celles de vos collègues par fournisseur avant l'émission du bon de commande.</p>
        </div>
      </div>
    </div>
  </div>
</form>
<?php endif; ?>
