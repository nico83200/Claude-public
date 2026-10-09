<?php
declare(strict_types=1);

/**
 * Gestion commerciale de la plateforme (super administrateurs, console.php) : tout ce que gérait auparavant le centre
 * d'assistance pour Centriva, désormais au même endroit que les clients.
 *
 *  - Licence de chaque client : formule, tarif, option IA, échéance (« payé jusqu'au »), suspension, message affiché.
 *    Les espaces lisent leur licence ici, sans appel réseau.
 *  - Abonnements en ligne (Stripe) : lien de paiement personnel, souscription par carte ou prélèvement SEPA, espace
 *    client Stripe, webhook (paiement reçu → échéance prolongée), journal des paiements.
 *  - Historique des versions installées (notes, paquets) et FAQ partagée du chatbot.
 *  - Liaison avec le centre d'assistance, qui ne garde que les conversations : clé de chaque espace créée automatiquement,
 *    reprise de son historique (versions, vidéos, FAQ, licences, paiements).
 *
 * Données : storage/central/{settings,licences,releases,faq,billing-events}.json (jamais accessibles depuis le web).
 */

// ---------------------------------------------------------------- Stockage

/** Lecture-modification-écriture d'un fichier commun sous verrou (console et webhook Stripe peuvent écrire en même temps). */
function central_update(string $file, callable $fn): mixed
{
    $lock = fopen(central_dir($file . '.lock'), 'c');
    flock($lock, LOCK_EX);
    try {
        $data = central_json($file);
        $result = null;
        $new = $fn($data, $result);
        central_json_save($file, is_array($new) ? $new : $data);
        return $result ?? $new;
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

const PLATFORM_DEFAULTS = [
    'operator_name' => 'NLapps', 'operator_email' => '', 'price_base' => 39.0, 'price_ai' => 15.0, 'vat' => 20.0, 'grace_days' => 0,
    'stripe_secret_key' => '', 'stripe_webhook_secret' => '', 'hub_url' => '', 'hub_console_key' => '', 'base_url' => '',
];

function platform_settings(): array
{
    return central_json('settings.json') + PLATFORM_DEFAULTS;
}

function platform_setting(string $k): mixed
{
    return platform_settings()[$k] ?? null;
}

function platform_settings_save(array $changes): void
{
    central_update('settings.json', fn(array $s) => array_merge($s, $changes));
}

/**
 * Adresse publique de la plateforme (https://centriva.fr), sans espace client. Mémorisée à chaque passage dans la console
 * ou sur la page d'accueil, pour le cron et les espaces servis à une adresse dédiée.
 */
function platform_base_url(): string
{
    $stored = rtrim((string)platform_setting('base_url'), '/');
    if (empty($_SERVER['HTTP_HOST']) || PHP_SAPI === 'cli') {
        return $stored;
    }
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $u = ($https ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . instance_web_dir();
    if (defined('NL_CONSOLE') || !empty($GLOBALS['central_login'])) {
        if ($stored !== $u) {
            platform_settings_save(['base_url' => $u]);
        }
        return $u;
    }
    return $stored ?: $u;
}

function platform_vat(): float
{
    return max(0.0, min(30.0, (float)platform_setting('vat')));
}

function platform_grace_days(): int
{
    return max(0, min(60, (int)platform_setting('grace_days')));
}

// ---------------------------------------------------------------- Licences des clients

const LICENCE_FIELDS = ['plan', 'price_base', 'price_ai', 'ai', 'paid_until', 'status', 'note', 'contact_email',
    'billing_token', 'stripe_customer', 'stripe_subscription', 'billing_status', 'billing_method', 'billing_next', 'billing_amount', 'hub_client', 'imported_at'];

function platform_licences(): array
{
    return central_json('licences.json');
}

/** Fiche licence d'un client (valeurs par défaut : abonnement actif sans échéance, sans option IA). */
function platform_licence_row(string $slug): array
{
    return (platform_licences()[$slug] ?? []) + ['plan' => 'Abonnement', 'price_base' => null, 'price_ai' => null, 'ai' => false, 'paid_until' => null,
        'status' => 'active', 'note' => '', 'contact_email' => '', 'billing_token' => null, 'stripe_customer' => null, 'stripe_subscription' => null,
        'billing_status' => null, 'billing_method' => null, 'billing_next' => null, 'billing_amount' => null, 'hub_client' => null];
}

function platform_licence_save(string $slug, array $changes): array
{
    $changes = array_intersect_key($changes, array_flip(LICENCE_FIELDS));
    return central_update('licences.json', function (array $all, &$result) use ($slug, $changes) {
        $all[$slug] = array_merge($all[$slug] ?? [], $changes);
        $result = $all[$slug];
        return $all;
    });
}

function platform_licence_delete(string $slug): void
{
    central_update('licences.json', function (array $all) use ($slug) {
        unset($all[$slug]);
        return $all;
    });
}

/**
 * Licence d'un client, au format attendu par l'application (même calcul que l'ancien centre d'assistance) :
 * active (payée ou sans échéance), grace (échéance passée, délai de grâce), expired, suspended.
 * Les espaces de démonstration ont toujours une licence active avec l'option IA.
 */
function platform_licence(string $slug, ?string $today = null): array
{
    $c = platform_licence_row($slug);
    $reg = instances_registry()[$slug] ?? [];
    $today ??= date('Y-m-d');
    $until = $c['paid_until'] ?: null;
    $grace = platform_grace_days();
    if (!empty($reg['demo'])) {
        $status = 'active';
        $until = null;
        $c['ai'] = true;
    } elseif ($c['status'] === 'suspended') {
        $status = 'suspended';
    } elseif (!$until || $today <= $until) {
        $status = 'active';
    } elseif ($grace > 0 && $today <= date('Y-m-d', strtotime($until . ' +' . $grace . ' days'))) {
        $status = 'grace';
    } else {
        $status = 'expired';
    }
    return [
        'status' => $status, 'plan' => (string)$c['plan'], 'paid_until' => $until,
        'days_left' => $until ? (int)floor((strtotime($until) - strtotime($today)) / 86400) : null,
        'ai' => !empty($c['ai']) && in_array($status, ['active', 'grace'], true),
        'grace_until' => $until ? date('Y-m-d', strtotime($until . ' +' . $grace . ' days')) : null,
        'message' => (string)$c['note'],
        'contact' => ['email' => (string)platform_setting('operator_email'), 'name' => (string)platform_setting('operator_name')],
    ];
}

/** Tarif mensuel HT d'un client en centimes, ligne par ligne (abonnement + option IA). */
function platform_billing_lines(string $slug): array
{
    $c = platform_licence_row($slug);
    $base = $c['price_base'] !== null && $c['price_base'] !== '' ? (float)$c['price_base'] : (float)platform_setting('price_base');
    $ai = $c['price_ai'] !== null && $c['price_ai'] !== '' ? (float)$c['price_ai'] : (float)platform_setting('price_ai');
    $lines = [['label' => 'Centriva — ' . ($c['plan'] ?: 'Abonnement') . ' mensuel', 'amount' => (int)round($base * 100)]];
    if (!empty($c['ai']) && $ai > 0) {
        $lines[] = ['label' => 'Centriva — option assistant IA', 'amount' => (int)round($ai * 100)];
    }
    return array_values(array_filter($lines, fn($l) => $l['amount'] > 0));
}

function platform_monthly_ttc(string $slug): int
{
    return (int)round(array_sum(array_column(platform_billing_lines($slug), 'amount')) * (1 + platform_vat() / 100));
}

// ---------------------------------------------------------------- Paiement en ligne (Stripe)

function platform_stripe_key(): string
{
    return trim((string)platform_setting('stripe_secret_key'));
}

function platform_stripe_ready(): bool
{
    return str_starts_with(platform_stripe_key(), 'sk_') || str_starts_with(platform_stripe_key(), 'rk_');
}

function platform_stripe_test_mode(): bool
{
    return str_contains(platform_stripe_key(), '_test_');
}

/** Appel à l'API Stripe (formulaire encodé, réponse JSON). Lève une exception avec le message de Stripe en cas d'erreur. */
function platform_stripe(string $method, string $path, array $params = []): array
{
    if (!platform_stripe_ready()) {
        throw new RuntimeException('Paiement en ligne non configuré : renseignez la clé secrète Stripe (console → Abonnements).');
    }
    $url = rtrim((string)(platform_setting('stripe_api_base') ?: 'https://api.stripe.com'), '/') . $path;
    $body = http_build_query($params, '', '&');
    if ($method === 'GET' && $body !== '') {
        $url .= '?' . $body;
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . platform_stripe_key(), 'Stripe-Version: 2024-06-20', 'Content-Type: application/x-www-form-urlencoded'],
    ] + ($method !== 'GET' ? [CURLOPT_POSTFIELDS => $body] : []));
    $raw = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($raw === false) {
        throw new RuntimeException('Stripe injoignable : ' . $err);
    }
    $data = json_decode((string)$raw, true);
    if (!is_array($data)) {
        throw new RuntimeException('Réponse illisible de Stripe (HTTP ' . $code . ').');
    }
    if ($code >= 400) {
        throw new RuntimeException('Stripe : ' . ($data['error']['message'] ?? ('erreur HTTP ' . $code)));
    }
    return $data;
}

/** Jeton du lien de paiement personnel d'un client (créé au premier besoin). */
function platform_billing_token(string $slug): string
{
    $t = (string)(platform_licence_row($slug)['billing_token'] ?? '');
    if ($t === '') {
        $t = bin2hex(random_bytes(20));
        platform_licence_save($slug, ['billing_token' => $t]);
    }
    return $t;
}

function platform_pay_url(string $slug): string
{
    return platform_base_url() . '/?paiement=' . platform_billing_token($slug);
}

function platform_webhook_url(): string
{
    return platform_base_url() . '/?webhook=stripe';
}

function platform_slug_by_token(string $t): ?string
{
    if (!preg_match('/^[a-f0-9]{40}$/', $t)) {
        return null;
    }
    foreach (platform_licences() as $slug => $c) {
        if (hash_equals((string)($c['billing_token'] ?? ''), $t) && isset(instances_registry()[$slug])) {
            return (string)$slug;
        }
    }
    return null;
}

function platform_billing_active(string $slug): bool
{
    $c = platform_licence_row($slug);
    return !empty($c['stripe_subscription']) && in_array((string)$c['billing_status'], ['active', 'trialing', 'past_due', 'unpaid', 'incomplete'], true);
}

function platform_billing_status_label(?string $s): string
{
    return [
        'active' => 'paiements automatiques', 'trialing' => 'période déjà payée, prélèvement à l\'échéance', 'past_due' => 'paiement en retard',
        'unpaid' => 'impayé', 'canceled' => 'résilié', 'incomplete' => 'en attente de confirmation', 'incomplete_expired' => 'souscription abandonnée',
    ][(string)$s] ?? ($s ?: 'non configuré');
}

/** Résumé transmis à l'espace du client (bandeau d'échéance, page Paramètres). */
function platform_billing_summary(string $slug): array
{
    $c = platform_licence_row($slug);
    return [
        'online' => platform_stripe_ready(), 'active' => platform_billing_active($slug), 'status' => (string)$c['billing_status'],
        'status_label' => platform_billing_status_label($c['billing_status']), 'method' => (string)$c['billing_method'],
        'next' => $c['billing_next'] ?: null, 'monthly_ttc' => platform_monthly_ttc($slug),
        'pay_url' => platform_stripe_ready() && platform_base_url() !== '' ? platform_pay_url($slug) : null,
    ];
}

/** Identifiant Stripe du taux de TVA (créé une fois, recréé si le taux ou le mode change). */
function platform_stripe_tax_rate(): ?string
{
    $rate = platform_vat();
    if ($rate <= 0) {
        return null;
    }
    $saved = (array)(platform_setting('stripe_tax_rate') ?: []);
    $mode = platform_stripe_test_mode() ? 'test' : 'live';
    if ((float)($saved['rate'] ?? -1) === $rate && !empty($saved['id']) && ($saved['mode'] ?? '') === $mode) {
        return $saved['id'];
    }
    $tr = platform_stripe('POST', '/v1/tax_rates', ['display_name' => 'TVA', 'description' => 'TVA ' . $rate . ' %', 'percentage' => $rate,
        'inclusive' => 'false', 'country' => 'FR', 'jurisdiction' => 'FR']);
    platform_settings_save(['stripe_tax_rate' => ['id' => $tr['id'], 'rate' => $rate, 'mode' => $mode]]);
    return $tr['id'];
}

function platform_stripe_customer(string $slug): string
{
    $c = platform_licence_row($slug);
    if (!empty($c['stripe_customer'])) {
        return (string)$c['stripe_customer'];
    }
    $cu = platform_stripe('POST', '/v1/customers', array_filter([
        'name' => instances_registry()[$slug]['name'] ?? $slug, 'email' => $c['contact_email'] ?: null, 'preferred_locales' => ['fr'],
        'metadata' => ['centriva_slug' => $slug],
    ], fn($v) => $v !== null));
    platform_licence_save($slug, ['stripe_customer' => $cu['id']]);
    return $cu['id'];
}

/**
 * Page Stripe où le client règle : souscription (carte ou prélèvement SEPA) s'il n'a pas encore d'abonnement,
 * sinon l'espace client Stripe (moyen de paiement, factures).
 */
function platform_billing_session_url(string $slug): string
{
    $back = platform_pay_url($slug);
    $customer = platform_stripe_customer($slug);
    if (platform_billing_active($slug)) {
        return platform_stripe('POST', '/v1/billing_portal/sessions', ['customer' => $customer, 'return_url' => $back, 'locale' => 'fr'])['url'];
    }
    $lines = platform_billing_lines($slug);
    if (!$lines) {
        throw new RuntimeException('Aucun tarif défini pour ce client.');
    }
    $tax = platform_stripe_tax_rate();
    $items = [];
    foreach ($lines as $l) {
        $items[] = ['quantity' => 1, 'price_data' => ['currency' => 'eur', 'unit_amount' => $l['amount'], 'recurring' => ['interval' => 'month'],
            'product_data' => ['name' => $l['label']]]] + ($tax ? ['tax_rates' => [$tax]] : []);
    }
    $c = platform_licence_row($slug);
    $params = [
        'mode' => 'subscription', 'customer' => $customer, 'client_reference_id' => $slug, 'locale' => 'fr',
        'payment_method_types' => ['card', 'sepa_debit'], 'line_items' => $items,
        'subscription_data' => ['metadata' => ['centriva_slug' => $slug], 'description' => instances_registry()[$slug]['name'] ?? $slug],
        'success_url' => $back . '&done=1', 'cancel_url' => $back,
    ];
    // Période déjà payée : le premier prélèvement a lieu à son terme (pas de double paiement)
    if ($c['paid_until'] && strtotime($c['paid_until'] . ' 23:59:59') > time() + 2 * 86400) {
        $params['subscription_data']['trial_end'] = strtotime($c['paid_until'] . ' 12:00:00');
    }
    return platform_stripe('POST', '/v1/checkout/sessions', $params)['url'];
}

/** Vérifie la signature d'un webhook Stripe (en-tête Stripe-Signature : t=…,v1=…), tolérance de 5 minutes. */
function platform_stripe_verify(string $payload, string $header, string $secret, int $tolerance = 300): bool
{
    if ($secret === '' || $header === '') {
        return false;
    }
    $t = null;
    $sigs = [];
    foreach (explode(',', $header) as $part) {
        [$k, $v] = array_pad(explode('=', trim($part), 2), 2, '');
        if ($k === 't') {
            $t = (int)$v;
        } elseif ($k === 'v1') {
            $sigs[] = $v;
        }
    }
    if (!$t || !$sigs || abs(time() - $t) > $tolerance) {
        return false;
    }
    $expected = hash_hmac('sha256', $t . '.' . $payload, $secret);
    foreach ($sigs as $s) {
        if (hash_equals($expected, $s)) {
            return true;
        }
    }
    return false;
}

/** Journal des paiements (500 derniers), le plus récent en premier. */
function platform_billing_events(?string $slug = null): array
{
    $all = central_json('billing-events.json');
    return $slug === null ? $all : array_values(array_filter($all, fn($e) => ($e['slug'] ?? '') === $slug));
}

/** Ajoute un événement ; false s'il est déjà enregistré (Stripe renvoie parfois deux fois le même). */
function platform_billing_log(string $slug, string $stripeId, string $type, ?int $amount, string $label, ?string $url = null, ?string $at = null): bool
{
    return (bool)central_update('billing-events.json', function (array $all, &$result) use ($slug, $stripeId, $type, $amount, $label, $url, $at) {
        foreach ($all as $e) {
            if ($stripeId !== '' && ($e['stripe_id'] ?? '') === $stripeId) {
                $result = false;
                return $all;
            }
        }
        array_unshift($all, ['slug' => $slug, 'stripe_id' => $stripeId, 'type' => $type, 'amount' => $amount, 'label' => mb_substr($label, 0, 300),
            'url' => $url, 'created_at' => $at ?? date('Y-m-d H:i:s')]);
        $result = true;
        return array_slice($all, 0, 500);
    });
}

/** Client concerné par un objet Stripe : métadonnées, abonnement ou client Stripe. */
function platform_billing_slug_for(array $o): ?string
{
    $registry = instances_registry();
    $slug = (string)($o['metadata']['centriva_slug'] ?? $o['subscription_details']['metadata']['centriva_slug'] ?? '');
    if ($slug === '' && ($o['object'] ?? '') === 'checkout.session') {
        $slug = (string)($o['client_reference_id'] ?? '');
    }
    if ($slug !== '' && isset($registry[$slug])) {
        return $slug;
    }
    $sub = is_string($o['subscription'] ?? null) ? $o['subscription'] : (($o['object'] ?? '') === 'subscription' ? (string)($o['id'] ?? '') : '');
    $cus = is_string($o['customer'] ?? null) ? $o['customer'] : '';
    foreach (platform_licences() as $s => $c) {
        if (($sub !== '' && ($c['stripe_subscription'] ?? '') === $sub) || ($cus !== '' && ($c['stripe_customer'] ?? '') === $cus)) {
            return (string)$s;
        }
    }
    return null;
}

/**
 * Traite un événement Stripe déjà authentifié. Renvoie un court compte rendu.
 * Événements utiles : checkout.session.completed, invoice.paid, invoice.payment_failed, customer.subscription.updated / deleted.
 */
function platform_billing_handle(array $event): string
{
    $o = $event['data']['object'] ?? [];
    $type = (string)($event['type'] ?? '');
    if (!in_array($type, ['checkout.session.completed', 'customer.subscription.created', 'customer.subscription.updated', 'invoice.paid',
        'invoice.payment_failed', 'customer.subscription.deleted'], true)) {
        return 'ignoré (' . $type . ')';
    }
    $slug = platform_billing_slug_for($o);
    if (!$slug) {
        return 'ignoré (client inconnu)';
    }
    $c = platform_licence_row($slug);
    $name = instances_registry()[$slug]['name'] ?? $slug;
    $amount = null;
    $url = null;
    $alert = null;
    switch ($type) {
        case 'checkout.session.completed':
            $methods = (array)($o['payment_method_types'] ?? []);
            platform_licence_save($slug, array_filter(['stripe_customer' => $o['customer'] ?? null, 'stripe_subscription' => $o['subscription'] ?? null,
                'billing_status' => 'active', 'billing_method' => $methods === ['sepa_debit'] ? 'sepa_debit' : null]));
            $label = 'Abonnement en ligne souscrit';
            $alert = ['Paiement en ligne activé : ' . $name, 'Le client ' . $name . ' a souscrit son abonnement (paiements automatiques).'];
            break;

        case 'customer.subscription.created':
        case 'customer.subscription.updated':
            platform_licence_save($slug, array_filter(['stripe_subscription' => $o['id'] ?? null, 'billing_status' => (string)($o['status'] ?? ''),
                'billing_next' => !empty($o['current_period_end']) ? date('Y-m-d', (int)$o['current_period_end']) : null,
                'billing_method' => $o['default_payment_method']['type'] ?? null]));
            $label = 'Abonnement : ' . platform_billing_status_label((string)($o['status'] ?? ''));
            break;

        case 'invoice.paid':
            $amount = (int)($o['amount_paid'] ?? 0);
            $end = 0;
            foreach ((array)($o['lines']['data'] ?? []) as $l) {
                $end = max($end, (int)($l['period']['end'] ?? 0));
            }
            $end = $end ?: (int)($o['period_end'] ?? 0);
            $until = $end ? date('Y-m-d', $end) : null;
            // La licence couvre la période payée (sans jamais raccourcir une échéance déjà plus lointaine)
            $changes = ['billing_status' => 'active', 'billing_amount' => $amount ?: null] + ($until ? ['billing_next' => $until] : []);
            if ($until && (!$c['paid_until'] || $until > $c['paid_until'])) {
                $changes['paid_until'] = $until;
            }
            platform_licence_save($slug, $changes);
            $url = $o['hosted_invoice_url'] ?? null;
            $label = $amount > 0 ? 'Paiement reçu : ' . number_format($amount / 100, 2, ',', ' ') . ' € TTC' . ($until ? ' · licence jusqu\'au ' . date('d/m/Y', strtotime($until)) : '')
                : 'Période sans paiement' . ($until ? ' jusqu\'au ' . date('d/m/Y', strtotime($until)) : '');
            break;

        case 'invoice.payment_failed':
            platform_licence_save($slug, ['billing_status' => 'past_due']);
            $amount = (int)($o['amount_due'] ?? 0);
            $url = $o['hosted_invoice_url'] ?? null;
            $label = 'Échec de paiement (' . number_format($amount / 100, 2, ',', ' ') . ' €)';
            $alert = ['Échec de paiement : ' . $name, 'Le prélèvement de ' . number_format($amount / 100, 2, ',', ' ') . ' € de ' . $name
                . ' a échoué. Stripe relance automatiquement ; sans régularisation, la licence échoit le ' . ($c['paid_until'] ? date('d/m/Y', strtotime($c['paid_until'])) : '—') . '.'];
            break;

        default: // customer.subscription.deleted
            platform_licence_save($slug, ['billing_status' => 'canceled']);
            $label = 'Abonnement en ligne résilié : la licence court jusqu\'au ' . ($c['paid_until'] ? date('d/m/Y', strtotime($c['paid_until'])) : '—');
            $alert = ['Abonnement résilié : ' . $name, $label . '.'];
    }
    if (!platform_billing_log($slug, (string)($event['id'] ?? ''), $type, $amount, $label, $url)) {
        return 'déjà traité';
    }
    if ($alert) {
        platform_alert($alert[0], $alert[1]);
    }
    return $label;
}

/** Prévient l'opérateur par e-mail (adresse de Réglages), sans bloquer en cas d'échec. */
function platform_alert(string $subject, string $text): void
{
    $to = (string)platform_setting('operator_email');
    if ($to === '' || !function_exists('mail')) {
        return;
    }
    @mail($to, '=?UTF-8?B?' . base64_encode('[Centriva] ' . $subject) . '?=', $text . "\n\n" . platform_base_url() . "/console.php?p=billing",
        "Content-Type: text/plain; charset=utf-8\r\nFrom: " . $to);
}

// ---------------------------------------------------------------- Historique des versions

function platform_releases_dir(): string
{
    $d = central_dir('releases');
    @mkdir($d, 0750, true);
    return $d;
}

/** Versions connues, de la plus récente à la plus ancienne. */
function platform_releases(): array
{
    $all = central_json('releases.json');
    usort($all, fn($a, $b) => version_compare((string)$b['version'], (string)$a['version']));
    return $all;
}

/** Ajoute ou complète une version (clé : numéro de version). */
function platform_release_record(array $r): void
{
    central_update('releases.json', function (array $all) use ($r) {
        foreach ($all as $i => $o) {
            if ($o['version'] === $r['version']) {
                $all[$i] = array_merge($o, array_filter($r, fn($v) => $v !== null && $v !== ''));
                return $all;
            }
        }
        $all[] = $r;
        return $all;
    });
}

/** Garde une copie du paquet installé (téléchargeable depuis l'historique) et l'inscrit dans l'historique. */
function platform_release_from_package(string $zip, array $info, string $by): void
{
    $file = 'centriva-' . preg_replace('/[^0-9a-z.-]/i', '', $info['version']) . '.zip';
    @copy($zip, platform_releases_dir() . '/' . $file);
    platform_release_record(['version' => $info['version'], 'notes' => (string)($info['notes'] ?? ''), 'date' => (string)($info['date'] ?? '') ?: date('Y-m-d'),
        'file' => $file, 'size' => filesize($zip), 'sha256' => hash_file('sha256', $zip), 'installed_at' => date('Y-m-d H:i:s'), 'installed_by' => $by, 'source' => 'console']);
}

// ---------------------------------------------------------------- FAQ partagée

function platform_faq(): array
{
    $all = central_json('faq.json');
    usort($all, fn($a, $b) => [(int)($a['position'] ?? 0), (int)$a['id']] <=> [(int)($b['position'] ?? 0), (int)$b['id']]);
    return $all;
}

function platform_faq_save(array $item): void
{
    central_update('faq.json', function (array $all) use ($item) {
        if (empty($item['id'])) {
            $item['id'] = ($all ? max(array_column($all, 'id')) : 0) + 1;
            $item['created_at'] = date('Y-m-d H:i:s');
            $all[] = $item;
            return $all;
        }
        foreach ($all as $i => $o) {
            if ((int)$o['id'] === (int)$item['id']) {
                $all[$i] = array_merge($o, $item);
            }
        }
        return $all;
    });
}

function platform_faq_delete(int $id): void
{
    central_update('faq.json', fn(array $all) => array_values(array_filter($all, fn($f) => (int)$f['id'] !== $id)));
}

/** Questions actives, au format du chatbot des espaces : [question, mots-clés, réponse, [libellé, route], administrateurs seulement]. */
function platform_faq_for_chatbot(): array
{
    $out = [];
    foreach (platform_faq() as $f) {
        if (!empty($f['active']) && trim((string)$f['question']) !== '' && trim((string)$f['answer']) !== '') {
            $link = !empty($f['link_label']) && !empty($f['link_route']) ? [(string)$f['link_label'], (string)$f['link_route']] : null;
            $out[] = [(string)$f['question'], trim((string)($f['keywords'] ?? '') . ' ' . platform_keywords((string)$f['question'])), (string)$f['answer'], $link, !empty($f['admin_only'])];
        }
    }
    return $out;
}

/** Mots significatifs d'une question (minuscules, sans accents), pour le chatbot. */
function platform_keywords(string $text): string
{
    $t = strtr(mb_strtolower($text), ['à' => 'a', 'â' => 'a', 'ä' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'î' => 'i', 'ï' => 'i', 'ô' => 'o', 'ö' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c', 'œ' => 'oe']);
    $stop = ['le', 'la', 'les', 'un', 'une', 'des', 'de', 'du', 'et', 'ou', 'au', 'aux', 'en', 'je', 'on', 'il', 'elle', 'est', 'pas', 'ne', 'que', 'qui', 'quoi',
        'comment', 'pour', 'par', 'sur', 'dans', 'avec', 'mon', 'ma', 'mes', 'ce', 'cet', 'cette', 'se', 'vous', 'nous', 'faire', 'peut', 'puis', 'bonjour', 'merci'];
    $words = array_filter(preg_split('/[^a-z0-9]+/', $t), fn($w) => strlen($w) > 1 && !in_array($w, $stop, true));
    return implode(' ', array_slice(array_values(array_unique($words)), 0, 20));
}

// ---------------------------------------------------------------- Centre d'assistance (conversations seulement)

/** Appel au centre d'assistance avec la clé de liaison de la console. */
function platform_hub_call(string $action, array $query = [], ?array $body = null, ?string $saveTo = null, int $timeout = 30): array
{
    $url = trim((string)platform_setting('hub_url'));
    $key = trim((string)platform_setting('hub_console_key'));
    if ($url === '' || $key === '') {
        throw new RuntimeException('Centre d\'assistance non relié (console → Assistance).');
    }
    $ch = curl_init($url . (str_contains($url, '?') ? '&' : '?') . http_build_query(['a' => $action] + $query));
    $fh = $saveTo ? fopen($saveTo, 'wb') : null;
    curl_setopt_array($ch, [
        CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HTTPHEADER => ['X-Console-Key: ' . $key, 'Content-Type: application/json', 'Accept: application/json'],
    ] + ($fh ? [CURLOPT_FILE => $fh] : [CURLOPT_RETURNTRANSFER => true])
      + ($body !== null ? [CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE)] : []));
    $raw = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($fh) {
        fclose($fh);
        if ($code !== 200) {
            @unlink($saveTo);
            throw new RuntimeException('Téléchargement impossible depuis le centre d\'assistance (HTTP ' . $code . ').');
        }
        return ['ok' => true];
    }
    if ($raw === false) {
        throw new RuntimeException('Centre d\'assistance injoignable : ' . $err);
    }
    $data = json_decode((string)$raw, true);
    if (!is_array($data) || $code >= 400) {
        throw new RuntimeException('Centre d\'assistance : ' . (is_array($data) ? ($data['error'] ?? 'erreur') : 'réponse illisible') . ' (HTTP ' . $code . ').');
    }
    return $data;
}

function platform_hub_linked(): bool
{
    return trim((string)platform_setting('hub_url')) !== '' && trim((string)platform_setting('hub_console_key')) !== '';
}

/** Relie la console : le centre d'assistance masque alors ses pages Centriva (clients, versions, FAQ, vidéos, abonnements). */
function platform_hub_link(string $url, string $key): array
{
    $url = trim($url);
    if (!preg_match('#^https?://#', $url)) {
        throw new RuntimeException('Adresse de l\'API du centre d\'assistance invalide (ex. https://nlapps.fr/assistance/api.php).');
    }
    $old = [platform_setting('hub_url'), platform_setting('hub_console_key')];
    platform_settings_save(['hub_url' => $url, 'hub_console_key' => trim($key)]);
    try {
        $r = platform_hub_call('console_link', [], ['app' => 'centriva', 'console_url' => platform_base_url() . '/console.php']);
    } catch (Throwable $e) {
        platform_settings_save(['hub_url' => $old[0], 'hub_console_key' => $old[1]]);
        throw $e;
    }
    platform_settings_save(['hub_linked_at' => date('Y-m-d H:i:s')]);
    return $r;
}

/**
 * Accès d'un espace au centre d'assistance (conversation en direct) : la clé est créée par le centre d'assistance et
 * enregistrée chiffrée dans l'espace. Un espace qui a déjà une clé valable la garde.
 */
function platform_hub_provision(string $slug, bool $newKey = false): string
{
    require_once APP . '/instances_admin.php';
    $reg = instances_registry()[$slug] ?? null;
    if (!$reg) {
        throw new RuntimeException('Client inconnu.');
    }
    $hasKey = instance_run($slug, fn() => support_hub_config()['source'] !== 'none');
    $r = platform_hub_call('console_client', [], [
        'app' => 'centriva', 'slug' => $slug, 'name' => $reg['name'], 'url' => platform_base_url() . '/' . $slug . '/',
        'active' => empty($reg['suspended']), 'hub_id' => platform_licence_row($slug)['hub_client'], 'new_key' => $newKey || !$hasKey,
    ]);
    platform_licence_save($slug, ['hub_client' => (int)$r['id']]);
    if (!empty($r['key'])) {
        $url = (string)platform_setting('hub_url');
        instance_run($slug, function () use ($url, $r) {
            set_setting('support_hub_url', $url);
            set_setting('support_hub_key', encrypt_secret((string)$r['key']));
        });
        return 'clé créée';
    }
    return 'déjà relié';
}

/** Ferme (ou rouvre) l'accès d'un espace au centre d'assistance (suspension, suppression). */
function platform_hub_client_state(string $slug, bool $active, ?string $name = null): void
{
    if (!platform_hub_linked() || !($id = platform_licence_row($slug)['hub_client'])) {
        return;
    }
    try {
        platform_hub_call('console_client', [], ['app' => 'centriva', 'slug' => $slug, 'hub_id' => $id, 'active' => $active, 'name' => $name, 'new_key' => false]);
    } catch (Throwable $e) {
        error_log('[console] assistance : ' . $e->getMessage());
    }
}

/**
 * Reprise de l'historique du centre d'assistance : versions (avec leurs paquets), vidéos (fichiers compris), FAQ,
 * tarifs et réglages de paiement, et pour chaque espace sa licence et son abonnement. Ré-exécutable sans doublon.
 * Renvoie un compte rendu détaillé.
 */
function platform_hub_import(bool $withPackages = true): array
{
    require_once APP . '/instances_admin.php';
    @set_time_limit(0);
    $x = platform_hub_call('console_export', ['app' => 'centriva'], null, null, 120);
    $report = ['releases' => 0, 'packages' => 0, 'videos' => 0, 'faq' => 0, 'clients' => [], 'unmatched' => [], 'events' => 0, 'errors' => []];

    // Tarifs et réglages (les valeurs déjà saisies dans la console ne sont pas écrasées par des valeurs vides)
    $s = (array)($x['settings'] ?? []);
    $set = array_filter([
        'price_base' => isset($s['price_base']) ? (float)$s['price_base'] : null, 'price_ai' => isset($s['price_ai']) ? (float)$s['price_ai'] : null,
        'vat' => isset($s['vat']) ? (float)$s['vat'] : null, 'grace_days' => isset($s['grace_days']) ? (int)$s['grace_days'] : null,
        'operator_name' => $s['operator_name'] ?? null, 'operator_email' => $s['operator_email'] ?? null,
    ], fn($v) => $v !== null && $v !== '');
    if (!platform_stripe_ready() && !empty($s['stripe_secret_key'])) {
        $set['stripe_secret_key'] = (string)$s['stripe_secret_key'];
    }
    platform_settings_save($set);

    // Versions
    foreach ((array)($x['releases'] ?? []) as $r) {
        if (!preg_match('/^\d+\.\d+\.\d+([.-][\w.]+)?$/', (string)($r['version'] ?? ''))) {
            continue;
        }
        $file = 'centriva-' . $r['version'] . '.zip';
        $have = is_file(platform_releases_dir() . '/' . $file) && hash_file('sha256', platform_releases_dir() . '/' . $file) === $r['sha256'];
        if ($withPackages && !$have && !empty($r['id'])) {
            try {
                platform_hub_call('console_file', ['kind' => 'release', 'id' => (int)$r['id']], null, platform_releases_dir() . '/' . $file, 900);
                $have = hash_file('sha256', platform_releases_dir() . '/' . $file) === $r['sha256'];
                $report['packages'] += $have ? 1 : 0;
            } catch (Throwable $e) {
                $report['errors'][] = 'Paquet ' . $r['version'] . ' : ' . $e->getMessage();
            }
        }
        platform_release_record(['version' => (string)$r['version'], 'notes' => (string)($r['notes'] ?? ''), 'date' => substr((string)($r['created_at'] ?? ''), 0, 10),
            'file' => $have ? $file : null, 'size' => (int)($r['size'] ?? 0), 'sha256' => (string)($r['sha256'] ?? ''), 'published_at' => (string)($r['created_at'] ?? ''), 'source' => 'assistance']);
        $report['releases']++;
    }

    // Vidéos : fichiers copiés dans les vidéos communes (même identifiant : pas de doublon à la reprise suivante)
    $list = central_videos();
    $known = array_column($list, 'uid');
    foreach ((array)($x['videos'] ?? []) as $v) {
        $uid = 'h-' . preg_replace('/[^a-z0-9_-]/', '', strtolower((string)$v['uid']));
        if (in_array($uid, $known, true)) {
            continue;
        }
        $dest = central_videos_dir() . '/' . $uid . '.mp4';
        try {
            platform_hub_call('console_file', ['kind' => 'video', 'id' => (int)$v['id']], null, $dest, 3600);
            if (!empty($v['sha256']) && !hash_equals((string)$v['sha256'], (string)hash_file('sha256', $dest))) {
                @unlink($dest);
                throw new RuntimeException('fichier corrompu pendant le transfert');
            }
        } catch (Throwable $e) {
            $report['errors'][] = 'Vidéo « ' . $v['title'] . ' » : ' . $e->getMessage();
            continue;
        }
        $list[] = ['uid' => $uid, 'title' => (string)$v['title'], 'description' => $v['description'] ?: null, 'keywords' => $v['keywords'] ?: null,
            'chapters' => json_decode((string)($v['chapters'] ?? ''), true) ?: [], 'audience' => ($v['audience'] ?? '') === 'admin' ? 'admin' : 'all',
            'position' => (int)($v['position'] ?? 0), 'welcome' => !empty($v['welcome']), 'file' => $uid . '.mp4', 'size' => filesize($dest),
            'duration' => (int)($v['duration'] ?? 0) ?: null, 'published' => !empty($v['published']), 'created_at' => (string)($v['created_at'] ?? date('Y-m-d H:i:s')),
            'updated_at' => date('Y-m-d H:i:s'), 'source' => 'assistance'];
        $known[] = $uid;
        $report['videos']++;
    }
    central_videos_save($list);

    // FAQ partagée
    $faqKnown = array_filter(array_column(platform_faq(), 'hub_id'));
    foreach ((array)($x['faq'] ?? []) as $f) {
        if (in_array((int)$f['id'], array_map('intval', $faqKnown), true)) {
            continue;
        }
        platform_faq_save(['question' => (string)$f['question'], 'keywords' => (string)($f['keywords'] ?? ''), 'answer' => (string)$f['answer'],
            'link_label' => (string)($f['link_label'] ?? ''), 'link_route' => (string)($f['link_route'] ?? ''), 'admin_only' => !empty($f['admin_only']),
            'active' => !empty($f['active']), 'position' => 0, 'hub_id' => (int)$f['id']]);
        $report['faq']++;
    }

    // Clients : rapprochement par la clé déjà enregistrée dans l'espace, puis par l'adresse, puis par le nom
    $registry = instances_registry();
    $keyHash = [];
    foreach ($registry as $slug => $i) {
        try {
            $k = instance_run((string)$slug, fn() => support_hub_config()['key'] ?? '');
            if ($k !== '') {
                $keyHash[hash('sha256', (string)$k)] = (string)$slug;
            }
        } catch (Throwable) {
        }
    }
    $norm = fn(string $s) => preg_replace('/[^a-z0-9]/', '', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $s) ?: $s));
    $taken = [];
    foreach ((array)($x['clients'] ?? []) as $c) {
        $slug = null;
        if (!empty($c['console_slug']) && isset($registry[$c['console_slug']])) {
            $slug = (string)$c['console_slug'];
        } elseif (isset($keyHash[$c['key_hash'] ?? ''])) {
            $slug = $keyHash[$c['key_hash']];
        } else {
            foreach ($registry as $s => $i) {
                $u = (string)($c['instance_url'] ?? '');
                $hostMatch = $u !== '' && in_array(instance_normalize_host(preg_replace('#:\d+(?=/|$)#', '', (string)parse_url($u, PHP_URL_HOST))), array_map('instance_normalize_host', (array)$i['hosts']), true);
                if (($u !== '' && preg_match('#/' . preg_quote((string)$s, '#') . '/?$#', rtrim((string)parse_url($u, PHP_URL_PATH), '/') . '/')) || $hostMatch
                    || ($norm((string)$c['name']) !== '' && $norm((string)$c['name']) === $norm((string)$i['name']))) {
                    $slug = (string)$s;
                    break;
                }
            }
        }
        if (!$slug || isset($taken[$slug])) {
            $report['unmatched'][] = (string)$c['name'];
            continue;
        }
        $taken[$slug] = true;
        platform_licence_save($slug, [
            'plan' => (string)($c['plan'] ?: 'Abonnement'), 'ai' => !empty($c['ai_option']), 'paid_until' => $c['paid_until'] ?: null,
            'status' => ($c['status'] ?? '') === 'suspended' || empty($c['active']) ? 'suspended' : 'active', 'note' => (string)($c['licence_note'] ?? ''),
            'contact_email' => (string)($c['contact_email'] ?? ''), 'billing_token' => $c['billing_token'] ?: null, 'stripe_customer' => $c['stripe_customer'] ?: null,
            'stripe_subscription' => $c['stripe_subscription'] ?: null, 'billing_status' => $c['billing_status'] ?: null, 'billing_method' => $c['billing_method'] ?: null,
            'billing_next' => $c['billing_next'] ?: null, 'billing_amount' => $c['billing_amount'] ?: null, 'hub_client' => (int)$c['id'], 'imported_at' => date('Y-m-d H:i:s'),
        ]);
        foreach ((array)($c['events'] ?? []) as $e) {
            if (platform_billing_log($slug, (string)($e['stripe_id'] ?: 'hub-' . $e['id']), (string)$e['type'], $e['amount'] !== null ? (int)$e['amount'] : null,
                (string)($e['label'] ?? ''), $e['url'] ?? null, (string)($e['created_at'] ?? ''))) {
                $report['events']++;
            }
        }
        $report['clients'][] = $c['name'] . ' → ' . $slug;
        // L'espace est relié au centre d'assistance pour les conversations (clé existante conservée, sinon créée)
        try {
            platform_hub_provision($slug);
        } catch (Throwable $e) {
            $report['errors'][] = $registry[$slug]['name'] . ' (assistance) : ' . $e->getMessage();
        }
    }
    // Les vidéos et la FAQ reçues autrefois du centre d'assistance par chaque espace sont désormais communes : on retire les copies
    foreach (array_keys($registry) as $slug) {
        try {
            instance_run((string)$slug, function () {
                videos_sync_remote([]);
                set_setting('faq_remote', null);
                set_setting('faq_remote_hash', null);
                set_setting('videos_remote_hash', null);
            });
        } catch (Throwable $e) {
            $report['errors'][] = $slug . ' : ' . $e->getMessage();
        }
    }
    platform_settings_save(['hub_imported_at' => date('Y-m-d H:i:s')]);
    return $report;
}

/** Inscrit la version en service dans l'historique si elle n'y est pas (notes : première section de CHANGELOG.md). */
function platform_release_current(): void
{
    foreach (central_json('releases.json') as $r) {
        if (($r['version'] ?? '') === APP_VERSION) {
            return;
        }
    }
    $notes = '';
    if (is_file(ROOT . '/CHANGELOG.md') && preg_match('/^##[^\n]*\n(.*?)(?=^## |\z)/ms', (string)file_get_contents(ROOT . '/CHANGELOG.md'), $m)) {
        $notes = trim($m[1]);
    }
    platform_release_record(['version' => APP_VERSION, 'notes' => $notes, 'date' => date('Y-m-d'), 'installed_at' => date('Y-m-d H:i:s'), 'source' => 'console']);
}

/** Prolonge l'échéance d'un client de N mois (paiement reçu hors ligne), à partir de l'échéance ou d'aujourd'hui si elle est passée. */
function platform_licence_extend(string $slug, int $months, string $by): string
{
    $c = platform_licence_row($slug);
    $from = $c['paid_until'] && $c['paid_until'] >= date('Y-m-d') ? $c['paid_until'] : date('Y-m-d');
    $until = date('Y-m-d', strtotime($from . ' +' . max(1, min(36, $months)) . ' months'));
    platform_licence_save($slug, ['paid_until' => $until]);
    platform_billing_log($slug, 'manuel-' . bin2hex(random_bytes(6)), 'manual', null, 'Paiement enregistré par ' . $by . ' : ' . $months . ' mois · licence jusqu\'au ' . date('d/m/Y', strtotime($until)));
    return $until;
}
