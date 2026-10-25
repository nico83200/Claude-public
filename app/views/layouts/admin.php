<?php
use App\Core\Database;
$unread = (int) Database::value('SELECT COUNT(*) FROM contact_messages WHERE is_read = 0');
$pendingReviews = App\Services\Reviews::pendingCount();
$attention = (int) Database::value("SELECT COUNT(*) FROM orders WHERE fulfillment_status IN ('error','draft','artwork') AND status IN ('paid','in_production')");
$success = flash('success');
$error = flash('error');
$nav = [
    'admin'              => ['Tableau de bord', '/admin', true],
    'admin/commandes'    => ['Commandes' . ($attention ? " <span class=\"pill\">$attention</span>" : ''), '/admin/commandes', false],
    'admin/produits'     => ['Produits', '/admin/produits', false],
    'admin/categories'   => ['Catégories', '/admin/categories', false],
    'admin/avis'         => ['Avis clients' . ($pendingReviews ? " <span class=\"pill\">$pendingReviews</span>" : ''), '/admin/avis', false],
    'admin/clients'      => ['Clients & newsletter', '/admin/clients', false],
    'admin/messages'     => ['Messages' . ($unread ? " <span class=\"pill\">$unread</span>" : ''), '/admin/messages', false],
    'admin/accueil'      => ['Page d\'accueil', '/admin/accueil', false],
    'admin/journal'      => ['Journal', '/admin/journal', false],
    'admin/pages'        => ['Pages', '/admin/pages', false],
    'admin/reglages'     => ['Réglages', '/admin/reglages', false],
    'admin/integrations' => ['Intégrations', '/admin/integrations', false],
    'admin/mise-a-jour'  => ['Mise à jour', '/admin/mise-a-jour', false],
];
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($pageTitle ?? 'Administration') ?> — <?= e(setting('shop_name')) ?></title>
<link rel="icon" href="<?= e(url('assets/img/favicon.svg')) ?>" type="image/svg+xml">
<link rel="stylesheet" href="<?= e(asset('assets/css/admin.css')) ?>">
</head>
<body class="admin">
<aside class="sidebar">
  <a class="sidebar-brand" href="<?= e(url('admin')) ?>"><img src="<?= e(url('assets/img/brand/logo-creme.svg')) ?>" alt="<?= e(setting('shop_name')) ?>" height="44"></a>
  <nav>
    <?php foreach ($nav as $path => [$label, $prefix, $exact]): ?>
      <a href="<?= e(url($path)) ?>" class="<?= ($exact ? request_path() === $prefix : is_active($prefix)) ? 'active' : '' ?>"><?= $label ?></a>
    <?php endforeach; ?>
  </nav>
  <div class="sidebar-foot">
    <a href="<?= e(url('/')) ?>" target="_blank">Voir la boutique ↗</a>
    <span>Version <?= e((new App\Services\Updater())->currentVersion()) ?></span>
    <form method="post" action="<?= e(url('admin/deconnexion')) ?>"><?= csrf_field() ?><button class="link" type="submit">Déconnexion</button></form>
  </div>
</aside>
<main class="admin-main">
  <header class="admin-head"><h1><?= e($pageTitle ?? '') ?></h1></header>
  <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
  <?= $content ?>
</main>
<script>
// Éditeur de texte mis en forme (descriptions produits, pages) -------------------------
document.querySelectorAll('textarea[data-rich-editor]').forEach(function (textarea) {
  var tools = [
    ['bold', '<b>G</b>', 'Gras (Ctrl+B)'], ['italic', '<i>I</i>', 'Italique (Ctrl+I)'], ['underline', '<u>S</u>', 'Souligné (Ctrl+U)'],
    ['h3', 'Titre', 'Intertitre'], ['p', '¶', 'Paragraphe normal'],
    ['insertUnorderedList', '• Liste', 'Liste à puces'], ['insertOrderedList', '1. Liste', 'Liste numérotée'],
    ['link', '🔗 Lien', 'Ajouter un lien sur le texte sélectionné'], ['unlink', 'Retirer le lien', 'Retirer le lien'],
    ['removeFormat', 'Effacer', 'Retirer la mise en forme du texte sélectionné'], ['source', '&lt;/&gt; HTML', 'Voir / modifier le code HTML']
  ];
  var wrap = document.createElement('div');
  wrap.className = 'rte';
  var bar = document.createElement('div');
  bar.className = 'rte-toolbar';
  var area = document.createElement('div');
  area.className = 'rte-area';
  area.contentEditable = 'true';
  area.innerHTML = textarea.value;
  tools.forEach(function (t) {
    var b = document.createElement('button');
    b.type = 'button'; b.innerHTML = t[1]; b.title = t[2]; b.dataset.cmd = t[0];
    bar.appendChild(b);
  });
  textarea.parentNode.insertBefore(wrap, textarea);
  wrap.appendChild(bar); wrap.appendChild(area); wrap.appendChild(textarea);
  textarea.classList.add('rte-source', 'mono');
  textarea.hidden = true;
  try { document.execCommand('defaultParagraphSeparator', false, 'p'); } catch (e) {}
  var sync = function () { if (textarea.hidden) textarea.value = area.innerHTML; };
  bar.addEventListener('mousedown', function (e) { if (e.target.closest('button')) e.preventDefault(); });
  bar.addEventListener('click', function (e) {
    var b = e.target.closest('button'); if (!b) return;
    var cmd = b.dataset.cmd;
    if (cmd === 'source') {
      if (textarea.hidden) { textarea.value = area.innerHTML; textarea.hidden = false; area.hidden = true; }
      else { area.innerHTML = textarea.value; textarea.hidden = true; area.hidden = false; }
      b.classList.toggle('active', !textarea.hidden);
      return;
    }
    if (!textarea.hidden) return;
    area.focus();
    if (cmd === 'h3' || cmd === 'p') document.execCommand('formatBlock', false, cmd);
    else if (cmd === 'link') { var url = prompt('Adresse du lien (ex. https://… ou /boutique) :'); if (url) document.execCommand('createLink', false, url); }
    else document.execCommand(cmd, false, null);
    sync();
  });
  area.addEventListener('input', sync);
  if (textarea.form) textarea.form.addEventListener('submit', sync);
});

// Téléphone : libellés des colonnes sur chaque cellule (tableaux affichés en cartes)
document.querySelectorAll('table.table').forEach(function (table) {
  var heads = Array.prototype.map.call(table.querySelectorAll('thead th'), function (th) { return th.textContent.trim(); });
  if (!heads.length) return;
  table.querySelectorAll('tbody tr').forEach(function (tr) {
    Array.prototype.forEach.call(tr.children, function (td, i) { if (heads[i] && !td.hasAttribute('data-label')) td.setAttribute('data-label', heads[i]); });
  });
});
// Téléphone : bouton « Enregistrer » du formulaire principal toujours visible en bas de l'écran
(function () {
  var best = null, bestHeight = 0;
  document.querySelectorAll('.admin-main form[method="post"]').forEach(function (form) {
    var btn = form.querySelector('button.btn-primary:not([form])') || document.querySelector('button.btn-primary[form="' + form.id + '"]');
    var h = form.getBoundingClientRect().height;
    if (btn && h > bestHeight && !form.closest('details:not([open])') && !form.closest('table')) { best = { form: form, btn: btn }; bestHeight = h; }
  });
  if (!best || bestHeight < 700) return;
  if (!best.form.id) best.form.id = 'form-principal';
  var bar = document.createElement('div');
  bar.className = 'mobile-savebar';
  var b = document.createElement('button');
  b.type = 'submit';
  b.className = 'btn btn-primary';
  b.setAttribute('form', best.form.id);
  b.textContent = best.btn.textContent.trim() || 'Enregistrer';
  bar.appendChild(b);
  document.body.appendChild(bar);
})();
document.querySelectorAll('[data-confirm]').forEach(function (f) {
  f.addEventListener('submit', function (e) { if (!confirm(f.dataset.confirm)) e.preventDefault(); });
});
document.querySelectorAll('[data-copy]').forEach(function (b) {
  b.addEventListener('click', function () { navigator.clipboard.writeText(b.dataset.copy); b.textContent = 'Copié ✓'; });
});
</script>
<script src="<?= e(asset('assets/js/password-toggle.js')) ?>" defer></script>
</body>
</html>
