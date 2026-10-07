<?php
/** FAQ partagée : enrichit le chatbot de toutes les installations clientes. */
defined('HUB') || exit;

$prefill = ['question' => '', 'answer' => '', 'source' => 0];
if ($from = (int)($_GET['from'] ?? 0)) {
    $msgs = hub_messages($from);
    $userMsgs = array_values(array_filter($msgs, fn($m) => in_array($m['from'], ['user', 'user_bot'], true)));
    $agentMsgs = array_values(array_filter($msgs, fn($m) => $m['from'] === 'agent'));
    $prefill = ['question' => end($userMsgs)['text'] ?? '', 'answer' => end($agentMsgs)['text'] ?? '', 'source' => $from];
    // La question de départ est souvent la plus parlante : celle posée au chatbot ou le premier message
    if ($userMsgs) {
        $prefill['question'] = $userMsgs[0]['text'];
    }
}
$faq = hall('SELECT * FROM faq ORDER BY active DESC, updated_at DESC');

function faq_form(array $f, string $submit): void
{
    ?>
    <?= csrf_input() ?><input type="hidden" name="action" value="faq_save"><input type="hidden" name="id" value="<?= (int)($f['id'] ?? 0) ?>"><input type="hidden" name="source_conv" value="<?= (int)($f['source_conv'] ?? 0) ?>">
    <label>Question (telle qu'un utilisateur la poserait)</label><input name="question" value="<?= h($f['question'] ?? '') ?>" required maxlength="200">
    <label>Réponse du chatbot</label><textarea name="answer" rows="4" required maxlength="2000"><?= h($f['answer'] ?? '') ?></textarea>
    <div class="grid2">
      <div><label>Mots-clés supplémentaires <small class="muted">(synonymes, fautes courantes)</small></label><input name="keywords" value="<?= h($f['keywords'] ?? '') ?>" placeholder="ex : imprimante impression pdf"></div>
      <div><label>Application</label><select name="app"><option value="*">Toutes</option><option value="approvia" <?= ($f['app'] ?? '') === 'approvia' ? 'selected' : '' ?>>Approvia</option></select></div>
      <div><label>Bouton vers une page <small class="muted">(facultatif)</small></label><input name="link_label" value="<?= h($f['link_label'] ?? '') ?>" placeholder="ex : Inventaire"></div>
      <div><label>Page de l'application</label><input name="link_route" value="<?= h($f['link_route'] ?? '') ?>" placeholder="ex : stock"></div>
    </div>
    <label class="check"><input type="checkbox" name="admin_only" value="1" <?= !empty($f['admin_only']) ? 'checked' : '' ?>> Réservée aux administrateurs</label>
    <?php if (!empty($f['id'])): ?><label class="check"><input type="checkbox" name="active" value="1" <?= !empty($f['active']) ? 'checked' : '' ?>> Active</label><?php endif; ?>
    <button class="btn primary"><?= h($submit) ?></button>
    <?php
}
?>
<main class="wrap">
  <?= $flashHtml ?>
  <h1>FAQ partagée</h1>
  <p class="muted">Ces questions s'ajoutent à celles intégrées au chatbot de chaque installation, sans mise à jour : elles sont transmises à la prochaine synchronisation (moins de 10 minutes). Depuis une conversation, « ＋ FAQ » prépare la question et votre réponse.</p>
  <form method="post" class="card">
    <h2><?= $prefill['source'] ? 'Nouvelle question, d\'après la conversation #' . $prefill['source'] : 'Nouvelle question' ?></h2>
    <?php faq_form(['question' => $prefill['question'], 'answer' => $prefill['answer'], 'source_conv' => $prefill['source']], 'Ajouter à la FAQ'); ?>
  </form>
  <?php foreach ($faq as $f): ?>
    <div class="card <?= $f['active'] ? '' : 'dim' ?>">
      <div class="row"><b style="flex:1"><?= h($f['question']) ?></b>
        <?= $f['admin_only'] ? '<span class="tag violet">admin</span>' : '' ?><span class="tag"><?= h($f['app'] === '*' ? 'toutes applis' : $f['app']) ?></span>
        <form method="post" onsubmit="return confirm('Supprimer cette question ?')"><?= csrf_input() ?><input type="hidden" name="action" value="faq_delete"><input type="hidden" name="id" value="<?= (int)$f['id'] ?>"><button class="btn sm danger">Supprimer</button></form>
      </div>
      <p style="margin:.4rem 0 0;white-space:pre-line"><?= h($f['answer']) ?></p>
      <details class="edit"><summary class="muted" style="margin-top:.4rem"><small>Modifier</small></summary><form method="post"><?php faq_form($f, 'Enregistrer'); ?></form></details>
    </div>
  <?php endforeach; ?>
</main>
