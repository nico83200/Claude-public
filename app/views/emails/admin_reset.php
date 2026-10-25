<?php use App\Core\View; ?>
<h1 style="font-family:Georgia,serif;font-size:24px;color:#2e2226;margin:0 0 12px;">Mot de passe administrateur</h1>
<p>Une réinitialisation du mot de passe de l'espace d'administration a été demandée. Cliquez ci-dessous pour en choisir un nouveau :</p>
<?= View::capture('emails/_button', ['href' => $link, 'label' => 'Choisir un nouveau mot de passe']) ?>
<p style="font-size:13px;color:#74645a;">Ce lien est valable 1 heure et ne sert qu'une fois. Demande faite depuis l'adresse IP <?= e($ip) ?>. Si vous n'êtes pas à l'origine de cette demande, ignorez cet e-mail : votre mot de passe reste inchangé.</p>
