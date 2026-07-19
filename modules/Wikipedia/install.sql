CREATE TABLE wiki_projects (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(160) NOT NULL,
    slug VARCHAR(191) NOT NULL,
    summary VARCHAR(500) NOT NULL,
    status ENUM('draft', 'published') NOT NULL DEFAULT 'draft',
    sort_order INT UNSIGNED NOT NULL DEFAULT 100,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_wiki_projects_slug (slug),
    INDEX idx_wiki_projects_status_order (status, sort_order, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE wiki_pages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(180) NOT NULL,
    slug VARCHAR(191) NOT NULL,
    summary VARCHAR(500) NOT NULL,
    content MEDIUMTEXT NOT NULL,
    content_format ENUM('html', 'markdown') NOT NULL DEFAULT 'markdown',
    status ENUM('draft', 'published') NOT NULL DEFAULT 'draft',
    sort_order INT UNSIGNED NOT NULL DEFAULT 100,
    author_id BIGINT UNSIGNED NOT NULL,
    published_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_wiki_pages_project
        FOREIGN KEY (project_id) REFERENCES wiki_projects(id) ON DELETE CASCADE,
    CONSTRAINT fk_wiki_pages_author
        FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE RESTRICT,
    UNIQUE KEY uq_wiki_pages_project_slug (project_id, slug),
    INDEX idx_wiki_pages_project_status_order (project_id, status, sort_order, title),
    INDEX idx_wiki_pages_author_updated (author_id, updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT IGNORE INTO permissions (name, label) VALUES
    ('wikipedia.view', 'Podgląd dokumentacji projektów'),
    ('wikipedia.create', 'Tworzenie dokumentacji projektów'),
    ('wikipedia.edit', 'Edycja dokumentacji projektów'),
    ('wikipedia.delete', 'Usuwanie dokumentacji projektów'),
    ('wikipedia.publish', 'Publikowanie dokumentacji projektów');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT roles.id, permissions.id
FROM roles
CROSS JOIN permissions
WHERE roles.name = 'administrator'
  AND permissions.name LIKE 'wikipedia.%';

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT roles.id, permissions.id
FROM roles
JOIN permissions ON permissions.name IN (
    'wikipedia.view',
    'wikipedia.create',
    'wikipedia.edit',
    'wikipedia.publish'
)
WHERE roles.name = 'editor';

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT roles.id, permissions.id
FROM roles
JOIN permissions ON permissions.name = 'wikipedia.view'
WHERE roles.name = 'auditor';

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT roles.id, permissions.id
FROM roles
JOIN permissions ON permissions.name IN ('wikipedia.view', 'wikipedia.create', 'wikipedia.edit')
WHERE roles.name = 'support';

INSERT INTO wiki_projects (name, slug, summary, status, sort_order) VALUES
    ('miniPORTAL', 'miniportal', 'Dokumentacja wdrożenia, modułów i decyzji architektonicznych miniPORTAL.', 'published', 10);
