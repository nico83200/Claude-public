<?php use App\Core\View; ?>
<h1 style="font-family:Georgia,serif;font-size:24px;color:#2e2226;margin:0 0 12px;">Alors, ça vous plaît ?</h1>
<p>Bonjour <?= e(explode(' ', (string) $order['customer_name'])[0]) ?>,</p>
<p>Votre commande <strong><?= e($order['number']) ?></strong> est arrivée il y a quelques jours. Nous espérons qu'elle vous ravit, vous et votre cheval !</p>
<p>Votre avis aide les autres cavalières à choisir — et nous aide à toujours mieux faire. Cela ne prend qu'une minute :</p>
<?= View::capture('emails/_button', ['href' => $link, 'label' => 'Donner mon avis ★★★★★']) ?>
<p style="font-size:13px;color:#74645a;">Un souci avec un article ? Répondez simplement à cet e-mail, nous trouvons toujours une solution.</p>
