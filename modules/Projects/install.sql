CREATE TABLE projects (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(180) NOT NULL,
    slug VARCHAR(191) NOT NULL,
    summary VARCHAR(500) NOT NULL DEFAULT '',
    lifecycle_status ENUM('planned', 'development', 'released', 'paused') NOT NULL DEFAULT 'planned',
    page_id BIGINT UNSIGNED NULL,
    wiki_project_id BIGINT UNSIGNED NULL,
    sort_order INT UNSIGNED NOT NULL DEFAULT 100,
    is_published TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_projects_page FOREIGN KEY (page_id) REFERENCES core_pages(id) ON DELETE SET NULL,
    CONSTRAINT fk_projects_wiki FOREIGN KEY (wiki_project_id) REFERENCES wiki_projects(id) ON DELETE SET NULL,
    UNIQUE KEY uq_projects_slug (slug),
    INDEX idx_projects_public_order (is_published, sort_order, name),
    INDEX idx_projects_page (page_id),
    INDEX idx_projects_wiki (wiki_project_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT IGNORE INTO permissions (name, label) VALUES
    ('projects.view', 'Podgląd projektów'),
    ('projects.manage', 'Zarządzanie projektami');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT roles.id, permissions.id FROM roles CROSS JOIN permissions
WHERE roles.name = 'administrator' AND permissions.name LIKE 'projects.%';

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT roles.id, permissions.id FROM roles
JOIN permissions ON permissions.name IN ('projects.view', 'projects.manage')
WHERE roles.name = 'editor';
