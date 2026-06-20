ALTER TABLE project_builds
    ADD COLUMN server_type VARCHAR(80) NOT NULL DEFAULT 'Server' AFTER project_id,
    ADD COLUMN build_number VARCHAR(80) NOT NULL DEFAULT '1' AFTER channel,
    ADD COLUMN storage_key VARCHAR(80) NULL AFTER filename,
    MODIFY COLUMN download_url VARCHAR(2048) NULL;
