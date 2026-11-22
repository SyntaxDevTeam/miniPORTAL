CREATE TABLE cooperation_partners (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    website_url VARCHAR(500) NOT NULL,
    description TEXT NOT NULL,
    sort_order INT UNSIGNED NOT NULL DEFAULT 100,
    is_visible TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_cooperation_visible_order (is_visible, sort_order, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
INSERT IGNORE INTO permissions (name, label) VALUES ('cooperation.manage', 'Zarządzanie współpracą');
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT roles.id, permissions.id FROM roles CROSS JOIN permissions
WHERE roles.name IN ('administrator', 'editor', 'maintainer') AND permissions.name = 'cooperation.manage';

CREATE TABLE cooperation_settings (
    id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
    is_enabled TINYINT(1) NOT NULL DEFAULT 1,
    placement VARCHAR(32) NOT NULL DEFAULT 'before_contact'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
INSERT INTO cooperation_settings (id, is_enabled, placement) VALUES (1, 1, 'before_contact');
