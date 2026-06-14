CREATE TABLE modules_config (
    module_id VARCHAR(64) PRIMARY KEY,
    version VARCHAR(32) NOT NULL,
    status ENUM('discovered', 'active', 'disabled') NOT NULL DEFAULT 'discovered',
    is_protected TINYINT(1) NOT NULL DEFAULT 0,
    installed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_modules_config_status (status, module_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE module_migrations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    module_id VARCHAR(64) NOT NULL,
    migration VARCHAR(191) NOT NULL,
    checksum CHAR(64) NOT NULL,
    executed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_module_migrations_module
        FOREIGN KEY (module_id) REFERENCES modules_config(module_id) ON DELETE CASCADE,
    UNIQUE KEY uq_module_migrations_name (module_id, migration),
    INDEX idx_module_migrations_executed (module_id, executed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO modules_config
    (module_id, version, status, is_protected, installed_at)
VALUES
    ('core_auth', '1.0.0', 'active', 1, CURRENT_TIMESTAMP),
    ('core_pages', '1.0.0', 'active', 1, CURRENT_TIMESTAMP),
    ('articles', '1.0.0', 'active', 0, CURRENT_TIMESTAMP),
    ('system_admin', '1.0.0', 'active', 1, CURRENT_TIMESTAMP);
