<?php use App\Services\Catalog; use App\Services\HomeImages; ?>
<p class="muted">Changez les visuels de la page d'accueil. Les photos sont automatiquement réduites (2000 px maximum) pour garder un site rapide. <a href="<?= e(url('/')) ?>" target="_blank">Voir la page d'accueil ↗</a> · Les textes du haut de page se modifient dans <a href="<?= e(url('admin/reglages')) ?>">Réglages</a>.</p>
<form method="post" action="<?= e(url('admin/accueil')) ?>" enctype="multipart/form-data">
  <?= csrf_field() ?>
  <div class="home-slots">
    <?php foreach (HomeImages::SLOTS as $slot => $s): $src = HomeImages::src($slot, $slot === 'portrait' ? $portraitDefault : null); ?>
      <section class="card home-slot">
        <h2><?= e($s['label']) ?></h2>
        <div class="home-slot-preview <?= $slot === 'hero' ? 'is-arch' : '' ?>" data-pick-preview="<?= $slot ?>">
          <?php if ($src): ?><img src="<?= e(image_url($src)) ?>" alt="" loading="lazy"><?php else: ?><span class="muted">Aucune image</span><?php endif; ?>
        </div>
        <p class="muted small"><?= e($s['help']) ?><?= HomeImages::isCustom($slot) ? ' <strong>· Image personnalisée</strong>' : ' · Image d\'origine' ?></p>
        <div class="pick-row">
          <label>Nouvelle image <input type="file" name="image_<?= $slot ?>" accept="image/jpeg,image/png,image/webp"></label>
          <button type="button" class="btn btn-sm" data-pick="<?= $slot ?>">Choisir une image du site</button>
          <input type="hidden" name="pick_<?= $slot ?>" value="" data-pick-input="<?= $slot ?>">
        </div>
        <label>Description de l'image (référencement, accessibilité) <input name="alt_<?= $slot ?>" value="<?= e((string) setting('home_alt_' . $slot, '')) ?>" placeholder="<?= e($s['alt']) ?>" maxlength="200"></label>
        <?php if (HomeImages::isCustom($slot)): ?><label class="checkbox"><input type="checkbox" name="reset_<?= $slot ?>" value="1"> Remettre l'image d'origine</label><?php endif; ?>
      </section>
    <?php endforeach; ?>
  </div>

  <section class="card mt">
    <h2>Visuels des collections</h2>
    <p class="muted small">Format carré — 1000 × 1000 px. Sans image choisie, la photo du premier produit de la collection est utilisée.</p>
    <div class="home-slots">
      <?php foreach ($categories as $c): $src = Catalog::categoryImage($c); ?>
        <div class="home-slot">
          <h3><?= e($c['name']) ?><?= $c['active'] ? '' : ' <small class="muted">(masquée)</small>' ?></h3>
          <div class="home-slot-preview" data-pick-preview="category_<?= (int) $c['id'] ?>"><?php if ($src): ?><img src="<?= e(image_url($src)) ?>" alt="" loading="lazy"><?php else: ?><span class="muted">Aucune image</span><?php endif; ?></div>
          <label>Nouvelle image <input type="file" name="image_category_<?= (int) $c['id'] ?>" accept="image/jpeg,image/png,image/webp"></label>
          <button type="button" class="btn btn-sm" data-pick="category_<?= (int) $c['id'] ?>">Choisir une image du site</button>
          <input type="hidden" name="pick_category_<?= (int) $c['id'] ?>" value="" data-pick-input="category_<?= (int) $c['id'] ?>">
          <?php if ($c['image']): ?><label class="checkbox"><input type="checkbox" name="reset_category_<?= (int) $c['id'] ?>" value="1"> Revenir à la photo du premier produit</label><?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <p class="mt"><button class="btn btn-primary">Enregistrer la page d'accueil</button></p>
</form>

<dialog class="media-dialog" data-media>
  <form method="dialog">
    <div class="card-head"><h2>Choisir une image du site</h2><button class="btn btn-sm" value="cancel">Fermer</button></div>
    <div class="media-tabs">
      <button type="button" class="btn btn-sm active" data-media-tab="uploads">Images téléversées (<?= count($library['uploads']) ?>)</button>
      <button type="button" class="btn btn-sm" data-media-tab="products">Photos des articles (<?= count($library['products']) ?>)</button>
    </div>
    <div class="media-grid" data-media-panel="uploads">
      <?php foreach ($library['uploads'] as $path): ?>
        <button type="button" class="media-item" data-media-path="<?= e($path) ?>" data-media-full="<?= e(image_url($path)) ?>"><img src="<?= e(thumb($path, 400)) ?>" alt="" loading="lazy"></button>
      <?php endforeach; ?>
      <?php if (!$library['uploads']): ?><p class="muted">Aucune image téléversée pour l'instant.</p><?php endif; ?>
    </div>
    <div class="media-grid" data-media-panel="products" hidden>
      <?php foreach ($library['products'] as $item): ?>
        <button type="button" class="media-item" data-media-path="<?= e($item['path']) ?>" data-media-full="<?= e(image_url($item['path'])) ?>" title="<?= e($item['name']) ?>"><img src="<?= e(thumb($item['path'], 400)) ?>" alt="" loading="lazy"><span><?= e($item['name']) ?></span></button>
      <?php endforeach; ?>
    </div>
  </form>
</dialog>
<style>
.pick-row { display: flex; flex-wrap: wrap; gap: .5rem; align-items: end; }
.pick-row label { flex: 1 1 200px; margin-bottom: .9rem; }
.pick-row .btn { margin-bottom: .9rem; }
.home-slot > .btn { margin-bottom: .9rem; }
.media-dialog { width: min(960px, 94vw); max-height: 88vh; border: 0; border-radius: 12px; padding: 1.2rem; }
.media-dialog::backdrop { background: rgba(30, 20, 25, .5); }
.media-tabs { display: flex; gap: .5rem; margin-bottom: 1rem; flex-wrap: wrap; }
.media-tabs .active { background: var(--navy); color: #fff; border-color: var(--navy); }
.media-grid[hidden] { display: none; }
.media-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: .7rem; }
.media-item { display: flex; flex-direction: column; gap: .25rem; padding: .3rem; border: 2px solid transparent; border-radius: 8px; background: #f4f1ec; cursor: pointer; font: inherit; text-align: left; }
.media-item:hover, .media-item:focus-visible { border-color: var(--navy); }
.media-item img { width: 100%; aspect-ratio: 1; object-fit: cover; border-radius: 6px; }
.media-item span { font-size: .72rem; color: #74645a; line-height: 1.25; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
.pick-chosen { font-size: .8rem; font-weight: 600; color: var(--navy); }
</style>
<script>
(function () {
  var dialog = document.querySelector('[data-media]');
  if (!dialog || !dialog.showModal) return;
  var target = null;
  document.querySelectorAll('[data-pick]').forEach(function (b) {
    b.addEventListener('click', function () { target = b.dataset.pick; dialog.showModal(); });
  });
  dialog.querySelectorAll('[data-media-tab]').forEach(function (tab) {
    tab.addEventListener('click', function () {
      dialog.querySelectorAll('[data-media-tab]').forEach(function (t) { t.classList.toggle('active', t === tab); });
      dialog.querySelectorAll('[data-media-panel]').forEach(function (p) { p.hidden = p.dataset.mediaPanel !== tab.dataset.mediaTab; });
    });
  });
  dialog.querySelectorAll('[data-media-path]').forEach(function (item) {
    item.addEventListener('click', function () {
      if (!target) return;
      document.querySelector('[data-pick-input="' + target + '"]').value = item.dataset.mediaPath;
      var preview = document.querySelector('[data-pick-preview="' + target + '"]');
      preview.innerHTML = '<img src="' + item.dataset.mediaFull + '" alt="">';
      var note = preview.parentNode.querySelector('.pick-chosen') || document.createElement('p');
      note.className = 'pick-chosen';
      note.textContent = 'Image choisie : cliquez sur « Enregistrer la page d’accueil » pour l’appliquer.';
      preview.insertAdjacentElement('afterend', note);
      dialog.close();
    });
  });
})();
</script>
