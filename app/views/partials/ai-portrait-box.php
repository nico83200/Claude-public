<?php
/** @var array $cfg @var array $product — création du portrait IA puis nom et année (fiche produit) */
use App\Services\AiPortrait;

$remaining = AiPortrait::remaining();
$perDay = max(1, (int) config('ai.daily_per_visitor', 3));
?>
<div class="perso-box ai-box" data-ai-box data-ai-url="<?= e(url('portrait-ia')) ?>" data-product-id="<?= (int) $product['id'] ?>">
  <p class="perso-title"><?= icon('sparkle', 16) ?> Ton portrait « Encre &amp; Poudre » <span class="pill pill-blush">Aperçu offert</span></p>

  <div class="ai-step" data-ai-start>
    <p class="ai-step-title"><span class="ai-num">1</span> Sa plus belle photo</p>
    <label class="photo-drop" data-photo-drop>
      <input type="file" accept="image/jpeg,image/png,image/webp" data-ai-photo>
      <span class="photo-thumb" data-photo-thumb hidden></span>
      <span class="photo-text">
        <strong data-photo-label>Ajoute la photo de ton cheval</strong>
        <small>Tête bien visible, cheval seul de préférence · JPG ou PNG, 20 Mo max.</small>
      </span>
    </label>
    <label class="ai-consent"><input type="checkbox" data-ai-consent>
      <span>J'accepte que ma photo soit transmise à notre service de dessin par IA pour créer le portrait (<a href="<?= e(url('page/confidentialite')) ?>" target="_blank">en savoir plus</a>).</span></label>
    <button type="button" class="btn btn-primary btn-block" data-ai-generate disabled><?= icon('sparkle', 16) ?> Créer mon portrait</button>
    <p class="ai-hint" data-ai-remaining data-per-day="<?= $perDay ?>">
      <?= $remaining > 0
        ? 'Environ 30 secondes · ' . $remaining . ' création' . ($remaining > 1 ? 's' : '') . ' offerte' . ($remaining > 1 ? 's' : '') . ' aujourd\'hui'
        : 'Tu as utilisé tes créations offertes du jour : reviens demain ♡' ?></p>
  </div>

  <div class="ai-progress" data-ai-progress role="status" aria-live="polite" hidden>
    <div class="ai-progress-bar"><span data-ai-bar></span></div>
    <p data-ai-progress-text>Analyse de la photo…</p>
  </div>

  <div class="ai-result" data-ai-result hidden>
    <img data-ai-thumb alt="Ton portrait" width="64" height="85">
    <div>
      <strong>Son portrait est prêt ♡</strong>
      <span class="small muted">Il te plaît ? Ajoute son nom ci-dessous.</span>
      <button type="button" class="link-button small" data-ai-retry>Créer une autre version</button>
    </div>
  </div>
  <p class="form-error" data-ai-error role="alert" hidden></p>
  <input type="hidden" name="ai_portrait" value="" data-ai-token>

  <div class="ai-step">
    <p class="ai-step-title"><span class="ai-num">2</span> Son nom<?= !empty($cfg['year_enabled']) ? ' et son année <span class="muted">(facultative)</span>' : '' ?></p>
    <div class="ai-fields">
      <div class="field">
        <label for="personalization"><?= e($cfg['label']) ?><?= empty($cfg['required']) ? ' <span class="muted">(facultatif)</span>' : '' ?></label>
        <input id="personalization" name="personalization" type="text" maxlength="<?= (int) $cfg['max_length'] ?>"
          <?= !empty($cfg['required']) ? 'required' : '' ?> placeholder="Ex. : Tornado" autocomplete="off" data-personalization>
      </div>
      <?php if (!empty($cfg['year_enabled'])): ?>
      <div class="field field-year">
        <label for="ai-year"><?= e($cfg['year_label']) ?></label>
        <input id="ai-year" name="year" type="text" inputmode="numeric" maxlength="4" pattern="(19|20)[0-9]{2}" placeholder="Ex. : 2014" autocomplete="off" data-ai-year-input>
      </div>
      <?php endif; ?>
    </div>
    <small><span data-char-count>0</span>/<?= (int) $cfg['max_length'] ?> caractères · imprimé exactement comme saisi, vérifie l'orthographe ♡</small>
  </div>
</div>
<noscript><p class="flash flash-error">Active JavaScript pour créer le portrait de ton cheval.</p></noscript>
