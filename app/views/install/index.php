<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Installation de la boutique</title>
<meta name="robots" content="noindex">
<link rel="stylesheet" href="<?= e(url('assets/css/admin.css')) ?>">
</head>
<body class="install">
<main class="install-card">
  <h1>Installation de la boutique</h1>
  <p class="muted">Renseignez les informations ci-dessous. Les clés Stripe, Printful et e-mail peuvent être ajoutées plus tard dans Administration › Intégrations ; elles sont enregistrées dans la base de données et ne sont jamais perdues lors d'une mise à jour.</p>

  <div class="alert alert-info">
    <strong>Réinstallation ?</strong> Indiquez simplement <strong>la même base MySQL</strong> : la boutique existante est reconnectée sans rien effacer (produits, commandes, clientes, réglages), et vos clés Stripe, Printful et e-mail, enregistrées en base, sont conservées — laissez ces champs vides. Le compte administrateur saisi ci-dessous est créé, ou son mot de passe remplacé.
  </div>

  <h2>Vérifications du serveur</h2>
  <ul class="checks">
    <?php foreach ($checks as $label => $ok): ?><li class="<?= $ok ? 'ok' : 'ko' ?>"><?= $ok ? '✔' : '✘' ?> <?= e($label) ?></li><?php endforeach; ?>
  </ul>

  <?php if ($errors): ?><div class="alert alert-error"><?php foreach ($errors as $err): ?><p><?= e($err) ?></p><?php endforeach; ?></div><?php endif; ?>

  <form method="post" action="<?= e(url('/')) ?>">
    <?= csrf_field() ?>
    <fieldset>
      <legend>Boutique</legend>
      <label>Nom de la boutique <input name="shop_name" required value="<?= e($old['shop_name'] ?? '') ?>"></label>
      <label>URL publique (https://…) <input name="app_url" type="url" required value="<?= e($old['app_url'] ?? '') ?>"></label>
      <label class="checkbox"><input type="checkbox" name="demo" value="1" <?= !isset($old['admin_email']) || !empty($old['demo']) ? 'checked' : '' ?>> Installer le catalogue de démonstration</label>
    </fieldset>

    <fieldset>
      <legend>Base de données</legend>
      <p class="muted">hPanel › Bases de données › MySQL : créez une base et un utilisateur, puis reportez-les ici.</p>
      <label>Type
        <select name="db_driver">
          <option value="mysql" <?= ($old['db_driver'] ?? 'mysql') === 'mysql' ? 'selected' : '' ?>>MySQL / MariaDB (Hostinger)</option>
          <option value="sqlite" <?= ($old['db_driver'] ?? '') === 'sqlite' ? 'selected' : '' ?>>SQLite (test local uniquement)</option>
        </select>
      </label>
      <div class="grid-2">
        <label>Hôte <input name="db_host" value="<?= e($old['db_host'] ?? 'localhost') ?>"></label>
        <label>Port <input name="db_port" value="<?= e($old['db_port'] ?? '3306') ?>"></label>
      </div>
      <label>Nom de la base <input name="db_name" value="<?= e($old['db_name'] ?? '') ?>" placeholder="u123456789_boutique"></label>
      <div class="grid-2">
        <label>Utilisateur <input name="db_user" value="<?= e($old['db_user'] ?? '') ?>" autocomplete="off"></label>
        <label>Mot de passe <input name="db_pass" type="password" autocomplete="new-password"></label>
      </div>
    </fieldset>

    <fieldset>
      <legend>Compte administrateur</legend>
      <label>E-mail <input name="admin_email" type="email" required value="<?= e($old['admin_email'] ?? '') ?>"></label>
      <label>Mot de passe (10 caractères min.) <input name="admin_password" type="password" required minlength="10" autocomplete="new-password"></label>
    </fieldset>

    <fieldset>
      <legend>E-mails (facultatif — vide = conserver l'existant)</legend>
      <p class="muted">hPanel › E-mails : créez une boîte (ex. contact@votre-domaine.fr).</p>
      <div class="grid-2">
        <label>Serveur SMTP <input name="mail_host" value="<?= e($old['mail_host'] ?? 'smtp.hostinger.com') ?>"></label>
        <label>Port <input name="mail_port" value="<?= e($old['mail_port'] ?? '465') ?>"></label>
      </div>
      <div class="grid-2">
        <label>Adresse e-mail <input name="mail_user" type="email" value="<?= e($old['mail_user'] ?? '') ?>"></label>
        <label>Mot de passe <input name="mail_pass" type="password" autocomplete="new-password"></label>
      </div>
    </fieldset>

    <fieldset>
      <legend>Stripe & Printful (facultatif)</legend>
      <p class="muted">Laissez vide pour conserver les clés déjà enregistrées lors d'une précédente installation.</p>
      <label>Stripe — clé secrète <input name="stripe_secret" placeholder="sk_live_…" autocomplete="off" value="<?= e($old['stripe_secret'] ?? '') ?>"></label>
      <label>Stripe — clé publique <input name="stripe_public" placeholder="pk_live_…" autocomplete="off" value="<?= e($old['stripe_public'] ?? '') ?>"></label>
      <label>Stripe — secret du webhook <input name="stripe_webhook" placeholder="whsec_…" autocomplete="off" value="<?= e($old['stripe_webhook'] ?? '') ?>"></label>
      <label>Printful — jeton API privé <input name="printful_token" autocomplete="off" value="<?= e($old['printful_token'] ?? '') ?>"></label>
      <label>Printful — ID de boutique <input name="printful_store" autocomplete="off" value="<?= e($old['printful_store'] ?? '') ?>"></label>
    </fieldset>

    <button class="btn btn-primary btn-lg" type="submit">Installer</button>
  </form>
</main>
<script src="<?= e(asset('assets/js/password-toggle.js')) ?>" defer></script>
</body>
</html>
