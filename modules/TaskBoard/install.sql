CREATE TABLE task_board_tasks (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    author_id BIGINT UNSIGNED NOT NULL,
    assignee_id BIGINT UNSIGNED NULL,
    project_id BIGINT UNSIGNED NULL,
    wiki_page_id BIGINT UNSIGNED NULL,
    title VARCHAR(180) NOT NULL,
    description TEXT NOT NULL,
    checklist_json MEDIUMTEXT NOT NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'idea',
    visibility VARCHAR(16) NOT NULL DEFAULT 'private',
    public_title VARCHAR(180) NOT NULL DEFAULT '',
    public_summary TEXT NOT NULL,
    auto_close VARCHAR(16) NOT NULL DEFAULT 'manual',
    release_channel VARCHAR(16) NOT NULL DEFAULT 'release',
    release_version VARCHAR(120) NOT NULL DEFAULT '',
    revision INT UNSIGNED NOT NULL DEFAULT 1,
    closed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_task_board_author (author_id, updated_at, id),
    INDEX idx_task_board_assignee (assignee_id, status),
    INDEX idx_task_board_scope (visibility, project_id, status, updated_at),
    INDEX idx_task_board_automation (auto_close, status, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE task_board_comments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    task_id BIGINT UNSIGNED NOT NULL,
    author_id BIGINT UNSIGNED NOT NULL,
    body TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_task_board_comments_task FOREIGN KEY (task_id) REFERENCES task_board_tasks(id) ON DELETE CASCADE,
    INDEX idx_task_board_comments (task_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE task_board_attachments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    task_id BIGINT UNSIGNED NOT NULL,
    author_id BIGINT UNSIGNED NOT NULL,
    media_id BIGINT UNSIGNED NULL,
    name VARCHAR(180) NOT NULL,
    mime_type VARCHAR(32) NOT NULL,
    image_data MEDIUMBLOB NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_task_board_attachments_task FOREIGN KEY (task_id) REFERENCES task_board_tasks(id) ON DELETE CASCADE,
    INDEX idx_task_board_attachments (task_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE task_board_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    task_id BIGINT UNSIGNED NOT NULL,
    actor_id BIGINT UNSIGNED NULL,
    event VARCHAR(120) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_task_board_history_task FOREIGN KEY (task_id) REFERENCES task_board_tasks(id) ON DELETE CASCADE,
    INDEX idx_task_board_history (task_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE task_board_assignees (
    task_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (task_id, user_id),
    CONSTRAINT fk_task_board_assignees_task FOREIGN KEY (task_id) REFERENCES task_board_tasks(id) ON DELETE CASCADE,
    INDEX idx_task_board_assignees_user (user_id, task_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE task_board_notifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    task_id BIGINT UNSIGNED NOT NULL,
    revision INT UNSIGNED NOT NULL,
    status VARCHAR(16) NOT NULL DEFAULT 'pending',
    attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    next_attempt_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    sent_at DATETIME NULL,
    last_status SMALLINT UNSIGNED NULL,
    CONSTRAINT fk_task_board_notifications_task FOREIGN KEY (task_id) REFERENCES task_board_tasks(id) ON DELETE CASCADE,
    UNIQUE KEY uq_task_board_notification (task_id, revision),
    INDEX idx_task_board_notification_pending (status, next_attempt_at, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT IGNORE INTO permissions (name, label) VALUES
('tasks.use', 'Korzystanie z zadań i pomysłów'),
('tasks.manage', 'Zarządzanie wspólnymi zadaniami'),
('tasks.publish', 'Publikacja planów rozwoju projektów');
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT roles.id, permissions.id FROM roles CROSS JOIN permissions
WHERE permissions.name = 'tasks.use';
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT roles.id, permissions.id FROM roles CROSS JOIN permissions
WHERE roles.name IN ('owner', 'administrator', 'maintainer') AND permissions.name IN ('tasks.manage', 'tasks.publish');
