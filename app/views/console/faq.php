<?php
/** Console : FAQ partagée, proposée par le chatbot de tous les clients. */
$faq = platform_faq();
$fq = function (array $f = []): void { ?>
  <div class="grid2">
    <div><label>Question</label><input name="question" required maxlength="200" value="<?= e($f['question'] ?? '') ?>"></div>
    <div><label>Mots-clés <small class="muted">(facultatif, aident le chatbot)</small></label><input name="keywords" maxlength="400" value="<?= e($f['keywords'] ?? '') ?>"></div>
  </div>
  <label>Réponse</label><textarea name="answer" rows="3" required><?= e($f['answer'] ?? '') ?></textarea>
  <div class="grid2">
    <div><label>Bouton : libellé <small class="muted">(facultatif)</small></label><input name="link_label" maxlength="60" value="<?= e($f['link_label'] ?? '') ?>" placeholder="Ouvrir le catalogue"></div>
    <div><label>Bouton : page <small class="muted">(route, ex. catalog)</small></label><input name="link_route" value="<?= e($f['link_route'] ?? '') ?>"></div>
  </div>
  <label class="check"><input type="checkbox" name="admin_only" value="1" <?= !empty($f['admin_only']) ? 'checked' : '' ?>> Réservée aux administrateurs</label>
  <label class="check"><input type="checkbox" name="active" value="1" <?= !$f || !empty($f['active']) ? 'checked' : '' ?>> Proposée par le chatbot</label>
<?php };
?>
<h1>FAQ partagée</h1>
<p class="muted">Ces questions s'ajoutent aux réponses intégrées du chatbot, dans tous les espaces clients, dès l'enregistrement.</p>
<form method="post" class="card"><?= csrf_field() ?><input type="hidden" name="action" value="faq_save"><h2>Nouvelle question</h2><?php $fq(); ?><p><button class="btn primary">Ajouter</button></p></form>
<?php foreach ($faq as $f): ?>
  <div class="card" style="<?= empty($f['active']) ? 'opacity:.65' : '' ?>">
    <div class="row" style="justify-content:space-between"><b><?= e($f['question']) ?></b>
      <span><?= empty($f['active']) ? '<span class="tag">masquée</span>' : '' ?><?= !empty($f['admin_only']) ? ' <span class="tag amber">administrateurs</span>' : '' ?></span></div>
    <p class="muted" style="margin:.3rem 0"><?= e($f['answer']) ?></p>
    <details><summary>Modifier</summary>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="faq_save"><input type="hidden" name="id" value="<?= (int)$f['id'] ?>"><?php $fq($f); ?><p><button class="btn sm primary">Enregistrer</button></p></form>
      <form method="post" onsubmit="return confirm('Supprimer cette question pour tous les clients ?')"><?= csrf_field() ?><input type="hidden" name="action" value="faq_delete"><input type="hidden" name="id" value="<?= (int)$f['id'] ?>"><button class="btn sm danger">Supprimer</button></form>
    </details>
  </div>
<?php endforeach; ?>
<?php if (!$faq): ?><div class="card muted">Aucune question partagée. La FAQ du centre d'assistance se reprend depuis le menu Assistance.</div><?php endif; ?>
