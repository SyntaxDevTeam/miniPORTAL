CREATE TABLE uptime_monitors (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    monitor_key VARCHAR(64) NOT NULL,
    monitor_uuid CHAR(36) NOT NULL,
    name VARCHAR(160) NOT NULL,
    target_url VARCHAR(500) NOT NULL DEFAULT '',
    monitor_type ENUM('heartbeat', 'http', 'manual') NOT NULL DEFAULT 'heartbeat',
    expected_event VARCHAR(32) NOT NULL DEFAULT 'online',
    expected_status SMALLINT UNSIGNED NOT NULL DEFAULT 200,
    check_interval_minutes INT UNSIGNED NOT NULL DEFAULT 5,
    notification_type ENUM('none', 'discord_webhook') NOT NULL DEFAULT 'none',
    notification_webhook_url VARCHAR(500) NOT NULL DEFAULT '',
    last_status ENUM('up', 'warn', 'down', 'neutral') NOT NULL DEFAULT 'neutral',
    last_event VARCHAR(32) NOT NULL DEFAULT '',
    last_message VARCHAR(220) NOT NULL DEFAULT '',
    last_event_at DATETIME NULL,
    last_checked_at DATETIME NULL,
    notification_sent_at DATETIME NULL,
    sort_order INT UNSIGNED NOT NULL DEFAULT 100,
    is_visible TINYINT(1) NOT NULL DEFAULT 1,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_uptime_monitor_key (monitor_key),
    UNIQUE KEY uq_uptime_monitor_uuid (monitor_uuid),
    INDEX idx_uptime_public (is_visible, is_active, sort_order, name),
    INDEX idx_uptime_status (last_status, last_checked_at),
    INDEX idx_uptime_event (expected_event, last_event_at),
    INDEX idx_uptime_stale (is_active, last_status, last_event_at, check_interval_minutes)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT IGNORE INTO permissions (name, label) VALUES
    ('uptime.manage', 'Zarządzanie monitoringiem Uptime');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT roles.id, permissions.id
FROM roles
JOIN permissions ON permissions.name = 'uptime.manage'
WHERE roles.name IN ('administrator', 'maintainer', 'support');
