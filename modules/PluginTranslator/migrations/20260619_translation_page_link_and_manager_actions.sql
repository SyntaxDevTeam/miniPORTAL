ALTER TABLE plugin_translation_projects
    ADD COLUMN page_id BIGINT UNSIGNED NULL AFTER slug,
    DROP COLUMN description,
    DROP COLUMN website_url,
    ADD CONSTRAINT fk_plugin_translation_project_page
        FOREIGN KEY (page_id) REFERENCES core_pages(id) ON DELETE SET NULL,
    ADD INDEX idx_plugin_translation_project_page (page_id);
