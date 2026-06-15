ALTER TABLE learning_entries
    ADD COLUMN status ENUM('draft', 'ready') NOT NULL DEFAULT 'draft' AFTER note,
    ADD INDEX idx_learning_entries_status_created (status, created_at);
