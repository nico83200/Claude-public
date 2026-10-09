<?php
declare(strict_types=1);

/**
 * Démarrage guidé d'un nouveau client : étapes détectées automatiquement à partir des données,
 * affichées sur le pilotage de l'administrateur avec une barre de progression.
 */

/** Étapes : [clé, titre, explication, faite ?, lien, libellé du lien, facultative ?]. */
function onboarding_steps(): array
{
    $count = fn(string $sql, array $p = []) => (int)val($sql, $p);
    $steps = [];
    if (demo_present()) {
        $steps[] = ['demo', 'Retirer les données de démonstration', 'Centres, fournisseurs, articles et comptes fictifs, avant d\'enregistrer vos vraies données.', false, url('admin/cleanup'), 'Nettoyer', false];
    }
    $steps[] = ['org', 'Renseigner votre organisation', 'Raison sociale et adresse (imprimées sur les bons de commande), logo.',
        trim((string)setting('company_name')) !== '' && trim((string)setting('company_address')) !== '', url('admin/settings') . '#general', 'Paramètres', false];
    $steps[] = ['centers', 'Créer vos centres', 'Chaque site qui commande : adresse de livraison, identité légale, couleur.',
        $count('SELECT COUNT(*) FROM centers WHERE active = 1') > 0, url('admin/center'), 'Ajouter un centre', false];
    $steps[] = ['suppliers', 'Ajouter vos fournisseurs', 'Coordonnées, mode de commande, minimum et franco de port.',
        $count('SELECT COUNT(*) FROM suppliers WHERE active = 1') > 0, url('admin/supplier'), 'Ajouter un fournisseur', false];
    $steps[] = ['catalog', 'Importer votre catalogue', 'Fichier Excel ou CSV de vos fournisseurs : les colonnes sont reconnues automatiquement.',
        $count('SELECT COUNT(*) FROM products WHERE active = 1') > 0, url('admin/products/import'), 'Importer', false];
    $steps[] = ['users', 'Inviter vos salariés', 'Collez une liste d\'e-mails : chacun reçoit un lien pour choisir son mot de passe.',
        $count("SELECT COUNT(*) FROM users WHERE role <> 'admin' AND status = 'active' AND deleted_at IS NULL") > 0, url('admin/invite'), 'Inviter', false];
    $steps[] = ['mail', 'Activer les e-mails', 'Notifications aux salariés, bons de commande aux fournisseurs, invitations.',
        setting('mail_enabled', '0') === '1', url('admin/settings') . '#mail', 'Configurer', false];
    $steps[] = ['licence', 'Relier Approvia à NLapps', 'Licence, conversation en direct avec l\'assistance, tutoriels vidéo et mises à jour.',
        licence_managed(), url('admin/settings') . '#assistance', 'Relier', false];
    $steps[] = ['deadlines', 'Programmer vos dates limites', 'Les centres voient le compte à rebours et reçoivent un rappel la veille.',
        $count('SELECT COUNT(*) FROM deadlines') > 0, url('admin/deadlines'), 'Programmer', true];
    $steps[] = ['budgets', 'Fixer les budgets des centres', 'Suivi de la consommation et alerte au seuil choisi.',
        $count('SELECT COUNT(*) FROM budgets WHERE year = ?', [(int)date('Y')]) > 0, url('admin/budgets'), 'Budgets', true];
    $steps[] = ['contracts', 'Enregistrer vos contrats et marchés', 'Prix contractuels appliqués automatiquement, alerte avant l\'échéance.',
        $count('SELECT COUNT(*) FROM contracts') > 0, url('admin/contracts'), 'Contrats', true];
    return array_map(fn($s) => array_combine(['key', 'title', 'text', 'done', 'link', 'cta', 'optional'], $s), $steps);
}

/** Avancement des étapes essentielles, en pourcentage. */
function onboarding_progress(array $steps): int
{
    $main = array_filter($steps, fn($s) => !$s['optional']);
    return $main ? (int)round(count(array_filter($main, fn($s) => $s['done'])) / count($main) * 100) : 100;
}

/** Le guide s'affiche à l'administrateur tant qu'il ne l'a pas masqué (jamais sur la démo publique). */
function onboarding_visible(): bool
{
    return is_superadmin() && !demo_mode() && setting('onboarding_hidden', '0') !== '1';
}

/**
 * Lignes d'invitation : « e-mail », « Prénom Nom <e-mail> », « Prénom ; Nom ; e-mail » ou colonnes collées depuis Excel.
 * Renvoie [[prénom, nom, e-mail], …] et les lignes illisibles.
 */
function invite_parse(string $text): array
{
    $out = [];
    $bad = [];
    foreach (preg_split('/\R/', $text) as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        if (!preg_match('/[A-Z0-9._%+\'-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', $line, $m)) {
            $bad[] = $line;
            continue;
        }
        $email = mb_strtolower($m[0]);
        $rest = trim(str_replace(['<', '>', $m[0]], ' ', $line));
        $parts = array_values(array_filter(array_map('trim', preg_split('/[;\t,]+|\s{2,}/', $rest)), fn($p) => $p !== ''));
        if (count($parts) === 1) {
            $parts = preg_split('/\s+/', $parts[0], 2);
        }
        [$first, $last] = array_pad($parts, 2, '');
        if ($first === '') { // déduit de l'adresse : prenom.nom@…
            $local = preg_split('/[._-]/', strstr($email, '@', true));
            $first = ucfirst($local[0] ?? '');
            $last = ucfirst($local[1] ?? '');
        }
        $out[$email] = [mb_substr($first, 0, 100), mb_substr($last, 0, 100) ?: '—', $email];
    }
    return [array_values($out), $bad];
}
