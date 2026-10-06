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

        'ai_cache' => "
            id {PK},
            cache_key VARCHAR(64) NOT NULL UNIQUE,
            response TEXT NOT NULL,
            created_at DATETIME NOT NULL",
    ];

    $map = $driver === 'sqlite'
        ? ['{PK}' => 'INTEGER PRIMARY KEY AUTOINCREMENT', '{FK}' => 'INTEGER']
        : ['{PK}' => 'INT UNSIGNED AUTO_INCREMENT PRIMARY KEY', '{FK}' => 'INT UNSIGNED'];
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
    foreach (schema_statements($driver) as $stmt) {
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
