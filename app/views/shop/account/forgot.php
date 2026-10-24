<section class="section">
  <div class="container narrow">
    <h1>Mot de passe <em>oublié</em></h1>
    <p class="lead">Indiquez l'adresse e-mail de votre compte : nous vous envoyons un lien pour en choisir un nouveau.</p>
    <form action="<?= e(url('compte/mot-de-passe-oublie')) ?>" method="post" class="form-card">
      <?= csrf_field() ?>
      <div class="field"><label for="email">Adresse e-mail</label><input id="email" type="email" name="email" required autocomplete="email" autofocus></div>
      <button class="btn btn-primary" type="submit">Recevoir le lien</button>
      <div class="form-links"><a href="<?= e(url('compte/connexion')) ?>">← Retour à la connexion</a></div>
    </form>
  </div>
</section>
