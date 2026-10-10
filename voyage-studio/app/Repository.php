<?php
declare(strict_types=1);

final class ValidationException extends RuntimeException
{
    public function __construct(public readonly array $errors)
    {
        parent::__construct('Données invalides');
    }
}

final class NotFoundException extends RuntimeException
{
}

/**
 * CRUD générique piloté par Schema : whitelist des colonnes, normalisation des saisies
 * (virgule décimale française, booléens, dates), suppression en cascade des enfants.
 */
final class Repository
{
    public function __construct(private Database $db)
    {
    }

    public function q(string $ident): string
    {
        return $this->db->driver() === 'mysql' ? "`$ident`" : "\"$ident\"";
    }

    private function def(string $entity): array
    {
        $def = Schema::entity($entity);
        if (!$def) {
            throw new NotFoundException("Entité inconnue : $entity");
        }
        return $def;
    }

    public function list(string $entity, array $filters = [], ?string $search = null, int $limit = 1000): array
    {
        $def = $this->def($entity);
        $where = [];
        $params = [];
        foreach ($filters as $col => $val) {
            $f = $def['fields'][$col] ?? null;
            if ($col === 'id' || ($f && in_array($f['type'], ['fk', 'enum', 'bool'], true))) {
                if (is_array($val)) {
                    $val = array_values(array_filter($val, fn($v) => $v !== ''));
                    if (!$val) {
                        continue;
                    }
                    $where[] = $this->q($col) . ' IN (' . implode(',', array_fill(0, count($val), '?')) . ')';
                    array_push($params, ...$val);
                } elseif ($val === 'null') {
                    $where[] = $this->q($col) . ' IS NULL';
                } else {
                    $where[] = $this->q($col) . ' = ?';
                    $params[] = $val;
                }
            }
        }
        if ($search !== null && trim($search) !== '') {
            $or = [];
            foreach ($def['fields'] as $col => $f) {
                if (!empty($f['search'])) {
                    $or[] = 'LOWER(' . $this->q($col) . ') LIKE ?';
                    $params[] = '%' . mb_strtolower(trim($search)) . '%';
                }
            }
            if ($or) {
                $where[] = '(' . implode(' OR ', $or) . ')';
            }
        }
        $sql = 'SELECT * FROM ' . $this->q($entity)
            . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
            . ' ORDER BY ' . $this->orderClause($def['order'] ?? 'id DESC')
            . ' LIMIT ' . max(1, min($limit, 5000));
        return array_map(fn($r) => $this->cast($entity, $r), $this->db->all($sql, $params));
    }

    private function orderClause(string $order): string
    {
        $parts = [];
        foreach (explode(',', $order) as $p) {
            [$col, $dir] = array_pad(preg_split('/\s+/', trim($p)), 2, 'ASC');
            $parts[] = $this->q($col) . (strtoupper($dir) === 'DESC' ? ' DESC' : ' ASC');
        }
        return implode(', ', $parts);
    }

    public function get(string $entity, int $id): array
    {
        $this->def($entity);
        $row = $this->db->one('SELECT * FROM ' . $this->q($entity) . ' WHERE id = ?', [$id]);
        if (!$row) {
            throw new NotFoundException('Enregistrement introuvable');
        }
        return $this->cast($entity, $row);
    }

    public function create(string $entity, array $data): array
    {
        $def = $this->def($entity);
        // Valeurs par défaut
        foreach ($def['fields'] as $col => $f) {
            if (!array_key_exists($col, $data) && array_key_exists('default', $f)) {
                $data[$col] = $f['default'];
            }
        }
        $clean = $this->validate($entity, $data, true);
        $now = date('Y-m-d H:i:s');
        $clean['created_at'] = $now;
        $clean['updated_at'] = $now;
        $cols = array_keys($clean);
        $sql = 'INSERT INTO ' . $this->q($entity) . ' (' . implode(',', array_map([$this, 'q'], $cols)) . ') VALUES ('
            . implode(',', array_fill(0, count($cols), '?')) . ')';
        $this->db->query($sql, array_values($clean));
        return $this->get($entity, (int)$this->db->pdo()->lastInsertId());
    }

    public function update(string $entity, int $id, array $data): array
    {
        $this->get($entity, $id);
        $clean = $this->validate($entity, $data, false);
        if ($clean) {
            $clean['updated_at'] = date('Y-m-d H:i:s');
            $set = implode(', ', array_map(fn($c) => $this->q($c) . ' = ?', array_keys($clean)));
            $this->db->query('UPDATE ' . $this->q($entity) . " SET $set WHERE id = ?", [...array_values($clean), $id]);
        }
        return $this->get($entity, $id);
    }

    public function delete(string $entity, int $id): void
    {
        $def = $this->def($entity);
        foreach ($def['children'] ?? [] as $child => $fk) {
            foreach ($this->db->all('SELECT id FROM ' . $this->q($child) . ' WHERE ' . $this->q($fk) . ' = ?', [$id]) as $r) {
                $this->delete($child, (int)$r['id']);
            }
        }
        $this->db->query('DELETE FROM ' . $this->q($entity) . ' WHERE id = ?', [$id]);
    }

    /** Copie profonde d'un enregistrement et de ses enfants. $override s'applique à la racine. */
    public function duplicate(string $entity, int $id, array $override = []): array
    {
        $def = $this->def($entity);
        $src = $this->get($entity, $id);
        $data = array_intersect_key($src, $def['fields']);
        $copy = $this->create($entity, array_merge($data, $override));
        foreach ($def['children'] ?? [] as $child => $fk) {
            if ($entity === 'trips' && $child === 'payments') {
                continue; // un nouveau dossier repart sans échéancier
            }
            foreach ($this->list($child, [$fk => $id]) as $c) {
                $this->duplicate($child, (int)$c['id'], [$fk => $copy['id']]);
            }
        }
        return $copy;
    }

    /* ---------------------------------------------------------------- Validation */

    public function validate(string $entity, array $data, bool $isCreate): array
    {
        $def = $this->def($entity);
        $clean = [];
        $errors = [];
        foreach ($def['fields'] as $col => $f) {
            if (!array_key_exists($col, $data)) {
                if ($isCreate && !empty($f['required'])) {
                    $errors[$col] = 'Champ obligatoire';
                }
                continue;
            }
            try {
                $v = self::normalize($f, $data[$col]);
            } catch (InvalidArgumentException $e) {
                $errors[$col] = $e->getMessage();
                continue;
            }
            if (!empty($f['required']) && ($v === null || $v === '')) {
                $errors[$col] = 'Champ obligatoire';
                continue;
            }
            $clean[$col] = $v;
        }
        if ($errors) {
            throw new ValidationException($errors);
        }
        return $clean;
    }

    public static function normalize(array $f, mixed $v): mixed
    {
        if (is_string($v)) {
            $v = trim($v);
        }
        $empty = $v === null || $v === '';
        switch ($f['type']) {
            case 'bool':
                return ($v === true || $v === 1 || $v === '1' || $v === 'true' || $v === 'on') ? 1 : 0;
            case 'int':
            case 'fk':
                if ($empty) {
                    return null;
                }
                if (!is_numeric($v)) {
                    throw new InvalidArgumentException('Nombre entier attendu');
                }
                return (int)$v;
            case 'float':
                if ($empty) {
                    return null;
                }
                if (is_string($v)) {
                    $v = str_replace([' ', "\u{a0}", "\u{202f}", '€'], '', $v);
                    $v = str_replace(',', '.', $v);
                }
                if (!is_numeric($v)) {
                    throw new InvalidArgumentException('Nombre attendu');
                }
                return round((float)$v, 4);
            case 'date':
                if ($empty) {
                    return null;
                }
                if (preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', (string)$v, $m)) {
                    $v = "$m[3]-$m[2]-$m[1]";
                }
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$v) || !checkdate((int)substr($v, 5, 2), (int)substr($v, 8, 2), (int)substr($v, 0, 4))) {
                    throw new InvalidArgumentException('Date invalide');
                }
                return $v;
            case 'time':
                if ($empty) {
                    return null;
                }
                $v = str_replace(['h', 'H', '.'], ':', (string)$v);
                if (!preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/', $v, $m)) {
                    throw new InvalidArgumentException('Heure invalide (HH:MM)');
                }
                return sprintf('%02d:%s', $m[1], $m[2]);
            case 'enum':
                $v = $empty ? '' : (string)$v;
                if (!array_key_exists($v, $f['options'])) {
                    if ($v === '') {
                        return null;
                    }
                    throw new InvalidArgumentException('Valeur non autorisée');
                }
                return $v === '' ? null : $v;
            case 'email':
                if ($empty) {
                    return null;
                }
                if (!filter_var($v, FILTER_VALIDATE_EMAIL)) {
                    throw new InvalidArgumentException('E-mail invalide');
                }
                return mb_strtolower((string)$v);
            case 'text':
                return $empty ? null : mb_substr((string)$v, 0, 65000);
            default:
                return $empty ? null : mb_substr((string)$v, 0, 255);
        }
    }

    /** Typage des valeurs lues (PDO SQLite/MySQL renvoie souvent des chaînes). */
    public function cast(string $entity, array $row): array
    {
        $def = $this->def($entity);
        $row['id'] = (int)$row['id'];
        foreach ($def['fields'] as $col => $f) {
            if (!array_key_exists($col, $row) || $row[$col] === null) {
                continue;
            }
            $row[$col] = match ($f['type']) {
                'int', 'fk' => (int)$row[$col],
                'bool' => (int)$row[$col],
                'float' => (float)$row[$col],
                default => $row[$col],
            };
        }
        return $row;
    }
}
