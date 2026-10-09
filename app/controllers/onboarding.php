<?php
declare(strict_types=1);

/** Démarrage guidé : masquer / réafficher le guide sur le pilotage. */
function admin_onboarding(): void
{
    require_superadmin();
    if (is_post()) {
        set_setting('onboarding_hidden', input('hide') ? '1' : '0');
        flash('success', input('hide') ? 'Guide de démarrage masqué : vous pouvez le réafficher depuis Paramètres.' : 'Guide de démarrage réaffiché sur le pilotage.');
    }
    redirect_back('admin');
}

/** Invitation des salariés en nombre : comptes créés, lien de choix du mot de passe valable 7 jours. */
function admin_invite(): void
{
    $me = require_superadmin();
    $centers = all('SELECT id, name, color FROM centers WHERE active = 1 ORDER BY name');
    if (is_post()) {
        [$people, $bad] = invite_parse((string)input('people', ''));
        $centerIds = array_values(array_intersect(array_map('intval', (array)($_POST['centers'] ?? [])), array_map('intval', array_column($centers, 'id'))));
        $role = in_array(input('role'), ['user', 'manager', 'buyer'], true) ? (string)input('role') : 'user';
        $job = mb_substr(trim((string)input('job', '')), 0, 100) ?: null;
        if (!$people) {
            flash('error', 'Aucune adresse e-mail reconnue dans la liste.');
            redirect('admin/invite');
        }
        if (!$centerIds && $role !== 'buyer') {
            flash('error', 'Cochez au moins un centre : chaque salarié commande pour un ou plusieurs centres.');
            $_SESSION['invite_draft'] = (string)input('people', '');
            redirect('admin/invite');
        }
        $sendMail = setting('mail_enabled', '0') === '1' && input('send') === '1';
        $results = [];
        foreach ($people as [$first, $last, $email]) {
            $existing = one('SELECT id, status FROM users WHERE email = ?', [$email]);
            if ($existing && $existing['status'] === 'active') {
                $results[] = ['name' => "$first $last", 'email' => $email, 'status' => 'existe déjà', 'link' => null];
                continue;
            }
            $id = tx(function () use ($existing, $first, $last, $email, $role, $job, $centerIds) {
                $data = ['first_name' => $first, 'last_name' => $last, 'role' => $role, 'job' => $job, 'status' => 'active'];
                if ($existing) { // demande d'accès en attente : on la valide
                    update('users', $data, 'id = ?', [$existing['id']]);
                    $id = (int)$existing['id'];
                } else {
                    $id = insert('users', $data + ['email' => $email, 'password_hash' => password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT), 'created_at' => now()]);
                }
                q('DELETE FROM user_centers WHERE user_id = ?', [$id]);
                foreach ($centerIds as $cid) {
                    insert('user_centers', ['user_id' => $id, 'center_id' => $cid]);
                }
                return $id;
            });
            $u = one('SELECT * FROM users WHERE id = ?', [$id]);
            $link = url('reset', ['token' => password_reset_create($u, 7 * 86400), 'invite' => 1]);
            $mailed = $sendMail && send_mail($email, 'Votre accès à ' . app_name(), mail_template($u, 'Bienvenue sur ' . app_name(),
                $me['first_name'] . ' ' . $me['last_name'] . ' vous a créé un accès à ' . app_name()
                . ", l'outil de commande de vos fournitures.\nCliquez sur le bouton ci-dessous pour choisir votre mot de passe (lien valable 7 jours) ; votre identifiant est votre adresse e-mail : " . $email . '.', $link));
            $results[] = ['name' => "$first $last", 'email' => $email, 'status' => $existing ? 'demande validée' : 'compte créé', 'link' => app_base_url() . $link, 'mailed' => $mailed];
        }
        $created = count(array_filter($results, fn($r) => $r['link']));
        audit('Invitations envoyées', 'user', null, $created . ' compte(s) — rôle ' . $role . ', centres ' . implode(',', $centerIds));
        $_SESSION['invite_results'] = ['rows' => $results, 'bad' => $bad, 'mail' => $sendMail];
        flash('success', plural($created, 'invitation préparée', 'invitations préparées') . ($sendMail ? ' et envoyée(s) par e-mail.' : ' : transmettez les liens ci-dessous.'));
        redirect('admin/invite');
    }
    $results = $_SESSION['invite_results'] ?? null;
    $draft = $_SESSION['invite_draft'] ?? '';
    unset($_SESSION['invite_results'], $_SESSION['invite_draft']);
    render('admin/invite', [
        'title' => 'Inviter des salariés', 'centers' => $centers, 'results' => $results, 'draft' => $draft,
        'mailOn' => setting('mail_enabled', '0') === '1',
        'registerUrl' => setting('allow_registration', '1') === '1' ? app_base_url() . url('register') : null,
        'jobs' => job_choices(),
    ]);
}
