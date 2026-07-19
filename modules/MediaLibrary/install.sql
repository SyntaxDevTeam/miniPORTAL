CREATE TABLE media_assets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(180) NOT NULL,
    slug VARCHAR(191) NOT NULL,
    category ENUM('project_icon', 'logo', 'screenshot', 'presentation', 'content', 'other') NOT NULL DEFAULT 'other',
    alt_text VARCHAR(255) NOT NULL DEFAULT '',
    original_name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(255) NOT NULL,
    public_path VARCHAR(500) NOT NULL,
    mime_type VARCHAR(120) NOT NULL,
    file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
    width INT UNSIGNED NULL,
    height INT UNSIGNED NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_media_assets_slug (slug),
    UNIQUE KEY uq_media_assets_stored_name (stored_name),
    INDEX idx_media_assets_category (category, title),
    INDEX idx_media_assets_created (created_at),
    CONSTRAINT fk_media_assets_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE media_optimization_usage (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    provider VARCHAR(32) NOT NULL,
    period_month CHAR(7) NOT NULL,
    used_count INT UNSIGNED NOT NULL DEFAULT 0,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_media_optimization_provider_month (provider, period_month)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (name, label) VALUES
    ('media.view', 'Podgląd biblioteki grafik'),
    ('media.manage', 'Zarządzanie biblioteką grafik');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT roles.id, permissions.id FROM roles CROSS JOIN permissions
WHERE roles.name = 'administrator' AND permissions.name LIKE 'media.%';

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT roles.id, permissions.id FROM roles
JOIN permissions ON permissions.name IN ('media.view', 'media.manage')
WHERE roles.name IN ('maintainer', 'editor');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT roles.id, permissions.id FROM roles
JOIN permissions ON permissions.name = 'media.view'
WHERE roles.name IN ('auditor', 'support');
