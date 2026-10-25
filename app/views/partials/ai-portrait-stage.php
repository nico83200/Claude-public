<?php
/**
 * Aperçu en direct du portrait IA encadré. La feuille reprend exactement la mise en page du fichier
 * d'impression (AiPortrait::LAYOUT) : repère 3600 × 4800, tailles de police GD converties en px (× 96/72).
 */
use App\Services\AiPortrait;

$L = AiPortrait::LAYOUT;
$W = 3600;
$H = 4800;
$pt = 96 / 72;
$geo = [
    'artX' => $L['art_x'] * $W, 'artY' => $L['art_y'] * $H, 'artW' => $L['art_w'] * $W, 'artH' => $L['art_h'] * $H,
    'nameY' => $L['name_y'] * $H, 'nameYAlone' => $L['name_y_alone'] * $H, 'nameSize' => $L['name_size'] * $H * $pt, 'nameMaxW' => $L['name_max_w'] * $W,
    'yearY' => $L['year_y'] * $H, 'yearSize' => $L['year_size'] * $H * $pt, 'yearSpacing' => $L['year_size'] * $H * $pt * 0.42,
    'ruleLen' => $L['year_rule'] * $W, 'ruleGap' => $W * 0.028, 'ruleThick' => max(2, round($H * 0.0011)),
];
?>
<div class="ai-stage" data-ai-stage data-geo="<?= e(json_encode($geo)) ?>" style="--frame: <?= e($firstColor ?: '#231f20') ?>" <?= empty($visible) ? 'hidden' : '' ?>>
  <div class="ai-frame" data-ai-frame>
    <svg viewBox="0 0 <?= $W ?> <?= $H ?>" role="img" aria-label="Aperçu de ton portrait encadré" data-ai-svg>
      <defs>
        <filter id="ai-soft" x="-10%" y="-10%" width="120%" height="120%"><feGaussianBlur stdDeviation="55"/></filter>
        <mask id="ai-feather" maskUnits="userSpaceOnUse" x="0" y="0" width="<?= $W ?>" height="<?= $H ?>">
          <rect data-ai-mask x="<?= $geo['artX'] + 90 ?>" y="<?= $geo['artY'] + 90 ?>" width="<?= $geo['artW'] - 180 ?>" height="<?= $geo['artH'] - 180 ?>" fill="#fff" filter="url(#ai-soft)"/>
        </mask>
      </defs>
      <rect width="<?= $W ?>" height="<?= $H ?>" fill="<?= AiPortrait::PAPER ?>"/>
      <image data-ai-art href="<?= e(asset('assets/img/products/portrait-ia-exemple.jpg')) ?>" x="<?= $geo['artX'] ?>" y="<?= $geo['artY'] ?>" width="<?= $geo['artW'] ?>" height="<?= $geo['artH'] ?>" preserveAspectRatio="xMidYMid meet" mask="url(#ai-feather)"/>
      <text data-ai-name x="1800" y="<?= $geo['nameY'] ?>" text-anchor="middle" font-family="Allura, cursive" font-size="<?= $geo['nameSize'] ?>" fill="<?= AiPortrait::INK ?>">Tornado</text>
      <text data-ai-year x="1800" y="<?= $geo['yearY'] ?>" text-anchor="middle" font-family="Montserrat, sans-serif" font-weight="600" font-size="<?= $geo['yearSize'] ?>" letter-spacing="<?= $geo['yearSpacing'] ?>" fill="<?= AiPortrait::TAUPE ?>">2014</text>
      <rect data-ai-rule="-1" y="0" width="<?= $geo['ruleLen'] ?>" height="<?= $geo['ruleThick'] ?>" fill="<?= AiPortrait::TAUPE ?>"/>
      <rect data-ai-rule="1" y="0" width="<?= $geo['ruleLen'] ?>" height="<?= $geo['ruleThick'] ?>" fill="<?= AiPortrait::TAUPE ?>"/>
    </svg>
  </div>
  <p class="ai-stage-note" data-ai-stage-note><?= icon('sparkle', 13) ?> Exemple de style · ton portrait apparaîtra ici</p>
</div>
