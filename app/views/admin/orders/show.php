<?php use App\Core\View; use App\Services\OrderService; ?>
<p><a href="<?= e(url('admin/commandes')) ?>">← Toutes les commandes</a></p>
<div class="grid-main">
  <div>
    <section class="card">
      <div class="card-head">
        <h2>Articles</h2>
        <div><?= View::capture('admin/_status', ['order' => $order]) ?></div>
      </div>
      <table class="table">
        <?php foreach ($items as $item): ?>
          <tr>
            <td style="width:64px"><img src="<?= e(image_url($item['image'])) ?>" alt="" width="56" height="56" class="thumb"></td>
            <td>
              <strong><?= e($item['product_name']) ?></strong><br><span class="muted"><?= e($item['variant_name']) ?><?= $item['sku'] ? ' · ' . e($item['sku']) : '' ?></span>
              <?php if ($item['personalization_text']): ?><br><span class="perso">✦ « <?= e($item['personalization_text']) ?> »</span><?php endif; ?>
              <?php if (!empty($item['personalization_year'])): ?><br><span class="perso">✦ Année : <?= e($item['personalization_year']) ?></span><?php endif; ?>
              <?php if (!empty($item['ai_portrait'])): ?>
                <div class="photo-row">
                  <img src="<?= e(url('admin/portrait-ia/' . $item['ai_portrait'], ['vignette' => 1])) ?>" alt="Portrait IA" width="72" height="96">
                  <a class="small" href="<?= e(url('admin/portrait-ia/' . $item['ai_portrait'])) ?>">Télécharger le dessin IA (sans nom)</a>
                </div>
              <?php endif; ?>
              <?php if ($item['photo_file']): ?>
                <div class="photo-row">
                  <img src="<?= e(url('admin/photo/' . $item['photo_file'], ['vignette' => 1])) ?>" alt="Photo du cheval" width="72" height="72">
                  <a class="small" href="<?= e(url('admin/photo/' . $item['photo_file'])) ?>">Télécharger la photo originale</a>
                </div>
              <?php endif; ?>
              <?php if ($item['print_file_url']): ?><br><a class="small" href="<?= e($item['print_file_url']) ?>" target="_blank">Fichier d'impression ↗</a><?php endif; ?>
              <?php if ($item['personalization_mode'] === 'portrait' && in_array($order['status'], ['paid', 'in_production'], true) && in_array($order['fulfillment_status'], ['artwork', 'pending', 'error'], true)): ?>
                <form method="post" action="<?= e(url('admin/commandes/' . $order['id'] . '/portrait/' . $item['id'])) ?>" enctype="multipart/form-data" class="artwork-form">
                  <?= csrf_field() ?>
                  <label class="small"><?= $item['print_file_url'] ? 'Remplacer le portrait' : '🎨 Déposer le portrait final' ?> (PNG/JPG HD)
                    <input type="file" name="artwork" accept="image/png,image/jpeg" required></label>
                  <button class="btn btn-sm btn-primary">Enregistrer</button>
                </form>
              <?php endif; ?>
            </td>
            <td><?= (int) $item['quantity'] ?> × <?= e(money($item['unit_price'])) ?></td>
            <td class="right"><?= e(money($item['unit_price'] * $item['quantity'])) ?></td>
          </tr>
        <?php endforeach; ?>
        <tr><td></td><td>Sous-total</td><td></td><td class="right"><?= e(money($order['subtotal'])) ?></td></tr>
        <?php if ($order['discount']): ?><tr><td></td><td>Réduction</td><td></td><td class="right">−<?= e(money($order['discount'])) ?></td></tr><?php endif; ?>
        <tr><td></td><td>Livraison (<?= e($order['shipping_method']) ?>)</td><td></td><td class="right"><?= e(money($order['shipping'])) ?></td></tr>
        <tr><td></td><td><strong>Total payé</strong></td><td></td><td class="right"><strong><?= e(money($order['total'])) ?></strong></td></tr>
      </table>
    </section>

    <section class="card">
      <h2>Production & expédition</h2>
      <dl class="kv">
        <dt>Statut Printful</dt><dd><?= e(fulfillment_status_label($order['fulfillment_status'])) ?><?= $order['printful_order_id'] ? ' — <a href="https://www.printful.com/dashboard/default/orders?search=' . e($order['number']) . '" target="_blank">#' . (int) $order['printful_order_id'] . ' ↗</a>' : '' ?></dd>
        <?php if ($order['last_error']): ?><dt>Dernière erreur</dt><dd class="error-text"><?= e($order['last_error']) ?></dd><?php endif; ?>
        <?php if ($order['tracking_number']): ?><dt>Suivi</dt><dd><?= e($order['carrier']) ?> <?= $order['tracking_url'] ? '<a href="' . e($order['tracking_url']) . '" target="_blank">' . e($order['tracking_number']) . '</a>' : e($order['tracking_number']) ?></dd><?php endif; ?>
      </dl>
      <div class="actions">
        <?php if (in_array($order['fulfillment_status'], ['pending', 'error'], true) && in_array($order['status'], ['paid', 'in_production'], true)): ?>
          <form method="post" action="<?= e(url('admin/commandes/' . $order['id'] . '/transmettre')) ?>"><?= csrf_field() ?><button class="btn btn-primary">Transmettre à Printful</button></form>
        <?php endif; ?>
        <?php if ($order['fulfillment_status'] === 'draft'): ?>
          <form method="post" action="<?= e(url('admin/commandes/' . $order['id'] . '/confirmer-printful')) ?>" data-confirm="Confirmer la commande chez Printful ? Elle vous sera facturée et partira en production."><?= csrf_field() ?><button class="btn btn-primary">Confirmer chez Printful</button></form>
        <?php endif; ?>
        <?php if ($order['printful_order_id']): ?>
          <form method="post" action="<?= e(url('admin/commandes/' . $order['id'] . '/synchroniser')) ?>"><?= csrf_field() ?><button class="btn">Synchroniser le statut</button></form>
        <?php endif; ?>
      </div>
      <?php if (in_array($order['status'], ['paid', 'in_production'], true)): ?>
      <details class="mt">
        <summary>Saisir une expédition manuellement</summary>
        <form method="post" action="<?= e(url('admin/commandes/' . $order['id'] . '/expedier')) ?>" class="form-grid">
          <?= csrf_field() ?>
          <label>Transporteur <input name="carrier" placeholder="Colissimo"></label>
          <label>N° de suivi <input name="tracking_number"></label>
          <label>Lien de suivi <input name="tracking_url" type="url"></label>
          <button class="btn">Marquer comme expédiée et prévenir le client</button>
        </form>
      </details>
      <?php endif; ?>
    </section>

    <section class="card">
      <h2>Historique</h2>
      <form method="post" action="<?= e(url('admin/commandes/' . $order['id'] . '/note')) ?>" class="inline mb">
        <?= csrf_field() ?><input name="note" placeholder="Ajouter une note interne…" required><button class="btn">Ajouter</button>
      </form>
      <ul class="events">
        <?php foreach ($events as $ev): ?>
          <li class="ev-<?= e($ev['type']) ?>"><time><?= e(date_fr($ev['created_at'])) ?></time> <?= e($ev['message']) ?></li>
        <?php endforeach; ?>
      </ul>
    </section>
  </div>

  <div>
    <?php if (!empty($order['is_gift'])): ?>
    <section class="card">
      <h2>🎁 Commande cadeau</h2>
      <p><?= $order['gift_to'] ? 'Pour <strong>' . e($order['gift_to']) . '</strong>' : 'Destinataire non précisé' ?></p>
      <?php if ($order['gift_message']): ?><blockquote class="gift-quote"><?= nl2br(e($order['gift_message'])) ?></blockquote><?php endif; ?>
      <p class="muted small">Transmis à Printful : imprimé sur le bon de livraison.</p>
    </section>
    <?php endif; ?>
    <section class="card">
      <h2>Client</h2>
      <p><strong><?= e($order['customer_name'] ?: '—') ?></strong><br>
        <?php if ($order['email']): ?><a href="mailto:<?= e($order['email']) ?>"><?= e($order['email']) ?></a><br><?php endif; ?>
        <?= e($order['phone']) ?></p>
      <?php if ($address): ?>
        <h3>Livraison</h3>
        <p><?= e($address['name']) ?><br><?= e($address['line1']) ?><?= !empty($address['line2']) ? '<br>' . e($address['line2']) : '' ?><br><?= e($address['postal_code'] . ' ' . $address['city']) ?><br><?= e($address['country']) ?></p>
      <?php endif; ?>
      <p class="small"><a href="<?= e(OrderService::trackingUrl($order)) ?>" target="_blank">Page de suivi client ↗</a></p>
    </section>
    <section class="card">
      <h2>Paiement</h2>
      <dl class="kv">
        <dt>Créée</dt><dd><?= e(date_fr($order['created_at'])) ?></dd>
        <dt>Payée</dt><dd><?= e(date_fr($order['paid_at'])) ?></dd>
        <?php if ($order['stripe_payment_intent']): ?><dt>Stripe</dt><dd><a href="https://dashboard.stripe.com/payments/<?= e($order['stripe_payment_intent']) ?>" target="_blank"><?= e(substr($order['stripe_payment_intent'], 0, 18)) ?>… ↗</a></dd><?php endif; ?>
      </dl>
      <?php if ($order['stripe_payment_intent'] && $order['status'] !== 'refunded' && $order['status'] !== 'canceled'): ?>
        <form method="post" action="<?= e(url('admin/commandes/' . $order['id'] . '/rembourser')) ?>" class="form-grid" data-confirm="Confirmer le remboursement via Stripe ?">
          <?= csrf_field() ?>
          <label>Montant (vide = total) <input name="amount" placeholder="<?= e(number_format($order['total'] / 100, 2, ',', '')) ?>"></label>
          <button class="btn btn-danger">Rembourser</button>
        </form>
      <?php endif; ?>
    </section>

    <?php if (OrderService::canCancel($order) || OrderService::canDelete($order)): ?>
    <section class="card card-danger">
      <h2>Annuler ou supprimer</h2>
      <?php if (OrderService::canCancel($order)): ?>
        <details <?= $order['paid_at'] ? '' : 'open' ?>>
          <summary class="btn btn-danger">Annuler la commande</summary>
          <form method="post" action="<?= e(url('admin/commandes/' . $order['id'] . '/annuler')) ?>" class="mt" data-confirm="Annuler la commande <?= e($order['number']) ?> ?">
            <?= csrf_field() ?>
            <?php if ($order['printful_order_id'] && in_array($order['fulfillment_status'], ['draft', 'submitted'], true)): ?>
              <p class="small">La commande sera aussi annulée chez Printful (impossible si elle est déjà en production).</p>
            <?php endif; ?>
            <?php if ($order['paid_at'] && $order['stripe_payment_intent'] && $order['status'] !== 'refunded'): ?>
              <label class="checkbox"><input type="checkbox" name="refund" value="1" checked> Rembourser intégralement la cliente (<?= e(money($order['total'])) ?>) via Stripe</label>
            <?php endif; ?>
            <?php if ($order['paid_at'] && $order['email']): ?>
              <label class="checkbox"><input type="checkbox" name="notify" value="1" checked> Prévenir la cliente par e-mail</label>
            <?php endif; ?>
            <label>Motif (facultatif, inclus dans l'e-mail) <input name="reason" maxlength="300" placeholder="Ex. : à votre demande, rupture chez notre atelier…"></label>
            <button class="btn btn-danger">Confirmer l'annulation</button>
          </form>
        </details>
      <?php endif; ?>
      <?php if (OrderService::canDelete($order)): ?>
        <form method="post" action="<?= e(url('admin/commandes/' . $order['id'] . '/supprimer')) ?>" class="mt" data-confirm="Supprimer définitivement la commande <?= e($order['number']) ?> ? Cette action est irréversible.">
          <?= csrf_field() ?><button class="btn btn-danger">Supprimer définitivement</button>
        </form>
        <p class="muted small">Commande jamais payée : elle peut être supprimée.</p>
      <?php elseif ($order['paid_at']): ?>
        <p class="muted small">Une commande payée ne peut pas être supprimée : la loi impose de conserver les ventes 10 ans. Annulez-la : elle n'apparaîtra plus dans les commandes à traiter.</p>
      <?php endif; ?>
    </section>
    <?php endif; ?>
  </div>
</div>
