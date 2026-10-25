<?php
$moneyKeys = ['welcome_amount', 'shipping_standard_price', 'shipping_free_threshold', 'shipping_express_price', 'gift_max_price'];
$toggles = ['christmas_banner', 'cart_recovery_enabled', 'popup_enabled', 'reviews_enabled', 'reviews_auto_publish', 'show_installments'];
$groups = [
    'Boutique' => ['shop_name' => 'Nom', 'shop_tagline' => 'Slogan', 'announcement' => 'Bandeau d\'annonce (messages séparés par | , vide = masqué)', 'hero_eyebrow' => 'Sur-titre de l\'accueil', 'hero_title' => 'Titre de l\'accueil (*mots en italique*)', 'hero_subtitle' => 'Sous-titre de l\'accueil', 'usp_marquee' => 'Bandeau défilant (arguments séparés par |)', 'gift_max_price' => 'Idées cadeaux : prix max (€)', 'home_meta_description' => 'Méta-description de l\'accueil'],
    'Conversion' => ['christmas_banner' => 'Compte à rebours « livrée avant Noël » dans le bandeau (du 1er novembre à la date limite)', 'cart_recovery_enabled' => 'Relance des paniers abandonnés (un e-mail, seulement aux clientes ayant accepté nos e-mails)', 'popup_enabled' => 'Popup de bienvenue (newsletter)', 'popup_delay' => 'Délai d\'apparition (secondes)', 'popup_title' => 'Titre de la popup', 'welcome_amount' => 'Cadeau de bienvenue (€) : un code personnel, utilisable une seule fois, est créé automatiquement dans Stripe pour chaque inscrite (0 = désactivé)', 'welcome_code' => 'Code commun de secours (utilisé seulement si Stripe n\'est pas configuré)', 'welcome_discount' => 'Avantage affiché avec le code de secours', 'show_installments' => 'Afficher « paiement en 3x » (Klarna activé dans Stripe)'],
    'Avis clients' => ['reviews_enabled' => 'Avis clients activés', 'review_request_days' => 'Demande d\'avis envoyée X jours après l\'expédition', 'reviews_auto_publish' => 'Publier les avis sans modération'],
    'Contact & réseaux' => ['contact_email' => 'E-mail de contact (affiché)', 'contact_phone' => 'Téléphone', 'social_instagram' => 'Instagram (URL)', 'social_facebook' => 'Facebook (URL)', 'social_tiktok' => 'TikTok (URL)', 'social_pinterest' => 'Pinterest (URL)'],
    'Informations légales' => ['company_name' => 'Raison sociale', 'company_address' => 'Adresse du siège', 'company_siret' => 'SIRET', 'company_vat' => 'N° TVA intracommunautaire', 'publisher_name' => 'Directeur de la publication', 'mediator' => 'Médiateur de la consommation'],
    'Livraison' => ['shipping_standard_label' => 'Libellé standard', 'shipping_standard_price' => 'Prix standard (€)', 'shipping_standard_min' => 'Délai min. (jours ouvrés)', 'shipping_standard_max' => 'Délai max. (jours ouvrés)', 'shipping_free_threshold' => 'Livraison offerte dès (€, 0 = jamais)', 'shipping_express_label' => 'Libellé express', 'shipping_express_price' => 'Prix express (€, 0 = désactivé)', 'shipping_express_min' => 'Délai express min.', 'shipping_express_max' => 'Délai express max.', 'production_min' => 'Fabrication min. (jours ouvrés)', 'production_max' => 'Fabrication max. (jours ouvrés)'],
    'Commandes' => ['order_prefix' => 'Préfixe des numéros', 'pending_order_ttl_hours' => 'Annulation des paniers non payés après (heures)', 'packing_slip_message' => 'Message sur le bon de livraison (200 car.)'],
];
?>
<form method="post" action="<?= e(url('admin/reglages')) ?>">
  <?= csrf_field() ?>
  <?php foreach ($groups as $title => $fields): ?>
    <section class="card">
      <h2><?= e($title) ?></h2>
      <div class="form-grid">
        <?php foreach ($fields as $key => $label): $value = (string) ($settings[$key] ?? ''); ?>
          <label><?= e($label) ?>
            <?php if (in_array($key, $toggles, true)): ?>
              <select name="<?= e($key) ?>"><option value="1" <?= $value === '1' ? 'selected' : '' ?>>Oui</option><option value="0" <?= $value !== '1' ? 'selected' : '' ?>>Non</option></select>
            <?php elseif (in_array($key, ['hero_subtitle', 'announcement', 'usp_marquee', 'home_meta_description', 'packing_slip_message', 'company_address', 'mediator'], true)): ?>
              <textarea name="<?= e($key) ?>" rows="2"><?= e($value) ?></textarea>
            <?php else: ?>
              <input name="<?= e($key) ?>" value="<?= e(in_array($key, $moneyKeys, true) ? number_format(((int) $value) / 100, 2, ',', '') : $value) ?>">
            <?php endif; ?>
          </label>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endforeach; ?>
  <section class="card" id="portrait-ia">
    <h2>Portrait IA</h2>
    <p>Les styles proposés (aquarelle, d'un seul trait, pop art, fusain, gravure vintage), leurs consignes envoyées à l'IA et leurs images d'exemple se règlent dans <a href="<?= e(url('admin/portrait-styles')) ?>">Styles du portrait</a>.</p>
  </section>

  <p class="muted small">Les codes promo se créent directement dans Stripe (Produits › Coupons › Codes promo) : le champ « code promo » est automatiquement proposé au paiement.</p>
  <button class="btn btn-primary btn-lg">Enregistrer les réglages</button>
</form>

<section class="card mt">
  <h2>Mot de passe administrateur</h2>
  <form method="post" action="<?= e(url('admin/reglages/mot-de-passe')) ?>" class="form-grid">
    <?= csrf_field() ?>
    <label>Mot de passe actuel <input type="password" name="current" required autocomplete="current-password"></label>
    <label>Nouveau mot de passe (10 car. min.) <input type="password" name="new" required minlength="10" autocomplete="new-password"></label>
    <button class="btn">Modifier</button>
  </form>
</section>
