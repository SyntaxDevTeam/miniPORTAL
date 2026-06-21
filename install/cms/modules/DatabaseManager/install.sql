CREATE TABLE database_manager_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    operation VARCHAR(40) NOT NULL,
    target_table VARCHAR(191) NULL,
    sql_preview VARCHAR(500) NULL,
    result VARCHAR(40) NOT NULL,
    rows_count INT UNSIGNED NULL,
    message VARCHAR(500) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_database_manager_history_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_database_manager_history_created (created_at),
    INDEX idx_database_manager_history_operation (operation, result),
    INDEX idx_database_manager_history_user (user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT IGNORE INTO permissions (name, label) VALUES
    ('database.manage', 'Zarządzanie bazą danych w Managerze SQL');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT roles.id, permissions.id
FROM roles
JOIN permissions ON permissions.name = 'database.manage'
WHERE roles.name = 'administrator';
