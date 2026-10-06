<h1 class="mb-2">Paramètres</h1>
<?php if ($test): ?><div class="flash flash-<?= $test['ok'] ? 'success' : 'error' ?>"><?= icon($test['ok'] ? 'sparkles' : 'alert') ?><div><?= e($test['msg']) ?></div></div><?php endif; ?>
<div class="grid grid-2">
  <form method="post" class="card">
    <?= csrf_field() ?>
    <div class="card-head"><h2><?= icon('settings') ?> Général</h2></div>
    <div class="card-body">
      <div class="field"><label>Nom de l'application</label><input type="text" name="app_name" value="<?= e(app_name()) ?>"></div>
      <div class="field"><label>Raison sociale (bons de commande)</label><input type="text" name="company_name" value="<?= e(setting('company_name')) ?>"></div>
      <div class="field"><label>Adresse du siège</label><textarea name="company_address" rows="2"><?= e(setting('company_address')) ?></textarea></div>
      <div class="field"><label>Informations de facturation (pied de bon)</label><textarea name="billing_info" rows="2" placeholder="Adresse de facturation, SIRET, e-mail comptabilité…"><?= e(setting('billing_info')) ?></textarea></div>
      <div class="field"><label>Message d'accueil des centres</label><input type="text" name="welcome_message" value="<?= e(setting('welcome_message')) ?>" placeholder="De quoi avez-vous besoin aujourd'hui ?"></div>
      <label class="check"><input type="checkbox" name="show_prices" value="1" <?= setting('show_prices', '1') === '1' ? 'checked' : '' ?>> Afficher les prix aux salariés</label>
      <label class="check"><input type="checkbox" name="allow_registration" value="1" <?= setting('allow_registration', '1') === '1' ? 'checked' : '' ?>> Autoriser les demandes de compte en ligne</label>

      <hr>
      <h3><?= icon('sparkles', 18) ?> Assistant de recherche IA (Claude)</h3>
      <div class="chips mb-2">
        <span class="badge <?= $sdk ? 'badge-green' : 'badge-red' ?>">SDK <?= $sdk ? 'installé' : 'absent (lancer composer install)' ?></span>
        <span class="badge <?= $hasKey ? 'badge-green' : 'badge-amber' ?>">Clé API <?= $hasKey ? 'configurée' : 'non configurée (config.php)' ?></span>
        <span class="badge badge-gray"><?= $cacheCount ?> réponse(s) en cache</span>
      </div>
      <label class="check"><input type="checkbox" name="ai_enabled" value="1" <?= setting('ai_enabled', '1') === '1' ? 'checked' : '' ?>> Activer l'assistant IA dans le catalogue</label>
      <div class="form-grid mt-1">
        <div class="field"><label>Modèle</label>
          <select name="ai_model">
            <?php foreach (['claude-opus-5-5' => 'Claude Opus 5.5 (recommandé, le plus pertinent)', 'claude-sonnet-5-5' => 'Claude Sonnet 5.5 (plus économique)', 'claude-haiku-4-5' => 'Claude Haiku 4.5 (le plus rapide et économique)'] as $k => $l): ?>
              <option value="<?= $k ?>" <?= ai_model() === $k ? 'selected' : '' ?>><?= $l ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field"><label>Articles max. transmis</label><input type="number" name="ai_max_products" min="50" value="<?= e(setting('ai_max_products', '1500')) ?>"></div>
      </div>
      <small class="muted">Seuls les noms, catégories, mots-clés et descriptifs des articles sont transmis — aucune donnée patient ni personnelle. Les réponses sont mises en cache 7 jours.</small>
    </div>
    <div class="card-foot"><button class="btn btn-primary" type="submit"><?= icon('check', 18) ?> Enregistrer</button></div>
  </form>
  <div class="stack">
    <form method="post" class="card">
      <?= csrf_field() ?><input type="hidden" name="action" value="test_ai">
      <div class="card-head"><h2><?= icon('sparkles') ?> Tester l'assistant</h2></div>
      <div class="card-body">
        <div class="field"><label>Requête de test</label><input type="text" name="test_query" value="de quoi désinfecter la table d'examen"></div>
        <button class="btn btn-primary" type="submit" <?= $hasKey && $sdk ? '' : 'disabled' ?>>Lancer le test</button>
      </div>
    </form>
    <form method="post" class="card card-body">
      <?= csrf_field() ?><input type="hidden" name="action" value="clear_ai_cache">
      <h3>Cache de l'assistant</h3>
      <p class="muted">À vider après une grosse mise à jour du catalogue (sinon automatique : le cache dépend du contenu du catalogue).</p>
      <button class="btn btn-sm" type="submit">Vider le cache</button>
    </form>
    <div class="card card-body">
      <h3><?= icon('info', 18) ?> Configuration de la clé API</h3>
      <p class="muted" style="font-size:.9rem">Pour des raisons de sécurité, la clé n'est pas saisie ici. Renseignez <code>'anthropic_api_key'</code> dans le fichier <code>config.php</code> du serveur, ou la variable d'environnement <code>ANTHROPIC_API_KEY</code>. Une clé se crée sur console.anthropic.com.</p>
    </div>
  </div>
  <form method="post" class="card" style="grid-column:1/-1">
    <?= csrf_field() ?><input type="hidden" name="action" value="notifications">
    <div class="card-head"><h2><?= icon('bell') ?> Notifications &amp; e-mails</h2></div>
    <div class="card-body grid grid-2">
      <div>
        <h3>Événements notifiés</h3>
        <p class="muted" style="font-size:.88rem">Décochez un événement pour ne plus le notifier à personne. Chaque utilisateur peut ensuite affiner dans son profil.</p>
        <?php foreach (NOTIFY_EVENTS as $k => $ev): ?>
          <label class="check"><input type="checkbox" name="events[<?= $k ?>]" value="1" <?= notify_event_enabled($k) ? 'checked' : '' ?>> <?= e($ev['label']) ?> <small class="muted">— <?= ['admin' => 'administrateurs', 'user' => 'demandeur', 'both' => 'administrateurs et salariés'][$ev['for']] ?></small></label>
        <?php endforeach; ?>
      </div>
      <div>
        <h3>Envoi des e-mails</h3>
        <label class="check"><input type="checkbox" name="mail_enabled" value="1" <?= setting('mail_enabled', '0') === '1' ? 'checked' : '' ?>> Envoyer aussi les notifications par e-mail</label>
        <div class="form-grid mt-1">
          <div class="field"><label>Adresse d'expédition</label><input type="email" name="mail_from" value="<?= e(setting('mail_from')) ?>" placeholder="achats@votre-groupe.fr"></div>
          <div class="field"><label>Nom d'expéditeur</label><input type="text" name="mail_from_name" value="<?= e(setting('mail_from_name')) ?>" placeholder="<?= e(app_name()) ?>"></div>
          <div class="field full"><label>Adresse de l'application (liens dans les e-mails)</label><input type="url" name="app_url" value="<?= e(setting('app_url')) ?>" placeholder="<?= e(app_base_url()) ?>"></div>
          <div class="field"><label>Serveur SMTP (optionnel)</label><input type="text" name="smtp_host" value="<?= e(setting('smtp_host')) ?>" placeholder="vide = fonction mail() de l'hébergeur"></div>
          <div class="field"><label>Port</label><input type="number" name="smtp_port" value="<?= e(setting('smtp_port', '587')) ?>"></div>
          <div class="field"><label>Utilisateur SMTP</label><input type="text" name="smtp_user" value="<?= e(setting('smtp_user')) ?>" autocomplete="off"></div>
          <div class="field"><label>Mot de passe SMTP</label><input type="password" name="smtp_pass" placeholder="<?= setting('smtp_pass') ? '•••••••• (inchangé)' : '' ?>" autocomplete="new-password"></div>
          <div class="field"><label>Sécurité</label><select name="smtp_secure"><?php foreach (['tls' => 'STARTTLS (587)', 'ssl' => 'SSL (465)', 'none' => 'Aucune'] as $k => $l): ?><option value="<?= $k ?>" <?= setting('smtp_secure', 'tls') === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
        </div>
      </div>
    </div>
    <div class="card-foot row">
      <button class="btn btn-primary" type="submit"><?= icon('check', 18) ?> Enregistrer</button>
      <button class="btn" type="submit" name="action" value="test_mail"><?= icon('mail', 18) ?> M'envoyer un e-mail de test</button>
    </div>
  </form>
</div>
