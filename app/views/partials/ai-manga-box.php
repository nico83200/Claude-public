<?php
/** @var array $cfg @var array $product — illustration manga : photo de la cavalière, photo du cheval, vue, création */
use App\Services\AiPortrait;
use App\Services\MangaIllustration;

$remaining = AiPortrait::remaining();
$perDay = max(1, (int) config('ai.daily_per_visitor', 3));
?>
<div class="perso-box ai-box" data-manga-box data-url="<?= e(url('illustration-ia')) ?>" data-status-url="<?= e(url('portrait-ia')) ?>" data-product-id="<?= (int) $product['id'] ?>" data-base-image="<?= e($baseImage ?? '') ?>">
  <p class="perso-title"><?= icon('sparkle', 16) ?> Ton illustration manga, toi et ton cheval <span class="pill pill-blush">Aperçu offert</span></p>

  <div class="ai-step" data-manga-start>
    <p class="ai-step-title"><span class="ai-num">1</span> La vue</p>
    <div class="manga-views" role="radiogroup" aria-label="Vue de l'illustration">
      <?php foreach (MangaIllustration::VIEWS as $key => $label): ?>
        <label class="chip-check"><input type="radio" name="manga_view_choice" value="<?= e($key) ?>" <?= $key === 'dos' ? 'checked' : '' ?> data-manga-view> <span><?= e($label) ?></span></label>
      <?php endforeach; ?>
    </div>

    <p class="ai-step-title"><span class="ai-num">2</span> Vos deux photos</p>
    <div class="manga-photos">
      <label class="photo-drop" data-manga-drop>
        <input type="file" accept="image/jpeg,image/png,image/webp" data-manga-photo="rider">
        <span class="photo-thumb" hidden></span>
        <span class="photo-text"><strong>Ta photo</strong><small>En pied, de préférence</small></span>
      </label>
      <label class="photo-drop" data-manga-drop>
        <input type="file" accept="image/jpeg,image/png,image/webp" data-manga-photo="horse">
        <span class="photo-thumb" hidden></span>
        <span class="photo-text"><strong>Ton cheval</strong><small>En entier, bien visible</small></span>
      </label>
    </div>

    <label class="ai-consent"><input type="checkbox" data-manga-consent>
      <span>J'accepte que nos photos soient transmises à notre service de dessin par IA pour créer l'illustration (<a href="<?= e(url('page/confidentialite')) ?>" target="_blank">en savoir plus</a>).</span></label>
    <button type="button" class="btn btn-primary btn-block" data-manga-generate disabled><?= icon('sparkle', 16) ?> <span data-manga-generate-label>Créer notre illustration</span></button>
    <p class="ai-hint" data-manga-remaining>
      <?= $remaining > 0
        ? 'Environ 30 secondes · ' . $remaining . ' création' . ($remaining > 1 ? 's' : '') . ' offerte' . ($remaining > 1 ? 's' : '') . ' aujourd\'hui'
        : 'Tu as utilisé tes créations offertes du jour : reviens demain ♡' ?></p>
  </div>

  <div class="ai-progress" data-manga-progress role="status" aria-live="polite" hidden>
    <div class="ai-progress-bar"><span data-manga-bar></span></div>
    <p data-manga-progress-text>Analyse des photos…</p>
  </div>

  <div class="ai-result" data-manga-result hidden>
    <img data-manga-thumb alt="Ton illustration" width="64" height="96">
    <div>
      <strong>Votre illustration est prête ♡</strong>
      <span class="small muted" data-manga-result-view></span>
      <button type="button" class="link-button small" data-manga-retry>Essayer l'autre vue</button>
    </div>
  </div>
  <div class="ai-versions" data-manga-versions hidden>
    <p class="small muted">Tes créations · touche celle que tu préfères</p>
    <div class="ai-versions-list" data-manga-versions-list></div>
  </div>
  <p class="form-error" data-manga-error role="alert" hidden></p>
  <input type="hidden" name="ai_portrait" value="" data-manga-token>

  <div class="ai-step">
    <p class="ai-step-title"><span class="ai-num">3</span> Vos noms sous l'illustration <span class="muted">(facultatifs)</span></p>
    <div class="ai-fields">
      <div class="field">
        <label for="manga-rider">Ton prénom</label>
        <input id="manga-rider" name="rider_name" type="text" maxlength="16" placeholder="Ex. : Léa" autocomplete="given-name" data-manga-name>
      </div>
      <div class="field">
        <label for="manga-horse">Nom de ton cheval</label>
        <input id="manga-horse" name="horse_name" type="text" maxlength="16" placeholder="Ex. : Tornado" autocomplete="off" data-manga-name>
      </div>
    </div>
    <small>Imprimés en écriture manuscrite sous l'illustration : « <span data-manga-names-sample>Léa &amp; Tornado</span> » · vérifie l'orthographe ♡</small>
  </div>
</div>
<noscript><p class="flash flash-error">Active JavaScript pour créer ton illustration.</p></noscript>
