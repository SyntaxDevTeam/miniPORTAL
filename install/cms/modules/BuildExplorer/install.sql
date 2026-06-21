CREATE TABLE project_builds (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_id BIGINT UNSIGNED NOT NULL,
    server_type VARCHAR(80) NOT NULL,
    version_label VARCHAR(120) NOT NULL,
    channel ENUM('release', 'snapshot', 'dev', 'wip') NOT NULL DEFAULT 'release',
    build_number VARCHAR(80) NOT NULL DEFAULT '',
    filename VARCHAR(255) NOT NULL,
    storage_key VARCHAR(80) NULL,
    download_url VARCHAR(2048) NULL,
    checksum_sha256 CHAR(64) NULL,
    file_size_bytes BIGINT UNSIGNED NULL,
    changelog TEXT NOT NULL,
    is_published TINYINT(1) NOT NULL DEFAULT 0,
    published_at DATETIME NULL,
    ci_build_id BIGINT UNSIGNED NULL,
    ci_build_time DATETIME NULL,
    commits_json JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_project_builds_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    INDEX idx_project_builds_public (project_id, is_published, channel, published_at),
    INDEX idx_project_builds_channel (channel, is_published),
    UNIQUE KEY uq_project_builds_ci (project_id, channel, server_type, ci_build_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT IGNORE INTO permissions (name, label) VALUES
    ('builds.view', 'Podgląd buildów projektów'),
    ('builds.manage', 'Zarządzanie buildami projektów');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT roles.id, permissions.id FROM roles CROSS JOIN permissions
WHERE roles.name = 'administrator' AND permissions.name LIKE 'builds.%';

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT roles.id, permissions.id FROM roles
JOIN permissions ON permissions.name IN ('builds.view', 'builds.manage')
WHERE roles.name = 'maintainer';

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT roles.id, permissions.id FROM roles
JOIN permissions ON permissions.name = 'builds.view'
WHERE roles.name IN ('auditor', 'support');
