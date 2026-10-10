<?php
/** Console : codes d'accès gratuit et bons de réduction, saisis par les clients dans Paramètres → Abonnement. */
$codes = platform_codes();
usort($codes, fn($a, $b) => strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? '')));
$today = date('Y-m-d');
?>
<h1>Codes d'accès gratuit et bons de réduction</h1>
<p class="muted">Le client saisit le code dans <b>Paramètres → Abonnement</b> : un accès gratuit prolonge aussitôt sa licence ; une réduction s'applique à son abonnement (et à son paiement en ligne). Chaque code ne sert qu'une fois par client.</p>
<form method="post" class="card" autocomplete="off"><?= csrf_field() ?><input type="hidden" name="action" value="code_save">
  <h2>Nouveau code</h2>
  <div class="grid2">
    <div><label>Code <small class="muted">(vide : généré)</small></label><input name="code" maxlength="40" placeholder="ex : BIENVENUE-2026" style="text-transform:uppercase"></div>
    <div><label>Type</label><select name="kind" data-kind><option value="discount">Bon de réduction</option><option value="free">Accès gratuit</option></select></div>
    <div data-for="free" hidden><label>Jours offerts</label><input type="number" name="days" min="1" max="1095" value="30"></div>
    <div data-for="discount"><label>Réduction</label><div class="row" style="flex-wrap:nowrap"><input name="value" inputmode="decimal" value="20" style="max-width:120px"><select name="unit" style="max-width:200px"><option value="percent">%</option><option value="amount">€ HT par mois</option></select></div></div>
    <div data-for="discount"><label>Pendant <small class="muted">(mois ; 0 = sans limite de durée)</small></label><input type="number" name="months" min="0" max="120" value="3"></div>
    <div><label>Utilisable à partir du</label><input type="date" name="valid_from" value="<?= e($today) ?>"></div>
    <div><label>Jusqu'au <small class="muted">(vide : sans date de fin)</small></label><input type="date" name="valid_until"></div>
    <div><label>Nombre maximal d'utilisations <small class="muted">(0 = illimité)</small></label><input type="number" name="max_uses" min="0" value="0"></div>
    <div><label>Note interne</label><input name="note" maxlength="200" placeholder="ex : salon santé 2026, partenaire…"></div>
  </div>
  <details><summary class="muted">Réserver à certains clients (facultatif)</summary>
    <?php foreach ($registry as $slug => $i): ?><label class="check"><input type="checkbox" name="only[]" value="<?= e($slug) ?>"> <?= e($i['name']) ?></label><?php endforeach; ?></details>
  <p><button class="btn primary">Créer le code</button></p>
</form>
<div class="card scroll">
  <table class="list">
    <tr><th>Code</th><th>Avantage</th><th>Validité</th><th>Utilisations</th><th></th></tr>
    <?php foreach ($codes as $c): $uses = $c['uses'] ?? []; $expired = !empty($c['valid_until']) && $today > $c['valid_until']; ?>
      <tr style="<?= empty($c['active']) || $expired ? 'opacity:.6' : '' ?>">
        <td><code><?= e($c['code']) ?></code><?= empty($c['active']) ? ' <span class="tag">désactivé</span>' : ($expired ? ' <span class="tag amber">expiré</span>' : '') ?><?= !empty($c['note']) ? '<br><small class="muted">' . e($c['note']) . '</small>' : '' ?></td>
        <td><?= $c['kind'] === 'free' ? '<span class="tag green">Accès gratuit</span> ' . (int)$c['days'] . ' jour(s)' : '<span class="tag">Réduction</span> ' . e(platform_discount_label($c)) ?>
          <?= !empty($c['only']) ? '<br><small class="muted">réservé à : ' . e(implode(', ', array_map(fn($s) => $registry[$s]['name'] ?? $s, (array)$c['only']))) . '</small>' : '' ?></td>
        <td><small><?= !empty($c['valid_from']) ? 'du ' . e(date('d/m/Y', strtotime($c['valid_from']))) : '' ?> <?= !empty($c['valid_until']) ? 'au ' . e(date('d/m/Y', strtotime($c['valid_until']))) : '· sans date de fin' ?></small></td>
        <td><?= count($uses) ?><?= (int)($c['max_uses'] ?? 0) ? ' / ' . (int)$c['max_uses'] : '' ?>
          <?php if ($uses): ?><br><small class="muted"><?= e(implode(', ', array_map(fn($u) => ($registry[$u['slug']]['name'] ?? $u['slug']) . ' (' . date('d/m/Y', strtotime($u['at'])) . ')', $uses))) ?></small><?php endif; ?></td>
        <td><div class="row">
          <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="code_toggle"><input type="hidden" name="code" value="<?= e($c['code']) ?>"><button class="btn sm"><?= !empty($c['active']) ? 'Désactiver' : 'Réactiver' ?></button></form>
          <form method="post" onsubmit="return confirm('Supprimer ce code ?')"><?= csrf_field() ?><input type="hidden" name="action" value="code_delete"><input type="hidden" name="code" value="<?= e($c['code']) ?>"><button class="btn sm danger">Supprimer</button></form>
        </div></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$codes): ?><tr><td colspan="5" class="muted">Aucun code pour l'instant.</td></tr><?php endif; ?>
  </table>
</div>
<script>
const k = document.querySelector('[data-kind]');
const sync = () => document.querySelectorAll('[data-for]').forEach(el => { el.hidden = el.dataset.for !== k.value; });
k.addEventListener('change', sync); sync();
</script>
