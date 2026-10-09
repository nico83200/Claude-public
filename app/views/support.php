<div class="page-head">
  <div><h1>Assistance</h1><p>Centriva est créé et maintenu par <strong><?= e($contact['editor']) ?></strong>. Une question, un souci, une idée ? Nous vous répondons.</p></div>
</div>

<?php if ($pending): ?>
  <div class="flash flash-info"><?= icon('info') ?><div style="flex:1">
    <strong>Demande n°<?= (int)$pending['id'] ?> enregistrée.</strong> L'envoi automatique d'e-mails n'est pas activé sur votre installation : transmettez-la en un clic depuis votre messagerie<?= $live ? ', ou écrivez-nous directement dans la conversation' : '' ?>.
    <div class="row mt-1"><a class="btn btn-sm" href="<?= e($pending['mailto']) ?>"><?= icon('mail', 15) ?> Envoyer par e-mail</a>
    <?php if ($live): ?><button type="button" class="btn btn-sm btn-success" data-help-live><?= icon('send', 15) ?> Discuter avec un conseiller</button><?php endif; ?></div>
  </div></div>
<?php endif; ?>

<div class="grid grid-main">
  <div class="stack">
    <form method="post" class="card">
      <?= csrf_field() ?>
      <input type="hidden" name="page" value="<?= e($page) ?>"><input type="hidden" name="bot_question" value="<?= e($q) ?>">
      <div class="card-head"><h2><?= icon('mail') ?> Écrire à l'équipe <?= e($contact['editor']) ?></h2></div>
      <div class="card-body">
        <div class="field"><label>Votre demande concerne</label>
          <div class="support-cats">
            <?php foreach (SUPPORT_CATEGORIES as $k => $l): ?>
              <label class="method-opt"><input type="radio" name="category" value="<?= $k ?>" <?= $k === 'question' ? 'checked' : '' ?>><span><?= e($l) ?></span></label>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="field"><label>Objet</label><input type="text" name="subject" required maxlength="200" value="<?= e(mb_substr($q, 0, 120)) ?>" placeholder="ex : la caméra ne s'ouvre pas sur la tablette de l'accueil"></div>
        <div class="field"><label>Message</label><textarea name="message" rows="6" required placeholder="Décrivez ce que vous faisiez, ce qui s'est passé, et sur quel appareil."></textarea>
          <small>Votre nom, votre centre, la version de l'application et la page concernée sont joints automatiquement.</small></div>
        <button class="btn btn-primary" type="submit"><?= icon('send', 16) ?> Envoyer la demande</button>
      </div>
    </form>

    <div class="card">
      <div class="card-head"><h2><?= icon('clock') ?> <?= $isAdmin ? 'Demandes envoyées' : 'Mes demandes' ?></h2></div>
      <?php if ($history): ?>
      <ul class="list">
        <?php foreach ($history as $h): ?>
          <li><div class="grow"><span class="title">#<?= (int)$h['id'] ?> · <?= e($h['subject']) ?></span>
            <small><?= e(SUPPORT_CATEGORIES[$h['category']] ?? $h['category']) ?> · <?= date_fr($h['created_at'], true) ?><?= $isAdmin && $h['first_name'] ? ' · ' . e($h['first_name'] . ' ' . $h['last_name']) : '' ?></small></div>
            <?= $h['sent_by'] === 'email' ? '<span class="badge badge-green">Transmise</span>' : '<span class="badge badge-gray">Enregistrée</span>' ?></li>
        <?php endforeach; ?>
      </ul>
      <?php else: ?><div class="empty"><p>Aucune demande pour l'instant.</p></div><?php endif; ?>
    </div>
  </div>

  <div class="stack">
    <?php if ($live): ?>
    <div class="card card-body support-direct">
      <h3><?= icon('send', 18) ?> Discuter en direct</h3>
      <p class="muted">Écrivez à l'équipe <?= e($contact['editor']) ?> ici même : la réponse s'affiche dans la conversation, et vous êtes prévenu(e) si vous avez quitté la page.</p>
      <button type="button" class="btn btn-success" style="width:100%" data-help-live><?= icon('send', 16) ?> <?= $openChat ? 'Reprendre la conversation' : 'Discuter avec un conseiller' ?></button>
    </div>
    <?php endif; ?>
    <div class="card card-body">
      <h3><?= icon('info', 18) ?> Coordonnées</h3>
      <ul class="contact-list">
        <li><?= icon('mail', 16) ?> <a href="mailto:<?= e($contact['email']) ?>"><?= e($contact['email']) ?></a></li>
        <li><?= icon('phone', 16) ?> <a href="tel:<?= e(preg_replace('/[^\d+]/', '', $contact['phone'])) ?>"><?= e($contact['phone']) ?></a></li>
        <li><?= icon('home', 16) ?> <a href="<?= e($contact['site']) ?>" target="_blank" rel="noopener"><?= e(preg_replace('#^https?://#', '', $contact['site'])) ?></a></li>
      </ul>
      <small class="muted">Version installée : <?= e(APP_VERSION) ?></small>
    </div>
  </div>
</div>
