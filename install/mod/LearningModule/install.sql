CREATE TABLE learning_entries (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(180) NOT NULL,
    note TEXT NOT NULL,
    status ENUM('draft', 'ready') NOT NULL DEFAULT 'draft',
    author_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_learning_entries_author
        FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_learning_entries_status_created (status, created_at),
    INDEX idx_learning_entries_author_updated (author_id, updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT IGNORE INTO permissions (name, label) VALUES
    ('learning.view', 'Podgląd modułu edukacyjnego'),
    ('learning.manage', 'Zarządzanie modułem edukacyjnym');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT roles.id, permissions.id
FROM roles
JOIN permissions ON permissions.name IN ('learning.view', 'learning.manage')
WHERE roles.name = 'administrator';
