<?php
use App\Services\Catalog;
$persoCategory = null;
foreach ($categories as $c) {
    if ($c['slug'] === 'mon-cheval-ma-fierte') { $persoCategory = $c; }
}
$persoUrl = $persoCategory ? url('categorie/' . $persoCategory['slug']) : url('boutique', ['personnalisable' => 1]);
$usp = array_filter(array_map('trim', explode('|', (string) setting('usp_marquee'))));
?>
<section class="hero">
  <div class="container hero-inner">
    <div class="hero-text">
      <p class="eyebrow"><?= icon('heart', 14) ?> <?= e(setting('hero_eyebrow')) ?></p>
      <h1><?= emphasis(setting('hero_title')) ?></h1>
      <p class="hero-sub"><?= e(setting('hero_subtitle')) ?></p>
      <div class="hero-cta">
        <a class="btn btn-primary btn-lg" href="#collections">Découvrir les collections</a>
        <a class="btn btn-ghost btn-lg" href="<?= e($persoUrl) ?>"><?= icon('sparkle', 16) ?> Personnaliser pour mon cheval</a>
      </div>
      <div class="hero-trust">
        <?php if ($globalRating['count'] >= 5): ?>
          <span><?= stars($globalRating['avg']) ?> <strong><?= e(number_format($globalRating['avg'], 1, ',', '')) ?>/5</strong> · <?= (int) $globalRating['count'] ?> avis vérifiés</span>
        <?php endif; ?>
        <span><?= icon('truck', 16) ?> Livraison offerte dès <?= e(money(setting('shipping_free_threshold'))) ?></span>
        <span><?= icon('leaf', 16) ?> Imprimé à la demande en Europe</span>
      </div>
    </div>
    <div class="hero-visual">
      <div class="arch"><img src="<?= e(url('assets/img/hero.jpg')) ?>" alt="Sweat Crinière Club crème avec le logo brodé" width="480" height="600" fetchpriority="high"></div>
      <div class="float-card float-card-1"><?= icon('sparkle', 20) ?><span>Personnalisé pour<strong class="float-name">Tornado</strong></span></div>
      <div class="float-card float-card-2"><img src="<?= e(url('assets/img/brand/cheval.svg')) ?>" alt="" width="34" height="26"><span class="script-sm">Le club des passionnées</span></div>
    </div>
  </div>
</section>

<?php if ($usp): ?>
<div class="marquee" aria-hidden="true"><div class="marquee-track">
  <?php for ($i = 0; $i < 2; $i++): foreach ($usp as $u): ?><span><?= e($u) ?></span><?php endforeach; endfor; ?>
</div></div>
<?php endif; ?>

<?php if ($categories): ?>
<section class="section" id="collections">
  <div class="container">
    <div class="section-head section-head-center">
      <p class="script-accent">nos collections</p>
      <h2>Trois façons de <em>vivre votre passion</em></h2>
    </div>
    <div class="collections">
      <?php foreach ($categories as $i => $c): ?>
        <a class="collection-card" href="<?= e(url('categorie/' . $c['slug'])) ?>">
          <div class="collection-media"><img src="<?= e(image_url(Catalog::categoryImage($c))) ?>" alt="" loading="lazy" width="600" height="600"></div>
          <div class="collection-body">
            <span class="collection-index">0<?= $i + 1 ?></span>
            <h3><?= e($c['name']) ?></h3>
            <p><?= e($c['description']) ?></p>
            <span class="link-arrow">Découvrir <?= icon('arrow', 16) ?></span>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="section section-blush">
  <div class="container">
    <div class="section-head">
      <div><p class="script-accent">les chouchous</p><h2>Les préférées <em>du club</em></h2></div>
      <a class="link-arrow" href="<?= e(url('boutique', ['tri' => 'bestsellers'])) ?>">Tout voir <?= icon('arrow', 16) ?></a>
    </div>
    <div class="product-grid">
      <?php foreach ($bestSellers as $product) { partial('product-card', ['product' => $product]); } ?>
    </div>
  </div>
</section>

<?php if (isset($modes['ai_portrait'])): ?>
<section class="section">
  <div class="container feature feature-reverse">
    <div>
      <p class="script-accent">nouveau au club</p>
      <h2>Son portrait aquarelle, <em>en quelques secondes</em></h2>
      <p class="lead">Envoyez sa photo : notre IA la redessine dans notre style « Encre &amp; Poudre », un trait fin bordeaux rehaussé d'aquarelle poudrée. Ajoutez son nom et son année de naissance, choisissez son cadre, et découvrez l'aperçu avant de commander.</p>
      <ul class="feature-checks">
        <li><?= icon('check', 16) ?> Aperçu offert, sans engagement</li>
        <li><?= icon('check', 16) ?> Imprimé en haute définition sur papier d'art</li>
        <li><?= icon('check', 16) ?> Cadre noir, blanc ou chêne, prêt à accrocher</li>
      </ul>
      <a class="btn btn-primary btn-lg" href="<?= e(url('portrait-aquarelle')) ?>"><?= icon('sparkle', 16) ?> Découvrir le portrait</a>
      <span class="feature-price">dès <?= e(money($modes['ai_portrait']['price'])) ?></span>
    </div>
    <a class="feature-visual" href="<?= e(url('portrait-aquarelle')) ?>"><img src="<?= e(image_url($modes['ai_portrait']['image'])) ?>" alt="Portrait aquarelle d'un cheval, encadré, avec son nom et son année de naissance" loading="lazy" width="600" height="600"></a>
  </div>
</section>
<?php endif; ?>

<section class="section">
  <div class="container feature">
    <div class="feature-visual"><img src="<?= e(url('assets/img/products/cheval-sweat-nom.jpg')) ?>" alt="Sweat personnalisé au nom du cheval" loading="lazy" width="600" height="600"></div>
    <div>
      <p class="script-accent">mon cheval, ma fierté</p>
      <h2>Une pièce unique, <em>à son image</em></h2>
      <p class="lead">Parce qu'il n'y a qu'un seul cheval comme le vôtre, chaque création se personnalise en quelques secondes, avec un aperçu en direct.</p>
      <div class="perso-ways">
        <?php foreach (['ai_portrait' => ['Son portrait aquarelle', 'sparkle', 'Redessiné par IA en quelques secondes, encadré'], 'text' => ['Son nom', 'brush', 'En écriture manuscrite, sur sweat, casquette…'], 'photo' => ['Sa photo', 'heart', 'Cadrée dans notre arche signature'], 'portrait' => ['Son portrait illustré', 'sparkle', 'Dessiné à la main d\'après votre photo']] as $mode => [$title, $ic, $desc]): ?>
          <?php if (isset($modes[$mode])): ?>
            <a class="perso-way" href="<?= e($mode === 'ai_portrait' ? url('portrait-aquarelle') : url('produit/' . $modes[$mode]['slug'])) ?>">
              <span class="ic"><?= icon($ic, 22) ?></span>
              <span><strong><?= e($title) ?></strong><small><?= e($desc) ?></small></span>
              <span class="perso-way-price">dès <?= e(money($modes[$mode]['price'])) ?></span>
            </a>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>
      <a class="btn btn-primary btn-lg" href="<?= e($persoUrl) ?>">Je crée sa pièce unique</a>
    </div>
  </div>
</section>

<section class="section section-tight">
  <div class="container">
    <div class="gift-banner">
      <div>
        <p class="eyebrow"><?= icon('gift', 14) ?> Les petites attentions</p>
        <h2>Le cadeau parfait <em>pour une cavalière</em></h2>
        <p>Anniversaire, Noël, Galop réussi, fin de stage… Des idées qui font mouche, à moins de <?= e(money($giftMax)) ?>.</p>
        <a class="btn btn-primary" href="<?= e(url('boutique', ['prix_max' => $giftMax / 100])) ?>">Voir les idées cadeaux</a>
      </div>
      <div class="gift-tags">
        <?php foreach (['Pour son anniversaire', 'Pour Noël', 'Pour un Galop réussi', 'Pour sa monitrice', 'Pour la propriétaire'] as $tag): ?>
          <a href="<?= e(url('boutique', ['prix_max' => $giftMax / 100])) ?>"><?= e($tag) ?></a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>

<?php if ($testimonials): ?>
<section class="section">
  <div class="container">
    <div class="section-head section-head-center">
      <p class="script-accent">elles en parlent</p>
      <h2>La parole <em>aux membres du club</em></h2>
      <?php if ($globalRating['count'] > 0): ?><p><?= stars($globalRating['avg']) ?> <?= e(number_format($globalRating['avg'], 1, ',', '')) ?>/5 sur <?= (int) $globalRating['count'] ?> avis vérifiés</p><?php endif; ?>
    </div>
    <div class="testimonials">
      <?php foreach (array_slice($testimonials, 0, 3) as $t): $timg = json_field($t['product_images'])[0] ?? null; ?>
        <figure class="testimonial">
          <?= stars((float) $t['rating']) ?>
          <blockquote>« <?= e(excerpt($t['body'], 220)) ?> »</blockquote>
          <footer>
            <img src="<?= e(image_url($timg)) ?>" alt="" width="44" height="44" loading="lazy">
            <span><strong><?= e($t['author_name']) ?></strong><a href="<?= e(url('produit/' . $t['product_slug'])) ?>"><?= e($t['product_name']) ?></a><br><span class="verified"><?= icon('check', 12) ?> Achat vérifié</span></span>
          </footer>
        </figure>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="section">
  <div class="container">
    <div class="club">
      <div>
        <img class="club-logo" src="<?= e(url('assets/img/brand/logo-signature.svg')) ?>" alt="Crinière Club — le club des passionnées" width="320" height="128">
      </div>
      <div>
        <h2>Rejoignez <em>le club</em></h2>
        <?php if (setting('welcome_code')): ?>
          <p class="club-offer"><?= e(setting('welcome_discount')) ?> sur votre première commande</p>
        <?php endif; ?>
        <p>Nouveautés en avant-première, éditions limitées, idées cadeaux et petites surprises réservées aux membres. Pas plus d'un ou deux e-mails par mois.</p>
        <form action="<?= e(url('newsletter')) ?>" method="post" data-newsletter>
          <?= csrf_field() ?>
          <input type="hidden" name="source" value="footer">
          <input type="text" name="website" class="hp" tabindex="-1" autocomplete="off" aria-hidden="true">
          <label class="sr-only" for="club-email">Adresse e-mail</label>
          <div class="input-group"><input id="club-email" type="email" name="email" required placeholder="votre@email.fr" autocomplete="email"><button class="btn btn-primary" type="submit">Je rejoins le club</button></div>
          <label class="checkbox small"><input type="checkbox" name="consent" value="1" required> <span>J'accepte de recevoir la newsletter (désinscription en un clic).</span></label>
          <p class="small" data-newsletter-message role="status"></p>
        </form>
      </div>
    </div>
  </div>
</section>
