<h1 style="font-size:20px;margin:0 0 12px;">Nouveau message de contact</h1>
<p><strong><?= e($data['name']) ?></strong> — <a href="mailto:<?= e($data['email']) ?>"><?= e($data['email']) ?></a></p>
<p>Sujet : <?= e($data['subject']) ?><?= $data['order_number'] ? ' — Commande ' . e($data['order_number']) : '' ?></p>
<blockquote style="margin:16px 0;padding:12px 16px;background:#faf7f1;border-left:3px solid #c89b5d;"><?= nl2br(e($data['message'])) ?></blockquote>
