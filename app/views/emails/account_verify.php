<?php use App\Core\View; ?>
<h1 style="font-family:Georgia,serif;font-size:24px;color:#2e2226;margin:0 0 12px;">Bienvenue au club, <?= e($name) ?> !</h1>
<p>Ton compte est créé. Confirme ton adresse e-mail pour retrouver dans ton espace toutes les commandes passées avec cette adresse :</p>
<?= View::capture('emails/_button', ['href' => $link, 'label' => 'Confirmer mon adresse']) ?>
<p style="font-size:13px;color:#74645a;">Ce lien est valable 7 jours. Tu n'es pas à l'origine de cette inscription ? Ignore simplement cet e-mail.</p>
