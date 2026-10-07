<?php
/** Étiquettes produits imprimables (page autonome, sans menu). */
$pad = $f['w'] < 65 ? 2 : 2.8;
$bandW = $band ? ($f['w'] < 65 ? 1.4 : 2) : 0;
$innerW = $f['w'] - 2 * $pad - $bandW;
[$fsName, $fsMeta, $barH] = $f['fs'];
$roll = !empty($f['roll']);
$perPage = $roll ? 1 : $f['cols'] * $f['rows'];
$cells = array_fill(0, $skip, null);
$warn = [];
foreach ($products as $p) {
    $code = trim((string)($p['barcode'] ?: $p['reference']));
    $bc = $code !== '' ? barcode_svg($code, $innerW, $barH) : null;
    if ($bc && !$bc['readable']) {
        $warn[] = $p['name'];
    }
    for ($i = 0; $i < $copies; $i++) {
        $cells[] = ['p' => $p, 'bc' => $bc];
    }
}
$pages = $cells ? array_chunk($cells, $perPage) : [];
$q = ['ids' => implode(',', $ids)];
?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Étiquettes · <?= e(app_name()) ?></title>
<link rel="icon" href="assets/brand/approvia-icon.svg">
<style>
  @page { size: <?= $roll ? $f['w'] . 'mm ' . $f['h'] . 'mm' : 'A4' ?>; margin: 0; }
  * { box-sizing: border-box; }
  html, body { margin: 0; padding: 0; }
  body { font-family: Inter, "Segoe UI", Roboto, Arial, sans-serif; color: #111; background: #e9ebf2; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  .toolbar { position: sticky; top: 0; z-index: 5; background: #fff; border-bottom: 1px solid #dde1ea; padding: 12px 16px; display: flex; flex-wrap: wrap; gap: 10px 14px; align-items: flex-end; font-size: 14px; box-shadow: 0 4px 18px rgba(15, 23, 42, .06); }
  .toolbar h1 { font-size: 17px; margin: 0 12px 0 0; align-self: center; }
  .toolbar label { display: flex; flex-direction: column; gap: 4px; font-weight: 600; font-size: 12px; color: #4b5470; }
  .toolbar select, .toolbar input[type=number], .toolbar input[type=text] { font: inherit; font-size: 14px; padding: 7px 9px; border: 1px solid #cfd4e0; border-radius: 9px; background: #fff; color: #111; min-height: 38px; }
  .toolbar input[type=number] { width: 80px; }
  .toolbar .check { flex-direction: row; align-items: center; min-height: 38px; font-weight: 500; color: #111; font-size: 14px; }
  .btn { font: inherit; font-weight: 600; font-size: 14px; border-radius: 10px; padding: 9px 14px; border: 1px solid #cfd4e0; background: #fff; color: #111; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; min-height: 38px; }
  .btn-primary { background: linear-gradient(135deg, #6366f1, #a855f7); color: #fff; border-color: transparent; }
  .spacer { flex: 1; }
  .hint { width: 100%; margin: 0; font-size: 12.5px; color: #5b6478; }
  .warn { width: 100%; margin: 0; font-size: 13px; color: #9a3412; background: #fff7ed; border: 1px solid #fed7aa; border-radius: 8px; padding: 6px 10px; }
  .pages { padding: 24px 16px 48px; display: flex; flex-direction: column; align-items: center; gap: 24px; }
  .page { position: relative; background: #fff; box-shadow: 0 6px 24px rgba(15, 23, 42, .14); overflow: hidden;
          width: <?= $roll ? $f['w'] : 210 ?>mm; height: <?= $roll ? $f['h'] : 297 ?>mm; flex: none; }
  .lbl { position: absolute; width: <?= $f['w'] ?>mm; height: <?= $f['h'] ?>mm; display: flex; overflow: hidden; }
  .screen .lbl { outline: 1px dashed #d4d8e2; outline-offset: -0.5px; }
  .band { width: <?= $bandW ?>mm; flex: none; }
  .in { flex: 1; min-width: 0; padding: <?= $pad ?>mm; display: flex; flex-direction: column; gap: <?= $f['h'] > 45 ? 1.6 : 0.8 ?>mm; }
  .top { display: flex; gap: 2mm; align-items: flex-start; }
  .name { flex: 1; min-width: 0; font-weight: 700; font-size: <?= $fsName ?>pt; line-height: 1.15; display: -webkit-box; -webkit-box-orient: vertical; -webkit-line-clamp: <?= $f['h'] > 45 ? 3 : 2 ?>; overflow: hidden; }
  .loc { flex: none; font-weight: 800; font-size: <?= $fsMeta + 1 ?>pt; border: 0.35mm solid #111; border-radius: 1.2mm; padding: 0.3mm 1.4mm; white-space: nowrap; }
  .meta { font-size: <?= $fsMeta ?>pt; line-height: 1.25; color: #222; overflow: hidden; display: -webkit-box; -webkit-box-orient: vertical; -webkit-line-clamp: 2; }
  .meta b { font-weight: 700; }
  .bc { margin-top: auto; display: flex; flex-direction: column; align-items: center; }
  .digits { font-family: "DejaVu Sans Mono", Menlo, Consolas, monospace; font-size: <?= max(6, $fsMeta) ?>pt; letter-spacing: .06em; line-height: 1.1; margin-top: .4mm; }
  .nocode { margin-top: auto; font-size: <?= $fsMeta ?>pt; color: #9a3412; }
  .empty { text-align: center; color: #5b6478; padding: 60px 16px; font-size: 15px; }
  @media print {
    body { background: #fff; }
    .toolbar { display: none; }
    .pages { padding: 0; gap: 0; display: block; }
    .page { box-shadow: none; break-after: page; page-break-after: always; }
    .page:last-child { break-after: auto; page-break-after: auto; }
    .screen .lbl { outline: none; }
  }
</style>
</head>
<body class="screen">
<form class="toolbar" method="get" action="index.php" id="label-form">
  <input type="hidden" name="r" value="labels">
  <?php if ($stockMode): ?><input type="hidden" name="stock" value="1"><?php else: ?><input type="hidden" name="ids" value="<?= e($q['ids']) ?>"><?php endif; ?>
  <a class="btn" href="<?= e($back) ?>">← Retour</a>
  <h1><?= count($products) ?> article<?= count($products) > 1 ? 's' : '' ?> · <?= count($cells) - $skip ?> étiquette<?= count($cells) - $skip > 1 ? 's' : '' ?></h1>
  <label>Format
    <select name="format"><?php foreach (LABEL_FORMATS as $k => $lf): ?><option value="<?= $k ?>" <?= $k === $format ? 'selected' : '' ?>><?= e($lf['label']) ?></option><?php endforeach; ?></select></label>
  <label>Exemplaires par article<input type="number" name="copies" min="1" max="200" value="<?= $copies ?>"></label>
  <?php if (!$roll): ?><label title="Pour réutiliser une planche déjà entamée">Commencer à l'étiquette n°<input type="number" name="skip" min="1" max="<?= $perPage ?>" value="<?= $skip + 1 ?>" data-skip></label><?php endif; ?>
  <label>Emplacement<input type="text" name="location" maxlength="40" value="<?= e($location) ?>" placeholder="auto : celui de l'inventaire"></label>
  <label class="check"><input type="hidden" name="band" value="0"><input type="checkbox" name="band" value="1" <?= $band ? 'checked' : '' ?>> Couleur de catégorie</label>
  <span class="spacer"></span>
  <button class="btn btn-primary" type="button" onclick="window.print()" <?= $pages ? '' : 'disabled' ?>>🖨 Imprimer</button>
  <p class="hint">À l'impression : échelle <strong>100 %</strong> (ou « taille réelle »), marges <strong>aucune</strong>, en-têtes et pieds de page désactivés<?= $roll ? ', format de papier ' . $f['w'] . ' × ' . $f['h'] . ' mm' : ', papier A4' ?>. Le code-barres reprend le code EAN de l'article (ou sa référence) : il se scanne avec l'appareil photo ou une douchette dans <?= e(app_name()) ?>.</p>
  <?php if ($warn): ?><p class="warn">Code trop long pour ce format, la lecture peut être difficile : <?= e(implode(', ', array_slice($warn, 0, 5))) ?><?= count($warn) > 5 ? '…' : '' ?>. Choisissez un format plus large.</p><?php endif; ?>
</form>
<div class="pages">
<?php if (!$pages): ?>
  <div class="empty">Aucun article à imprimer<?= $stockMode ? ' : aucun article n\'est suivi en stock dans ce centre.' : '.' ?></div>
<?php endif; ?>
<?php foreach ($pages as $page): ?>
  <div class="page">
  <?php foreach ($page as $i => $cell): if (!$cell) continue; $p = $cell['p'];
      $x = $roll ? 0 : $f['l'] + ($i % $f['cols']) * ($f['w'] + $f['gx']);
      $y = $roll ? 0 : $f['t'] + intdiv($i, $f['cols']) * ($f['h'] + $f['gy']); ?>
    <div class="lbl" style="left:<?= $x ?>mm;top:<?= $y ?>mm">
      <?php if ($band): ?><div class="band" style="background:<?= e($p['category_color'] ?: '#8b5cf6') ?>"></div><?php endif; ?>
      <div class="in">
        <?php $loc = $location !== '' ? $location : (string)($p['stock_location'] ?? ''); ?>
        <div class="top"><div class="name"><?= e($p['name']) ?></div><?php if ($loc !== ''): ?><div class="loc"><?= e($loc) ?></div><?php endif; ?></div>
        <div class="meta"><b><?= e($p['supplier_name']) ?></b><?= $p['reference'] ? ' · Réf. ' . e($p['reference']) : '' ?><?= $p['unit'] ? ' · ' . e($p['unit']) : '' ?></div>
        <?php if ($cell['bc']): ?>
          <div class="bc"><?= $cell['bc']['svg'] ?><div class="digits"><?= e($cell['bc']['text']) ?></div></div>
        <?php else: ?>
          <div class="nocode">Pas de code-barres ni de référence</div>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>
  </div>
<?php endforeach; ?>
</div>
<script>
  // Mise à jour de l'aperçu dès qu'une option change
  (function () {
    const f = document.getElementById('label-form');
    f.addEventListener('change', () => submit());
    f.addEventListener('submit', (e) => { e.preventDefault(); submit(); });
    function submit() { if (submit.done) return; submit.done = true; const s = f.querySelector('[data-skip]'); if (s) s.name = 'skip_ui'; if (s) { const h = document.createElement('input'); h.type = 'hidden'; h.name = 'skip'; h.value = Math.max(0, (parseInt(s.value, 10) || 1) - 1); f.appendChild(h); } f.submit(); }
    if (new URLSearchParams(location.search).get('print') === '1') window.addEventListener('load', () => setTimeout(() => window.print(), 300));
  })();
</script>
</body>
</html>
