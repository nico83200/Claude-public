<section class="section">
  <div class="container narrow">
    <h1>Suivre ma commande</h1>
    <p class="lead">Indiquez votre numéro de commande (reçu par e-mail) et l'adresse e-mail utilisée lors de l'achat.</p>
    <form action="<?= e(url('suivi')) ?>" method="post" class="form-card">
      <?= csrf_field() ?>
      <div class="field"><label for="number">Numéro de commande</label><input id="number" name="number" required placeholder="<?= e(setting('order_prefix')) ?>-1001" value="<?= e(old('number')) ?>"></div>
      <div class="field"><label for="email">Adresse e-mail</label><input id="email" type="email" name="email" required value="<?= e(old('email')) ?>"></div>
      <button class="btn btn-primary" type="submit">Voir ma commande</button>
    </form>
  </div>
</section>
