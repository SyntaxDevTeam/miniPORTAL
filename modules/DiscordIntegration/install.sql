INSERT IGNORE INTO permissions(name,label) VALUES ('discord.manage','Zarządzanie integracją Discord');
INSERT IGNORE INTO role_permissions(role_id,permission_id)
SELECT roles.id,permissions.id FROM roles JOIN permissions ON permissions.name='discord.manage' WHERE roles.name IN ('owner','administrator');
CREATE TABLE discord_integration_outbox (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 event_key VARCHAR(120) NOT NULL UNIQUE,
 route_key VARCHAR(100) NOT NULL,
 destination VARCHAR(180) NOT NULL,
 payload TEXT NOT NULL,
 status VARCHAR(20) NOT NULL DEFAULT 'pending',
 attempts INT NOT NULL DEFAULT 0,
 last_status INT NOT NULL DEFAULT 0,
 next_attempt_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX discord_outbox_pending(status,next_attempt_at,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
