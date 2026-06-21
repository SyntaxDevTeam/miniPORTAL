ALTER TABLE plugin_translation_submissions
    ADD COLUMN target_language CHAR(2) NOT NULL DEFAULT 'EN' AFTER source_filename,
    ADD INDEX idx_plugin_translation_language_status (target_language, status);
