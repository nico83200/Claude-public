<?php
/** Bulle « ? » ouvrant le tableau comparatif des droits par rôle ; la fenêtre elle-même (dialog => true) se place une fois par page, hors des formulaires. */
$y = '<span class="rh-yes" aria-label="oui">✓</span>';
$n = '<span class="rh-no" aria-label="non">—</span>';
// [droit, salarié, responsable de centre, acheteur, administrateur]
$groups = [
    'Commander et recevoir' => [
        ['Catalogue, panier, listes types, proposer un article hors catalogue', $y, $y, $y, $y],
        ['Suivre ses demandes, réceptionner les livraisons, inventaire', $y, $y, $y, $y],
        ['Centres accessibles', 'Ses centres', 'Ses centres', 'Tous', 'Tous'],
        ['Valider les demandes au-delà du seuil fixé', $n, 'Ses centres', 'Tous', 'Tous'],
        ['Demandes dispensées de validation par un responsable', $n, $y, $y, $y],
        ['Listes types partagées avec tous les centres', $n, $n, $y, $y],
    ],
    'Service achats' => [
        ['Pilotage et tableau Direction', $n, $n, $y, $y],
        ['Traiter les demandes, bons de commande, envoi aux fournisseurs', $n, $n, $y, $y],
        ['Articles proposés, catalogue, fournisseurs, catégories, prix', $n, $n, $y, $y],
        ['Contrats et marchés (prix contractuels, échéances)', $n, $n, $y, $y],
        ['Factures, exports comptables, budgets, dates limites, stocks des centres', $n, $n, $y, $y],
        ['Notifications des nouvelles demandes et alertes (prix, budget, stock)', $n, 'Validations', $y, $y],
    ],
    'Organisation et paramètres' => [
        ['Centres', $n, $n, $n, $y],
        ['Comptes, rôles et validation des inscriptions', $n, $n, $n, $y],
        ['Paramètres (e-mails, assistant IA, seuil de validation, sécurité, RGPD)', $n, $n, $n, $y],
        ['Journal d\'audit et nettoyage des données', $n, $n, $n, $y],
        ['Mises à jour et sauvegardes', $n, $n, $n, $y],
        ['Gestion des tutoriels vidéo et de la vidéo d\'accueil', $n, $n, $n, $y],
    ],
];
?>
<?php if (empty($dialog)): ?>
<button type="button" class="rh-bubble" data-dialog-open="roles-help" title="Comparer les droits des rôles" aria-label="Comparer les droits des rôles">?</button>
<?php else: ?>
<dialog class="rh-dialog" id="roles-help" aria-labelledby="rh-title">
  <div class="rh-head">
    <h2 id="rh-title"><?= icon('users') ?> Droits de chaque rôle</h2>
    <button type="button" class="btn btn-ghost btn-icon" data-dialog-close aria-label="Fermer"><?= icon('x') ?></button>
  </div>
  <p class="muted rh-legend"><small>Resp. : responsable de centre · Ach. : acheteur · Admin : administrateur</small></p>
  <div class="table-wrap">
    <table class="table rh-table">
      <thead><tr><th></th><?php foreach (["Salarié" => "Salarié", "Responsable de centre" => "Resp.", "Acheteur" => "Ach.", "Administrateur" => "Admin"] as $l => $s): ?><th><span class="rh-long"><?= e($l) ?></span><abbr class="rh-short" title="<?= e($l) ?>"><?= e($s) ?></abbr></th><?php endforeach; ?></tr></thead>
      <?php foreach ($groups as $g => $rows): ?>
        <tbody>
          <tr class="rh-group"><td colspan="5"><?= e($g) ?></td></tr>
          <?php foreach ($rows as $r): ?>
            <tr><td><?= e($r[0]) ?></td><?php for ($i = 1; $i <= 4; $i++): ?><td class="rh-cell"><?= str_starts_with($r[$i], '<span') ? $r[$i] : '<small>' . e($r[$i]) . '</small>' ?></td><?php endfor; ?></tr>
          <?php endforeach; ?>
        </tbody>
      <?php endforeach; ?>
    </table>
  </div>
  <p class="muted rh-foot"><small>Lorsque la double authentification est exigée des administrateurs, elle l'est aussi des acheteurs. Le rôle se change à tout moment depuis la fiche du compte.</small></p>
</dialog>
<?php endif; ?>
