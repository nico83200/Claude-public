<div class="page-head">
  <div><h1>Tutoriels vidéo</h1><p>Apprenez à utiliser Approvia en quelques minutes. Cliquez sur un chapitre pour aller directement au passage qui vous intéresse.</p></div>
  <?php if (is_superadmin()): ?><a class="btn" href="<?= url('admin/videos') ?>"><?= icon('settings', 18) ?> Gérer les vidéos</a><?php endif; ?>
</div>

<?php if (!$videos): ?>
  <div class="card"><div class="empty">
    <?= icon('play') ?><h3>Aucune vidéo pour le moment</h3>
    <?php if (is_superadmin()): ?>
      <p><?= $pending ? $pending . ' vidéo(s) en cours de téléchargement depuis ' . e(support_contact()['editor']) . '.' : 'Les tutoriels publiés par ' . e(support_contact()['editor']) . ' apparaîtront ici automatiquement. Vous pouvez aussi déposer vos propres vidéos.' ?></p>
      <a class="btn btn-primary" href="<?= url('admin/videos') ?>"><?= icon('plus', 18) ?> Ajouter une vidéo</a>
    <?php else: ?>
      <p>Les tutoriels apparaîtront ici dès leur publication. En attendant, le bouton « Aide » répond à vos questions.</p>
    <?php endif; ?>
  </div></div>
<?php else: ?>
<div class="grid grid-main">
  <div class="card video-card" data-video-player data-start="<?= (int)$start ?>">
    <video controls playsinline preload="metadata" src="<?= url('video/file', ['v' => $cur['uid']]) ?>"></video>
    <div class="card-body">
      <h2 class="mb-0"><?= e($cur['title']) ?></h2>
      <?php if ($cur['description']): ?><p class="muted mt-1" style="white-space:pre-line"><?= e($cur['description']) ?></p><?php endif; ?>
      <?php if ($cur['chapters']): ?>
        <h3 class="mt-2"><?= icon('list', 18) ?> Chapitres</h3>
        <ol class="video-chapters">
          <?php foreach ($cur['chapters'] as $c): ?>
            <li><button type="button" data-t="<?= (int)$c['t'] ?>"><span class="t"><?= e(video_time((int)$c['t'])) ?></span> <?= e($c['title']) ?></button></li>
          <?php endforeach; ?>
        </ol>
      <?php endif; ?>
    </div>
  </div>

  <div class="stack">
    <?php foreach ($videos as $v): ?>
      <a class="card video-item <?= $v['uid'] === $cur['uid'] ? 'active' : '' ?>" href="<?= url('videos', ['v' => $v['uid']]) ?>">
        <div class="video-thumb"><?= icon('play', 28) ?></div>
        <div class="grow">
          <div class="strong"><?= e($v['title']) ?></div>
          <small class="muted"><?= $v['duration'] ? e(video_time((int)$v['duration'])) . ' · ' : '' ?><?= count($v['chapters']) ? count($v['chapters']) . ' chapitres' : 'Vidéo' ?><?= $v['audience'] === 'admin' ? ' · administrateurs' : '' ?></small>
        </div>
      </a>
    <?php endforeach; ?>
    <div class="card card-body"><small class="muted"><?= icon('info', 14) ?> Une question précise ? Le bouton « Aide » en bas à droite propose aussi la vidéo et le passage qui y répondent.</small></div>
  </div>
</div>
<?php endif; ?>
