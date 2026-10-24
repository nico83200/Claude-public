<?php use App\Core\View; ?>
<h1 style="font-family:Georgia,serif;font-size:26px;color:#2e2226;margin:0 0 12px;">Bienvenue au club ✨</h1>
<p>Merci de nous rejoindre ! Comme promis, voici votre cadeau de bienvenue : <strong><?= e($discount) ?></strong> sur votre première commande.</p>
<p style="text-align:center;margin:28px 0;"><span style="display:inline-block;border:2px dashed #5a2a3a;border-radius:12px;padding:14px 28px;font-size:22px;letter-spacing:3px;color:#5a2a3a;font-weight:bold;"><?= e($code) ?></span></p>
<p style="text-align:center;color:#74645a;font-size:13px;">À saisir dans le champ « Code promo » au moment du paiement.</p>
<?= View::capture('emails/_button', ['href' => url('boutique'), 'label' => 'Découvrir la collection']) ?>
<p>Au programme de nos e-mails : les nouveautés en avant-première, les éditions limitées et quelques surprises. Promis, pas plus d'un ou deux par mois.</p>
