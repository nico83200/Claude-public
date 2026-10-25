<?php
/** @var array $enabled @var ?string $testPhoto @var string $provider */
use App\Services\PortraitStyles;
?>
<section class="card">
  <div class="card-head"><h2>Images d'exemple des styles</h2></div>
  <p class="muted small">Les exemples s'affichent dans le choix du style sur la fiche produit et sur les pages de chaque style. Ils sont créés avec la même consigne et le même service que pour les clientes : c'est aussi le meilleur moyen de vérifier qu'un style garde bien la ressemblance du cheval.
    <?php if ($provider === 'demo'): ?><br><strong>Le service d'IA n'est pas configuré (mode démonstration) : renseignez le jeton Replicate dans Intégrations › Portrait IA.</strong><?php endif; ?></p>

  <form method="post" action="<?= e(url('admin/portrait-styles/photo')) ?>" enctype="multipart/form-data" class="ps-photo">
    <?= csrf_field() ?>
    <?php if ($testPhoto): ?><img src="<?= $testPhoto ?>" alt="Photo test" width="120"><?php endif; ?>
    <div>
      <label>Photo test du cheval <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" required></label>
      <button class="btn"><?= $testPhoto ? 'Remplacer la photo test' : 'Enregistrer la photo test' ?></button>
    </div>
  </form>

  <?php if ($testPhoto): ?>
  <div class="ps-grid" data-ps data-url="<?= e(url('admin/portrait-styles/generer')) ?>" data-status="<?= e(url('admin/portrait-styles/etat')) ?>">
    <?php foreach (PortraitStyles::STYLES as $key => $style): $example = PortraitStyles::example($key); ?>
      <figure class="ps-item" data-ps-item="<?= e($key) ?>">
        <div class="ps-img"><?= $example ? '<img src="' . e(url($example)) . '" alt="" width="150" height="200">' : '<span>Pas encore d\'exemple</span>' ?></div>
        <figcaption><strong><?= e($style['title']) ?></strong><span class="muted small" data-ps-state></span>
          <button type="button" class="btn btn-sm" data-ps-one="<?= e($key) ?>">Générer</button></figcaption>
      </figure>
    <?php endforeach; ?>
  </div>
  <button type="button" class="btn btn-primary mt" data-ps-all>Générer les 5 exemples</button>
  <p class="muted small">Environ 30 secondes par style. Gardez la page ouverte pendant la génération.</p>
  <?php endif; ?>
</section>

<form method="post" action="<?= e(url('admin/portrait-styles')) ?>">
  <?= csrf_field() ?>
  <section class="card">
    <h2>Détourage avant le dessin</h2>
    <label class="checkbox"><input type="checkbox" name="ai_isolate" value="1" <?= App\Services\AiPortrait::isolates() ? 'checked' : '' ?>> Supprimer le fond et ne garder que la tête et l'encolure avant de dessiner</label>
    <p class="muted small">L'IA fait d'abord une première passe qui recadre la photo sur la tête et l'encolure, sur fond blanc (décor, personnes, corps et jambes retirés), puis redessine ce portrait dans le style choisi. Résultat plus propre, surtout avec une photo du cheval en entier, mais deux appels Replicate par création (environ le double du coût et quelques secondes de plus). <strong>Replicate uniquement</strong> : avec OpenAI, la consigne de chaque style demande déjà de ne garder que la tête et l'encolure, en un seul appel.</p>
  </section>
  <?php foreach (PortraitStyles::STYLES as $key => $style):
      $custom = trim((string) setting(PortraitStyles::settingKey($key), '')) !== ''; ?>
    <section class="card">
      <div class="card-head">
        <h2><?= e($style['title']) ?></h2>
        <label class="checkbox"><input type="checkbox" name="styles[]" value="<?= e($key) ?>" <?= in_array($key, $enabled, true) ? 'checked' : '' ?>> Proposé aux clientes</label>
      </div>
      <p class="muted small"><?= e($style['tagline']) ?> · page dédiée : <a href="<?= e(url('portrait-cheval/' . $style['slug'])) ?>" target="_blank">/portrait-cheval/<?= e($style['slug']) ?> ↗</a>
        · <?= $custom ? '<strong>Consigne personnalisée.</strong>' : 'Consigne d\'origine.' ?></p>
      <label class="full">Consigne envoyée à l'IA (en anglais de préférence)
        <textarea name="prompt[<?= e($key) ?>]" rows="8" class="mono" maxlength="6000"><?= e(PortraitStyles::prompt($key)) ?></textarea>
      </label>
      <?php if ($custom): ?>
        <label class="checkbox"><input type="checkbox" name="reset[<?= e($key) ?>]" value="1" style="width:auto"> Revenir à la consigne d'origine</label>
      <?php endif; ?>
    </section>
  <?php endforeach; ?>
  <p class="muted small">Chaque consigne commence par l'exigence de ressemblance (forme de la tête, robe, marques) : gardez-la si vous modifiez le style. Les signes particuliers cochés par la cliente sont ajoutés à la fin. Le nom et l'année sont imprimés ensuite sous le dessin : gardez « no text, no letters ».</p>
  <button class="btn btn-primary btn-lg">Enregistrer les styles</button>
</form>

<style>
.ps-photo { display: flex; gap: 1rem; align-items: flex-end; margin-bottom: 1rem; flex-wrap: wrap; }
.ps-photo img { border-radius: 8px; }
.ps-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 1rem; }
.ps-item { margin: 0; }
.ps-img { aspect-ratio: 3 / 4; border-radius: 8px; overflow: hidden; background: #f4f1ec; display: grid; place-items: center; text-align: center; font-size: .8rem; color: #8c7a6b; }
.ps-img img { width: 100%; height: 100%; object-fit: cover; }
.ps-item figcaption { display: flex; flex-direction: column; gap: .3rem; margin-top: .4rem; align-items: flex-start; }
.ps-item.is-busy .ps-img { opacity: .5; }
</style>
<script>
(function () {
  var box = document.querySelector('[data-ps]');
  if (!box) return;
  var token = document.querySelector('input[name="_token"]').value;
  var setState = function (key, text) { box.querySelector('[data-ps-item="' + key + '"] [data-ps-state]').textContent = text; };
  var wait = function (ms) { return new Promise(function (r) { setTimeout(r, ms); }); };
  var follow = function (key, state, started) {
    if (state.status === 'ready') {
      box.querySelector('[data-ps-item="' + key + '"] .ps-img').innerHTML = '<img src="' + state.example + '" alt="" width="150" height="200">';
      setState(key, 'Exemple enregistré ✓');
      return Promise.resolve();
    }
    if (state.status === 'failed') { setState(key, 'Échec : ' + state.message); return Promise.resolve(); }
    if (Date.now() - started > 240000) { setState(key, 'Délai dépassé, réessayez.'); return Promise.resolve(); }
    return wait(3000).then(function () {
      return fetch(box.dataset.status + '/' + state.token, { credentials: 'same-origin' }).then(function (r) { return r.json(); });
    }).then(function (next) { return follow(key, next, started); });
  };
  var generate = function (key) {
    var item = box.querySelector('[data-ps-item="' + key + '"]');
    item.classList.add('is-busy');
    setState(key, 'Dessin en cours…');
    var data = new FormData();
    data.append('_token', token);
    data.append('style', key);
    return fetch(box.dataset.url, { method: 'POST', body: data, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (state) {
        if (!state.ok) { setState(key, state.message); return; }
        return follow(key, state, Date.now());
      })
      .catch(function () { setState(key, 'Erreur réseau, réessayez.'); })
      .then(function () { item.classList.remove('is-busy'); });
  };
  box.querySelectorAll('[data-ps-one]').forEach(function (b) { b.addEventListener('click', function () { generate(b.dataset.psOne); }); });
  var all = document.querySelector('[data-ps-all]');
  all.addEventListener('click', function () {
    all.disabled = true;
    var keys = Array.prototype.map.call(box.querySelectorAll('[data-ps-item]'), function (i) { return i.dataset.psItem; });
    keys.reduce(function (p, key) { return p.then(function () { return generate(key); }); }, Promise.resolve())
      .then(function () { all.disabled = false; });
  });
})();
</script>
