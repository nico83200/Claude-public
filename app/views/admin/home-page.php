<?php use App\Services\Catalog; use App\Services\HomeImages; ?>
<p class="muted">Changez les visuels de la page d'accueil. Les photos sont automatiquement réduites (2000 px maximum) pour garder un site rapide. <a href="<?= e(url('/')) ?>" target="_blank">Voir la page d'accueil ↗</a> · Les textes du haut de page se modifient dans <a href="<?= e(url('admin/reglages')) ?>">Réglages</a>.</p>
<form method="post" action="<?= e(url('admin/accueil')) ?>" enctype="multipart/form-data">
  <?= csrf_field() ?>
  <div class="home-slots">
    <?php foreach (HomeImages::SLOTS as $slot => $s): $src = HomeImages::src($slot, $slot === 'portrait' ? $portraitDefault : null); ?>
      <section class="card home-slot">
        <h2><?= e($s['label']) ?></h2>
        <div class="home-slot-preview <?= $slot === 'hero' ? 'is-arch' : '' ?>">
          <?php if ($src): ?><img src="<?= e(image_url($src)) ?>" alt="" loading="lazy"><?php else: ?><span class="muted">Aucune image</span><?php endif; ?>
        </div>
        <p class="muted small"><?= e($s['help']) ?><?= HomeImages::isCustom($slot) ? ' <strong>· Image personnalisée</strong>' : ' · Image d\'origine' ?></p>
        <label>Nouvelle image <input type="file" name="image_<?= $slot ?>" accept="image/jpeg,image/png,image/webp"></label>
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
          <div class="home-slot-preview"><?php if ($src): ?><img src="<?= e(image_url($src)) ?>" alt="" loading="lazy"><?php else: ?><span class="muted">Aucune image</span><?php endif; ?></div>
          <label>Nouvelle image <input type="file" name="image_category_<?= (int) $c['id'] ?>" accept="image/jpeg,image/png,image/webp"></label>
          <?php if ($c['image']): ?><label class="checkbox"><input type="checkbox" name="reset_category_<?= (int) $c['id'] ?>" value="1"> Revenir à la photo du premier produit</label><?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <p class="mt"><button class="btn btn-primary">Enregistrer la page d'accueil</button></p>
</form>
