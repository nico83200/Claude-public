<?php
/** Console : liaison avec le centre d'assistance (qui ne garde que les conversations) et reprise de son historique. */
$ps = platform_settings();
$rep = $_SESSION['import_report'] ?? null;
unset($_SESSION['import_report']);
?>
<h1>Centre d'assistance</h1>
<p class="muted">Le centre d'assistance NLapps ne sert plus qu'aux <b>conversations en direct</b> avec les utilisateurs. Clients, licences, abonnements, versions, vidéos et FAQ se gèrent ici ; une fois la console reliée, ces pages disparaissent de l'assistance pour Centriva.</p>
<form method="post" class="card" autocomplete="off"><?= csrf_field() ?><input type="hidden" name="action" value="hub_link">
  <h2>Liaison <?= platform_hub_linked() ? '<span class="tag green">reliée' . (!empty($ps['hub_linked_at']) ? ' le ' . e(date('d/m/Y', strtotime((string)$ps['hub_linked_at']))) : '') . '</span>' : '<span class="tag amber">non reliée</span>' ?></h2>
  <div class="grid2">
    <div><label>Adresse de l'API du centre d'assistance</label><input name="hub_url" required value="<?= e((string)($ps['hub_url'] ?: 'https://nlapps.fr/assistance/api.php')) ?>"></div>
    <div><label>Clé de liaison</label><input type="password" name="hub_console_key" <?= platform_hub_linked() ? 'placeholder="enregistrée — saisir pour remplacer"' : 'required placeholder="nlc_…"' ?> autocomplete="new-password"></div>
  </div>
  <p class="muted"><small>La clé se crée dans le centre d'assistance : Réglages → Console Centriva → « Créer la clé de liaison ».</small></p>
  <p><button class="btn primary"><?= platform_hub_linked() ? 'Vérifier et enregistrer' : 'Relier' ?></button></p>
</form>
<?php if (platform_hub_linked()): ?>
<form method="post" class="card"><?= csrf_field() ?><input type="hidden" name="action" value="hub_import">
  <h2>Reprendre l'historique du centre d'assistance</h2>
  <p class="muted">Importe ici l'historique des <b>versions</b> (notes et paquets), les <b>vidéos</b> (fichiers compris), la <b>FAQ partagée</b>, les tarifs et la clé Stripe, puis pour chaque espace sa <b>licence</b> (formule, échéance, option IA), son <b>abonnement en ligne</b> et ses paiements. Chaque client est reconnu par la clé déjà enregistrée dans son espace, son adresse ou son nom. Sans doublon si vous relancez.<?= !empty($ps['hub_imported_at']) ? ' Dernière reprise : ' . e(date('d/m/Y H:i', strtotime((string)$ps['hub_imported_at']))) . '.' : '' ?></p>
  <label class="check"><input type="checkbox" name="packages" value="1" checked> Copier aussi les paquets des anciennes versions</label>
  <p><button class="btn primary" onclick="this.textContent='Reprise en cours… (les vidéos peuvent prendre quelques minutes)'">Lancer la reprise</button></p>
  <?php if ($rep): ?>
    <div class="licence"><b>Compte rendu</b><br>
      <small><?= (int)$rep['releases'] ?> version(s) (<?= (int)$rep['packages'] ?> paquet(s) copié(s)) · <?= (int)$rep['videos'] ?> vidéo(s) · <?= (int)$rep['faq'] ?> question(s) · <?= (int)$rep['events'] ?> paiement(s)</small>
      <?php if ($rep['clients']): ?><br><small>Clients repris : <?= e(implode(' · ', $rep['clients'])) ?></small><?php endif; ?>
      <?php if ($rep['unmatched']): ?><br><small style="color:var(--amber)">Sans espace correspondant (anciennes installations ou clients à créer) : <?= e(implode(' · ', $rep['unmatched'])) ?></small><?php endif; ?>
      <?php foreach ($rep['errors'] as $er): ?><br><small style="color:var(--red)"><?= e($er) ?></small><?php endforeach; ?>
    </div>
  <?php endif; ?>
</form>
<div class="card">
  <h2>Conversation en direct, espace par espace</h2>
  <?php foreach ($registry as $slug => $i): if (!empty($i['public_demo'])) continue; $lr = platform_licence_row((string)$slug); ?>
    <form method="post" class="row" style="justify-content:space-between;padding:.4rem 0;border-bottom:1px solid var(--border)"><?= csrf_field() ?><input type="hidden" name="action" value="hub_provision"><input type="hidden" name="slug" value="<?= e($slug) ?>"><input type="hidden" name="back" value="assistance">
      <span><b><?= e($i['name']) ?></b> <?= $lr['hub_client'] ? '<span class="tag green">reliée</span>' : '<span class="tag amber">non reliée</span>' ?></span>
      <button class="btn sm"><?= $lr['hub_client'] ? 'Mettre à jour' : 'Relier' ?></button></form>
  <?php endforeach; ?>
</div>
<?php endif; ?>
