CREATE TABLE plugin_translation_projects (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(160) NOT NULL,
    slug VARCHAR(160) NOT NULL UNIQUE,
    description VARCHAR(500) NOT NULL DEFAULT '',
    website_url VARCHAR(500) NOT NULL DEFAULT '',
    status ENUM('active', 'hidden') NOT NULL DEFAULT 'active',
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_plugin_translation_project_creator
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_plugin_translation_project_status_name (status, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO plugin_translation_projects (name, slug, description, status)
VALUES ('Nieprzypisane', 'nieprzypisane', 'Historyczne tłumaczenia bez przypisanego pluginu.', 'hidden');

ALTER TABLE plugin_translation_submissions
    ADD COLUMN project_id BIGINT UNSIGNED NULL AFTER id,
    ADD COLUMN plugin_version VARCHAR(40) NOT NULL DEFAULT '' AFTER source_filename,
    ADD COLUMN submission_kind ENUM('editor', 'completed_upload') NOT NULL DEFAULT 'editor' AFTER plugin_version;

UPDATE plugin_translation_submissions
SET project_id = (SELECT id FROM plugin_translation_projects WHERE slug = 'nieprzypisane' LIMIT 1)
WHERE project_id IS NULL;

ALTER TABLE plugin_translation_submissions
    MODIFY COLUMN project_id BIGINT UNSIGNED NOT NULL,
    ADD CONSTRAINT fk_plugin_translation_project
        FOREIGN KEY (project_id) REFERENCES plugin_translation_projects(id) ON DELETE RESTRICT,
    ADD INDEX idx_plugin_translation_project_language (project_id, target_language, status);
