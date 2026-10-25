<?php use App\Core\View; ?>
<h1 style="font-family:Georgia,serif;font-size:24px;color:#2e2226;margin:0 0 12px;">Nouveau mot de passe</h1>
<p>Bonjour <?= e($name) ?>,</p>
<p>Tu as demandé à changer le mot de passe de ton compte. Clique ci-dessous pour en choisir un nouveau :</p>
<?= View::capture('emails/_button', ['href' => $link, 'label' => 'Choisir un nouveau mot de passe']) ?>
<p style="font-size:13px;color:#74645a;">Ce lien est valable 1 heure. Si tu n'as rien demandé, ignore cet e-mail : ton mot de passe reste inchangé.</p>
