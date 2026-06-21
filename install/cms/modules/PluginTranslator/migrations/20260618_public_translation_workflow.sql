CREATE TABLE plugin_translation_submissions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    author_name VARCHAR(160) NOT NULL,
    author_email VARCHAR(190) NOT NULL,
    title VARCHAR(180) NOT NULL,
    source_filename VARCHAR(190) NOT NULL,
    source_yaml MEDIUMTEXT NOT NULL,
    translations_json MEDIUMTEXT NOT NULL,
    output_yaml MEDIUMTEXT NOT NULL,
    total_items INT UNSIGNED NOT NULL DEFAULT 0,
    translated_items INT UNSIGNED NOT NULL DEFAULT 0,
    progress_percent TINYINT UNSIGNED NOT NULL DEFAULT 0,
    status ENUM('draft', 'ready_for_review', 'approved', 'rejected') NOT NULL DEFAULT 'draft',
    reviewer_id BIGINT UNSIGNED NULL,
    review_note VARCHAR(500) NOT NULL DEFAULT '',
    reviewed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_plugin_translation_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_plugin_translation_reviewer
        FOREIGN KEY (reviewer_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_plugin_translation_status_updated (status, updated_at),
    INDEX idx_plugin_translation_user_updated (user_id, updated_at),
    INDEX idx_plugin_translation_reviewed (reviewed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT IGNORE INTO permissions (name, label) VALUES
    ('plugin_translator.review', 'Zatwierdzanie tłumaczeń YAML pluginów');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT roles.id, permissions.id
FROM roles
JOIN permissions ON permissions.name = 'plugin_translator.review'
WHERE roles.name = 'administrator';
