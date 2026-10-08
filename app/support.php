<?php
declare(strict_types=1);

/**
 * Assistance de l'éditeur (NLapps) : chatbot de premier niveau, conversation en direct avec l'équipe
 * (via le centre d'assistance NLapps) et formulaire de contact.
 * Coordonnées modifiables dans config.php (clés support_*), sans exposition dans l'interface.
 */

function support_contact(): array
{
    return [
        'editor'   => (string)cfg('support_editor', 'NLapps'),
        'site'     => (string)cfg('support_site', 'https://nlapps.fr'),
        'email'    => (string)cfg('support_email', 'contact@nlapps.fr'),
        'phone'    => (string)cfg('support_phone', '+33 6 52 43 67 47'),
    ];
}

const SUPPORT_CATEGORIES = [
    'question' => 'Question d\'utilisation',
    'bug'      => 'Un problème, un message d\'erreur',
    'idee'     => 'Une idée d\'amélioration',
    'compte'   => 'Accès, compte, abonnement',
];

/**
 * Base de réponses du chatbot : [question type, mots-clés, réponse, [libellé, route], réservé aux administrateurs,
 * expression régulière facultative qui favorise l'entrée quand la tournure de la question la désigne clairement].
 * Les réponses restent courtes et renvoient vers la bonne page.
 */
function support_faq(): array
{
    return array_merge(support_faq_builtin(), support_faq_remote());
}

/** Questions partagées par NLapps depuis le centre d'assistance (synchronisées avec la licence). */
function support_faq_remote(): array
{
    $items = json_decode((string)setting('faq_remote', ''), true);
    if (!is_array($items)) {
        return [];
    }
    $out = [];
    foreach ($items as $f) {
        if (!empty($f['q']) && !empty($f['a'])) {
            $link = is_array($f['link'] ?? null) && count($f['link']) === 2 ? [(string)$f['link'][0], (string)$f['link'][1]] : null;
            $out[] = [(string)$f['q'], (string)($f['k'] ?? ''), (string)$f['a'], $link, !empty($f['admin'])];
        }
    }
    return $out;
}

/** Questions intégrées à l'application. */
function support_faq_builtin(): array
{
    return [
        ['Comment commander un article ?', 'commander commande article panier demande ajouter acheter besoin',
            "Cherchez l'article (barre de recherche en haut ou Catalogue), indiquez la quantité puis « Ajouter ». Ouvrez ensuite « Mon panier » et cliquez sur « Envoyer la demande » : le service achats la reçoit, classée par fournisseur.", ['Ouvrir le catalogue', 'catalog'], false],
        ['Comment scanner un code-barres ?', 'scanner scan code barre barres camera appareil photo lecteur douchette ean',
            "Touchez l'icône appareil photo (en haut sur mobile, ou le code-barres dans la recherche) et visez le code : un bip confirme la lecture et l'article s'ouvre. Une douchette USB ou Bluetooth fonctionne aussi : tapez dans le champ « saisissez le code ».", ['Ouvrir le catalogue', 'catalog'], false],
        ['La caméra ne s\'ouvre pas', 'camera marche pas fonctionne noir autorisation permission iphone ipad android bloque https ecran',
            "Le navigateur doit autoriser la caméra : acceptez la demande d'accès (ou réglages du navigateur → Autorisations → Caméra pour ce site). La caméra n'est disponible qu'en connexion sécurisée (adresse en https://). Sur iPhone, utilisez Safari ou l'application installée sur l'écran d'accueil.", null, false],
        ['L\'article n\'existe pas dans le catalogue', 'introuvable trouve pas inexistant hors catalogue nouveau proposer suggestion absent manque',
            "Dans « Mon panier », utilisez « Article hors catalogue » pour le décrire (nom, marque, lien, photo…). Si vous avez scanné un code inconnu, l'application vous propose directement de le soumettre. Le service achats complète et l'ajoute au catalogue.", ['Mon panier', 'cart'], false],
        ['Où en est ma demande ?', 'suivi suivre statut etat demande attente livree commandee commande quand recu ou en est arrive',
            "« Suivi des demandes » indique pour chaque article : en attente, validé, commandé (avec le n° de bon) ou reçu. Vous recevez aussi une notification à chaque étape.", ['Suivi des demandes', 'requests'], false,
            '/\\bou en (est|sont)\\b|\\bsuivi\\b|\\bquand\\b.*\\b(arrive|livre|recu)/'],
        ['Comment annuler une demande ?', 'annuler annulation supprimer retirer demande ligne erreur trompe plus besoin',
            "Dans « Suivi des demandes », cliquez sur « Annuler » sur la ligne concernée : c'est possible tant qu'elle est « En attente » (pas encore commandée). Ensuite, prévenez le service achats.", ['Suivi des demandes', 'requests'], false],
        ['Comment enregistrer une livraison ?', 'reception reçu livraison livre colis cocher arrive bon recu receptionner',
            "Ouvrez « Réceptions », choisissez le bon livré, cochez les articles reçus (ou saisissez la quantité réellement livrée) puis « Enregistrer la réception ». Le stock du centre est mis à jour automatiquement.", ['Réceptions', 'receptions'], false],
        ['Comment faire l\'inventaire ou une sortie de stock ?', 'stock inventaire sortie entree comptage quantite reserve mode reserve consommation',
            "« Inventaire » permet de compter ou d'enregistrer une sortie. Sur tablette, « Inventaire tablette » est le plus rapide : on scanne, on saisit le stock présent, on valide ; l'entrée ou la sortie est calculée et le scanner se relance pour l'article suivant.", ['Inventaire', 'stock'], false],
        ['Comment activer la double authentification ?', 'double authentification 2fa code securite telephone authenticator authy connexion securiser compte',
            "Dans « Mon profil », carte « Double authentification » : « Configurer », scannez le QR code avec Google Authenticator, Microsoft Authenticator ou Authy, puis saisissez le code affiché. Téléphone perdu : un administrateur peut la réinitialiser depuis votre fiche utilisateur.", ['Mon profil', 'profile'], false],
        ['Où voir les dépenses et les économies ?', 'depenses economies direction tableau bord rapport mensuel pdf budget statistiques pilotage', 
            "Menu « Direction » : dépenses, économies obtenues grâce aux tarifs négociés, répartition par centre, catégorie et fournisseur, évolution sur 12 mois et budgets. Un rapport PDF est créé chaque mois et peut être envoyé automatiquement à la direction.", ['Direction', 'admin/direction'], true],
        ['Comment fonctionne l\'inventaire tournant ?', 'inventaire tournant semaine compter comptage liste ecart cycle',
            "Chaque semaine, « Inventaire → Inventaire tournant » propose une dizaine d'articles à compter (ceux qui ne l'ont pas été depuis longtemps, en priorité les plus coûteux). Saisissez la quantité présente : l'écart s'affiche aussitôt, en quantité et en euros.", ['Inventaire tournant', 'stock/cycle'], false],
        ['Comment réceptionner en scannant ?', 'reception scanner scan livraison colis carton douchette receptionner livre',
            "Ouvrez le bon dans « Réceptions », puis « Scanner les articles livrés » : chaque code-barres lu ajoute une unité à la bonne ligne (caméra ou douchette). Vérifiez puis « Enregistrer la réception ».", ['Réceptions', 'receptions'], false],
        ['Où est rangé un article ?', 'ou range rangement emplacement trouver etagere reserve placard armoire stockage lieu salle de stock cherche',
            "La fiche de l'article indique, pour votre centre, l'emplacement de rangement (« Rangé à ») et la quantité en stock. Il s'affiche aussi quand vous scannez l'article. Pour le renseigner : sur la fiche (« Indiquer où il est rangé ») ou dans « Inventaire », sous le nom de chaque article ; il est repris automatiquement sur les étiquettes d'étagère.", ['Inventaire', 'stock'], false],
        ['Comment imprimer une étiquette d\'étagère ?', 'etiquette imprimer impression etagere code barre planche rouleau avery brother dymo',
            "Sur la fiche d'un article, « Imprimer l'étiquette » : nom, fournisseur, référence, emplacement et code-barres scannable. Pour tout le stock d'un centre : bouton « Étiquettes » dans « Inventaire ». Formats : planches A4 (24, 14 ou 8 étiquettes) ou rouleau. Imprimez à l'échelle 100 %, sans marges.", ['Inventaire', 'stock'], false],
        ['Comment corriger une erreur de stock ?', 'erreur stock mouvement supprimer corriger modifier annuler sortie entree ajout mauvais centre trompe transferer deplacer',
            "Ouvrez « Inventaire » puis l'article (ou « Mouvements ») : sur chaque ligne, le crayon corrige la quantité, le motif ou le centre, la corbeille supprime le mouvement ; le stock est recalculé. Plusieurs lignes peuvent être cochées et supprimées en une fois. Si tout le stock d'un article a été saisi dans le mauvais centre, utilisez « Changer de centre » en haut de la page de l'article.", ['Mouvements de stock', 'stock/history'], true],
        ['Comment changer de centre ?', 'changer centre site autre centre selection basculer plusieurs centres',
            "Utilisez le sélecteur « Centre / site » en haut du menu. Si un centre manque, demandez à l'administrateur de vous y rattacher.", null, false],
        ['J\'ai oublié mon mot de passe', 'mot de passe oublie perdu connexion connecter identifiant reinitialiser',
            "Sur la page de connexion, cliquez sur « Mot de passe oublié » : un lien valable une heure est envoyé par e-mail. Sinon, un administrateur peut vous définir un nouveau mot de passe.", null, false],
        ['Comment utiliser une liste type ?', 'liste type kit recurrent habituel mensuel modele favoris',
            "« Listes types » regroupe les commandes récurrentes : ouvrez une liste et « Tout ajouter au panier ». Vous pouvez aussi créer une liste depuis votre panier.", ['Listes types', 'kits'], false],
        ['Comment installer l\'application sur la tablette ?', 'installer application tablette telephone ecran accueil raccourci pwa icone',
            "Ouvrez le site dans Chrome (Android) ou Safari (iPhone/iPad) puis « Ajouter à l'écran d'accueil » (ou le bouton « Installer » en haut). L'application s'ouvre ensuite comme une app.", null, false],
        ['Pourquoi ma demande doit-elle être validée ?', 'validation valider responsable approbation seuil attente approuver',
            "Au-delà d'un montant fixé par l'administrateur, le responsable de centre valide la demande avant son envoi au service achats. Il la retrouve dans « Validations ».", null, false],
        ['Comment créer un bon de commande ?', 'bon commande creer fournisseur regrouper demandes traiter minimum franco',
            "Dans « Demandes à traiter », les lignes sont classées par fournisseur puis par centre : cochez les lignes et « Créer le bon de commande ». Une commande groupée multi-centres est proposée quand c'est possible.", ['Demandes à traiter', 'admin/requests'], true],
        ['Comment envoyer le bon au fournisseur ?', 'envoyer bon fournisseur email pdf mail commande en ligne site outlook',
            "Sur la fiche du bon : envoi du PDF par e-mail, ou ouverture du site du fournisseur (commande en ligne). Si l'envoi automatique est désactivé, « E-mail prêt, PDF joint » ouvre un brouillon dans Outlook. Le mode se règle dans la fiche fournisseur.", ['Bons de commande', 'admin/orders'], true],
        ['Comment importer un catalogue ?', 'importer import catalogue excel csv tarif fichier xlsx liste articles',
            "Articles → « Importer » : déposez le fichier du fournisseur (Excel ou CSV), vérifiez la correspondance des colonnes puis l'aperçu avant import. Les articles déjà présents sont mis à jour.", ['Importer', 'admin/products/import'], true],
        ['Comment ajouter un utilisateur ?', 'utilisateur compte ajouter creer salarie acces inscription valider compte',
            "Comptes utilisateurs → « Créer un compte », ou validez les demandes d'accès en attente. Rattachez chaque compte à un ou plusieurs centres.", ['Comptes utilisateurs', 'admin/users'], true],
        ['Comment fixer les budgets ?', 'budget annuel centre alerte depense seuil plafond',
            "« Budgets » : un montant annuel par centre et un seuil d'alerte. L'engagé et le commandé s'affichent en continu, y compris dans le panier des équipes.", ['Budgets', 'admin/budgets'], true],
        ['Les e-mails ne partent pas', 'email mail envoi part pas recu notification smtp courrier spam',
            "Paramètres → E-mails : vérifiez que l'envoi est activé, les réglages SMTP (serveur, port, identifiant) puis utilisez « Envoyer un e-mail de test ». Pensez aux courriers indésirables.", ['Paramètres', 'admin/settings'], true],
        ['Comment mettre à jour l\'application ?', 'mise a jour version nouvelle installer paquet zip retour arriere sauvegarde',
            "« Mises à jour » : envoyez le paquet .zip fourni par NLapps et confirmez. Une sauvegarde est faite automatiquement et un retour à la version précédente reste possible.", ['Mises à jour', 'admin/updates'], true],
        ['Comment supprimer les données de démonstration ?', 'supprimer donnees demo demonstration test nettoyage effacer',
            "« Nettoyage des données » supprime en un clic le jeu de démonstration ou toute l'activité de test (sauvegarde automatique avant).", ['Nettoyage des données', 'admin/cleanup'], true],
    ];
}

/** Recherche locale dans la base de réponses. Renvoie les meilleures entrées avec leur score. */
function support_match(string $question, bool $isAdmin): array
{
    $q = search_tokens($question);
    if (!$q) {
        return [];
    }
    $out = [];
    $norm = search_normalize($question);
    foreach (support_faq() as $i => $entry) {
        [$title, $kw, $answer, $link, $adminOnly] = $entry;
        if ($adminOnly && !$isAdmin) {
            continue;
        }
        $words = array_unique(array_merge(search_tokens($title), search_tokens($kw)));
        $score = 0.0;
        foreach ($q as $t) {
            $best = 0.0;
            foreach ($words as $w) {
                $best = max($best, search_word_match($t, $w));
            }
            $score += $best;
        }
        $score /= max(2, count($q));
        if (!empty($entry[5]) && preg_match($entry[5], $norm)) {
            $score += 0.5;
        }
        if ($score >= 0.34) {
            $out[] = ['i' => $i, 'score' => round($score, 2), 'title' => $title, 'answer' => $answer,
                'link' => $link ? ['label' => $link[0], 'url' => url($link[1])] : null];
        }
    }
    usort($out, fn($a, $b) => $b['score'] <=> $a['score']);
    return array_slice($out, 0, 3);
}

/**
 * Réponse du chatbot : base de réponses, et IA si l'option est activée.
 * Renvoie ['answer', 'links', 'source' => 'faq'|'ai'|'none', 'confident' => bool].
 */
function support_answer(string $question, bool $isAdmin, string $page = ''): array
{
    $matches = support_match($question, $isAdmin);
    if (ai_available()) {
        $faq = [];
        foreach (support_faq() as [$title, , $answer, $link, $adminOnly]) {
            if (!$adminOnly || $isAdmin) {
                $faq[] = '- ' . $title . ' → ' . $answer . ($link ? ' (page : ' . $link[0] . ')' : '');
            }
        }
        $system = "Tu es l'assistant d'aide de Approvia, logiciel de commandes pour centres de santé édité par NLapps. "
            . "Réponds en français, en 2 à 4 phrases simples, uniquement à partir de la documentation ci-dessous et du bon sens d'utilisation. "
            . "Si la question sort de ce cadre (bug, erreur technique, facturation, demande spécifique) ou si tu n'es pas sûr, dis-le et mets confident à false : "
            . "l'utilisateur pourra discuter avec l'équipe NLapps. N'invente jamais de fonctionnalité.\n"
            . "Profil de l'utilisateur : " . ($isAdmin ? 'administrateur (service achats)' : 'salarié d\'un centre') . ".\n\nDOCUMENTATION :\n" . implode("\n", $faq);
        $r = ai_json($system, 'Page actuelle : ' . mb_substr($page, 0, 80) . "\nQuestion : " . mb_substr($question, 0, 600), [
            'type' => 'object',
            'properties' => ['answer' => ['type' => 'string'], 'confident' => ['type' => 'boolean']],
            'required' => ['answer', 'confident'], 'additionalProperties' => false,
        ], 1500, 30);
        if ($r !== null && trim($r['answer']) !== '') {
            return ['answer' => mb_substr(trim($r['answer']), 0, 1200), 'links' => array_values(array_filter(array_map(fn($m) => $m['link'], array_slice($matches, 0, 1)))),
                'source' => 'ai', 'confident' => (bool)$r['confident']];
        }
    }
    if (!$matches) {
        return ['answer' => "Je n'ai pas trouvé de réponse toute faite à cette question. L'équipe NLapps peut vous aider directement.",
            'links' => [], 'source' => 'none', 'confident' => false, 'others' => []];
    }
    $best = $matches[0];
    return [
        'answer' => $best['answer'], 'title' => $best['title'], 'links' => $best['link'] ? [$best['link']] : [],
        'source' => 'faq', 'confident' => $best['score'] >= 0.55,
        'others' => array_map(fn($m) => $m['title'], array_slice($matches, 1)),
    ];
}

/** Contexte joint à chaque demande d'assistance. */
function support_context(?array $u, ?array $center, string $page = ''): string
{
    return "Client : " . (setting('company_name') ?: app_name()) . "\n"
        . "Site : " . (setting('app_url') ?: ((!empty($_SERVER['HTTP_HOST']) ? 'https://' . $_SERVER['HTTP_HOST'] : '') . dirname($_SERVER['SCRIPT_NAME'] ?? '/'))) . "\n"
        . "Version : " . APP_VERSION . "\n"
        . ($u ? "Utilisateur : " . $u['first_name'] . ' ' . $u['last_name'] . ' (' . $u['email'] . ', ' . $u['role'] . ")\n" : '')
        . ($center ? "Centre : " . $center['name'] . "\n" : '')
        . ($page !== '' ? "Page : " . $page . "\n" : '');
}

// ---------------------------------------------------------------- Conversation en direct avec NLapps (centre d'assistance)

/**
 * Accès au centre d'assistance NLapps : paramètres de l'application (clé chiffrée en base) en priorité, sinon config.php.
 * Renvoie ['url', 'key', 'source' => 'settings'|'config'|'none'].
 */
function support_hub_config(): array
{
    $url = (string)setting('support_hub_url', '');
    $key = decrypt_secret(setting('support_hub_key'));
    if ($url !== '' && $key !== '') {
        return ['url' => $url, 'key' => $key, 'source' => 'settings'];
    }
    if ((string)cfg('support_hub_url', '') !== '' && (string)cfg('support_hub_key', '') !== '') {
        return ['url' => (string)cfg('support_hub_url'), 'key' => (string)cfg('support_hub_key'), 'source' => 'config'];
    }
    return ['url' => '', 'key' => '', 'source' => 'none'];
}

/**
 * Lit les deux lignes fournies par NLapps (format config.php), ou une adresse et une clé collées telles quelles.
 * Renvoie ['url' => ?string, 'key' => ?string].
 */
function support_hub_parse(string $text): array
{
    $url = preg_match("/support_hub_url'?\s*=>\s*'([^']+)'/", $text, $m) ? $m[1] : null;
    $key = preg_match("/support_hub_key'?\s*=>\s*'([^']+)'/", $text, $m) ? $m[1] : null;
    $url ??= preg_match('#https?://\S+?api\.php#i', $text, $m) ? $m[0] : null;
    $key ??= preg_match('/\bnlh_[a-f0-9]{20,}\b/i', $text, $m) ? $m[0] : null;
    return ['url' => $url, 'key' => $key];
}

/** Conversation en direct disponible : adresse et clé du centre d'assistance renseignées (paramètres ou config.php). */
function support_live_enabled(): bool
{
    return support_hub_config()['source'] !== 'none';
}

/** Appel du centre d'assistance NLapps (serveur à serveur). Renvoie la réponse décodée, ou null si indisponible. */
function support_hub(string $action, array $query = [], ?array $body = null): ?array
{
    if (!support_live_enabled()) {
        return null;
    }
    $hub = support_hub_config();
    $url = $hub['url'] . (str_contains($hub['url'], '?') ? '&' : '?') . http_build_query(['a' => $action] + $query);
    $headers = ['X-Api-Key: ' . $hub['key'], 'Accept: application/json'];
    $payload = $body !== null ? json_encode($body, JSON_UNESCAPED_UNICODE) : null;
    if ($payload !== null) {
        $headers[] = 'Content-Type: application/json';
    }
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8, CURLOPT_CONNECTTIMEOUT => 4, CURLOPT_HTTPHEADER => $headers]);
        if ($payload !== null) {
            curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $payload]);
        }
        $raw = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
    } else {
        $ctx = stream_context_create(['http' => ['method' => $payload !== null ? 'POST' : 'GET', 'header' => implode("\r\n", $headers),
            'content' => (string)$payload, 'timeout' => 8, 'ignore_errors' => true]]);
        $raw = @file_get_contents($url, false, $ctx);
        $code = (int)preg_replace('/^HTTP\/\S+ (\d+).*/', '$1', $http_response_header[0] ?? 'HTTP/1.1 0');
    }
    $GLOBALS['support_hub_code'] = $code;
    $data = is_string($raw) ? json_decode($raw, true) : null;
    if (!is_array($data) || $code >= 400) {
        error_log('[support-hub] ' . $action . ' : HTTP ' . $code . ' ' . mb_substr((string)$raw, 0, 200));
        return null;
    }
    return $data;
}

/** Code HTTP du dernier appel au centre d'assistance (0 = injoignable). */
function support_hub_last_code(): int
{
    return (int)($GLOBALS['support_hub_code'] ?? 0);
}

/**
 * Appel du centre d'assistance renvoyant un contenu binaire (paquet de mise à jour, image) :
 * écrit dans $dest si fourni. Renvoie le code HTTP ; $type reçoit le type de contenu.
 */
function support_hub_download(string $action, array $query, ?string $dest = null, ?string &$type = null, ?string &$body = null, int $timeout = 120): int
{
    if (!support_live_enabled()) {
        return 0;
    }
    $hub = support_hub_config();
    $url = $hub['url'] . (str_contains($hub['url'], '?') ? '&' : '?') . http_build_query(['a' => $action] + $query);
    $headers = ['X-Api-Key: ' . $hub['key']];
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        $fh = $dest ? fopen($dest, 'wb') : null;
        curl_setopt_array($ch, [CURLOPT_HTTPHEADER => $headers, CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => 6, CURLOPT_FOLLOWLOCATION => false]
            + ($fh ? [CURLOPT_FILE => $fh] : [CURLOPT_RETURNTRANSFER => true]));
        $out = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $type = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);
        if ($fh) {
            fclose($fh);
        } else {
            $body = is_string($out) ? $out : '';
        }
        return $code;
    }
    $ctx = stream_context_create(['http' => ['method' => 'GET', 'header' => implode("\r\n", $headers), 'timeout' => $timeout, 'ignore_errors' => true]]);
    $out = @file_get_contents($url, false, $ctx);
    $code = (int)preg_replace('/^HTTP\/\S+ (\d+).*/', '$1', $http_response_header[0] ?? 'HTTP/1.1 0');
    foreach ($http_response_header ?? [] as $h) {
        if (stripos($h, 'Content-Type:') === 0) {
            $type = trim(substr($h, 13));
        }
    }
    if ($dest) {
        file_put_contents($dest, (string)$out);
    } else {
        $body = (string)$out;
    }
    return $code;
}

/** Conversation ouverte de l'utilisateur (une seule à la fois). */
function support_open_chat(int $userId): ?array
{
    return one("SELECT * FROM support_chats WHERE user_id = ? AND status = 'open' ORDER BY id DESC LIMIT 1", [$userId]);
}

/**
 * Tâche planifiée : relève les réponses de l'équipe NLapps pour les conversations ouvertes
 * et prévient l'utilisateur (notification dans l'application et e-mail selon ses réglages).
 */
function support_sync(): int
{
    if (!support_live_enabled()) {
        return 0;
    }
    $n = 0;
    foreach (all("SELECT * FROM support_chats WHERE status = 'open' AND updated_at >= ?", [date('Y-m-d H:i:s', strtotime('-30 days'))]) as $c) {
        $r = support_hub('poll', ['id' => $c['hub_id'], 'token' => $c['token'], 'after' => max((int)$c['notified_id'], (int)$c['seen_id'])]);
        if ($r === null) {
            if (support_hub_last_code() === 404) { // conversation inconnue du centre d'assistance (clé changée) : close
                update('support_chats', ['status' => 'closed', 'rated' => 1, 'updated_at' => now()], 'id = ?', [$c['id']]);
            }
            continue;
        }
        $agent = array_values(array_filter($r['messages'] ?? [], fn($m) => $m['from'] === 'agent'));
        $maxId = max([(int)$c['notified_id'], ...array_map(fn($m) => (int)$m['id'], $r['messages'] ?? [])]);
        if ($agent) {
            $last = end($agent);
            notify([(int)$c['user_id']], 'support_reply', 'Réponse de l\'assistance ' . support_contact()['editor'],
                mb_substr($last['text'], 0, 300), url('support', ['chat' => 1]));
            $n++;
        }
        update('support_chats', ['notified_id' => $maxId, 'status' => ($r['status'] ?? 'open') === 'closed' ? 'closed' : 'open', 'updated_at' => now()], 'id = ?', [$c['id']]);
    }
    return $n;
}
