CREATE TABLE project_builds (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_id BIGINT UNSIGNED NOT NULL,
    server_type VARCHAR(80) NOT NULL,
    version_label VARCHAR(120) NOT NULL,
    channel ENUM('release', 'snapshot', 'dev', 'wip') NOT NULL DEFAULT 'release',
    build_number VARCHAR(80) NOT NULL DEFAULT '',
    filename VARCHAR(255) NOT NULL,
    storage_key VARCHAR(80) NULL,
    download_url VARCHAR(2048) NULL,
    checksum_sha256 CHAR(64) NULL,
    file_size_bytes BIGINT UNSIGNED NULL,
    changelog TEXT NOT NULL,
    is_published TINYINT(1) NOT NULL DEFAULT 0,
    published_at DATETIME NULL,
    ci_build_id BIGINT UNSIGNED NULL,
    ci_build_time DATETIME NULL,
    commits_json JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_project_builds_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    INDEX idx_project_builds_public (project_id, is_published, channel, published_at),
    INDEX idx_project_builds_channel (channel, is_published),
    UNIQUE KEY uq_project_builds_ci (project_id, channel, server_type, ci_build_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE build_releases (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_id BIGINT UNSIGNED NOT NULL,
    version_label VARCHAR(120) NOT NULL,
    channel ENUM('release', 'snapshot', 'dev', 'wip') NOT NULL DEFAULT 'release',
    build_number VARCHAR(80) NOT NULL DEFAULT '',
    changelog TEXT NOT NULL,
    is_published TINYINT(1) NOT NULL DEFAULT 0,
    retention_lock TINYINT(1) NOT NULL DEFAULT 0,
    published_at DATETIME NULL,
    ci_build_id BIGINT UNSIGNED NULL,
    ci_build_time DATETIME NULL,
    commit_sha CHAR(40) NULL,
    branch VARCHAR(255) NULL,
    commits_json JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_build_releases_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    UNIQUE KEY uq_build_releases_ci (project_id, channel, ci_build_id),
    INDEX idx_build_releases_public (project_id, is_published, channel, published_at),
    INDEX idx_build_releases_version (project_id, version_label, channel, published_at),
    INDEX idx_build_releases_retention (channel, retention_lock, project_id, version_label, published_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE build_artifacts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    release_id BIGINT UNSIGNED NOT NULL,
    platform VARCHAR(80) NOT NULL,
    platform_family ENUM('server', 'proxy', 'other') NOT NULL DEFAULT 'server',
    filename VARCHAR(255) NOT NULL,
    storage_key VARCHAR(80) NULL,
    download_url VARCHAR(2048) NULL,
    checksum_sha256 CHAR(64) NULL,
    file_size_bytes BIGINT UNSIGNED NULL,
    java_version SMALLINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_build_artifacts_release FOREIGN KEY (release_id) REFERENCES build_releases(id) ON DELETE CASCADE,
    UNIQUE KEY uq_build_artifacts_platform (release_id, platform),
    INDEX idx_build_artifacts_platform (platform, release_id),
    INDEX idx_build_artifacts_storage (storage_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE build_artifact_game_versions (
    artifact_id BIGINT UNSIGNED NOT NULL,
    game_version VARCHAR(40) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (artifact_id, game_version),
    CONSTRAINT fk_build_artifact_game_versions_artifact FOREIGN KEY (artifact_id) REFERENCES build_artifacts(id) ON DELETE CASCADE,
    INDEX idx_build_artifact_game_versions_version (game_version, artifact_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE build_artifact_compatible_platforms (
    artifact_id BIGINT UNSIGNED NOT NULL,
    platform VARCHAR(80) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (artifact_id, platform),
    CONSTRAINT fk_build_artifact_compatible_platforms_artifact
        FOREIGN KEY (artifact_id) REFERENCES build_artifacts(id) ON DELETE CASCADE,
    INDEX idx_build_artifact_compatible_platforms_platform (platform, artifact_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE build_explorer_project_settings (
    project_id BIGINT UNSIGNED PRIMARY KEY,
    requires_auth TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_build_explorer_project_settings_project
        FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    INDEX idx_build_explorer_project_settings_access (requires_auth)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE build_retention_settings (
    id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
    snapshot_keep INT UNSIGNED NOT NULL DEFAULT 10,
    dev_keep INT UNSIGNED NOT NULL DEFAULT 5,
    wip_keep INT UNSIGNED NOT NULL DEFAULT 3,
    soft_limit_bytes BIGINT UNSIGNED NOT NULL DEFAULT 21474836480,
    hard_limit_bytes BIGINT UNSIGNED NOT NULL DEFAULT 26843545600,
    reserve_bytes BIGINT UNSIGNED NOT NULL DEFAULT 5368709120,
    cleanup_interval_hours SMALLINT UNSIGNED NOT NULL DEFAULT 24,
    orphan_min_age_hours SMALLINT UNSIGNED NOT NULL DEFAULT 24,
    last_cleanup_at DATETIME NULL,
    last_cleanup_reason VARCHAR(80) NULL,
    last_cleanup_releases INT UNSIGNED NOT NULL DEFAULT 0,
    last_cleanup_artifacts INT UNSIGNED NOT NULL DEFAULT 0,
    last_cleanup_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
    last_cleanup_orphans INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT IGNORE INTO build_retention_settings (id) VALUES (1);

INSERT IGNORE INTO permissions (name, label) VALUES
    ('builds.view', 'Podgląd buildów projektów'),
    ('builds.manage', 'Zarządzanie buildami projektów');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT roles.id, permissions.id FROM roles CROSS JOIN permissions
WHERE roles.name = 'administrator' AND permissions.name LIKE 'builds.%';

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT roles.id, permissions.id FROM roles
JOIN permissions ON permissions.name IN ('builds.view', 'builds.manage')
WHERE roles.name = 'maintainer';

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT roles.id, permissions.id FROM roles
JOIN permissions ON permissions.name = 'builds.view'
WHERE roles.name IN ('auditor', 'support');
