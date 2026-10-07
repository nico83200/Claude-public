<?php
/** Boîte de réception : conversations par statut et fil de la conversation ouverte. */
defined('HUB') || exit;

$cid = (int)($_GET['c'] ?? 0);
$conv = $cid ? hone('SELECT c.*, cl.name AS client, cl.site, cl.app FROM conversations c JOIN clients cl ON cl.id = c.client_id WHERE c.id = ?', [$cid]) : null;
if ($conv) {
    hq('UPDATE conversations SET unread = 0 WHERE id = ?', [$cid]);
}
$tab = (string)($_GET['tab'] ?? ($conv ? ($conv['status'] === 'open' ? 'open' : $conv['status']) : 'open'));
$counts = tab_counts();
$tabs = ['open' => 'À traiter', 'pending' => 'En attente', 'closed' => 'Résolues', 'all' => 'Toutes'];
$quick = hall('SELECT * FROM quick_replies ORDER BY position, id');
$statusTag = ['open' => ['À traiter', 'blue'], 'pending' => ['En attente de l\'utilisateur', 'amber'], 'closed' => ['Résolue', 'green']];
$first = $conv ? explode(' ', trim((string)$conv['user_name']))[0] : '';
?>
<main class="inbox <?= $conv ? 'has-conv' : '' ?>">
  <aside class="list">
    <div class="tabs" data-tabs><?php foreach ($tabs as $k => $label): ?><a href="index.php?tab=<?= $k ?>" class="<?= $tab === $k ? 'act' : '' ?>" data-tab="<?= $k ?>"><?= h($label) ?><?= $k !== 'all' && !empty($counts[$k]) ? ' (' . (int)$counts[$k] . ')' : '' ?></a><?php endforeach; ?></div>
    <div data-list data-tab="<?= h($tab) ?>">
      <?php $list = conv_list($tab); foreach ($list as $c): ?>
        <a href="index.php?c=<?= (int)$c['id'] ?>" class="item <?= (int)$c['id'] === $cid ? 'sel' : '' ?>">
          <span class="who"><?= h($c['user_name'] ?: 'Utilisateur') ?><?php if ($c['unread']): ?><i class="badge"><?= (int)$c['unread'] ?></i><?php endif; ?></span>
          <small><?= h($c['client']) ?><?= $c['center'] ? ' · ' . h($c['center']) : '' ?></small>
          <span class="last"><?= h(mb_substr((string)$c['last'], 0, 70)) ?></span>
        </a>
      <?php endforeach; ?>
      <?php if (!$list): ?><p class="muted pad"><?= $tab === 'open' ? 'Rien à traiter. Les nouvelles conversations apparaîtront ici dès qu\'un utilisateur demandera à parler à un conseiller.' : 'Aucune conversation.' ?></p><?php endif; ?>
    </div>
  </aside>
  <section class="conv">
    <?php if ($conv): $stt = $statusTag[$conv['status']] ?? ['?', '']; ?>
      <div class="conv-head">
        <a href="index.php?tab=<?= h($tab) ?>" class="back">←</a>
        <div><b><?= h($conv['user_name'] ?: 'Utilisateur') ?></b> <small class="muted"><?= h($conv['user_role']) ?></small> <span class="tag <?= $stt[1] ?>" data-status-tag><?= h($stt[0]) ?></span>
          <?php if ($conv['rating'] !== null): ?><span class="stars" title="<?= h((string)$conv['rating_comment']) ?>"><?= str_repeat('★', (int)$conv['rating']) . str_repeat('☆', 5 - (int)$conv['rating']) ?></span><?php endif; ?><br>
          <small><?= h($conv['client']) ?><?= $conv['center'] ? ' · ' . h($conv['center']) : '' ?><?= $conv['user_email'] ? ' · ' . h($conv['user_email']) : '' ?></small></div>
        <a class="btn sm" href="index.php?p=faq&from=<?= $cid ?>" title="Transformer la réponse en question de la FAQ partagée">＋ FAQ</a>
        <form method="post" class="row" style="gap:.4rem">
          <?= csrf_input() ?><input type="hidden" name="c" value="<?= $cid ?>"><input type="hidden" name="action" value="set_status">
          <?php if ($conv['status'] !== 'pending' && $conv['status'] !== 'closed'): ?><button class="btn sm" name="status" value="pending" title="Vous attendez une réponse ou une action de l'utilisateur">En attente</button><?php endif; ?>
          <?php if ($conv['status'] !== 'closed'): ?>
            <?php if ($conv['user_email']): ?><label class="check hide-sm" style="margin:0;font-size:.8rem" title="Envoyer à l'utilisateur la transcription de la conversation"><input type="checkbox" name="transcript" value="1" checked> transcription par e-mail</label><?php endif; ?>
            <button class="btn sm primary" name="status" value="closed">Résoudre</button>
          <?php else: ?><button class="btn sm" name="status" value="open">Rouvrir</button><?php endif; ?>
        </form>
      </div>
      <?php if ($conv['context']): ?><details class="ctx"><summary>Contexte technique</summary><pre><?= h($conv['context']) ?></pre></details><?php endif; ?>
      <div class="msgs" data-msgs data-c="<?= $cid ?>">
        <?php $last = 0; foreach (hub_messages($cid) as $m): $last = $m['id']; ?>
          <div class="m <?= h($m['from']) ?>"><div><?= nl2br(h($m['text'])) ?></div><?php if ($m['file']): ?><a href="index.php?file=<?= h($m['file']) ?>" target="_blank"><img src="index.php?file=<?= h($m['file']) ?>" alt="Image jointe" loading="lazy"></a><?php endif; ?><small><?= ['bot' => 'Chatbot', 'user_bot' => 'Question au chatbot', 'user' => 'Utilisateur', 'agent' => 'Vous', 'system' => ''][$m['from']] ?? '' ?> · <?= h(substr($m['at'], 0, 10) === date('Y-m-d') ? substr($m['at'], 11, 5) : date('d/m H:i', strtotime($m['at']))) ?></small></div>
        <?php endforeach; ?>
      </div>
      <form class="reply" method="post" enctype="multipart/form-data" data-reply data-last="<?= $last ?>" data-first="<?= h($first) ?>">
        <?= csrf_input() ?><input type="hidden" name="c" value="<?= $cid ?>"><input type="hidden" name="action" value="reply">
        <div class="tools">
          <?php if ($quick): ?><select data-quick aria-label="Réponse rapide"><option value="">Réponses rapides…</option><?php foreach ($quick as $q): ?><option value="<?= h($q['body']) ?>"><?= h($q['title']) ?></option><?php endforeach; ?></select><?php endif; ?>
          <button class="btn sm" type="button" data-suggest title="Proposer une réponse rédigée par l'IA, à relire avant d'envoyer">✨ Suggérer une réponse</button>
          <label class="btn sm" style="margin:0;font-weight:600" title="Joindre une capture d'écran">📎 Image<input type="file" name="image" accept="image/png,image/jpeg,image/webp" hidden data-image></label>
          <span class="attach-name" data-image-name></span>
        </div>
        <div class="send">
          <textarea name="text" rows="2" placeholder="Votre réponse… ({prenom} est remplacé par le prénom)" title="Entrée pour envoyer, Maj+Entrée pour un retour à la ligne"></textarea>
          <button class="btn primary">Envoyer</button>
        </div>
      </form>
    <?php else: ?>
      <div class="empty"><img src="assets/nlapps-mark.svg" alt="" width="56" height="56"><?= $flashHtml ?><p>Sélectionnez une conversation.</p>
        <p class="muted"><?= $st['online'] ? 'Vous êtes affiché comme disponible : les utilisateurs peuvent vous écrire en direct.' : 'Vous êtes affiché comme absent : les messages sont gardés et les utilisateurs prévenus de votre réponse.' ?></p></div>
    <?php endif; ?>
  </section>
</main>
