ALTER TABLE widgets
    MODIFY widget_type ENUM('terminal', 'card', 'uptime') NOT NULL DEFAULT 'card';
