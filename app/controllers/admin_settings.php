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
        $data = [
            'name' => (string)input('name'),
            'code' => (string)input('code') ?: null,
            'address' => (string)input('address') ?: null,
            'city' => (string)input('city') ?: null,
            'phone' => (string)input('phone') ?: null,
            'delivery_info' => (string)input('delivery_info') ?: null,
            'color' => preg_match('/^#[0-9a-f]{6}$/i', (string)input('color')) ? input('color') : '#6366f1',
            'active' => input('active') === '1' ? 1 : 0,
        ];
        if ($data['name'] === '') {
            flash('error', 'Le nom du centre est obligatoire.');
        } else {
            if ($id) {
                update('centers', $data, 'id = ?', [$id]);
            } else {
                insert('centers', $data + ['created_at' => now()]);
            }
            flash('success', 'Centre enregistré.');
            redirect('admin/centers');
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
    $sql = 'SELECT * FROM users';
    $params = [];
    if (in_array($status, ['pending', 'active', 'disabled'], true)) {
        $sql .= ' WHERE status = ?';
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
        'counts' => array_column(all('SELECT status, COUNT(*) n FROM users GROUP BY status'), 'n', 'status'),
    ]);
}

function admin_user_edit(): void
{
    $me = require_admin();
    $id = input_int('id');
    $u = $id ? one('SELECT * FROM users WHERE id = ?', [$id]) : null;
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
            'role' => input('role') === 'admin' ? 'admin' : 'user',
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
    render('admin/user_form', ['title' => $u ? $u['first_name'] . ' ' . $u['last_name'] : 'Nouveau compte', 'u' => $u, 'centers' => $centers, 'selected' => $selected]);
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
                ? ['ok' => false, 'msg' => 'Échec de l\'appel à l\'IA : vérifiez la clé API, l\'installation (composer install) et les journaux d\'erreurs PHP.']
                : ['ok' => true, 'msg' => sprintf('Réponse en %.1f s — %d article(s) proposé(s). « %s »', microtime(true) - $t0, count($r['ids']), $r['message'])];
        } elseif (input('action') === 'clear_ai_cache') {
            q('DELETE FROM ai_cache');
            flash('success', 'Cache de l\'assistant IA vidé.');
            redirect('admin/settings');
        } else {
            set_setting('app_name', (string)input('app_name') ?: 'Commandes Centres');
            set_setting('company_name', (string)input('company_name'));
            set_setting('company_address', (string)input('company_address'));
            set_setting('billing_info', (string)input('billing_info'));
            set_setting('show_prices', input('show_prices') === '1' ? '1' : '0');
            set_setting('allow_registration', input('allow_registration') === '1' ? '1' : '0');
            set_setting('ai_enabled', input('ai_enabled') === '1' ? '1' : '0');
            set_setting('ai_model', (string)input('ai_model') ?: 'claude-opus-5-5');
            set_setting('ai_max_products', (string)max(50, input_int('ai_max_products', 1500)));
            set_setting('welcome_message', (string)input('welcome_message'));
            flash('success', 'Paramètres enregistrés.');
            redirect('admin/settings');
        }
    }
    render('admin/settings', [
        'title' => 'Paramètres', 'test' => $test,
        'hasKey' => ai_api_key() !== '', 'sdk' => ai_sdk_installed(),
        'cacheCount' => (int)val('SELECT COUNT(*) FROM ai_cache'),
    ]);
}
