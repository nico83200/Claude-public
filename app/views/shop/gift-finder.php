<?php
$steps = [
    'pour'   => ['C\'est pour qui ?', ['elle' => ['Une cavalière', 'heart'], 'lui' => ['L\'homme d\'une cavalière', 'user'], '' => ['Peu importe', 'sparkle']]],
    'budget' => ['Quel budget ?', [20 => ['Moins de 20 €', 'gift'], 30 => ['Moins de 30 €', 'gift'], 50 => ['Moins de 50 €', 'gift'], 80 => ['Moins de 80 €', 'gift'], 0 => ['Sans limite', 'sparkle']]],
    'perso'  => ['Un cadeau personnalisé ?', ['oui' => ['Oui, à son nom ou avec son cheval', 'brush'], 'non' => ['Non, prêt à offrir', 'gift'], '' => ['Les deux me vont', 'sparkle']]],
];
$current = ['pour' => $who, 'budget' => $budget ?: ($done ? 0 : null), 'perso' => $perso];
?>
<section class="page-hero">
  <div class="container narrow center">
    <p class="script-accent">l'assistant cadeau</p>
    <h1>Le cadeau parfait <em>en 3 questions</em></h1>
    <p class="lead">Anniversaire, Noël, Galop réussi ou simple envie de faire plaisir : réponds à 3 questions, on s'occupe du reste.</p>
    <?php if ($deadline): ?><p class="pill pill-blush">🎄 Livré avant Noël pour toute commande passée avant le <?= e($deadline) ?></p><?php endif; ?>
  </div>
</section>
<section class="section section-tight">
  <div class="container">
    <form method="get" action="<?= e(url('idees-cadeaux')) ?>" class="gift-finder" data-gift-finder>
      <?php $n = 0; foreach ($steps as $name => [$question, $options]): $n++; ?>
        <fieldset class="gift-step" data-step="<?= $n ?>">
          <legend><span class="gift-step-num"><?= $n ?></span> <?= e($question) ?></legend>
          <div class="gf-options">
            <?php foreach ($options as $value => [$label, $ic]): ?>
              <label class="gf-option">
                <input type="radio" name="<?= $name ?>" value="<?= e((string) $value) ?>" <?= $current[$name] !== null && (string) $current[$name] === (string) $value && $done ? 'checked' : '' ?>>
                <span><?= icon($ic, 20) ?><?= e($label) ?></span>
              </label>
            <?php endforeach; ?>
          </div>
        </fieldset>
      <?php endforeach; ?>
      <button class="btn btn-primary btn-lg" type="submit"><?= icon('gift', 18) ?> Voir mes idées cadeaux</button>
    </form>

    <?php if ($done): ?>
      <div class="gift-results" id="resultats">
        <div class="section-head">
          <div><p class="script-accent">notre sélection</p><h2><?= count($results) ?> idée<?= count($results) > 1 ? 's' : '' ?> <em>pour faire plaisir</em></h2></div>
          <a class="link-arrow" href="<?= e(url('boutique', array_filter(['pour' => $who ?: null, 'prix_max' => $budget ?: null, 'personnalisable' => $perso === 'oui' ? 1 : null]))) ?>">Tout voir dans la boutique <?= icon('arrow', 16) ?></a>
        </div>
        <?php if ($relaxed): ?><p class="muted">Rien ne correspondait exactement : voici nos idées <?= e($relaxed) ?>.</p><?php endif; ?>
        <?php if ($results): ?>
          <div class="product-grid"><?php foreach ($results as $product) { partial('product-card', ['product' => $product]); } ?></div>
          <p class="gift-tip"><?= icon('gift', 18) ?> Au moment de commander, coche « C'est un cadeau » : ton petit mot sera glissé dans le colis, et tu peux le faire livrer directement chez la personne.</p>
        <?php else: ?>
          <div class="empty"><p>Aucun article pour l'instant. Découvre <a href="<?= e(url('boutique')) ?>">toute la boutique</a>.</p></div>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
</section>
