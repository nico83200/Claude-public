<section class="page-hero page-hero-sm">
  <div class="container narrow">
    <h1>Contact</h1>
    <p class="lead">Une question sur une taille, une personnalisation, une commande pour ton club ? On te répond sous 48 h ouvrées.</p>
  </div>
</section>
<section class="section section-tight">
  <div class="container narrow">
    <form action="<?= e(url('contact')) ?>" method="post" class="form-card">
      <?= csrf_field() ?>
      <input type="text" name="website" class="hp" tabindex="-1" autocomplete="off" aria-hidden="true">
      <div class="grid-2">
        <div class="field"><label for="c-name">Nom</label><input id="c-name" name="name" required value="<?= e(old('name')) ?>" autocomplete="name"></div>
        <div class="field"><label for="c-email">E-mail</label><input id="c-email" type="email" name="email" required value="<?= e(old('email')) ?>" autocomplete="email"></div>
      </div>
      <div class="grid-2">
        <div class="field"><label for="c-subject">Sujet</label>
          <select id="c-subject" name="subject">
            <?php foreach (['Question sur un article', 'Suivi de commande', 'Personnalisation', 'Commande pour un club / une écurie', 'Autre'] as $s): ?>
              <option <?= old('subject') === $s ? 'selected' : '' ?>><?= e($s) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field"><label for="c-order">N° de commande <span class="muted">(facultatif)</span></label><input id="c-order" name="order_number" value="<?= e(old('order_number')) ?>"></div>
      </div>
      <div class="field"><label for="c-message">Message</label><textarea id="c-message" name="message" rows="6" required minlength="10"><?= e(old('message')) ?></textarea></div>
      <label class="checkbox small"><input type="checkbox" name="consent" value="1" required> <span>J'accepte que mes données soient utilisées pour répondre à ma demande (<a href="<?= e(url('page/confidentialite')) ?>">politique de confidentialité</a>).</span></label>
      <button class="btn btn-primary" type="submit">Envoyer</button>
    </form>
    <?php if (setting('contact_email')): ?><p class="muted center">Ou directement : <a href="mailto:<?= e(setting('contact_email')) ?>"><?= e(setting('contact_email')) ?></a></p><?php endif; ?>
  </div>
</section>
