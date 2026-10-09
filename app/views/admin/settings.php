<div class="page-head"><h1>Paramètres</h1><div class="row">
  <?php if (setting('onboarding_hidden', '0') === '1'): ?><form method="post" action="<?= url('admin/onboarding') ?>"><?= csrf_field() ?><button class="btn" type="submit"><?= icon('sparkles', 18) ?> Réafficher le guide de démarrage</button></form><?php endif; ?>
  <a class="btn" href="<?= url('admin/rgpd') ?>"><?= icon('lock', 18) ?> Fiche RGPD et sécurité</a></div></div>
<?php if ($test): ?><div class="flash flash-<?= $test['ok'] ? 'success' : 'error' ?>"><?= icon($test['ok'] ? 'sparkles' : 'alert') ?><div><?= e($test['msg']) ?></div></div><?php endif; ?>
<div class="grid grid-2">
  <form method="post" class="card" enctype="multipart/form-data" id="general">
    <?= csrf_field() ?>
    <div class="card-head"><h2><?= icon('settings') ?> Général</h2></div>
    <div class="card-body">
      <div class="field">
        <label>Logo de l'entreprise</label>
        <div class="logo-setting">
          <div class="logo-preview"><?php if ($logo = brand_logo_url()): ?><img src="<?= e($logo) ?>" alt="Logo actuel" id="logo-preview"><?php else: ?><img id="logo-preview" class="hidden" alt=""><span class="muted" id="logo-empty">Aucun logo</span><?php endif; ?></div>
          <div style="flex:1">
            <input type="file" name="logo" accept="image/png,image/jpeg,image/webp" data-preview="#logo-preview" data-no-shrink>
            <small>PNG à fond transparent recommandé (JPEG ou WEBP acceptés). Affiché dans le menu, sur la page de connexion, les bons de commande et les e-mails.</small>
            <?php if (brand_logo_url()): ?><label class="check mt-1" style="font-weight:500"><input type="checkbox" name="logo_remove" value="1"> Supprimer le logo</label><?php endif; ?>
          </div>
        </div>
      </div>
      <div class="field"><label>Nom de l'application</label><input type="text" name="app_name" value="<?= e(app_name()) ?>"></div>
      <div class="field"><label>Raison sociale (bons de commande)</label><input type="text" name="company_name" value="<?= e(setting('company_name')) ?>"></div>
      <div class="field"><label>Adresse du siège</label><textarea name="company_address" rows="2"><?= e(setting('company_address')) ?></textarea></div>
      <div class="field"><label>Informations de facturation (pied de bon)</label><textarea name="billing_info" rows="2" placeholder="Adresse de facturation, SIRET, e-mail comptabilité…"><?= e(setting('billing_info')) ?></textarea></div>
      <div class="field"><label>Message d'accueil des centres</label><input type="text" name="welcome_message" value="<?= e(setting('welcome_message')) ?>" placeholder="De quoi avez-vous besoin aujourd'hui ?"></div>
      <label class="check"><input type="checkbox" name="show_prices" value="1" <?= setting('show_prices', '1') === '1' ? 'checked' : '' ?>> Afficher les prix aux salariés</label>
      <label class="check"><input type="checkbox" name="allow_registration" value="1" <?= setting('allow_registration', '1') === '1' ? 'checked' : '' ?>> Autoriser les demandes de compte en ligne</label>
      <label class="check"><input type="checkbox" name="admin_2fa_required" value="1" <?= admin_2fa_required() ? 'checked' : '' ?>> Exiger la double authentification pour les administrateurs</label>
      <hr>
      <h3><?= icon('activity', 18) ?> Règles de gestion</h3>
      <div class="form-grid">
        <div class="field"><label>Validation par le responsable au-delà de (€ HT)</label><input type="text" name="approval_threshold" value="<?= e(str_replace('.', ',', setting('approval_threshold', '0'))) ?>"><small>0 = pas de validation. Ne s'applique qu'aux centres ayant un « responsable de centre ».</small></div>
        <div class="field"><label>Livraison en retard après (jours)</label><input type="number" min="1" name="late_days" value="<?= e(setting('late_days', '10')) ?>"><small>Ou dès la date de livraison prévue dépassée.</small></div>
        <div class="field"><label>Tolérance d'écart facture (€)</label><input type="text" name="invoice_tolerance" value="<?= e(str_replace('.', ',', setting('invoice_tolerance', '1'))) ?>"></div>
        <div class="field"><label>Conservation des sauvegardes (jours)</label><input type="number" min="3" name="backup_keep_days" value="<?= e(setting('backup_keep_days', '30')) ?>"></div>
      </div>
      <label class="check"><input type="checkbox" name="pseudo_cron" value="1" <?= setting('pseudo_cron', '1') === '1' ? 'checked' : '' ?>> Exécuter les tâches de fond pendant l'utilisation de l'application (si aucune tâche cron n'est programmée)</label>

      <hr>
      <h3><?= icon('sparkles', 18) ?> Assistant de recherche IA (Claude)</h3>
      <div class="chips mb-2">
        <span class="badge <?= $sdk ? 'badge-green' : 'badge-red' ?>">SDK <?= $sdk ? 'installé' : 'absent (lancer composer install)' ?></span>
        <span class="badge <?= $hasKey ? 'badge-green' : 'badge-amber' ?>">Clé API <?= $hasKey ? 'configurée' : 'non configurée' ?></span>
        <span class="badge badge-gray"><?= $cacheCount ?> réponse(s) en cache</span>
      </div>
      <div class="field">
        <label for="ai_api_key">Clé API Anthropic</label>
        <input type="password" id="ai_api_key" name="ai_api_key" autocomplete="new-password" spellcheck="false"
               placeholder="<?= $keyInfo['source'] === 'settings' ? e(mask_secret($keyInfo['key'])) . ' — saisir une nouvelle clé pour la remplacer' : 'sk-ant-…' ?>">
        <small>
          <?php if ($keyInfo['source'] === 'settings'): ?>Clé enregistrée ici (<?= e(mask_secret($keyInfo['key'])) ?>), chiffrée dans la base.
          <?php elseif ($keyInfo['source'] === 'config'): ?>Clé actuellement lue dans <code>config.php</code> (<?= e(mask_secret($keyInfo['key'])) ?>). Une clé saisie ici sera prioritaire.
          <?php elseif ($keyInfo['source'] === 'env'): ?>Clé actuellement lue dans la variable d'environnement <code>ANTHROPIC_API_KEY</code>. Une clé saisie ici sera prioritaire.
          <?php else: ?>Créez une clé sur console.anthropic.com (rubrique API Keys) et collez-la ici.<?php endif; ?>
          Laissez vide pour conserver la clé actuelle.
        </small>
        <?php if ($keyInfo['source'] === 'settings'): ?><label class="check mt-1" style="font-weight:500"><input type="checkbox" name="ai_api_key_remove" value="1"> Supprimer la clé enregistrée</label><?php endif; ?>
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
      <h3><?= icon('lock', 18) ?> Sécurité de la clé API</h3>
      <p class="muted" style="font-size:.9rem">La clé saisie dans « Général » est chiffrée dans la base de données avec une clé propre à votre installation (<code>storage/secret.key</code>, hors d'atteinte depuis le web) et n'est jamais réaffichée en clair. Chaque modification est tracée dans le journal d'audit. Ordre de priorité : clé des paramètres, puis <code>config.php</code>, puis variable d'environnement <code>ANTHROPIC_API_KEY</code>.</p>
      <p class="muted mb-0" style="font-size:.9rem">⚠️ Conservez une copie de <code>storage/secret.key</code> avec vos sauvegardes : sans elle, les clés enregistrées devront être ressaisies après une restauration sur un autre serveur.</p>
    </div>
  </div>
  <form method="post" class="card" style="grid-column:1/-1" id="assistance">
    <?= csrf_field() ?><input type="hidden" name="action" value="support_hub">
    <div class="card-head"><h2><?= icon('send') ?> Licence et assistance NLapps</h2>
      <?php if (licence_platform()): ?><span class="badge badge-green">Gérée par NLapps</span><?php elseif ($hub['source'] !== 'none'): ?><span class="badge badge-green">Activée<?= $hub['source'] === 'config' ? ' (config.php)' : '' ?></span><?php else: ?><span class="badge badge-gray">Non configurée</span><?php endif; ?></div>
    <div class="card-body">
      <p class="muted" style="font-size:.9rem;margin-top:0<?= licence_platform() ? ';display:none' : '' ?>">La clé fournie par NLapps active votre licence, les mises à jour en un clic, les réponses partagées du chatbot et la conversation en direct avec l'équipe (« Parler à un conseiller »). Collez simplement les deux lignes reçues.</p>
      <?php $li = licence_info(); if (($li['status'] ?? '') !== 'unmanaged'): $lt = ['active' => ['Active', 'green'], 'grace' => ['Échue · délai de grâce', 'amber'], 'expired' => ['Expirée', 'red'], 'suspended' => ['Suspendue', 'red'], 'invalid' => ['Clé refusée', 'red'], 'unknown' => ['Pas encore vérifiée', 'gray']][$li['status'] ?? 'unknown'] ?? ['?', 'gray']; ?>
        <div class="licence-box mb-2">
          <div><small class="muted">Licence</small><div><span class="badge badge-<?= $lt[1] ?>"><?= $lt[0] ?></span> <?= !empty($li['plan']) ? e($li['plan']) : '' ?></div></div>
          <div><small class="muted">Échéance</small><div class="strong"><?= !empty($li['paid_until']) ? date_fr($li['paid_until']) : '—' ?></div></div>
          <div><small class="muted">Assistant IA</small><div class="strong"><?= !empty($li['ai']) ? 'Inclus' : 'Non souscrit' ?></div></div>
          <?php if (!empty($li['billing']['online'])): $bi = $li['billing']; ?>
          <div><small class="muted">Paiement</small><div class="strong"><?= !empty($bi['active']) ? e(($bi['method'] === 'sepa_debit' ? 'Prélèvement SEPA' : ($bi['method'] === 'card' ? 'Carte bancaire' : 'Automatique')) . (!empty($bi['next']) ? ' · prochain le ' . date_fr($bi['next']) : '')) : 'À la demande' ?></div>
            <small class="muted"><?= !empty($bi['monthly_ttc']) ? e(money($bi['monthly_ttc'] / 100)) . ' TTC / mois' : '' ?><?= in_array($bi['status'] ?? '', ['past_due', 'unpaid'], true) ? ' · <span style="color:var(--red)">' . e($bi['status_label']) . '</span>' : '' ?></small></div>
          <?php endif; ?>
          <?php if ($payUrl = licence_pay_url()): ?><div style="align-self:center"><a class="btn <?= licence_autopay() ? '' : 'btn-primary' ?>" href="<?= e($payUrl) ?>" target="_blank" rel="noopener"><?= icon('euro', 16) ?> <?= licence_autopay() ? 'Gérer mon abonnement' : 'Payer en ligne' ?></a></div><?php endif; ?>
          <div><small class="muted">Dernière vérification</small><div><?= !empty($li['checked_at']) ? date_fr($li['checked_at'], true) : '—' ?><?= !empty($li['last_error']) ? '<br><small style="color:var(--red)">' . e($li['last_error']) . '</small>' : '' ?></div></div>
        </div>
      <?php endif; ?>
      <?php if (licence_platform()): ?>
        <p class="muted mb-0" style="font-size:.9rem">Licence, abonnement et accès à l'assistance sont gérés par <?= e(support_contact()['editor']) ?> pour votre espace : rien à configurer.<?= support_live_enabled() ? ' La conversation en direct avec l\'équipe est active.' : '' ?></p>
    </div>
  </form>
      <?php else: ?>
      <div class="grid grid-2">
        <div class="field mb-0"><label>Lignes fournies par NLapps</label>
          <textarea name="hub_paste" rows="3" spellcheck="false" style="font-family:monospace;font-size:.82rem" placeholder="'support_hub_url' => 'https://nlapps.fr/assistance/api.php',&#10;'support_hub_key' => 'nlh_…',"></textarea>
          <small>Les champs ci-contre se remplissent automatiquement.</small></div>
        <div>
          <div class="field"><label>Adresse du centre d'assistance</label><input type="url" name="hub_url" value="<?= e($hub['source'] === 'settings' ? $hub['url'] : '') ?>" placeholder="<?= e($hub['source'] === 'config' ? $hub['url'] . ' (config.php)' : 'https://nlapps.fr/assistance/api.php') ?>"></div>
          <div class="field mb-0"><label>Clé d'accès</label><input type="password" name="hub_key" autocomplete="new-password" spellcheck="false"
            placeholder="<?= $hub['source'] !== 'none' ? e(mask_secret($hub['key'])) . ' — saisir une nouvelle clé pour la remplacer' : 'nlh_…' ?>">
            <small>Chiffrée dans la base, jamais réaffichée en clair.</small></div>
        </div>
      </div>
      <div class="row row-wrap mt-1">
        <button class="btn btn-primary" type="submit" name="hub_do" value="save"><?= icon('check', 16) ?> Enregistrer et tester</button>
        <?php if ($hub['source'] !== 'none'): ?><button class="btn" type="submit" name="hub_do" value="test" formnovalidate><?= icon('check-circle', 16) ?> Vérifier maintenant</button><?php endif; ?>
        <?php if ($hub['source'] === 'settings'): ?><button class="btn btn-danger" type="submit" name="hub_do" value="remove" formnovalidate data-confirm="Supprimer l'accès au centre d'assistance ? La conversation en direct sera désactivée."><?= icon('trash', 16) ?> Supprimer l'accès</button><?php endif; ?>
      </div>
    </div>
  </form>
      <?php endif; ?>
  <form method="post" class="card" style="grid-column:1/-1" id="mail">
    <?= csrf_field() ?><input type="hidden" name="action" value="notifications">
    <div class="card-head"><h2><?= icon('bell') ?> Notifications &amp; e-mails</h2></div>
    <div class="card-body">
      <div style="max-width:900px">
        <h3>Envoi des e-mails</h3>
        <label class="check"><input type="checkbox" name="mail_enabled" value="1" <?= setting('mail_enabled', '0') === '1' ? 'checked' : '' ?>> <strong>Activer l'envoi d'e-mails</strong> <small class="muted">(interrupteur général)</small></label>
        <small class="muted" style="display:block;margin:-.2rem 0 .6rem 1.7rem">Désactivé : aucun e-mail ne part, quels que soient les réglages ci-dessous. Les notifications dans l'application continuent.</small>
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
    <div class="card-body" style="border-top:1px solid var(--border)">
      <h3>Réglage au cas par cas</h3>
      <p class="muted" style="font-size:.88rem">Pour chaque situation, choisissez la notification dans l'application et/ou l'e-mail. Chaque utilisateur peut ensuite désactiver ce qui ne l'intéresse pas dans « Mon profil ».</p>
      <div class="table-wrap"><table class="table">
        <thead><tr><th>Situation</th><th>Destinataires</th><th class="text-center">Dans l'application</th><th class="text-center">Par e-mail</th></tr></thead>
        <tbody>
        <?php foreach (NOTIFY_EVENTS as $k => $ev): ?>
          <tr>
            <td class="strong"><?= e($ev['label']) ?></td>
            <td><small class="muted"><?= ['admin' => 'Administrateurs', 'user' => 'Demandeur', 'both' => 'Administrateurs et salariés', 'manager' => 'Responsables de centre'][$ev['for']] ?? '' ?></small></td>
            <td class="text-center"><input type="checkbox" name="events[<?= $k ?>]" value="1" <?= notify_event_enabled($k) ? 'checked' : '' ?> aria-label="Notification dans l'application"></td>
            <td class="text-center"><input type="checkbox" name="mails[<?= $k ?>]" value="1" <?= setting('mailev_' . $k, '1') === '1' ? 'checked' : '' ?> aria-label="E-mail"></td>
          </tr>
        <?php endforeach; ?>
        <?php foreach (MAIL_CASES as $k => $label): ?>
          <tr>
            <td class="strong"><?= e($label) ?></td>
            <td><small class="muted"><?= $k === 'password_reset' ? 'Salarié concerné' : ($k === 'supplier_po' ? 'Fournisseur' : 'Administrateur qui envoie') ?></small></td>
            <td class="text-center"><small class="muted">—</small></td>
            <td class="text-center"><input type="checkbox" name="mails[<?= $k ?>]" value="1" <?= setting('mailev_' . $k, '1') === '1' ? 'checked' : '' ?> aria-label="E-mail"></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    </div>
    <div class="card-foot row">
      <button class="btn btn-primary" type="submit"><?= icon('check', 18) ?> Enregistrer</button>
      <button class="btn" type="submit" name="action" value="test_mail"><?= icon('mail', 18) ?> M'envoyer un e-mail de test</button>
    </div>
  </form>
  <div class="card" style="grid-column:1/-1">
    <div class="card-head"><h2><?= icon('clock') ?> Tâches planifiées &amp; file d'e-mails</h2></div>
    <div class="card-body grid grid-2">
      <div>
        <p class="muted" style="font-size:.9rem">Envoi des e-mails, rappels la veille des dates limites, relance des livraisons en retard, sauvegarde quotidienne et nettoyage. Pour une exécution régulière, programmez chez votre hébergeur (toutes les 5 à 15 minutes) :</p>
        <div class="field"><label>Commande (cron)</label><input type="text" readonly value="php <?= e(ROOT) ?>/cron.php" onclick="this.select()"></div>
        <div class="field"><label>ou URL à appeler</label><input type="text" readonly value="<?= e(app_base_url() . 'cron.php?key=' . setting('cron_key')) ?>" onclick="this.select()"></div>
        <ul class="list" style="font-size:.88rem">
          <?php foreach (CRON_TASKS as $k => $t): $last = cron_last($k); ?>
            <li style="padding:.4rem 0"><div class="grow"><?= e($t['label']) ?></div><small class="muted"><?= $last ? 'dernière exécution ' . date('d/m H:i', $last) : 'jamais' ?></small></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <div>
        <?php $mq = one('SELECT SUM(CASE WHEN sent_at IS NULL AND attempts < 5 THEN 1 ELSE 0 END) AS waiting, SUM(CASE WHEN sent_at IS NULL AND attempts >= 5 THEN 1 ELSE 0 END) AS failed, SUM(CASE WHEN sent_at IS NOT NULL THEN 1 ELSE 0 END) AS sent FROM mail_queue'); ?>
        <div class="chips mb-2">
          <span class="badge badge-amber"><?= (int)$mq['waiting'] ?> e-mail(s) en attente</span>
          <span class="badge <?= (int)$mq['failed'] ? 'badge-red' : 'badge-gray' ?>"><?= (int)$mq['failed'] ?> en échec</span>
          <span class="badge badge-green"><?= (int)$mq['sent'] ?> envoyé(s) (30 j)</span>
        </div>
        <?php $err = one('SELECT last_error, to_email FROM mail_queue WHERE sent_at IS NULL AND last_error IS NOT NULL ORDER BY id DESC LIMIT 1'); if ($err): ?><div class="flash flash-error" style="font-size:.85rem"><?= icon('alert', 16) ?><div>Dernière erreur (<?= e($err['to_email']) ?>) : <?= e($err['last_error']) ?></div></div><?php endif; ?>
        <form method="post" action="<?= url('admin/mail-queue') ?>" class="row row-wrap">
          <?= csrf_field() ?>
          <button class="btn" type="submit" name="action" value="retry"><?= icon('repeat', 16) ?> Relancer les e-mails en attente</button>
          <button class="btn" type="submit" name="action" value="run"><?= icon('check-circle', 16) ?> Exécuter toutes les tâches maintenant</button>
        </form>
      </div>
    </div>
  </div>
</div>
