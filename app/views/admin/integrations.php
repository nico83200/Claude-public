<?php
$rows = [
    ['Stripe (paiement)', config('stripe.secret_key') !== '', str_starts_with((string) config('stripe.secret_key'), 'sk_live') ? 'Mode production' : (config('stripe.secret_key') ? 'Mode test' : 'Clé secrète manquante'), 'test-stripe', 'Tester'],
    ['Printful (production)', config('printful.api_token') !== '', config('printful.auto_confirm') ? 'Confirmation automatique des commandes' : 'Commandes créées en brouillon (confirmation manuelle)', 'test-printful', 'Tester'],
    ['E-mails (' . config('mail.transport') . ')', config('mail.transport') !== 'smtp' || config('mail.username') !== '', config('mail.host') . ':' . config('mail.port'), 'test-mail', 'Envoyer un test'],
    ['Brevo (newsletter)', config('brevo.api_key') !== '', config('brevo.list_id') ? 'Liste #' . config('brevo.list_id') : 'Facultatif', null, null],
    ['Google Analytics 4', config('analytics.ga4_id') !== '', config('analytics.ga4_id') ?: 'Facultatif — chargé après consentement', null, null],
    ['Meta Pixel', config('analytics.meta_pixel_id') !== '', config('analytics.meta_pixel_id') ?: 'Facultatif — chargé après consentement', null, null],
    ['Portrait IA (' . App\Services\AiImage::provider() . ')', App\Services\AiImage::provider() !== 'demo',
        App\Services\AiImage::provider() === 'demo' ? 'Mode démonstration : effet croquis local, sans IA — choisissez OpenAI ou Replicate ci-dessous' : 'Limite : ' . (int) config('ai.daily_per_visitor') . ' créations par visiteuse, ' . (int) config('ai.daily_budget') . ' par jour au total',
        'test-ai', 'Tester'],
];
?>
<section class="card">
  <h2>Connexions</h2>
  <p class="muted small">Les clés sont enregistrées dans la base de données : elles sont conservées lors des mises à jour et des réinstallations. Modifiez-les dans la section « Clés et jetons » ci-dessous.</p>
  <table class="table">
    <?php foreach ($rows as [$label, $ok, $detail, $action, $btn]): ?>
      <tr>
        <td><?= $ok ? '<span class="status status-shipped">Connecté</span>' : '<span class="status status-pending">Non configuré</span>' ?></td>
        <td><strong><?= e($label) ?></strong><br><small class="muted"><?= e($detail) ?></small></td>
        <td class="right"><?php if ($action && $ok): ?><form method="post" action="<?= e(url('admin/integrations/' . $action)) ?>"><?= csrf_field() ?><button class="btn"><?= e($btn) ?></button></form><?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
</section>

<section class="card">
  <h2>Webhooks</h2>
  <dl class="kv">
    <dt>Stripe</dt>
    <dd><code><?= e($stripeHook) ?></code> <button type="button" class="btn btn-sm" data-copy="<?= e($stripeHook) ?>">Copier</button><br>
      <small class="muted">Stripe › Développeurs › Webhooks › Ajouter un endpoint. Événements : <code>checkout.session.completed</code>, <code>checkout.session.async_payment_succeeded</code>, <code>checkout.session.async_payment_failed</code>, <code>checkout.session.expired</code>, <code>charge.refunded</code>. Copiez ensuite le « secret de signature » (whsec_…) dans <a href="#cles">Clés et jetons</a> ci-dessous.</small></dd>
    <dt>Printful</dt>
    <dd><?php if ($printfulHook): ?><code><?= e(preg_replace('#/[^/]+$#', '/••••••', $printfulHook)) ?></code>
      <?php if (config('printful.api_token')): ?><form method="post" action="<?= e(url('admin/integrations/printful-webhook')) ?>" class="inline"><?= csrf_field() ?><button class="btn btn-sm btn-primary">Enregistrer chez Printful</button></form><?php endif; ?>
      <br><small class="muted">Reçoit expéditions (avec n° de suivi), échecs, annulations et mises en attente.</small>
      <?php else: ?><span class="muted">Jeton créé automatiquement à l'enregistrement des clés.</span><?php endif; ?></dd>
  </dl>
</section>

<section class="card">
  <h2>Tâche planifiée (Cron)</h2>
  <p>Dernière exécution : <strong><?= $cronLast ? e(date_fr(date('Y-m-d H:i:s', strtotime($cronLast)))) : 'jamais' ?></strong></p>
  <p class="small">hPanel › Avancé › Tâches Cron › type « PHP » , toutes les 15 minutes :</p>
  <pre class="code">/usr/bin/php <?= e(BASE_PATH) ?>/cron/cron.php</pre>
  <?php if ($cronUrl): ?><p class="small muted">Alternative (service de cron externe) : <code><?= e(preg_replace('#/[^/]+$#', '/••••••', $cronUrl)) ?></code></p><?php endif; ?>
  <form method="post" action="<?= e(url('admin/integrations/run-cron')) ?>"><?= csrf_field() ?><button class="btn">Exécuter maintenant</button></form>
</section>

<?php
use App\Core\Secrets;
$groups = [];
foreach (Secrets::KEYS as $key => [$label, $group, $type]) {
    $groups[$group][$key] = [$label, $type];
}
?>
<section class="card" id="cles">
  <h2>Clés et jetons</h2>
  <p class="muted small">Enregistrés dans la base de données (jamais dans les fichiers du site). Les valeurs secrètes ne sont jamais réaffichées : laissez un champ secret vide pour conserver la valeur actuelle.</p>
  <form method="post" action="<?= e(url('admin/integrations/cles')) ?>" autocomplete="off">
    <?= csrf_field() ?>
    <?php foreach ($groups as $group => $keys): ?>
      <fieldset class="keys-group">
        <legend><?= e($group) ?></legend>
        <div class="form-grid">
          <?php foreach ($keys as $key => [$label, $type]): $field = str_replace('.', '__', $key); $current = (string) config($key, ''); ?>
            <?php if ($type === 'secret'): ?>
              <label><?= e($label) ?>
                <input type="password" name="keys[<?= e($field) ?>]" placeholder="<?= $current !== '' ? e(Secrets::mask($current)) : 'Non renseigné' ?>" autocomplete="new-password">
                <?php if ($current !== ''): ?><span class="small muted"><input type="checkbox" name="clear[<?= e($field) ?>]" value="1"> effacer</span><?php endif; ?>
              </label>
            <?php elseif ($type === 'bool'): ?>
              <label class="checkbox"><input type="checkbox" name="keys[<?= e($field) ?>]" value="1" <?= config($key) ? 'checked' : '' ?>> <?= e($label) ?></label>
            <?php elseif (str_starts_with($type, 'select:')): ?>
              <label><?= e($label) ?>
                <select name="keys[<?= e($field) ?>]"><?php foreach (explode(',', substr($type, 7)) as $opt): ?><option <?= $current === $opt ? 'selected' : '' ?>><?= e($opt) ?></option><?php endforeach; ?></select>
              </label>
            <?php else: ?>
              <label><?= e($label) ?><input name="keys[<?= e($field) ?>]" value="<?= e($current) ?>"></label>
            <?php endif; ?>
          <?php endforeach; ?>
        </div>
      </fieldset>
    <?php endforeach; ?>
    <div class="form-grid">
      <label>Votre mot de passe administrateur (confirmation)
        <input type="password" name="confirm_password" required autocomplete="current-password"></label>
      <button class="btn btn-primary">Enregistrer les clés</button>
    </div>
    <p class="muted small">Si vous modifiez le jeton du webhook Printful, cliquez ensuite sur « Enregistrer chez Printful » ; pour Stripe, mettez à jour le secret du webhook depuis votre tableau de bord Stripe.</p>
  </form>
</section>
