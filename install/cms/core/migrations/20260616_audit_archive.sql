CREATE TABLE auth_events_archive (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    source_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NULL,
    provider VARCHAR(32) NULL,
    event_type VARCHAR(64) NOT NULL,
    result VARCHAR(32) NOT NULL,
    ip_hash CHAR(64) NULL,
    user_agent VARCHAR(512) NULL,
    created_at DATETIME NOT NULL,
    archived_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_auth_events_archive_source (source_id),
    INDEX idx_auth_events_archive_created (created_at),
    INDEX idx_auth_events_archive_archived (archived_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
