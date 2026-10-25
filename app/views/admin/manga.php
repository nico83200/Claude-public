<?php
/** @var ?string $rider @var ?string $horse @var ?string $reference @var bool $customReference @var string $provider */
use App\Services\MangaIllustration;

$examples = MangaIllustration::examples();
$custom = static fn (string $key) => trim((string) setting($key, '')) !== '';
?>
<section class="card">
  <div class="card-head"><h2>Exemples et illustration de référence</h2></div>
  <p class="muted small">Les exemples (de face, de dos) ouvrent la galerie du t-shirt sur la fiche produit. L'illustration de référence est envoyée à l'IA avec les deux photos de la cliente, uniquement pour fixer le style de la collection.
    <?php if ($provider !== 'replicate'): ?><br><strong>L'illustration manga nécessite Replicate (Intégrations › Portrait IA). Sans jeton, le site fonctionne en mode démonstration.</strong><?php endif; ?></p>

  <form method="post" action="<?= e(url('admin/illustration-manga/photos')) ?>" enctype="multipart/form-data" class="mg-photos">
    <?= csrf_field() ?>
    <div>
      <?php if ($rider): ?><img src="<?= $rider ?>" alt="Photo test de la cavalière" width="100"><?php endif; ?>
      <label>Photo test : cavalière <input type="file" name="rider" accept="image/jpeg,image/png,image/webp"></label>
    </div>
    <div>
      <?php if ($horse): ?><img src="<?= $horse ?>" alt="Photo test du cheval" width="100"><?php endif; ?>
      <label>Photo test : cheval <input type="file" name="horse" accept="image/jpeg,image/png,image/webp"></label>
    </div>
    <div>
      <?php if ($reference): ?><img src="<?= $reference ?>" alt="Illustration de référence" width="100"><?php endif; ?>
      <label>Illustration de référence <?= $customReference ? '' : '<span class="muted">(fournie avec le site)</span>' ?> <input type="file" name="reference" accept="image/jpeg,image/png,image/webp"></label>
      <?php if ($customReference): ?><label class="checkbox"><input type="checkbox" name="reference_reset" value="1" style="width:auto"> Revenir à l'illustration fournie</label><?php endif; ?>
    </div>
    <button class="btn">Enregistrer les images</button>
  </form>

  <?php if ($rider && $horse): ?>
  <div class="mg-grid" data-mg data-url="<?= e(url('admin/illustration-manga/generer')) ?>" data-status="<?= e(url('admin/illustration-manga/etat')) ?>">
    <?php foreach (MangaIllustration::VIEWS as $view => $label): ?>
      <figure class="mg-item" data-mg-item="<?= e($view) ?>">
        <div class="mg-img"><?= isset($examples[$view]) ? '<img src="' . e(url($examples[$view])) . '" alt="" width="200">' : '<span>Pas encore d\'exemple</span>' ?></div>
        <figcaption><strong><?= e($label) ?></strong><span class="muted small" data-mg-state></span>
          <button type="button" class="btn btn-sm" data-mg-one="<?= e($view) ?>">Générer</button></figcaption>
      </figure>
    <?php endforeach; ?>
  </div>
  <p class="muted small">Environ 30 secondes par vue. Gardez la page ouverte pendant la génération.</p>
  <?php endif; ?>
</section>

<form method="post" action="<?= e(url('admin/illustration-manga')) ?>">
  <?= csrf_field() ?>
  <section class="card">
    <h2>Consigne de base</h2>
    <p class="muted small"><?= $custom('ai_manga_prompt') ? '<strong>Consigne personnalisée.</strong>' : 'Consigne d\'origine.' ?> Photo 1 = cavalière, photo 2 = cheval, image 3 = illustration de référence (précision ajoutée automatiquement).</p>
    <textarea name="ai_manga_prompt" rows="18" class="mono" maxlength="12000"><?= e(MangaIllustration::basePrompt()) ?></textarea>
    <?php if ($custom('ai_manga_prompt')): ?><label class="checkbox"><input type="checkbox" name="reset[ai_manga_prompt]" value="1" style="width:auto"> Revenir à la consigne d'origine</label><?php endif; ?>
  </section>
  <section class="card">
    <h2>Partie variable selon la vue</h2>
    <div class="grid-2">
      <?php foreach (MangaIllustration::VIEWS as $view => $label): $key = 'ai_manga_' . $view; ?>
        <label><?= e($label) ?>
          <textarea name="<?= e($key) ?>" rows="5" class="mono"><?= e(MangaIllustration::viewPrompt($view)) ?></textarea>
          <?php if ($custom($key)): ?><span class="checkbox"><input type="checkbox" name="reset[<?= e($key) ?>]" value="1" style="width:auto"> Revenir à l'origine</span><?php endif; ?>
        </label>
      <?php endforeach; ?>
    </div>
    <label>Modèle Replicate (plusieurs images de référence)
      <input name="ai_manga_model" value="<?= e(MangaIllustration::model()) ?>" placeholder="<?= e(MangaIllustration::DEFAULT_MODEL) ?>"></label>
    <p class="muted small">Par défaut <code><?= e(MangaIllustration::DEFAULT_MODEL) ?></code> (Gemini) : il reçoit la photo de la cavalière, celle du cheval et l'illustration de référence en une seule fois.</p>
  </section>
  <button class="btn btn-primary btn-lg">Enregistrer</button>
</form>

<style>
.mg-photos { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 1rem; align-items: end; margin-bottom: 1rem; }
.mg-photos img { display: block; border-radius: 8px; margin-bottom: .4rem; }
.mg-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 1rem; }
.mg-item { margin: 0; }
.mg-img { aspect-ratio: 2 / 3; border-radius: 8px; overflow: hidden; background: #f4f1ec; display: grid; place-items: center; font-size: .8rem; color: #8c7a6b; }
.mg-img img { width: 100%; height: 100%; object-fit: cover; }
.mg-item figcaption { display: flex; flex-direction: column; gap: .3rem; margin-top: .4rem; align-items: flex-start; }
.mg-item.is-busy .mg-img { opacity: .5; }
</style>
<script>
(function () {
  var box = document.querySelector('[data-mg]');
  if (!box) return;
  var token = document.querySelector('input[name="_token"]').value;
  var setState = function (view, text) { box.querySelector('[data-mg-item="' + view + '"] [data-mg-state]').textContent = text; };
  var follow = function (view, state, started) {
    if (state.status === 'ready') {
      box.querySelector('[data-mg-item="' + view + '"] .mg-img').innerHTML = '<img src="' + state.example + '" alt="" width="200">';
      setState(view, 'Exemple enregistré ✓');
      return Promise.resolve();
    }
    if (state.status === 'failed') { setState(view, 'Échec : ' + state.message); return Promise.resolve(); }
    if (Date.now() - started > 240000) { setState(view, 'Délai dépassé, réessayez.'); return Promise.resolve(); }
    return new Promise(function (r) { setTimeout(r, 3000); })
      .then(function () { return fetch(box.dataset.status + '/' + state.token, { credentials: 'same-origin' }).then(function (r) { return r.json(); }); })
      .then(function (next) { return follow(view, next, started); });
  };
  box.querySelectorAll('[data-mg-one]').forEach(function (b) {
    b.addEventListener('click', function () {
      var view = b.dataset.mgOne;
      var item = box.querySelector('[data-mg-item="' + view + '"]');
      item.classList.add('is-busy');
      b.disabled = true;
      setState(view, 'Dessin en cours…');
      var data = new FormData();
      data.append('_token', token);
      data.append('view', view);
      fetch(box.dataset.url, { method: 'POST', body: data, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (state) { if (!state.ok) { setState(view, state.message); return; } return follow(view, state, Date.now()); })
        .catch(function () { setState(view, 'Erreur réseau, réessayez.'); })
        .then(function () { item.classList.remove('is-busy'); b.disabled = false; });
    });
  });
})();
</script>
