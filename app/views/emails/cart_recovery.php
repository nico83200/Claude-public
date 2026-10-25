<?php use App\Core\View; ?>
<h1 style="font-family:Georgia,serif;font-size:24px;color:#5a2a3a;margin:0 0 12px;">Ton panier t'attend ♡</h1>
<p>Bonjour,</p>
<p>Tu as commencé une commande sur <?= e(setting('shop_name')) ?> sans la terminer. Pas d'inquiétude : nous avons tout gardé de côté, personnalisations comprises.</p>
<?= View::capture('emails/_items', ['items' => $items]) ?>
<?= View::capture('emails/_button', ['href' => $link, 'label' => 'Reprendre ma commande']) ?>
<p style="font-size:13px;color:#74645a;">Un souci pendant le paiement, une question sur une taille ou une personnalisation ? Réponds simplement à cet e-mail, on t'aide avec plaisir.</p>
<p style="font-size:12px;color:#9a8a80;">Tu reçois ce message unique car tu as accepté de recevoir nos e-mails. Tu ne seras pas relancée une seconde fois.</p>
