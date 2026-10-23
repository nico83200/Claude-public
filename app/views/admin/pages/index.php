<div class="toolbar"><span></span><a class="btn btn-primary" href="<?= e(url('admin/pages/nouvelle')) ?>">+ Nouvelle page</a></div>
<section class="card">
  <p class="muted small">Les variables <code>{{company_name}}</code>, <code>{{company_siret}}</code>, <code>{{contact_email}}</code>… sont remplacées par les valeurs des Réglages.</p>
  <table class="table">
    <thead><tr><th>Titre</th><th>Adresse</th><th>Pied de page</th><th>Modifiée</th></tr></thead>
    <tbody>
    <?php foreach ($pages as $p): ?>
      <tr><td><a href="<?= e(url('admin/pages/' . $p['id'])) ?>"><strong><?= e($p['title']) ?></strong></a></td><td><a href="<?= e(url('page/' . $p['slug'])) ?>" target="_blank">/page/<?= e($p['slug']) ?></a></td><td><?= $p['in_footer'] ? 'Oui' : 'Non' ?></td><td><?= e(date_fr($p['updated_at'])) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</section>
