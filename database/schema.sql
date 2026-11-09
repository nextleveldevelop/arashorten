-- AraShorten schema (v2.3). Generated from includes/schema.php.
-- Fresh install: the web installer does this for you.
-- Manual install/upgrade: import this file; every statement is safe to re-run.

CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','user') NOT NULL DEFAULT 'user',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_login_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME DEFAULT NULL,
    INDEX idx_users_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS urls (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED DEFAULT NULL,
    short_code VARCHAR(32) NOT NULL UNIQUE,
    destination_url VARCHAR(2048) NOT NULL,
    clicks BIGINT UNSIGNED NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    INDEX idx_urls_user (user_id),
    INDEX idx_urls_created (created_at),
    CONSTRAINT fk_urls_user FOREIGN KEY (user_id)
        REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS url_clicks (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    url_id BIGINT UNSIGNED NOT NULL,
    clicked_at DATETIME NOT NULL,
    INDEX idx_clicks_url_date (url_id, clicked_at),
    INDEX idx_clicks_date (clicked_at),
    CONSTRAINT fk_clicks_url FOREIGN KEY (url_id)
        REFERENCES urls(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rate_limits (
    bucket_key CHAR(64) NOT NULL PRIMARY KEY,
    hits INT UNSIGNED NOT NULL DEFAULT 0,
    window_start DATETIME NOT NULL,
    INDEX idx_rate_window (window_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(100) NOT NULL UNIQUE,
    title VARCHAR(200) NOT NULL,
    content MEDIUMTEXT NOT NULL,
    meta_title VARCHAR(200) DEFAULT NULL,
    meta_description VARCHAR(320) DEFAULT NULL,
    is_published TINYINT(1) NOT NULL DEFAULT 0,
    show_in_footer TINYINT(1) NOT NULL DEFAULT 0,
    noindex TINYINT(1) NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_pages_published (is_published, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contact_messages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(190) NOT NULL,
    subject VARCHAR(150) NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    INDEX idx_contact_read (is_read, created_at),
    INDEX idx_contact_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED DEFAULT NULL,
    action VARCHAR(64) NOT NULL,
    details VARCHAR(500) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL,
    INDEX idx_audit_created (created_at),
    INDEX idx_audit_action (action),
    CONSTRAINT fk_audit_user FOREIGN KEY (user_id)
        REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Upgrade from v2.0/v2.1 (adds role columns to an existing users table).
-- Run each line only if the column does not exist yet; the app also does this automatically.
-- ALTER TABLE users ADD COLUMN role ENUM('admin','user') NOT NULL DEFAULT 'user' AFTER password;
-- ALTER TABLE users ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER role;
-- ALTER TABLE users ADD COLUMN last_login_at DATETIME DEFAULT NULL AFTER is_active;
-- UPDATE users SET role = 'admin' WHERE id = (SELECT id FROM (SELECT MIN(id) AS id FROM users) t);

INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
    ('allow_registration', '1'),
    ('allow_guest_shortening', '1'),
    ('default_timezone', 'UTC'),
    ('seo_robots_index', '1'),
    ('seo_sitemap_include_static', '1'),
    ('blocked_domains', ''),
    ('schema_version', '3');
