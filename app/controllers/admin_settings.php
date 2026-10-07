<?php
declare(strict_types=1);

// ---------------------------------------------------------------- Centres

function admin_centers(): void
{
    require_admin();
    $centers = all("SELECT c.*,
                      (SELECT COUNT(*) FROM user_centers uc WHERE uc.center_id = c.id) AS nb_users,
                      (SELECT COUNT(*) FROM request_lines rl WHERE rl.center_id = c.id AND rl.status = 'pending') AS nb_pending,
                      (SELECT COUNT(*) FROM purchase_orders po WHERE po.center_id = c.id AND po.status IN ('commande','partiel')) AS nb_to_receive
                    FROM centers c ORDER BY c.active DESC, c.name");
    render('admin/centers', ['title' => 'Centres', 'centers' => $centers]);
}

function admin_center_edit(): void
{
    require_admin();
    $id = input_int('id');
    $c = $id ? one('SELECT * FROM centers WHERE id = ?', [$id]) : null;
    if ($id && !$c) {
        abort(404);
    }
    if (is_post()) {
        $txt = fn(string $k, int $max = 255) => mb_substr(trim((string)input($k, '')), 0, $max) ?: null;
        $data = [
            'name' => (string)$txt('name', 150),
            'code' => $txt('code', 20),
            'legal_name' => $txt('legal_name', 200),
            'contact_name' => $txt('contact_name', 150),
            'email' => $txt('email', 190),
            'phone' => $txt('phone', 40),
            'address' => $txt('address'),
            'address2' => $txt('address2'),
            'city' => $txt('city', 120),
            'delivery_info' => $txt('delivery_info', 2000),
            'billing_same' => input('billing_same') === '1' ? 1 : 0,
            'billing_name' => $txt('billing_name', 200),
            'billing_address' => $txt('billing_address'),
            'billing_city' => $txt('billing_city', 120),
            'billing_email' => $txt('billing_email', 190),
            'billing_notes' => $txt('billing_notes', 2000),
            'siren' => digits_only(input('siren')) ?: null,
            'siret' => digits_only(input('siret')) ?: null,
            'finess' => strtoupper(preg_replace('/\s+/', '', (string)input('finess'))) ?: null,
            'vat_number' => strtoupper(preg_replace('/\s+/', '', (string)input('vat_number'))) ?: null,
            'color' => preg_match('/^#[0-9a-f]{6}$/i', (string)input('color')) ? input('color') : '#6366f1',
            'active' => input('active') === '1' ? 1 : 0,
        ];
        // SIREN déduit du SIRET, TVA calculée à partir du SIREN si non renseignés
        if (!$data['siren'] && $data['siret'] && strlen($data['siret']) === 14) {
            $data['siren'] = substr($data['siret'], 0, 9);
        }
        if (!$data['vat_number'] && $data['siren'] && siren_valid($data['siren'])) {
            $data['vat_number'] = vat_from_siren($data['siren']);
        }
        $errors = [];
        if ($data['name'] === '') {
            $errors[] = 'Le nom du centre est obligatoire.';
        }
        foreach (['email' => 'E-mail du centre', 'billing_email' => 'E-mail de facturation'] as $k => $l) {
            if ($data[$k] && !filter_var($data[$k], FILTER_VALIDATE_EMAIL)) {
                $errors[] = $l . ' invalide.';
            }
        }
        if ($data['siren'] && !siren_valid($data['siren'])) {
            $errors[] = 'SIREN invalide (9 chiffres, clé de contrôle incorrecte).';
        }
        if ($data['siret'] && !siret_valid($data['siret'])) {
            $errors[] = 'SIRET invalide (14 chiffres, clé de contrôle incorrecte).';
        } elseif ($data['siret'] && $data['siren'] && !str_starts_with($data['siret'], $data['siren'])) {
            $errors[] = 'Le SIRET doit commencer par le SIREN.';
        }
        if ($data['finess'] && !finess_valid($data['finess'])) {
            $errors[] = 'N° FINESS invalide (9 caractères : département puis 7 chiffres).';
        }
        if ($data['vat_number'] && !preg_match('/^[A-Z]{2}[0-9A-Z]{2,13}$/', $data['vat_number'])) {
            $errors[] = 'N° de TVA intracommunautaire invalide.';
        } elseif ($data['vat_number'] && $data['siren'] && str_starts_with($data['vat_number'], 'FR') && $data['vat_number'] !== vat_from_siren($data['siren'])) {
            $errors[] = 'Le n° de TVA ne correspond pas au SIREN (attendu : ' . vat_from_siren($data['siren']) . ').';
        }
        if (!$data['billing_same'] && (!$data['billing_address'] || !$data['billing_city'])) {
            $errors[] = 'Adresse de facturation incomplète (ou cochez « identique à l\'adresse de livraison »).';
        }
        if (!$errors) {
            if ($id) {
                $before = one('SELECT * FROM centers WHERE id = ?', [$id]);
                update('centers', $data, 'id = ?', [$id]);
                if ($diff = audit_diff($before, $data, ['name' => 'Nom', 'legal_name' => 'Raison sociale', 'siren' => 'SIREN', 'siret' => 'SIRET',
                    'finess' => 'FINESS', 'vat_number' => 'TVA', 'address' => 'Adresse', 'city' => 'Ville', 'billing_address' => 'Adresse de facturation',
                    'billing_city' => 'Ville de facturation', 'billing_email' => 'E-mail facturation', 'email' => 'E-mail', 'phone' => 'Téléphone', 'active' => 'Actif'])) {
                    audit('Centre modifié', 'center', $id, $data['name'] . ' — ' . $diff);
                }
            } else {
                $id = insert('centers', $data + ['created_at' => now()]);
                audit('Centre créé', 'center', $id, $data['name']);
            }
            flash('success', 'Centre « ' . $data['name'] . ' » enregistré.');
            redirect('admin/centers');
        }
        foreach ($errors as $err) {
            flash('error', $err);
        }
        $c = array_merge($c ?? [], $data);
    }
    render('admin/center_form', ['title' => $c ? 'Centre : ' . $c['name'] : 'Nouveau centre', 'c' => $c]);
}

// ---------------------------------------------------------------- Comptes

function admin_users(): void
{
    require_admin();
    $status = (string)input('status', '');
    $sql = 'SELECT * FROM users WHERE deleted_at IS NULL';
    $params = [];
    if (in_array($status, ['pending', 'active', 'disabled'], true)) {
        $sql .= ' AND status = ?';
        $params[] = $status;
    }
    $sql .= " ORDER BY CASE status WHEN 'pending' THEN 0 WHEN 'active' THEN 1 ELSE 2 END, last_name, first_name";
    $users = all($sql, $params);
    $uc = [];
    foreach (all('SELECT uc.user_id, c.name, c.color FROM user_centers uc JOIN centers c ON c.id = uc.center_id ORDER BY c.name') as $r) {
        $uc[(int)$r['user_id']][] = $r;
    }
    $centerNames = array_column(all('SELECT id, name FROM centers'), 'name', 'id');
    render('admin/users', [
        'title' => 'Comptes utilisateurs', 'users' => $users, 'userCenters' => $uc, 'status' => $status, 'centerNames' => $centerNames,
        'counts' => array_column(all('SELECT status, COUNT(*) n FROM users WHERE deleted_at IS NULL GROUP BY status'), 'n', 'status'),
        'me' => (int)user()['id'],
    ]);
}

/** Suppression d'un ou plusieurs comptes (les comptes avec historique de commandes sont anonymisés). */
function admin_users_delete(): void
{
    $me = require_admin();
    $ids = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['ids'] ?? [])))));
    if (!$ids) {
        flash('error', 'Aucun compte sélectionné.');
        redirect('admin/users');
    }
    $deleted = $anonymized = 0;
    $errors = [];
    foreach ($ids as $id) {
        $email = (string)val('SELECT email FROM users WHERE id = ?', [$id]);
        $r = user_delete($id, (int)$me['id']);
        if ($r === 'deleted' || $r === 'anonymized') {
            $r === 'deleted' ? $deleted++ : $anonymized++;
            audit($r === 'deleted' ? 'Compte supprimé' : 'Compte supprimé (anonymisé)', 'user', $id, $email);
        } else {
            $errors[$r] = true;
        }
    }
    if ($deleted || $anonymized) {
        flash('success', plural($deleted + $anonymized, 'compte supprimé', 'comptes supprimés') . '.'
            . ($anonymized ? ' ' . plural($anonymized, 'compte avait', 'comptes avaient') . ' passé des commandes : '
                . 'nom et e-mail ont été effacés, l\'historique des commandes est conservé de façon anonyme.' : ''));
    }
    foreach (array_keys($errors) as $err) {
        flash('error', $err);
    }
    redirect('admin/users');
}

function admin_user_edit(): void
{
    $me = require_admin();
    $id = input_int('id');
    $u = $id ? one('SELECT * FROM users WHERE id = ? AND deleted_at IS NULL', [$id]) : null;
    if ($id && !$u) {
        abort(404);
    }
    $centers = all('SELECT id, name, city FROM centers WHERE active = 1 ORDER BY name');
    $selected = $id ? array_map('intval', array_column(all('SELECT center_id FROM user_centers WHERE user_id = ?', [$id]), 'center_id')) : [];
    if ($u && $u['status'] === 'pending' && !$selected && $u['requested_centers']) {
        $selected = array_map('intval', explode(',', $u['requested_centers']));
    }
    $tempPassword = null;

    if (is_post()) {
        $data = [
            'first_name' => (string)input('first_name'),
            'last_name' => (string)input('last_name'),
            'email' => mb_strtolower((string)input('email')),
            'job' => (string)input('job') ?: null,
            'phone' => (string)input('phone') ?: null,
            'role' => in_array(input('role'), ['admin', 'manager'], true) ? input('role') : 'user',
            'status' => in_array(input('status'), ['pending', 'active', 'disabled'], true) ? input('status') : 'active',
        ];
        $centerIds = array_map('intval', (array)($_POST['centers'] ?? []));
        $errors = [];
        if ($data['first_name'] === '' || $data['last_name'] === '') {
            $errors[] = 'Nom et prénom obligatoires.';
        }
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'E-mail invalide.';
        } elseif (val('SELECT COUNT(*) FROM users WHERE email = ? AND id <> ?', [$data['email'], $id])) {
            $errors[] = 'Cet e-mail est déjà utilisé.';
        }
        if ($data['role'] !== 'admin' && $data['status'] === 'active' && !$centerIds) {
            $errors[] = 'Un compte actif doit être rattaché à au moins un centre.';
        }
        if ($id === (int)$me['id'] && ($data['role'] !== 'admin' || $data['status'] !== 'active')) {
            $errors[] = 'Vous ne pouvez pas retirer vos propres droits administrateur.';
        }
        $password = (string)($_POST['password'] ?? '');
        if (!$id && $password === '') {
            $password = $tempPassword = substr(str_replace(['/', '+', '='], '', base64_encode(random_bytes(12))), 0, 10);
        }
        if ($password !== '' && mb_strlen($password) < 8) {
            $errors[] = 'Mot de passe : 8 caractères minimum.';
        }
        if (!$errors) {
            tx(function () use (&$id, $data, $centerIds, $password) {
                if ($password !== '') {
                    $data['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
                }
                if ($id) {
                    update('users', $data, 'id = ?', [$id]);
                } else {
                    $id = insert('users', $data + ['created_at' => now()]);
                }
                q('DELETE FROM user_centers WHERE user_id = ?', [$id]);
                foreach (array_unique($centerIds) as $cid) {
                    insert('user_centers', ['user_id' => $id, 'center_id' => $cid]);
                }
            });
            audit($u ? 'Compte modifié' : 'Compte créé', 'user', $id, $data['email'] . ' — rôle ' . $data['role'] . ', statut ' . $data['status'] . ', centres ' . implode(',', $centerIds)
                . (($_POST['password'] ?? '') !== '' ? ', mot de passe réinitialisé' : ''));
            flash('success', 'Compte enregistré' . ($data['status'] === 'active' ? ' et actif.' : '.')
                . ($tempPassword ? ' Mot de passe provisoire à transmettre : ' . $tempPassword : ''));
            redirect('admin/users');
        }
        foreach ($errors as $err) {
            flash('error', $err);
        }
        $u = array_merge($u ?? [], $data);
        $selected = $centerIds;
    }
    render('admin/user_form', ['title' => $u ? $u['first_name'] . ' ' . $u['last_name'] : 'Nouveau compte', 'u' => $u, 'centers' => $centers, 'selected' => $selected,
        'me' => (int)$me['id'], 'hasHistory' => $id ? (int)val('SELECT COUNT(*) FROM requests WHERE user_id = ?', [$id]) : 0]);
}

// ---------------------------------------------------------------- Dates limites de commande

function admin_deadlines(): void
{
    $me = require_admin();
    if (is_post()) {
        if (input('action') === 'delete') {
            q('DELETE FROM deadlines WHERE id = ?', [input_int('id')]);
            flash('success', 'Date limite supprimée.');
        } else {
            $date = (string)input('date');
            $time = (string)input('time') ?: '12:00';
            $ts = strtotime("$date $time");
            $title = (string)input('title');
            $centerIds = array_map('intval', (array)($_POST['centers'] ?? []));
            $repeat = max(1, min(12, input_int('repeat', 1)));
            $every = (string)input('every', 'month');
            if (!$ts || $title === '') {
                flash('error', 'Titre et date obligatoires.');
            } else {
                $n = 0;
                for ($i = 0; $i < $repeat; $i++) {
                    $when = match ($every) {
                        'week'   => strtotime("+$i week", $ts),
                        '2weeks' => strtotime('+' . (2 * $i) . ' weeks', $ts),
                        default  => strtotime("+$i month", $ts),
                    };
                    foreach ($centerIds ?: [null] as $cid) {
                        insert('deadlines', [
                            'title' => $title, 'deadline_at' => date('Y-m-d H:i:s', $when),
                            'supplier_id' => input_int('supplier_id') ?: null, 'center_id' => $cid,
                            'description' => (string)input('description') ?: null,
                            'created_by' => $me['id'], 'created_at' => now(),
                        ]);
                        $n++;
                    }
                }
                flash('success', plural($n, 'date limite créée', 'dates limites créées') . '.');
            }
        }
        redirect('admin/deadlines');
    }
    $showPast = input('past') === '1';
    $rows = all('SELECT d.*, s.name AS supplier_name, s.color AS supplier_color, c.name AS center_name, c.color AS center_color
                 FROM deadlines d LEFT JOIN suppliers s ON s.id = d.supplier_id LEFT JOIN centers c ON c.id = d.center_id
                 WHERE d.deadline_at ' . ($showPast ? '<' : '>=') . ' ? ORDER BY d.deadline_at ' . ($showPast ? 'DESC LIMIT 100' : 'ASC'),
        [date('Y-m-d H:i:s', strtotime('-1 day'))]);
    render('admin/deadlines', [
        'title' => 'Dates limites de commande', 'deadlines' => $rows, 'showPast' => $showPast,
        'suppliers' => all('SELECT id, name FROM suppliers WHERE active = 1 ORDER BY name'),
        'centers' => all('SELECT id, name FROM centers WHERE active = 1 ORDER BY name'),
    ]);
}

// ---------------------------------------------------------------- Paramètres

function admin_settings(): void
{
    require_admin();
    $test = null;
    if (is_post()) {
        if (input('action') === 'test_ai') {
            $products = all('SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE p.active = 1 LIMIT 300');
            $t0 = microtime(true);
            $r = ai_search($products, (string)input('test_query', 'de quoi désinfecter la table d\'examen'));
            $test = $r === null
                ? ['ok' => false, 'msg' => 'Échec de l\'appel à l\'IA : ' . (ai_last_error() ?: 'cause inconnue, voir storage/logs/php-errors.log.')]
                : ['ok' => true, 'msg' => sprintf('Réponse en %.1f s — %d article(s) proposé(s). « %s »', microtime(true) - $t0, count($r['ids']), $r['message'])];
        } elseif (input('action') === 'test_mail') {
            $ok = send_mail((string)user()['email'], 'Test d\'envoi — ' . app_name(),
                mail_template(user(), 'Test d\'envoi réussi', 'Si vous lisez ce message, les notifications par e-mail fonctionnent.', url('admin/settings')));
            flash($ok ? 'success' : 'error', $ok ? 'E-mail de test envoyé à ' . user()['email'] . '. Vérifiez aussi les courriers indésirables.' : 'Échec de l\'envoi : vérifiez la configuration SMTP ou la fonction mail() de l\'hébergement (voir journaux PHP).');
            redirect('admin/settings');
        } elseif (input('action') === 'notifications') {
            set_setting('mail_enabled', input('mail_enabled') === '1' ? '1' : '0');
            foreach (['mail_from', 'mail_from_name', 'smtp_host', 'smtp_port', 'smtp_user', 'app_url'] as $k) {
                set_setting($k, (string)input($k));
            }
            set_setting('smtp_secure', in_array(input('smtp_secure'), ['tls', 'ssl', 'none'], true) ? input('smtp_secure') : 'tls');
            if ((string)($_POST['smtp_pass'] ?? '') !== '') {
                try {
                    set_setting('smtp_pass', encrypt_secret((string)$_POST['smtp_pass']));
                } catch (Throwable $e) {
                    flash('error', 'Mot de passe SMTP non enregistré : ' . $e->getMessage());
                }
            }
            // Chaque cas se règle séparément : notification dans l'application et/ou e-mail
            foreach (NOTIFY_EVENTS as $k => $ev) {
                set_setting('notif_' . $k, isset($_POST['events'][$k]) ? '1' : '0');
                set_setting('mailev_' . $k, isset($_POST['mails'][$k]) ? '1' : '0');
            }
            foreach (MAIL_CASES as $k => $label) {
                set_setting('mailev_' . $k, isset($_POST['mails'][$k]) ? '1' : '0');
            }
            audit('Paramètres de notification modifiés', 'settings');
            flash('success', 'Paramètres de notification enregistrés.');
            redirect('admin/settings');
        } elseif (input('action') === 'clear_ai_cache') {
            q('DELETE FROM ai_cache');
            flash('success', 'Cache de l\'assistant IA vidé.');
            redirect('admin/settings');
        } else {
            try {
                if (input('logo_remove') === '1') {
                    brand_logo_delete();
                    audit('Logo supprimé', 'settings');
                }
                if (!empty($_FILES['logo']['name'])) {
                    brand_logo_save('logo');
                    audit('Logo modifié', 'settings');
                }
            } catch (RuntimeException $e) {
                flash('error', $e->getMessage());
            }
            set_setting('app_name', (string)input('app_name') ?: 'ScanAppro');
            set_setting('company_name', (string)input('company_name'));
            set_setting('company_address', (string)input('company_address'));
            set_setting('billing_info', (string)input('billing_info'));
            set_setting('show_prices', input('show_prices') === '1' ? '1' : '0');
            set_setting('allow_registration', input('allow_registration') === '1' ? '1' : '0');
            set_setting('ai_enabled', input('ai_enabled') === '1' ? '1' : '0');
            // Clé API Anthropic : chiffrée en base, jamais réaffichée en clair
            $newKey = trim((string)($_POST['ai_api_key'] ?? ''));
            if (input('ai_api_key_remove') === '1') {
                set_setting('ai_api_key', null);
                q('DELETE FROM ai_cache');
                audit('Clé API IA supprimée', 'settings');
            } elseif ($newKey !== '') {
                if (!preg_match('/^sk-[A-Za-z0-9_\-]{20,}$/', $newKey)) {
                    flash('error', 'Clé API non enregistrée : format inattendu (une clé Anthropic commence par « sk-ant- »).');
                } else {
                    try {
                        set_setting('ai_api_key', encrypt_secret($newKey));
                        audit('Clé API IA modifiée', 'settings', null, mask_secret($newKey));
                        flash('info', 'Nouvelle clé API enregistrée (' . mask_secret($newKey) . '). Utilisez « Tester l\'assistant » pour la vérifier.');
                    } catch (Throwable $e) {
                        error_log('[ai-key] ' . $e->getMessage());
                        flash('error', 'Clé API non enregistrée : ' . $e->getMessage());
                    }
                }
            }
            set_setting('ai_model', (string)input('ai_model') ?: 'claude-opus-5-5');
            set_setting('ai_max_products', (string)max(50, input_int('ai_max_products', 1500)));
            set_setting('welcome_message', (string)input('welcome_message'));
            set_setting('approval_threshold', (string)(input_money('approval_threshold', 0.0) ?? 0));
            set_setting('late_days', (string)max(1, input_int('late_days', 10)));
            set_setting('invoice_tolerance', (string)(input_money('invoice_tolerance', 1.0) ?? 1));
            set_setting('backup_keep_days', (string)max(3, input_int('backup_keep_days', 30)));
            set_setting('pseudo_cron', input('pseudo_cron') === '1' ? '1' : '0');
            audit('Paramètres généraux modifiés', 'settings');
            flash('success', 'Paramètres enregistrés.');
            redirect('admin/settings');
        }
    }
    render('admin/settings', [
        'title' => 'Paramètres', 'test' => $test,
        'hasKey' => ai_api_key() !== '', 'sdk' => ai_sdk_installed(), 'keyInfo' => ai_key_info(),
        'cacheCount' => (int)val('SELECT COUNT(*) FROM ai_cache'),
    ]);
}
