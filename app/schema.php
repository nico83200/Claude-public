<?php
declare(strict_types=1);

/**
 * Schéma de la base. Les marqueurs {PK}, {FK}, {OPT} sont adaptés au moteur.
 */
function schema_statements(string $driver): array
{
    $tables = [
        'settings' => "
            skey VARCHAR(100) NOT NULL PRIMARY KEY,
            svalue TEXT NULL",

        'centers' => "
            id {PK},
            name VARCHAR(150) NOT NULL,
            code VARCHAR(20) NULL,
            address VARCHAR(255) NULL,
            city VARCHAR(120) NULL,
            phone VARCHAR(40) NULL,
            delivery_info TEXT NULL,
            color VARCHAR(20) NOT NULL DEFAULT '#6366f1',
            active TINYINT NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL",

        'users' => "
            id {PK},
            email VARCHAR(190) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            first_name VARCHAR(100) NOT NULL,
            last_name VARCHAR(100) NOT NULL,
            job VARCHAR(100) NULL,
            phone VARCHAR(40) NULL,
            role VARCHAR(20) NOT NULL DEFAULT 'user',
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            requested_centers VARCHAR(255) NULL,
            notify_email TINYINT NOT NULL DEFAULT 1,
            notify_prefs TEXT NULL,
            last_login DATETIME NULL,
            created_at DATETIME NOT NULL",

        'user_centers' => "
            user_id {FK} NOT NULL,
            center_id {FK} NOT NULL,
            PRIMARY KEY (user_id, center_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (center_id) REFERENCES centers(id) ON DELETE CASCADE",

        'suppliers' => "
            id {PK},
            name VARCHAR(150) NOT NULL,
            contact_name VARCHAR(150) NULL,
            email VARCHAR(190) NULL,
            phone VARCHAR(40) NULL,
            website VARCHAR(255) NULL,
            customer_number VARCHAR(80) NULL,
            min_order_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
            shipping_fee DECIMAL(10,2) NOT NULL DEFAULT 0,
            free_shipping_from DECIMAL(10,2) NOT NULL DEFAULT 0,
            delivery_delay VARCHAR(80) NULL,
            order_method VARCHAR(80) NULL,
            notes TEXT NULL,
            all_centers TINYINT NOT NULL DEFAULT 1,
            color VARCHAR(20) NOT NULL DEFAULT '#0ea5e9',
            active TINYINT NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL",

        'supplier_centers' => "
            supplier_id {FK} NOT NULL,
            center_id {FK} NOT NULL,
            PRIMARY KEY (supplier_id, center_id),
            FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE,
            FOREIGN KEY (center_id) REFERENCES centers(id) ON DELETE CASCADE",

        'categories' => "
            id {PK},
            name VARCHAR(120) NOT NULL,
            icon VARCHAR(40) NOT NULL DEFAULT 'box',
            color VARCHAR(20) NOT NULL DEFAULT '#8b5cf6',
            position INT NOT NULL DEFAULT 0",

        'products' => "
            id {PK},
            supplier_id {FK} NOT NULL,
            category_id {FK} NULL,
            reference VARCHAR(80) NULL,
            barcode VARCHAR(64) NULL,
            name VARCHAR(200) NOT NULL,
            description TEXT NULL,
            unit VARCHAR(80) NULL,
            catalog_price DECIMAL(10,2) NOT NULL DEFAULT 0,
            negotiated_price DECIMAL(10,2) NULL,
            vat_rate DECIMAL(5,2) NOT NULL DEFAULT 20,
            keywords VARCHAR(500) NULL,
            image VARCHAR(255) NULL,
            min_qty INT NOT NULL DEFAULT 1,
            active TINYINT NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NULL,
            FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
            FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL",

        'cart_items' => "
            id {PK},
            user_id {FK} NOT NULL,
            center_id {FK} NOT NULL,
            product_id {FK} NOT NULL,
            qty INT NOT NULL DEFAULT 1,
            comment VARCHAR(255) NULL,
            created_at DATETIME NOT NULL,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (center_id) REFERENCES centers(id) ON DELETE CASCADE,
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE",

        'requests' => "
            id {PK},
            center_id {FK} NOT NULL,
            user_id {FK} NOT NULL,
            comment TEXT NULL,
            urgent TINYINT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            FOREIGN KEY (center_id) REFERENCES centers(id),
            FOREIGN KEY (user_id) REFERENCES users(id)",

        'purchase_orders' => "
            id {PK},
            po_number VARCHAR(40) NOT NULL,
            center_id {FK} NOT NULL,
            supplier_id {FK} NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'a_commander',
            shipping_fee DECIMAL(10,2) NOT NULL DEFAULT 0,
            notes TEXT NULL,
            supplier_reference VARCHAR(120) NULL,
            expected_date DATE NULL,
            created_by {FK} NULL,
            created_at DATETIME NOT NULL,
            ordered_by {FK} NULL,
            ordered_at DATETIME NULL,
            received_at DATETIME NULL,
            FOREIGN KEY (center_id) REFERENCES centers(id),
            FOREIGN KEY (supplier_id) REFERENCES suppliers(id)",

        'request_lines' => "
            id {PK},
            request_id {FK} NOT NULL,
            center_id {FK} NOT NULL,
            product_id {FK} NOT NULL,
            supplier_id {FK} NOT NULL,
            qty INT NOT NULL,
            unit_price DECIMAL(10,2) NOT NULL DEFAULT 0,
            comment VARCHAR(255) NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            purchase_order_id {FK} NULL,
            cancel_reason VARCHAR(255) NULL,
            created_at DATETIME NOT NULL,
            FOREIGN KEY (request_id) REFERENCES requests(id) ON DELETE CASCADE,
            FOREIGN KEY (product_id) REFERENCES products(id),
            FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
            FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(id) ON DELETE SET NULL",

        'purchase_order_lines' => "
            id {PK},
            purchase_order_id {FK} NOT NULL,
            product_id {FK} NULL,
            reference VARCHAR(80) NULL,
            label VARCHAR(255) NOT NULL,
            unit VARCHAR(80) NULL,
            qty INT NOT NULL,
            unit_price DECIMAL(10,2) NOT NULL DEFAULT 0,
            catalog_price DECIMAL(10,2) NOT NULL DEFAULT 0,
            qty_received INT NOT NULL DEFAULT 0,
            received_at DATETIME NULL,
            received_by {FK} NULL,
            FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(id) ON DELETE CASCADE",

        'po_history' => "
            id {PK},
            purchase_order_id {FK} NOT NULL,
            user_id {FK} NULL,
            action VARCHAR(60) NOT NULL,
            details TEXT NULL,
            created_at DATETIME NOT NULL,
            FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(id) ON DELETE CASCADE",

        'deadlines' => "
            id {PK},
            title VARCHAR(200) NOT NULL,
            deadline_at DATETIME NOT NULL,
            supplier_id {FK} NULL,
            center_id {FK} NULL,
            description TEXT NULL,
            created_by {FK} NULL,
            created_at DATETIME NOT NULL,
            FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE,
            FOREIGN KEY (center_id) REFERENCES centers(id) ON DELETE CASCADE",

        'favorites' => "
            user_id {FK} NOT NULL,
            product_id {FK} NOT NULL,
            PRIMARY KEY (user_id, product_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE",

        'stock' => "
            center_id {FK} NOT NULL,
            product_id {FK} NOT NULL,
            qty INT NOT NULL DEFAULT 0,
            alert_qty INT NOT NULL DEFAULT 0,
            counted_at DATETIME NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (center_id, product_id),
            FOREIGN KEY (center_id) REFERENCES centers(id) ON DELETE CASCADE,
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE",

        'stock_movements' => "
            id {PK},
            center_id {FK} NOT NULL,
            product_id {FK} NOT NULL,
            type VARCHAR(20) NOT NULL,
            delta INT NOT NULL,
            qty_after INT NOT NULL,
            purchase_order_id {FK} NULL,
            user_id {FK} NULL,
            note VARCHAR(255) NULL,
            created_at DATETIME NOT NULL,
            FOREIGN KEY (center_id) REFERENCES centers(id) ON DELETE CASCADE,
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE",

        'budgets' => "
            center_id {FK} NOT NULL,
            year INT NOT NULL,
            amount DECIMAL(12,2) NOT NULL DEFAULT 0,
            alert_pct INT NOT NULL DEFAULT 80,
            alert_sent TINYINT NOT NULL DEFAULT 0,
            PRIMARY KEY (center_id, year),
            FOREIGN KEY (center_id) REFERENCES centers(id) ON DELETE CASCADE",

        'notifications' => "
            id {PK},
            user_id {FK} NOT NULL,
            type VARCHAR(40) NOT NULL,
            title VARCHAR(255) NOT NULL,
            body TEXT NULL,
            link VARCHAR(255) NULL,
            read_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE",

        'update_history' => "
            id {PK},
            action VARCHAR(20) NOT NULL,
            from_version VARCHAR(20) NULL,
            to_version VARCHAR(20) NULL,
            backup_file VARCHAR(255) NULL,
            notes TEXT NULL,
            user_id {FK} NULL,
            created_at DATETIME NOT NULL",

        'product_suggestions' => "
            id {PK},
            center_id {FK} NOT NULL,
            user_id {FK} NOT NULL,
            source VARCHAR(10) NOT NULL DEFAULT 'cart',
            barcode VARCHAR(64) NULL,
            name VARCHAR(200) NOT NULL,
            brand VARCHAR(120) NULL,
            reference VARCHAR(80) NULL,
            description TEXT NULL,
            unit VARCHAR(80) NULL,
            supplier_hint VARCHAR(200) NULL,
            url VARCHAR(500) NULL,
            estimated_price DECIMAL(10,2) NULL,
            qty INT NOT NULL DEFAULT 0,
            image VARCHAR(255) NULL,
            in_cart TINYINT NOT NULL DEFAULT 0,
            request_id {FK} NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            product_id {FK} NULL,
            admin_note VARCHAR(255) NULL,
            handled_by {FK} NULL,
            handled_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            FOREIGN KEY (center_id) REFERENCES centers(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (request_id) REFERENCES requests(id) ON DELETE SET NULL",

        'password_resets' => "
            id {PK},
            user_id {FK} NOT NULL,
            token_hash VARCHAR(64) NOT NULL,
            expires_at DATETIME NOT NULL,
            used_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE",

        'login_attempts' => "
            id {PK},
            email VARCHAR(190) NOT NULL,
            ip VARCHAR(45) NOT NULL,
            success TINYINT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL",

        'mail_queue' => "
            id {PK},
            to_email VARCHAR(190) NOT NULL,
            subject VARCHAR(255) NOT NULL,
            html {LONGTEXT} NOT NULL,
            attachments TEXT NULL,
            attempts INT NOT NULL DEFAULT 0,
            last_error VARCHAR(255) NULL,
            sent_at DATETIME NULL,
            created_at DATETIME NOT NULL",

        'kits' => "
            id {PK},
            name VARCHAR(150) NOT NULL,
            description VARCHAR(255) NULL,
            center_id {FK} NULL,
            user_id {FK} NULL,
            shared TINYINT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            FOREIGN KEY (center_id) REFERENCES centers(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE",

        'kit_items' => "
            kit_id {FK} NOT NULL,
            product_id {FK} NOT NULL,
            qty INT NOT NULL DEFAULT 1,
            PRIMARY KEY (kit_id, product_id),
            FOREIGN KEY (kit_id) REFERENCES kits(id) ON DELETE CASCADE,
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE",

        'price_history' => "
            id {PK},
            product_id {FK} NOT NULL,
            catalog_price DECIMAL(10,2) NOT NULL,
            negotiated_price DECIMAL(10,2) NULL,
            source VARCHAR(30) NULL,
            user_id {FK} NULL,
            created_at DATETIME NOT NULL,
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE",

        'audit_log' => "
            id {PK},
            user_id {FK} NULL,
            action VARCHAR(60) NOT NULL,
            entity VARCHAR(40) NULL,
            entity_id INT NULL,
            details TEXT NULL,
            ip VARCHAR(45) NULL,
            created_at DATETIME NOT NULL",

        'ai_cache' => "
            id {PK},
            cache_key VARCHAR(64) NOT NULL UNIQUE,
            response TEXT NOT NULL,
            created_at DATETIME NOT NULL",
    ];

    $map = $driver === 'sqlite'
        ? ['{PK}' => 'INTEGER PRIMARY KEY AUTOINCREMENT', '{FK}' => 'INTEGER', '{LONGTEXT}' => 'TEXT']
        : ['{PK}' => 'INT UNSIGNED AUTO_INCREMENT PRIMARY KEY', '{FK}' => 'INT UNSIGNED', '{LONGTEXT}' => 'MEDIUMTEXT'];
    $suffix = $driver === 'sqlite' ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

    $sql = [];
    foreach ($tables as $name => $body) {
        $sql[] = "CREATE TABLE IF NOT EXISTS $name (" . strtr($body, $map) . "\n)$suffix";
    }

    $indexes = [
        'idx_products_supplier'   => 'products(supplier_id)',
        'idx_products_category'   => 'products(category_id)',
        'idx_cart_user_center'    => 'cart_items(user_id, center_id)',
        'idx_requests_center'     => 'requests(center_id)',
        'idx_rl_status'           => 'request_lines(status, center_id, supplier_id)',
        'idx_rl_po'               => 'request_lines(purchase_order_id)',
        'idx_po_status'           => 'purchase_orders(status, center_id)',
        'idx_pol_po'              => 'purchase_order_lines(purchase_order_id)',
        'idx_deadlines_date'      => 'deadlines(deadline_at)',
        'idx_products_barcode'    => 'products(barcode)',
        'idx_moves_center_prod'   => 'stock_movements(center_id, product_id)',
        'idx_notif_user'          => 'notifications(user_id, read_at)',
        'idx_sugg_status'         => 'product_suggestions(status, in_cart)',
        'idx_login_email'         => 'login_attempts(email, created_at)',
        'idx_login_ip'            => 'login_attempts(ip, created_at)',
        'idx_mailq_sent'          => 'mail_queue(sent_at, attempts)',
        'idx_price_prod'          => 'price_history(product_id, created_at)',
        'idx_audit_date'          => 'audit_log(created_at)',
        'idx_po_group'            => 'purchase_orders(group_ref)',
        'idx_products_compare'    => 'products(compare_group)',
    ];
    foreach ($indexes as $n => $def) {
        $sql[] = $driver === 'sqlite'
            ? "CREATE INDEX IF NOT EXISTS $n ON $def"
            : "CREATE INDEX $n ON $def";
    }
    return $sql;
}

function schema_install(): void
{
    $driver = db_driver();
    $stmts = schema_statements($driver);
    // 1) tables, 2) colonnes ajoutées par les versions ultérieures, 3) index
    foreach ($stmts as $stmt) {
        if (str_starts_with($stmt, 'CREATE TABLE')) {
            db()->exec($stmt);
        }
    }
    schema_add_columns();
    foreach ($stmts as $stmt) {
        if (str_starts_with($stmt, 'CREATE TABLE')) {
            continue;
        }
        try {
            db()->exec($stmt);
        } catch (PDOException $e) {
            // Index déjà existant sous MySQL : on ignore
            if ($driver === 'mysql' && str_contains($e->getMessage(), 'Duplicate key name')) {
                continue;
            }
            throw $e;
        }
    }
}

/**
 * Colonnes ajoutées après la version 1.0 (installations existantes).
 * Les migrations sont uniquement additives : une version antérieure du code
 * reste compatible avec un schéma plus récent, ce qui sécurise le retour arrière.
 */
function schema_added_columns(): array
{
    return [
        'products' => ['barcode' => 'VARCHAR(64) NULL', 'compare_group' => 'VARCHAR(80) NULL'],
        'users'    => ['notify_email' => 'TINYINT NOT NULL DEFAULT 1', 'notify_prefs' => 'TEXT NULL', 'deleted_at' => 'DATETIME NULL'],
        'deadlines' => ['reminded_at' => 'DATETIME NULL'],
        'suppliers' => ['order_url' => 'VARCHAR(255) NULL', 'order_note' => 'VARCHAR(255) NULL'],
        'centers'  => [
            'legal_name' => 'VARCHAR(200) NULL', 'contact_name' => 'VARCHAR(150) NULL', 'email' => 'VARCHAR(190) NULL',
            'address2' => 'VARCHAR(255) NULL', 'siren' => 'VARCHAR(9) NULL', 'siret' => 'VARCHAR(14) NULL', 'finess' => 'VARCHAR(9) NULL',
            'vat_number' => 'VARCHAR(20) NULL', 'billing_same' => 'TINYINT NOT NULL DEFAULT 1', 'billing_name' => 'VARCHAR(200) NULL',
            'billing_address' => 'VARCHAR(255) NULL', 'billing_city' => 'VARCHAR(120) NULL', 'billing_email' => 'VARCHAR(190) NULL',
            'billing_notes' => 'TEXT NULL',
        ],
        'requests' => ['approval_status' => 'VARCHAR(20) NULL', 'approved_by' => 'INT NULL', 'approved_at' => 'DATETIME NULL', 'approval_note' => 'VARCHAR(255) NULL'],
        'purchase_orders' => [
            'group_ref' => 'VARCHAR(40) NULL', 'late_notified_at' => 'DATETIME NULL', 'sent_to_supplier_at' => 'DATETIME NULL',
            'invoice_number' => 'VARCHAR(80) NULL', 'invoice_date' => 'DATE NULL', 'invoice_amount' => 'DECIMAL(10,2) NULL',
            'invoice_file' => 'VARCHAR(255) NULL', 'invoice_status' => 'VARCHAR(20) NULL',
        ],
    ];
}

function column_exists(string $table, string $column): bool
{
    if (db_driver() === 'sqlite') {
        foreach (all("PRAGMA table_info($table)") as $c) {
            if ($c['name'] === $column) {
                return true;
            }
        }
        return false;
    }
    return (bool)one("SHOW COLUMNS FROM $table LIKE " . db()->quote($column));
}

/** Ajoute les colonnes apparues dans les versions ultérieures (installations existantes). */
function schema_add_columns(): void
{
    foreach (schema_added_columns() as $table => $cols) {
        foreach ($cols as $col => $def) {
            if (!column_exists($table, $col)) {
                db()->exec("ALTER TABLE $table ADD COLUMN $col $def");
            }
        }
    }
}

/** Met le schéma au niveau de la version du code (idempotent). */
function schema_migrate(): void
{
    schema_install();
}
