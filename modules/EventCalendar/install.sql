CREATE TABLE event_calendar_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    entry_kind VARCHAR(12) NOT NULL DEFAULT 'event',
    scheduled_payload MEDIUMTEXT NULL,
    publish_at DATETIME NULL,
    scheduled_by BIGINT UNSIGNED NULL,
    title VARCHAR(180) NOT NULL,
    state VARCHAR(20) NOT NULL DEFAULT 'idea',
    priority VARCHAR(16) NOT NULL DEFAULT 'normal',
    owner_id BIGINT UNSIGNED NULL,
    category VARCHAR(80) NOT NULL DEFAULT '',
    content_format VARCHAR(16) NOT NULL DEFAULT 'plain',
    starts_at DATETIME NULL,
    ends_at DATETIME NULL,
    plan MEDIUMTEXT NOT NULL,
    resources TEXT NOT NULL,
    risks TEXT NOT NULL,
    public_title VARCHAR(180) NOT NULL DEFAULT '',
    summary TEXT NOT NULL,
    description MEDIUMTEXT NOT NULL,
    location VARCHAR(180) NOT NULL DEFAULT '',
    rules TEXT NOT NULL,
    rewards TEXT NOT NULL,
    published_payload MEDIUMTEXT NULL,
    public_start DATETIME NULL,
    public_end DATETIME NULL,
    discord_mode VARCHAR(8) NOT NULL DEFAULT 'now',
    revision INT UNSIGNED NOT NULL DEFAULT 1,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_event_calendar_schedule (publish_at, id),
    INDEX idx_event_calendar_public_end (public_end, public_start, id),
    INDEX idx_event_calendar_public_start (public_start, public_end),
    INDEX idx_event_calendar_state (state, updated_at),
    INDEX idx_event_calendar_owner (owner_id, updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE event_calendar_tasks (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(180) NOT NULL,
    description TEXT NOT NULL,
    assignee_id BIGINT UNSIGNED NULL,
    status VARCHAR(16) NOT NULL DEFAULT 'todo',
    due_at DATETIME NULL,
    CONSTRAINT fk_event_calendar_task FOREIGN KEY (event_id) REFERENCES event_calendar_events(id) ON DELETE CASCADE,
    INDEX idx_event_calendar_tasks (event_id, status),
    INDEX idx_event_calendar_assignee (assignee_id, event_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE event_calendar_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id BIGINT UNSIGNED NOT NULL,
    actor_id BIGINT UNSIGNED NOT NULL,
    action VARCHAR(24) NOT NULL,
    body TEXT NOT NULL,
    created_at DATETIME NOT NULL,
    CONSTRAINT fk_event_calendar_history FOREIGN KEY (event_id) REFERENCES event_calendar_events(id) ON DELETE CASCADE,
    INDEX idx_event_calendar_history (event_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE event_calendar_notifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id BIGINT UNSIGNED NULL,
    task_id BIGINT UNSIGNED NULL,
    channel VARCHAR(12) NOT NULL,
    is_test TINYINT NOT NULL DEFAULT 0,
    source_hash CHAR(64) NOT NULL,
    destination_hash CHAR(64) NOT NULL,
    dedupe_key CHAR(64) NOT NULL UNIQUE,
    status VARCHAR(12) NOT NULL DEFAULT 'pending',
    attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    last_status SMALLINT UNSIGNED NULL,
    next_attempt_at DATETIME NULL,
    sent_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_event_calendar_notification_pending (status, next_attempt_at, id),
    CONSTRAINT fk_event_calendar_notification_event FOREIGN KEY (event_id) REFERENCES event_calendar_events(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
INSERT IGNORE INTO permissions (name,label) VALUES
('events.view','Kalendarz: plany zespołu i postęp własnych zadań'),
('events.manage','Kalendarz: zarządzanie wydarzeniami i zadaniami'),
('events.publish','Kalendarz: publikacja i wycofanie wydarzeń');
INSERT IGNORE INTO role_permissions (role_id,permission_id)
SELECT roles.id,permissions.id FROM roles CROSS JOIN permissions
WHERE roles.name IN ('owner','administrator') AND permissions.name IN ('events.view','events.manage','events.publish');
