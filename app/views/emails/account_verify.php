<?php use App\Core\View; ?>
<h1 style="font-family:Georgia,serif;font-size:24px;color:#2e2226;margin:0 0 12px;">Bienvenue au club, <?= e($name) ?> !</h1>
<p>Votre compte est créé. Confirmez votre adresse e-mail pour retrouver dans votre espace toutes les commandes passées avec cette adresse :</p>
<?= View::capture('emails/_button', ['href' => $link, 'label' => 'Confirmer mon adresse']) ?>
<p style="font-size:13px;color:#74645a;">Ce lien est valable 7 jours. Vous n'êtes pas à l'origine de cette inscription ? Ignorez simplement cet e-mail.</p>
