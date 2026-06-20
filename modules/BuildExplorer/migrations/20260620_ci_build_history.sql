ALTER TABLE project_builds
    MODIFY COLUMN build_number VARCHAR(80) NOT NULL DEFAULT '',
    ADD COLUMN ci_build_id BIGINT UNSIGNED NULL AFTER published_at,
    ADD COLUMN ci_build_time DATETIME NULL AFTER ci_build_id,
    ADD COLUMN commits_json JSON NULL AFTER ci_build_time,
    ADD UNIQUE KEY uq_project_builds_ci (project_id, channel, server_type, ci_build_id);
