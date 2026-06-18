CREATE TABLE team_members (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    slug VARCHAR(191) NOT NULL,
    public_name VARCHAR(160) NOT NULL,
    role_label VARCHAR(160) NOT NULL,
    bio MEDIUMTEXT NOT NULL,
    profile_url VARCHAR(500) NOT NULL DEFAULT '',
    sort_order INT UNSIGNED NOT NULL DEFAULT 100,
    is_visible TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_team_members_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uq_team_members_user (user_id),
    UNIQUE KEY uq_team_members_slug (slug),
    INDEX idx_team_members_visible_order (is_visible, sort_order, public_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT IGNORE INTO permissions (name, label) VALUES
    ('team.manage', 'Zarządzanie publiczną listą zespołu');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT roles.id, permissions.id
FROM roles
JOIN permissions ON permissions.name = 'team.manage'
WHERE roles.name = 'administrator';
