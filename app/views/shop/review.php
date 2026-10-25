<section class="page-hero page-hero-sm">
  <div class="container narrow center">
    <p class="eyebrow">Commande <?= e($order['number']) ?></p>
    <h1>Ton avis <em>compte</em></h1>
    <p class="lead">Note tes articles en quelques secondes. Merci de partager ton expérience avec la communauté !</p>
  </div>
</section>
<section class="section section-tight">
  <div class="container narrow">
    <form method="post" action="<?= e(url('avis/' . rawurlencode($order['number']))) ?>" class="review-form" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <input type="hidden" name="t" value="<?= e($order['access_token']) ?>">
      <?php foreach ($products as $p): ?>
        <div class="review-item card-soft">
          <img src="<?= e(image_url($p['image'])) ?>" alt="" width="88" height="88">
          <div class="review-item-body">
            <h2 class="h4"><?= e($p['product_name']) ?></h2>
            <?php if ($p['review_id']): ?>
              <p class="muted">✓ Merci, ton avis a bien été reçu.</p>
            <?php else: ?>
              <fieldset class="star-input">
                <legend class="sr-only">Note pour <?= e($p['product_name']) ?></legend>
                <?php for ($i = 5; $i >= 1; $i--): ?>
                  <input type="radio" id="r<?= (int) $p['product_id'] ?>-<?= $i ?>" name="reviews[<?= (int) $p['product_id'] ?>][rating]" value="<?= $i ?>">
                  <label for="r<?= (int) $p['product_id'] ?>-<?= $i ?>" title="<?= $i ?> étoile<?= $i > 1 ? 's' : '' ?>">★</label>
                <?php endfor; ?>
              </fieldset>
              <input type="text" name="reviews[<?= (int) $p['product_id'] ?>][title]" maxlength="120" placeholder="Un titre (ex. : « Coup de cœur ! »)">
              <textarea name="reviews[<?= (int) $p['product_id'] ?>][body]" rows="3" maxlength="2000" placeholder="Qualité, taille, rendu de la personnalisation…"></textarea>
              <label class="review-photo"><?= icon('heart', 16) ?> <span>Ajoute une photo (facultatif) : ton article porté, ou avec ton cheval</span>
                <input type="file" name="review_photo[<?= (int) $p['product_id'] ?>]" accept="image/jpeg,image/png,image/webp"></label>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
      <div class="field">
        <label for="author_name">Nom affiché</label>
        <input id="author_name" name="author_name" value="<?= e($author) ?>" maxlength="80">
      </div>
      <button class="btn btn-primary btn-lg" type="submit">Envoyer mon avis</button>
      <p class="muted small mt">Les avis sont publiés avec ton prénom et l'initiale de ton nom, accompagnés de la mention « Achat vérifié ». Ils sont modérés uniquement pour écarter les contenus illicites ou hors sujet ; les avis négatifs sont publiés au même titre que les autres.</p>
    </form>
  </div>
</section>
