<?php
$moneyKeys = ['shipping_standard_price', 'shipping_free_threshold', 'shipping_express_price'];
$groups = [
    'Boutique' => ['shop_name' => 'Nom', 'shop_tagline' => 'Slogan', 'announcement' => 'Bandeau d\'annonce (vide = masqué)', 'hero_title' => 'Titre de l\'accueil', 'hero_subtitle' => 'Sous-titre de l\'accueil', 'home_meta_description' => 'Méta-description de l\'accueil'],
    'Contact & réseaux' => ['contact_email' => 'E-mail de contact (affiché)', 'contact_phone' => 'Téléphone', 'social_instagram' => 'Instagram (URL)', 'social_facebook' => 'Facebook (URL)', 'social_tiktok' => 'TikTok (URL)', 'social_pinterest' => 'Pinterest (URL)'],
    'Informations légales' => ['company_name' => 'Raison sociale', 'company_address' => 'Adresse du siège', 'company_siret' => 'SIRET', 'company_vat' => 'N° TVA intracommunautaire', 'publisher_name' => 'Directeur de la publication', 'mediator' => 'Médiateur de la consommation'],
    'Livraison' => ['shipping_standard_label' => 'Libellé standard', 'shipping_standard_price' => 'Prix standard (€)', 'shipping_standard_min' => 'Délai min. (jours ouvrés)', 'shipping_standard_max' => 'Délai max. (jours ouvrés)', 'shipping_free_threshold' => 'Livraison offerte dès (€, 0 = jamais)', 'shipping_express_label' => 'Libellé express', 'shipping_express_price' => 'Prix express (€, 0 = désactivé)', 'shipping_express_min' => 'Délai express min.', 'shipping_express_max' => 'Délai express max.'],
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
            <?php if (in_array($key, ['hero_subtitle', 'home_meta_description', 'packing_slip_message', 'company_address', 'mediator'], true)): ?>
              <textarea name="<?= e($key) ?>" rows="2"><?= e($value) ?></textarea>
            <?php else: ?>
              <input name="<?= e($key) ?>" value="<?= e(in_array($key, $moneyKeys, true) ? number_format(((int) $value) / 100, 2, ',', '') : $value) ?>">
            <?php endif; ?>
          </label>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endforeach; ?>
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
