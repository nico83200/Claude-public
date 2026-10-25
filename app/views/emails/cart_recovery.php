<?php use App\Core\View; ?>
<h1 style="font-family:Georgia,serif;font-size:24px;color:#5a2a3a;margin:0 0 12px;">Votre panier vous attend ♡</h1>
<p>Bonjour,</p>
<p>Vous avez commencé une commande sur <?= e(setting('shop_name')) ?> sans la terminer. Pas d'inquiétude : nous avons tout gardé de côté, personnalisations comprises.</p>
<?= View::capture('emails/_items', ['items' => $items]) ?>
<?= View::capture('emails/_button', ['href' => $link, 'label' => 'Reprendre ma commande']) ?>
<p style="font-size:13px;color:#74645a;">Un souci pendant le paiement, une question sur une taille ou une personnalisation ? Répondez simplement à cet e-mail, nous vous aidons avec plaisir.</p>
<p style="font-size:12px;color:#9a8a80;">Vous recevez ce message unique car vous avez accepté de recevoir nos e-mails. Vous ne serez pas relancée une seconde fois.</p>
