<?php
declare(strict_types=1);

final class HttpException extends RuntimeException
{
    public function __construct(public readonly int $status, string $message)
    {
        parent::__construct($message);
    }
}

/**
 * Routeur de l'API JSON. Indépendant des superglobales pour être testable :
 * handle() reçoit méthode, route, query, body et renvoie ['status', 'json'|'raw', 'headers'].
 */
final class Api
{
    private Repository $repo;
    private Settings $settings;
    private Rates $rates;

    private const PUBLIC_ROUTES = ['GET auth/me', 'POST auth/login'];

    public function __construct(private Database $db, private Auth $auth, private ?array $aiConfig = null)
    {
        $this->repo = new Repository($db);
        $this->settings = new Settings($db);
        $this->rates = new Rates($this->repo, $db);
    }

    public function handle(string $method, string $route, array $query = [], array $body = [], ?string $csrf = null, string $ip = ''): array
    {
        $this->extra = [];
        try {
            $route = trim($route, '/');
            $parts = $route === '' ? [] : explode('/', $route);
            $key = $method . ' ' . implode('/', array_slice($parts, 0, 2));

            if (!in_array($key, self::PUBLIC_ROUTES, true) && !$this->auth->user()) {
                throw new HttpException(401, 'Authentification requise');
            }
            if ($method !== 'GET' && !$this->auth->checkCsrf($csrf)) {
                throw new HttpException(419, 'Session expirée, rechargez la page');
            }
            return ['status' => 200, 'json' => ['data' => $this->dispatch($method, $parts, $query, $body, $ip)]] + $this->extra;
        } catch (ValidationException $e) {
            return ['status' => 422, 'json' => ['error' => 'Veuillez corriger les champs signalés', 'fields' => $e->errors]];
        } catch (NotFoundException $e) {
            return ['status' => 404, 'json' => ['error' => $e->getMessage()]];
        } catch (HttpException $e) {
            return ['status' => $e->status, 'json' => ['error' => $e->getMessage()]];
        } catch (Throwable $e) {
            error_log('[VoyageStudio] ' . $e);
            $msg = $e instanceof RuntimeException ? $e->getMessage() : 'Erreur interne';
            return ['status' => 500, 'json' => ['error' => $msg]];
        }
    }

    /** Réponses non JSON (téléchargements). */
    private array $extra = [];

    /** Fichier téléversé (vérifié par is_uploaded_file dans api.php). */
    private ?string $upload = null;

    public function setUpload(?string $path): void
    {
        $this->upload = $path;
    }

    private function dispatch(string $m, array $p, array $q, array $b, string $ip): mixed
    {
        [$a, $id, $action] = array_pad($p, 3, null);

        switch ($a) {
            case 'auth':
                return $this->authRoute($m, (string)$id, $b, $ip);
            case 'bootstrap':
                return [
                    'user' => $this->auth->user(),
                    'schema' => Schema::publicSchema(),
                    'settings' => $this->settings->all(),
                    'currencies' => $this->repo->list('currencies'),
                    'ai' => $this->ai()->status(),
                ];
            case 'settings':
                return $m === 'PUT' ? $this->settings->save($b) : $this->settings->all();
            case 'rates':
                if ($m === 'POST' && $id === 'refresh') {
                    $r = $this->rates->refreshFromEcb();
                    return $r + ['currencies' => $this->repo->list('currencies')];
                }
                break;
            case 'dashboard':
                return (new Dashboard($this->db, $this->repo))->build();
            case 'search':
                return $this->globalSearch((string)($q['q'] ?? ''));
            case 'backup':
                return $this->backup($m, $b);
            case 'export':
                return $this->exportCsv((string)$id);
            case 'ai':
                return $this->aiRoute($m, (string)$id, $b);
            case 'update':
                return $this->updateRoute($m, (string)$id, $b, $q);
        }

        if (!$a || !Schema::entity($a)) {
            throw new NotFoundException('Route inconnue');
        }
        $entity = $a;
        $id = $id !== null && ctype_digit((string)$id) ? (int)$id : null;

        // Actions spécifiques
        if ($entity === 'trips' && $id && $action === 'full' && $m === 'GET') {
            return $this->tripFull($id);
        }
        if ($id && $action === 'duplicate' && $m === 'POST' && in_array($entity, ['trips', 'options', 'items'], true)) {
            return $this->duplicateRoute($entity, $id);
        }
        if ($action !== null) {
            throw new NotFoundException('Route inconnue');
        }

        return match (true) {
            $m === 'GET' && $id === null => $this->listRoute($entity, $q),
            $m === 'GET' => $this->repo->get($entity, $id),
            $m === 'POST' && $id === null => $this->createRoute($entity, $b),
            $m === 'PUT' && $id !== null => $this->repo->update($entity, $id, $b),
            $m === 'DELETE' && $id !== null => $this->deleteRoute($entity, $id),
            default => throw new HttpException(405, 'Méthode non autorisée'),
        };
    }

    private function ai(): AiAssistant
    {
        return new AiAssistant($this->aiConfig);
    }

    private function aiRoute(string $m, string $action, array $b): mixed
    {
        if ($m !== 'POST') {
            return $this->ai()->status();
        }
        if ($action === 'extract') {
            $context = [];
            if (!empty($b['trip_id'])) {
                $t = $this->repo->get('trips', (int)$b['trip_id']);
                $dest = $t['destination_id'] ? $this->tryGet('destinations', $t['destination_id']) : null;
                $context = [
                    'voyage' => $t['titre'], 'destination' => $dest['nom'] ?? null, 'depart' => $t['date_depart'], 'retour' => $t['date_retour'],
                    'ville_depart' => $t['ville_depart'], 'adultes' => $t['nb_adultes'], 'enfants' => $t['nb_enfants'], 'bebes' => $t['nb_bebes'],
                ];
            }
            $res = $this->ai()->extract((array)($b['files'] ?? []), (string)($b['text'] ?? ''), $context);
            // Rapprochement avec les fournisseurs existants (par nom)
            $suppliers = $this->repo->list('suppliers', [], null, 5000);
            foreach ($res['items'] as &$it) {
                $name = mb_strtolower(trim((string)($it['_fournisseur'] ?? $it['compagnie'] ?? '')));
                if ($name === '') {
                    continue;
                }
                foreach ($suppliers as $s) {
                    $sn = mb_strtolower($s['nom']);
                    if ($sn !== '' && (str_contains($name, $sn) || str_contains($sn, $name))) {
                        $it['supplier_id'] = $s['id'];
                        if ($s['commission_pct'] && empty($it['commission_pct'])) {
                            $it['commission_pct'] = $s['commission_pct'];
                        }
                        break;
                    }
                }
            }
            return $res;
        }
        if ($action === 'suggest') {
            return $this->ai()->suggest((array)($b['summary'] ?? []));
        }
        throw new NotFoundException('Route inconnue');
    }

    private function updateRoute(string $m, string $action, array $b, array $q): mixed
    {
        $up = new Updater(VS_ROOT, $this->db);
        if ($m === 'GET' && $action === '') {
            return $up->status();
        }
        if ($m === 'GET' && $action === 'download') {
            $path = $up->backupPath((string)($q['file'] ?? ''));
            $this->extra = ['raw' => (string)file_get_contents($path), 'headers' => [
                'Content-Type' => str_ends_with($path, '.zip') ? 'application/zip' : 'application/json',
                'Content-Disposition' => 'attachment; filename="' . basename($path) . '"',
            ]];
            return null;
        }
        if ($m === 'POST' && $action === 'backup') {
            return $up->backup();
        }
        if ($m === 'POST' && in_array($action, ['inspect', 'install'], true)) {
            if (!$this->upload || !is_file($this->upload)) {
                throw new HttpException(400, 'Aucun fichier ZIP reçu');
            }
            if ($action === 'inspect') {
                $info = $up->inspect($this->upload);
                unset($info['_map']);
                return $info;
            }
            return $up->install($this->upload, !empty($b['allow_downgrade']));
        }
        throw new NotFoundException('Route inconnue');
    }

    private function authRoute(string $m, string $action, array $b, string $ip): mixed
    {
        switch ("$m $action") {
            case 'GET me':
                return ['user' => $this->auth->user(), 'csrf' => $this->auth->csrfToken()];
            case 'POST login':
                $u = $this->auth->login((string)($b['email'] ?? ''), (string)($b['password'] ?? ''), $ip);
                if (!$u) {
                    throw new HttpException(401, 'Identifiants incorrects');
                }
                return ['user' => $u, 'csrf' => $this->auth->csrfToken()];
            case 'POST logout':
                $this->auth->logout();
                return ['csrf' => $this->auth->csrfToken()];
            case 'POST password':
                $u = $this->auth->user();
                $this->auth->changePassword((int)$u['id'], (string)($b['current'] ?? ''), (string)($b['password'] ?? ''));
                return true;
        }
        throw new NotFoundException('Route inconnue');
    }

    private function listRoute(string $entity, array $q): array
    {
        $def = Schema::entity($entity);
        $filters = array_intersect_key($q, $def['fields'] + ['id' => true]);
        $rows = $this->repo->list($entity, $filters, $q['q'] ?? null, (int)($q['limit'] ?? 1000));
        if ($entity === 'trips') {
            $rows = $this->enrichTrips($rows);
        }
        return $rows;
    }

    private function enrichTrips(array $rows): array
    {
        $clients = $this->labels('clients', fn($c) => trim(($c['nom'] ?? '') . ' ' . ($c['prenom'] ?? '')));
        $dests = $this->labels('destinations', fn($d) => $d['nom']);
        foreach ($rows as &$r) {
            $r['_client'] = $clients[$r['client_id']] ?? null;
            $r['_destination'] = $dests[$r['destination_id']] ?? null;
        }
        return $rows;
    }

    private function labels(string $entity, callable $fn): array
    {
        $out = [];
        foreach ($this->repo->list($entity, [], null, 5000) as $r) {
            $out[$r['id']] = $fn($r);
        }
        return $out;
    }

    private function createRoute(string $entity, array $b): array
    {
        if ($entity === 'trips') {
            return $this->db->transaction(function () use ($b) {
                if (empty($b['reference'])) {
                    $b['reference'] = $this->nextReference();
                }
                $trip = $this->repo->create('trips', $b);
                $s = $this->settings->all();
                $opt = $this->repo->create('options', [
                    'trip_id' => $trip['id'], 'nom' => 'Variante A',
                    'marge_mode' => $s['marge_mode'], 'marge_valeur' => $s['marge_valeur'],
                    'frais_dossier' => $s['frais_dossier'], 'frais_dossier_pers' => $s['frais_dossier_pers'],
                    'arrondi' => $s['arrondi'], 'inclus' => $s['devis_inclus'], 'non_inclus' => $s['devis_non_inclus'],
                ]);
                return $this->repo->update('trips', $trip['id'], ['selected_option_id' => $opt['id']]);
            });
        }
        if ($entity === 'options' && !empty($b['trip_id'])) {
            $s = $this->settings->all();
            $b += [
                'marge_mode' => $s['marge_mode'], 'marge_valeur' => $s['marge_valeur'],
                'frais_dossier' => $s['frais_dossier'], 'frais_dossier_pers' => $s['frais_dossier_pers'],
                'arrondi' => $s['arrondi'], 'inclus' => $s['devis_inclus'], 'non_inclus' => $s['devis_non_inclus'],
                'ordre' => (int)$this->db->value('SELECT COUNT(*) FROM options WHERE trip_id = ?', [(int)$b['trip_id']]),
            ];
        }
        if ($entity === 'currencies' && isset($b['code'])) {
            $b['code'] = strtoupper(trim((string)$b['code']));
            if (!preg_match('/^[A-Z]{3}$/', $b['code'])) {
                throw new ValidationException(['code' => 'Code ISO à 3 lettres']);
            }
        }
        return $this->repo->create($entity, $b);
    }

    private function deleteRoute(string $entity, int $id): bool
    {
        if ($entity === 'options') {
            $opt = $this->repo->get('options', $id);
            $count = (int)$this->db->value('SELECT COUNT(*) FROM options WHERE trip_id = ?', [$opt['trip_id']]);
            if ($count <= 1) {
                throw new HttpException(409, 'Un dossier doit conserver au moins une variante');
            }
        }
        $this->db->transaction(fn() => $this->repo->delete($entity, $id));
        if ($entity === 'options') {
            $this->db->query('UPDATE trips SET selected_option_id = NULL WHERE selected_option_id = ?', [$id]);
        }
        return true;
    }

    private function duplicateRoute(string $entity, int $id): array
    {
        return $this->db->transaction(function () use ($entity, $id) {
            $src = $this->repo->get($entity, $id);
            if ($entity === 'trips') {
                $copy = $this->repo->duplicate('trips', $id, [
                    'reference' => $this->nextReference(), 'titre' => $src['titre'] . ' (copie)',
                    'statut' => 'prospect', 'selected_option_id' => null,
                ]);
                $first = $this->db->value('SELECT id FROM options WHERE trip_id = ? ORDER BY ordre, id LIMIT 1', [$copy['id']]);
                return $this->repo->update('trips', $copy['id'], ['selected_option_id' => $first ?: null]);
            }
            if ($entity === 'options') {
                return $this->repo->duplicate('options', $id, ['nom' => $src['nom'] . ' (copie)', 'ordre' => (int)$src['ordre'] + 1]);
            }
            return $this->repo->duplicate($entity, $id, ['libelle' => $src['libelle'] . ' (copie)', 'statut' => 'a_demander', 'ref_reservation' => null]);
        });
    }

    private function nextReference(): string
    {
        $prefix = 'VS' . date('y');
        $last = $this->db->value('SELECT reference FROM trips WHERE reference LIKE ? ORDER BY reference DESC LIMIT 1', [$prefix . '-%']);
        $n = $last ? (int)substr((string)$last, strlen($prefix) + 1) + 1 : 1;
        return sprintf('%s-%04d', $prefix, $n);
    }

    private function tripFull(int $id): array
    {
        $trip = $this->repo->get('trips', $id);
        $options = $this->repo->list('options', ['trip_id' => $id]);
        $optIds = array_column($options, 'id');
        $items = $optIds ? $this->repo->list('items', ['option_id' => $optIds], null, 5000) : [];
        $days = $optIds ? $this->repo->list('days', ['option_id' => $optIds], null, 5000) : [];
        foreach ($options as &$o) {
            $o['items'] = array_values(array_filter($items, fn($i) => $i['option_id'] === $o['id']));
            $o['days'] = array_values(array_filter($days, fn($d) => $d['option_id'] === $o['id']));
        }
        unset($o);
        $travelers = $this->repo->list('travelers', ['trip_id' => $id]);
        $travelerClients = $travelers ? $this->repo->list('clients', ['id' => array_column($travelers, 'client_id')]) : [];
        foreach ($travelers as &$t) {
            $t['client'] = current(array_filter($travelerClients, fn($c) => $c['id'] === $t['client_id'])) ?: null;
        }
        return [
            'trip' => $trip,
            'client' => $trip['client_id'] ? $this->tryGet('clients', $trip['client_id']) : null,
            'destination' => $trip['destination_id'] ? $this->tryGet('destinations', $trip['destination_id']) : null,
            'options' => $options,
            'payments' => $this->repo->list('payments', ['trip_id' => $id]),
            'travelers' => $travelers,
            'settings' => $this->settings->all(),
            'currencies' => $this->repo->list('currencies'),
        ];
    }

    private function tryGet(string $entity, int $id): ?array
    {
        try {
            return $this->repo->get($entity, $id);
        } catch (NotFoundException) {
            return null;
        }
    }

    private function globalSearch(string $q): array
    {
        if (mb_strlen(trim($q)) < 2) {
            return [];
        }
        $out = [];
        foreach (['trips', 'clients', 'suppliers', 'destinations'] as $e) {
            foreach ($this->repo->list($e, [], $q, 8) as $r) {
                $def = Schema::entity($e);
                $out[] = [
                    'entity' => $e, 'id' => $r['id'], 'type' => $def['singular'],
                    'label' => trim(implode(' ', array_map(fn($f) => (string)($r[$f] ?? ''), $def['title']))),
                ];
            }
        }
        return $out;
    }

    private function backup(string $m, array $b): mixed
    {
        $tables = array_merge(array_keys(Schema::entities()), ['settings']);
        if ($m === 'GET') {
            $dump = ['app' => 'VoyageStudio', 'version' => 1, 'date' => date('c'), 'tables' => []];
            foreach ($tables as $t) {
                $dump['tables'][$t] = $this->db->all('SELECT * FROM ' . $this->repo->q($t));
            }
            $this->extra = [
                'raw' => json_encode($dump, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
                'headers' => [
                    'Content-Type' => 'application/json; charset=utf-8',
                    'Content-Disposition' => 'attachment; filename="voyagestudio-sauvegarde-' . date('Y-m-d') . '.json"',
                ],
            ];
            return null;
        }
        if ($m === 'POST') {
            if (($b['app'] ?? '') !== 'VoyageStudio' || !is_array($b['tables'] ?? null)) {
                throw new HttpException(400, 'Fichier de sauvegarde invalide');
            }
            $this->db->transaction(function () use ($b, $tables) {
                foreach ($tables as $t) {
                    $rows = $b['tables'][$t] ?? null;
                    if (!is_array($rows)) {
                        continue;
                    }
                    $this->db->query('DELETE FROM ' . $this->repo->q($t));
                    $allowed = $t === 'settings' ? ['id', 'k', 'v', 'created_at', 'updated_at']
                        : array_merge(['id', 'created_at', 'updated_at'], array_keys(Schema::entity($t)['fields']));
                    foreach ($rows as $row) {
                        $row = array_intersect_key((array)$row, array_flip($allowed));
                        if (!$row) {
                            continue;
                        }
                        $cols = array_keys($row);
                        $this->db->query('INSERT INTO ' . $this->repo->q($t) . ' (' . implode(',', array_map([$this->repo, 'q'], $cols)) . ') VALUES ('
                            . implode(',', array_fill(0, count($cols), '?')) . ')', array_values($row));
                    }
                }
            });
            return true;
        }
        throw new HttpException(405, 'Méthode non autorisée');
    }

    private function exportCsv(string $entity): mixed
    {
        $def = Schema::entity($entity);
        if (!$def || !in_array($entity, ['clients', 'suppliers', 'destinations', 'trips', 'payments'], true)) {
            throw new NotFoundException('Export indisponible');
        }
        $rows = $this->repo->list($entity, [], null, 5000);
        if ($entity === 'trips') {
            $rows = $this->enrichTrips($rows);
        }
        $cols = array_keys($def['fields']);
        $fh = fopen('php://temp', 'w+');
        fwrite($fh, "\xEF\xBB\xBF"); // BOM : ouverture correcte dans Excel
        fputcsv($fh, array_merge(['id'], array_map(fn($c) => $def['fields'][$c]['label'], $cols), $entity === 'trips' ? ['Client', 'Destination'] : []), ';');
        foreach ($rows as $r) {
            $line = [$r['id']];
            foreach ($cols as $c) {
                $v = $r[$c] ?? '';
                if (is_float($v)) {
                    $v = str_replace('.', ',', (string)$v);
                } elseif (is_string($v) && $v !== '' && in_array($v[0], ['=', '+', '-', '@'], true)) {
                    $v = "'" . $v; // neutralise l'injection de formules dans les tableurs
                }
                $line[] = $v;
            }
            if ($entity === 'trips') {
                $line[] = $r['_client'] ?? '';
                $line[] = $r['_destination'] ?? '';
            }
            fputcsv($fh, $line, ';');
        }
        rewind($fh);
        $this->extra = [
            'raw' => stream_get_contents($fh),
            'headers' => [
                'Content-Type' => 'text/csv; charset=utf-8',
                'Content-Disposition' => 'attachment; filename="' . $entity . '-' . date('Y-m-d') . '.csv"',
            ],
        ];
        return null;
    }
}
