<?php use App\Core\View; ?>
<h1 style="font-family:Georgia,serif;font-size:24px;color:#2e2226;margin:0 0 12px;">Alors, ça te plaît ?</h1>
<p>Bonjour <?= e(explode(' ', (string) $order['customer_name'])[0]) ?>,</p>
<p>Ta commande <strong><?= e($order['number']) ?></strong> est arrivée il y a quelques jours. On espère qu'elle te ravit, toi et ton cheval !</p>
<p>📸 Bonus : ajoute une photo de ton article porté (ou avec ton cheval !), on adore les voir.</p>
<p>Ton avis aide les autres cavalières à choisir — et nous aide à toujours mieux faire. Ça ne prend qu'une minute :</p>
<?= View::capture('emails/_button', ['href' => $link, 'label' => 'Donner mon avis ★★★★★']) ?>
<p style="font-size:13px;color:#74645a;">Un souci avec un article ? Réponds simplement à cet e-mail, on trouve toujours une solution.</p>
