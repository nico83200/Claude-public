-- Schéma MySQL / MariaDB (Hostinger). Converti automatiquement pour SQLite en dev.
-- Montants stockés en centimes (INT). Dates stockées en heure de Paris.

CREATE TABLE admins (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(190) NOT NULL UNIQUE,
    name VARCHAR(120) NOT NULL DEFAULT '',
    password_hash VARCHAR(255) NOT NULL,
    reset_token_hash VARCHAR(64) NULL,
    reset_expires_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    last_login_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE login_attempts (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    ip VARCHAR(45) NOT NULL,
    created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE INDEX idx_login_attempts_ip ON login_attempts (ip, created_at);

CREATE TABLE customers (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(190) NOT NULL UNIQUE,
    name VARCHAR(120) NOT NULL DEFAULT '',
    password_hash VARCHAR(255) NOT NULL,
    email_verified_at DATETIME NULL,
    token_hash VARCHAR(64) NULL,
    token_expires_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    last_login_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE INDEX idx_customers_token ON customers (token_hash);

CREATE TABLE wishlist_items (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    customer_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    UNIQUE (customer_id, product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE journal_posts (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(190) NOT NULL UNIQUE,
    title VARCHAR(190) NOT NULL,
    tag VARCHAR(60) NULL,
    excerpt VARCHAR(400) NULL,
    content MEDIUMTEXT NULL,
    cover_image VARCHAR(500) NULL,
    product_ids TEXT NULL,
    meta_description VARCHAR(300) NULL,
    published TINYINT(1) NOT NULL DEFAULT 0,
    published_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE INDEX idx_journal_published ON journal_posts (published, published_at);

CREATE TABLE settings (
    name VARCHAR(100) NOT NULL PRIMARY KEY,
    value TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE categories (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(140) NOT NULL UNIQUE,
    description TEXT NULL,
    image VARCHAR(255) NULL,
    position INT NOT NULL DEFAULT 0,
    active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE products (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    category_id INT UNSIGNED NULL,
    name VARCHAR(190) NOT NULL,
    slug VARCHAR(200) NOT NULL UNIQUE,
    short_description VARCHAR(300) NULL,
    description TEXT NULL,
    price INT NOT NULL DEFAULT 0,
    compare_at_price INT NULL,
    images TEXT NULL,
    featured TINYINT(1) NOT NULL DEFAULT 0,
    active TINYINT(1) NOT NULL DEFAULT 1,
    personalizable TINYINT(1) NOT NULL DEFAULT 0,
    audience VARCHAR(10) NULL,
    personalization TEXT NULL,
    design_file VARCHAR(255) NULL,
    print_placement VARCHAR(40) NOT NULL DEFAULT 'front',
    printful_sync_product_id BIGINT NULL,
    seo_title VARCHAR(190) NULL,
    seo_description VARCHAR(300) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE INDEX idx_products_category ON products (category_id, active);

CREATE TABLE product_variants (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED NOT NULL,
    name VARCHAR(190) NOT NULL,
    color VARCHAR(80) NULL,
    color_hex VARCHAR(9) NULL,
    size VARCHAR(40) NULL,
    sku VARCHAR(100) NULL,
    price INT NULL,
    printful_variant_id BIGINT NULL,
    printful_sync_variant_id BIGINT NULL,
    image VARCHAR(500) NULL,
    position INT NOT NULL DEFAULT 0,
    active TINYINT(1) NOT NULL DEFAULT 1,
    FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE INDEX idx_variants_product ON product_variants (product_id, active);

CREATE TABLE orders (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    number VARCHAR(40) NOT NULL UNIQUE,
    access_token VARCHAR(64) NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'pending',
    fulfillment_status VARCHAR(30) NOT NULL DEFAULT 'none',
    fulfillment_attempts INT NOT NULL DEFAULT 0,
    last_error TEXT NULL,
    email VARCHAR(190) NULL,
    customer_name VARCHAR(190) NULL,
    phone VARCHAR(40) NULL,
    shipping_address TEXT NULL,
    shipping_method VARCHAR(30) NOT NULL DEFAULT 'STANDARD',
    subtotal INT NOT NULL DEFAULT 0,
    discount INT NOT NULL DEFAULT 0,
    shipping INT NOT NULL DEFAULT 0,
    tax INT NOT NULL DEFAULT 0,
    total INT NOT NULL DEFAULT 0,
    currency VARCHAR(3) NOT NULL DEFAULT 'EUR',
    stripe_session_id VARCHAR(255) NULL,
    stripe_payment_intent VARCHAR(255) NULL,
    printful_order_id BIGINT NULL,
    carrier VARCHAR(100) NULL,
    tracking_number VARCHAR(190) NULL,
    tracking_url VARCHAR(500) NULL,
    newsletter_optin TINYINT(1) NOT NULL DEFAULT 0,
    customer_id INT UNSIGNED NULL,
    is_gift TINYINT(1) NOT NULL DEFAULT 0,
    gift_to VARCHAR(100) NULL,
    gift_message VARCHAR(200) NULL,
    recovery_sent_at DATETIME NULL,
    review_requested_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    paid_at DATETIME NULL,
    shipped_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE INDEX idx_orders_status ON orders (status, fulfillment_status);
CREATE INDEX idx_orders_session ON orders (stripe_session_id);
CREATE INDEX idx_orders_email ON orders (email);
CREATE INDEX idx_orders_customer ON orders (customer_id);

CREATE TABLE order_items (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NULL,
    variant_id INT UNSIGNED NULL,
    product_name VARCHAR(190) NOT NULL,
    variant_name VARCHAR(190) NULL,
    sku VARCHAR(100) NULL,
    image VARCHAR(500) NULL,
    unit_price INT NOT NULL,
    quantity INT NOT NULL,
    personalization_text VARCHAR(100) NULL,
    personalization_mode VARCHAR(20) NULL,
    photo_file VARCHAR(190) NULL,
    personalization_year VARCHAR(10) NULL,
    ai_portrait CHAR(32) NULL,
    print_file_url VARCHAR(500) NULL,
    FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE INDEX idx_order_items_order ON order_items (order_id);

-- Portraits redessinés par IA d'après la photo d'une cliente (voir App\Services\AiPortrait)
CREATE TABLE ai_portraits (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    token CHAR(32) NOT NULL UNIQUE,
    session_hash CHAR(40) NOT NULL,
    ip_hash CHAR(40) NOT NULL,
    provider VARCHAR(20) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'queued',
    photo_file VARCHAR(190) NOT NULL,
    result_file VARCHAR(190) NULL,
    prediction_id VARCHAR(120) NULL,
    master_file VARCHAR(190) NULL,
    master_prediction_id VARCHAR(120) NULL,
    style VARCHAR(20) NULL,
    marks VARCHAR(200) NULL,
    step VARCHAR(10) NULL,
    photo2_file VARCHAR(190) NULL,
    view VARCHAR(10) NULL,
    error VARCHAR(500) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE INDEX idx_ai_portraits_session ON ai_portraits (session_hash, created_at);
CREATE INDEX idx_ai_portraits_ip ON ai_portraits (ip_hash, created_at);
CREATE INDEX idx_ai_portraits_created ON ai_portraits (created_at);

CREATE TABLE order_events (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    type VARCHAR(40) NOT NULL,
    message TEXT NOT NULL,
    created_at DATETIME NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE INDEX idx_order_events_order ON order_events (order_id);

CREATE TABLE webhook_events (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    provider VARCHAR(20) NOT NULL,
    event_id VARCHAR(190) NOT NULL,
    type VARCHAR(80) NOT NULL,
    created_at DATETIME NOT NULL,
    UNIQUE (provider, event_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE pages (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(140) NOT NULL UNIQUE,
    title VARCHAR(190) NOT NULL,
    content MEDIUMTEXT NULL,
    meta_description VARCHAR(300) NULL,
    in_footer TINYINT(1) NOT NULL DEFAULT 1,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE newsletter_subscribers (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(190) NOT NULL UNIQUE,
    source VARCHAR(40) NOT NULL DEFAULT 'footer',
    ip VARCHAR(45) NULL,
    synced TINYINT(1) NOT NULL DEFAULT 0,
    welcome_code VARCHAR(40) NULL,
    created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE contact_messages (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(190) NOT NULL,
    email VARCHAR(190) NOT NULL,
    subject VARCHAR(190) NULL,
    message TEXT NOT NULL,
    order_number VARCHAR(40) NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE reviews (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED NOT NULL,
    order_id INT UNSIGNED NULL,
    author_name VARCHAR(80) NOT NULL,
    rating TINYINT NOT NULL,
    title VARCHAR(120) NULL,
    body TEXT NULL,
    verified TINYINT(1) NOT NULL DEFAULT 1,
    photo VARCHAR(190) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    created_at DATETIME NOT NULL,
    FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE INDEX idx_reviews_product ON reviews (product_id, status);
CREATE INDEX idx_reviews_order ON reviews (order_id);
