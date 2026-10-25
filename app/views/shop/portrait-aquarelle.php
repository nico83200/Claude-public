<?php
/**
 * Page dédiée au portrait aquarelle par IA (« Encre & Poudre ») : accroche, style expliqué, exemples,
 * étapes, créateur intégré (même bloc d'achat que la fiche produit), occasions, détails, garanties, FAQ.
 */
$frameColors = ['noir' => '#231f20', 'blanc' => '#f4f1ec', 'chene' => '#dcb68f'];
$examples = [
    ['alezan', 'Tornado', '2014', 'Alezan, liste blanche', 'chene'],
    ['bai', 'Ulysse', '2011', 'Bai, petite étoile', 'noir'],
    ['gris', 'Opale', '2016', 'Gris pommelé', 'blanc'],
    ['palomino', 'Caramel', '2019', 'Palomino, liste', 'chene'],
    ['noir', 'Sultan', '2009', 'Noir, étoile', 'blanc'],
    ['clair', 'Nuage', '', 'Gris clair, sans année', 'noir'],
];
$img = static fn (string $name) => url('assets/img/portrait-ia/' . $name . '.jpg');
use App\Services\PortraitStyles;
$styleKey ??= PortraitStyles::DEFAULT;
$isAquarelle = $styleKey === PortraitStyles::DEFAULT;
$style = PortraitStyles::STYLES[$styleKey];
$styles = PortraitStyles::enabled();
$styleUrl = static fn (string $key) => $key === PortraitStyles::DEFAULT ? url('portrait-aquarelle') : url('portrait-cheval/' . PortraitStyles::STYLES[$key]['slug']);
$exampleUrl = static fn (string $key) => ($ex = PortraitStyles::example($key)) ? url($ex) : null;
// Visuels du haut de page : l'exemple du style, entouré de deux autres styles quand ils existent
$heroMain = $isAquarelle ? $img('affiche-tornado') : ($exampleUrl($styleKey) ?? image_url($product['main_image']));
$others = array_values(array_filter(array_map($exampleUrl, array_diff(array_keys($styles), [$styleKey]))));
$heroBack = $isAquarelle || count($others) < 2 ? [$img('exemple-gris'), $img('exemple-bai')] : array_slice($others, 0, 2);
$rating = $reviewSummary['count'] > 0 ? $reviewSummary : ($globalRating['count'] >= 3 ? $globalRating : null);
$cameraIcon = '<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 8h3l1.5-2h7L17 8h3v11H4z"/><circle cx="12" cy="13" r="3.5"/></svg>';
$frameIcon = '<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="3" width="16" height="18" rx="1"/><rect x="7" y="6" width="10" height="9"/><path d="M9 18h6"/></svg>';
?>
<section class="lp-hero">
  <div class="container lp-hero-inner">
    <div class="lp-hero-text">
      <p class="eyebrow"><?= icon('sparkle', 14) ?> Nouveau au club</p>
      <h1><?= $stylePage['h1'] ?? PortraitStyles::PAGES['aquarelle']['h1'] ?></h1>
      <p class="lead"><?= e(($stylePage ?? PortraitStyles::PAGES['aquarelle'])['lead']) ?></p>
      <ul class="lp-checks">
        <li><?= icon('check', 16) ?> Aperçu offert avant de commander</li>
        <li><?= icon('check', 16) ?> Fidèle à sa robe et à ses marques</li>
        <li><?= icon('check', 16) ?> Imprimé en haute définition, encadré, prêt à accrocher</li>
      </ul>
      <div class="lp-cta">
        <a class="btn btn-primary btn-lg" href="#creer" data-lp-cta><?= icon('sparkle', 16) ?> Créer son portrait gratuitement</a>
        <a class="btn btn-ghost btn-lg" href="<?= $isAquarelle ? '#exemples' : '#styles' ?>">Voir des exemples</a>
      </div>
      <p class="lp-price">Aperçu gratuit · encadré dès <strong><?= e(money($minPrice)) ?></strong><?= $freeShipping ? ' · <strong>livraison offerte</strong>' : '' ?></p>
      <div class="lp-trust">
        <?php if ($rating): ?><span><?= stars((float) $rating['avg']) ?> <?= e(number_format((float) $rating['avg'], 1, ',', '')) ?>/5 · <?= (int) $rating['count'] ?> avis</span><?php endif; ?>
        <?php if ($portraitsCount >= 100): ?><span><?= icon('heart', 15) ?> Déjà <?= e(number_format($portraitsCount, 0, ',', ' ')) ?> portraits créés</span><?php endif; ?>
        <span><?= icon('lock', 15) ?> Paiement sécurisé</span>
        <span><?= icon('leaf', 15) ?> Imprimé en Europe</span>
      </div>
      <?php if ($christmas): ?><p class="lp-season">🎄 Pour Noël, commande avant le <strong><?= e($christmas) ?></strong></p><?php endif; ?>
    </div>
    <div class="lp-hero-visual" aria-hidden="true">
      <div class="lp-frame lp-frame-back lp-frame-1" style="--frame: <?= $frameColors['blanc'] ?>"><img src="<?= e($heroBack[0]) ?>" alt="" width="600" height="800"></div>
      <div class="lp-frame lp-frame-back lp-frame-2" style="--frame: <?= $frameColors['noir'] ?>"><img src="<?= e($heroBack[1]) ?>" alt="" width="600" height="800"></div>
      <div class="lp-frame lp-frame-main" style="--frame: <?= $frameColors['chene'] ?>"><img src="<?= e($heroMain) ?>" alt="" width="900" height="1200"></div>
      <span class="lp-chip lp-chip-1"><?= icon('sparkle', 14) ?> Prêt en quelques secondes</span>
      <span class="lp-chip lp-chip-2"><?= icon('heart', 14) ?> Aperçu offert</span>
    </div>
  </div>
</section>

<div class="lp-ribbon" aria-hidden="true">
  <div class="lp-ribbon-track">
    <?php for ($i = 0; $i < 2; $i++): ?>
      <span>Alezan</span><span>✦</span><span>Bai</span><span>✦</span><span>Gris pommelé</span><span>✦</span><span>Palomino</span><span>✦</span><span>Noir</span><span>✦</span><span>Pie</span><span>✦</span><span>Isabelle</span><span>✦</span><span>Appaloosa</span><span>✦</span><span>Poney ou cheval de trait</span><span>✦</span>
    <?php endfor; ?>
  </div>
</div>

<?php if ($isAquarelle): ?>
<section class="section" id="style">
  <div class="container lp-style">
    <figure class="lp-annotated">
      <img src="<?= e($img('affiche-tornado')) ?>" alt="Exemple de portrait « Encre & Poudre » : Tornado, 2014" width="900" height="1200" loading="lazy">
      <?php foreach ([[66.5, 23.5], [58.3, 14.8], [69.2, 36.0], [40.8, 47.5], [75, 80]] as $n => [$x, $y]): ?>
        <span class="lp-pin" style="left: <?= $x ?>%; top: <?= $y ?>%"><?= $n + 1 ?></span>
      <?php endforeach; ?>
    </figure>
    <div>
      <p class="script-accent">notre signature</p>
      <h2>Le style « Encre &amp; Poudre », <em>pensé pour lui</em></h2>
      <p class="lead">Nous avons imaginé un style moderne, lumineux et délicat, qui s'accorde à tous les intérieurs. Le trait fin dessine son caractère, l'aquarelle garde sa vraie robe, adoucie de nos tons poudrés.</p>
      <ol class="lp-legend">
        <li><strong>Son regard</strong><span>Travaillé avec soin : c'est à lui qu'on le reconnaît au premier coup d'œil.</span></li>
        <li><strong>Un trait fin à l'encre bordeaux</strong><span>Précis et élégant, comme le croquis d'une artiste.</span></li>
        <li><strong>Sa robe et ses marques</strong><span>Liste, étoile, pommelures : l'IA respecte ce qui le rend unique.</span></li>
        <li><strong>Une aquarelle poudrée</strong><span>Rose, beige et taupe pour une douceur qui ne se démode pas.</span></li>
        <li><strong>Son nom, son année</strong><span>En écriture manuscrite, sous le portrait : c'est sa signature.</span></li>
      </ol>
    </div>
  </div>
</section>

<section class="section section-blush" id="exemples">
  <div class="container">
    <div class="section-head section-head-center">
      <p class="script-accent">chaque robe a sa palette</p>
      <h2>Ils ont déjà <em>leur aquarelle</em></h2>
      <p>Alezan, bai, gris, palomino, noir… le style s'adapte à chaque cheval, et le cadre à ton intérieur.</p>
    </div>
    <div class="lp-gallery">
      <?php foreach ($examples as [$file, $name, $year, $robe, $frame]): ?>
        <figure class="lp-example">
          <div class="lp-frame" style="--frame: <?= $frameColors[$frame] ?>"><img src="<?= e($img('exemple-' . $file)) ?>" alt="Portrait aquarelle de <?= e($name) ?> (<?= e(mb_strtolower($robe)) ?>)" width="600" height="800" loading="lazy"></div>
          <figcaption><strong><?= e($name) ?></strong><?= $year ? ' · ' . e($year) : '' ?><span><?= e($robe) ?> · cadre <?= e(['noir' => 'noir', 'blanc' => 'blanc', 'chene' => 'chêne'][$frame]) ?></span></figcaption>
        </figure>
      <?php endforeach; ?>
    </div>
    <p class="lp-note">Exemples présentant le style. Ton portrait est créé d'après la photo de ton cheval.</p>
    <p class="lp-center"><a class="btn btn-primary btn-lg" href="#creer" data-lp-cta><?= icon('sparkle', 16) ?> À ton tour</a></p>
  </div>
</section>
<?php else: ?>
<section class="section" id="style">
  <div class="container lp-style">
    <figure class="lp-annotated">
      <?php if ($ex = $exampleUrl($styleKey)): ?>
        <img src="<?= e($ex) ?>" alt="Exemple de portrait <?= e(mb_strtolower($style['title'])) ?>" width="600" height="800" loading="lazy">
      <?php else: ?>
        <div class="ai-style-img ai-style-<?= e($styleKey) ?> lp-style-placeholder"><span><?= e($style['name']) ?></span></div>
      <?php endif; ?>
    </figure>
    <div>
      <p class="script-accent">le style</p>
      <h2><?= e($style['title']) ?>, <em>pensé pour lui</em></h2>
      <p class="lead"><?= e($style['tagline']) ?>. <?= e($style['pick']) ?>.</p>
      <ol class="lp-legend">
        <?php foreach ($stylePage['points'] as [$title, $text]): ?>
          <li><strong><?= e($title) ?></strong><span><?= e($text) ?></span></li>
        <?php endforeach; ?>
        <li><strong>Son nom, son année</strong><span>En écriture manuscrite, sous le portrait : c'est sa signature.</span></li>
      </ol>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if (count($styles) > 1): ?>
<section class="section<?= $isAquarelle ? '' : ' section-blush' ?>" id="styles">
  <div class="container">
    <div class="section-head section-head-center">
      <p class="script-accent">une photo, plusieurs artistes</p>
      <h2>Un cheval, <em><?= e(number_words(count($styles))) ?> styles</em></h2>
      <p>Avec la même photo, essaie les styles un à un et garde celui qui lui ressemble le plus… et qui te ressemble aussi.</p>
    </div>
    <div class="lp-styles">
      <?php foreach ($styles as $key => $s): $ex = $exampleUrl($key); ?>
        <a class="lp-style-card<?= $key === $styleKey ? ' is-current' : '' ?>" href="<?= e($key === $styleKey ? '#creer' : $styleUrl($key)) ?>">
          <span class="ai-style-img ai-style-<?= e($key) ?>"><?= $ex ? '<img src="' . e($ex) . '" alt="" width="300" height="400" loading="lazy">' : '<span>' . e($s['name']) . '</span>' ?></span>
          <strong><?= e($s['title']) ?></strong>
          <small><?= e($key === $styleKey ? 'Tu es ici · ' . $s['pick'] : $s['pick']) ?></small>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="section" id="comment">
  <div class="container">
    <div class="section-head section-head-center">
      <p class="script-accent">simple comme bonjour</p>
      <h2>Comment <em>ça marche ?</em></h2>
    </div>
    <ol class="lp-steps">
      <li>
        <span class="lp-step-icon"><?= $cameraIcon ?></span>
        <h3>Envoie sa photo</h3>
        <p>Une photo de téléphone suffit : nette, bien éclairée, avec sa tête bien visible.</p>
      </li>
      <li>
        <span class="lp-step-icon"><?= icon('sparkle', 26) ?></span>
        <h3>L'IA la redessine</h3>
        <p>En quelques secondes, son portrait apparaît dans le cadre. Pas convaincue ? Crée une autre version.</p>
      </li>
      <li>
        <span class="lp-step-icon"><?= $frameIcon ?></span>
        <h3>Personnalise et commande</h3>
        <p>Son nom, son année de naissance, la couleur du cadre et le format : tu vois le résultat en direct.</p>
      </li>
    </ol>
  </div>
</section>

<section class="section section-blush lp-creator" id="creer">
  <div class="container">
    <div class="section-head section-head-center">
      <p class="script-accent">à toi de jouer</p>
      <h2>Crée son portrait <em>maintenant</em></h2>
      <p>Aperçu offert et sans engagement · <?= (int) $perDay ?> créations par jour · environ 30 secondes</p>
    </div>
    <?php partial('product-main', compact('product', 'variants', 'personalization', 'reviewSummary', 'sizeGuide', 'delivery') + ['titleTag' => 'h2', 'previewFirst' => true, 'detailsOpen' => false, 'aiStyle' => $styleKey]); ?>
  </div>
</section>

<section class="section" id="occasions">
  <div class="container">
    <div class="section-head section-head-center">
      <p class="script-accent">le cadeau qui touche en plein cœur</p>
      <h2>Pour toi, pour elle, <em>pour lui</em></h2>
    </div>
    <div class="lp-occasions">
      <article><span><?= icon('heart', 22) ?></span><h3>Pour toi</h3><p>Parce qu'il mérite sa place au salon : ta fierté, chaque jour sous les yeux.</p></article>
      <article><span><?= icon('gift', 22) ?></span><h3>Pour une cavalière</h3><p>Anniversaire, Noël, Galop réussi : le cadeau dont elle parlera longtemps.</p></article>
      <article><span><?= icon('sparkle', 22) ?></span><h3>Pour dire merci</h3><p>À sa propriétaire, sa monitrice ou sa groom : un merci élégant pour celles qui prennent soin de lui.</p></article>
      <article><span><?= icon('leaf', 22) ?></span><h3>En souvenir</h3><p>Un hommage tout en douceur pour garder près de soi un compagnon parti trop tôt.</p></article>
    </div>
  </div>
</section>

<section class="section section-tight">
  <div class="container">
   <div class="lp-details">
    <div>
      <h3>Les cadres</h3>
      <ul class="lp-frames">
        <?php foreach ($frames as $name => $hex): ?>
          <li><span class="lp-swatch" style="background: <?= e($hex ?: '#ccc') ?>"></span><?= e($name) ?></li>
        <?php endforeach; ?>
      </ul>
      <p class="muted small">Cadre fin et moderne, livré prêt à accrocher.</p>
    </div>
    <div>
      <h3>Les formats</h3>
      <ul class="lp-formats">
        <?php foreach ($formats as $size => $price): ?>
          <li><span><?= e($size) ?></span><strong><?= e(money($price)) ?></strong></li>
        <?php endforeach; ?>
      </ul>
      <p class="muted small">Prix TTC, cadre compris.</p>
    </div>
    <div>
      <h3>La qualité</h3>
      <ul class="lp-quality">
        <li><?= icon('check', 15) ?> Papier d'art mat épais</li>
        <li><?= icon('check', 15) ?> Impression haute définition, sans filigrane</li>
        <li><?= icon('check', 15) ?> Fabriqué à la commande, rien que pour toi</li>
      </ul>
      <p class="muted small"><?= icon('calendar', 14) ?> Commandé aujourd'hui, reçu entre le <strong><?= e($delivery['from']) ?></strong> et le <strong><?= e($delivery['to']) ?></strong></p>
    </div>
   </div>
  </div>
</section>

<section class="lp-guarantees">
  <div class="container">
    <div><?= icon('sparkle', 22) ?><strong>Aperçu offert</strong><span>Tu ne commandes que s'il te plaît</span></div>
    <div><?= icon('lock', 22) ?><strong>Paiement sécurisé</strong><span>CB, Apple Pay, Google Pay</span></div>
    <div><?= icon('truck', 22) ?><strong>Livraison suivie</strong><span><?= $freeShipping ? 'Offerte pour ce tableau' : 'Numéro de suivi par e-mail' ?></span></div>
    <div><?= icon('refresh', 22) ?><strong>Un défaut ? On réimprime</strong><span>Casse ou défaut d'impression : c'est pour nous</span></div>
  </div>
</section>

<?php if ($reviewSummary['count'] > 0): ?>
<section class="section section-blush">
  <div class="container">
    <div class="section-head section-head-center">
      <p class="script-accent">elles l'ont adopté</p>
      <h2>Ce qu'elles <em>en pensent</em></h2>
      <p><?= stars((float) $reviewSummary['avg']) ?> <?= e(number_format($reviewSummary['avg'], 1, ',', '')) ?>/5 sur <?= (int) $reviewSummary['count'] ?> avis vérifiés</p>
    </div>
    <div class="lp-reviews">
      <?php foreach (array_slice($reviews, 0, 3) as $r): ?>
        <article class="review">
          <?= stars((float) $r['rating']) ?>
          <?php if ($r['title']): ?><h3><?= e($r['title']) ?></h3><?php endif; ?>
          <?php if ($r['body']): ?><p><?= nl2br(e($r['body'])) ?></p><?php endif; ?>
          <div class="review-meta"><strong><?= e($r['author_name']) ?></strong><?php if ($r['verified']): ?><span class="verified"><?= icon('check', 12) ?> Achat vérifié</span><?php endif; ?></div>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="section" id="faq">
  <div class="container lp-faq">
    <div class="section-head section-head-center">
      <p class="script-accent">tes questions</p>
      <h2>Questions <em>fréquentes</em></h2>
    </div>
    <?php foreach ($faq as $i => [$question, $answer]): ?>
      <details class="accordion" <?= $i === 0 ? 'open' : '' ?>>
        <summary><?= e($question) ?></summary>
        <div class="rich"><p><?= e($answer) ?></p></div>
      </details>
    <?php endforeach; ?>
    <p class="lp-center muted small">Une autre question ? <a href="<?= e(url('contact')) ?>">Écris-nous</a>, on te répond avec plaisir.</p>
  </div>
</section>

<section class="lp-final">
  <div class="container">
    <p class="script-accent">il n'attend plus que toi</p>
    <h2>Son portrait, <em>en quelques secondes</em></h2>
    <p>Aperçu offert, sans engagement · encadré dès <?= e(money($minPrice)) ?></p>
    <a class="btn btn-primary btn-lg" href="#creer" data-lp-cta><?= icon('sparkle', 16) ?> Créer son portrait</a>
    <?php if ($handDrawn): ?>
      <p class="lp-alt">Tu rêves d'un portrait dessiné à la main ? <a href="<?= e(url('produit/' . $handDrawn['slug'])) ?>">Découvre notre portrait illustré par une artiste</a></p>
    <?php endif; ?>
  </div>
</section>
