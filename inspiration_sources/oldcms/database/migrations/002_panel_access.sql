CREATE TABLE IF NOT EXISTS panel_guilds (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    discord_guild_id VARCHAR(32) NOT NULL UNIQUE,
    name VARCHAR(120) NOT NULL,
    icon_url VARCHAR(255) NULL,
    member_count INT UNSIGNED NULL,
    plan VARCHAR(40) NOT NULL DEFAULT 'Freemium',
    status VARCHAR(40) NOT NULL DEFAULT 'Demo',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS panel_user_guilds (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    admin_id INT UNSIGNED NOT NULL,
    discord_guild_id VARCHAR(32) NOT NULL,
    name VARCHAR(120) NOT NULL,
    icon_url VARCHAR(255) NULL,
    is_owner TINYINT(1) NOT NULL DEFAULT 0,
    permissions VARCHAR(40) NOT NULL DEFAULT '0',
    last_synced_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_panel_user_guild (admin_id, discord_guild_id),
    KEY idx_panel_user_guild_discord (discord_guild_id),
    CONSTRAINT fk_panel_user_guild_admin
        FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS panel_roles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_key VARCHAR(80) NOT NULL UNIQUE,
    name VARCHAR(120) NOT NULL,
    scope ENUM('global', 'guild') NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS panel_permissions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    permission_key VARCHAR(120) NOT NULL UNIQUE,
    description VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS panel_role_permissions (
    role_id INT UNSIGNED NOT NULL,
    permission_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    CONSTRAINT fk_panel_role_permission_role
        FOREIGN KEY (role_id) REFERENCES panel_roles(id) ON DELETE CASCADE,
    CONSTRAINT fk_panel_role_permission_permission
        FOREIGN KEY (permission_id) REFERENCES panel_permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS panel_user_roles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    admin_id INT UNSIGNED NOT NULL,
    role_id INT UNSIGNED NOT NULL,
    guild_id INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_panel_user_role_admin (admin_id),
    KEY idx_panel_user_role_guild (guild_id),
    CONSTRAINT fk_panel_user_role_admin
        FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE,
    CONSTRAINT fk_panel_user_role_role
        FOREIGN KEY (role_id) REFERENCES panel_roles(id) ON DELETE CASCADE,
    CONSTRAINT fk_panel_user_role_guild
        FOREIGN KEY (guild_id) REFERENCES panel_guilds(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO panel_permissions (permission_key, description) VALUES
('global.dashboard.view', 'Podglad globalnego panelu.'),
('global.pages.manage', 'Zarzadzanie stronami CMS.'),
('global.guilds.manage', 'Zarzadzanie wszystkimi serwerami bota.'),
('global.users.manage', 'Zarzadzanie uzytkownikami panelu.'),
('global.logs.view', 'Podglad logow systemowych.'),
('guild.dashboard.view', 'Podglad panelu serwera.'),
('guild.settings.view', 'Podglad ustawien bota na serwerze.'),
('guild.settings.update', 'Edycja ustawien bota na serwerze.'),
('guild.members.view', 'Podglad uzytkownikow serwera.'),
('guild.logs.view', 'Podglad logow serwera.'),
('guild.economy.manage', 'Zarzadzanie ekonomia serwera.'),
('guild.shop.manage', 'Zarzadzanie sklepem serwera.'),
('guild.member.view', 'Podglad informacji uzytkownika na serwerze.'),
('user.dashboard.view', 'Podglad panelu uzytkownika.'),
('user.profile.view', 'Podglad profilu uzytkownika.'),
('user.profile.update', 'Edycja profilu uzytkownika.')
ON DUPLICATE KEY UPDATE description = VALUES(description);

INSERT INTO panel_roles (role_key, name, scope) VALUES
('owner', 'Developer / wlasciciel', 'global'),
('guild_admin', 'Administrator serwera', 'guild'),
('member', 'Uzytkownik serwera', 'guild')
ON DUPLICATE KEY UPDATE name = VALUES(name), scope = VALUES(scope);

INSERT IGNORE INTO panel_role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM panel_roles r
JOIN panel_permissions p
WHERE r.role_key = 'owner';

INSERT IGNORE INTO panel_role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM panel_roles r
JOIN panel_permissions p ON p.permission_key IN (
    'guild.dashboard.view',
    'guild.settings.view',
    'guild.settings.update',
    'guild.members.view',
    'guild.logs.view',
    'guild.economy.manage',
    'guild.shop.manage',
    'user.dashboard.view',
    'user.profile.view',
    'user.profile.update'
)
WHERE r.role_key = 'guild_admin';

INSERT IGNORE INTO panel_role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM panel_roles r
JOIN panel_permissions p ON p.permission_key IN (
    'guild.member.view',
    'user.dashboard.view',
    'user.profile.view',
    'user.profile.update'
)
WHERE r.role_key = 'member';

INSERT INTO panel_guilds (discord_guild_id, name, member_count, plan, status) VALUES
('syntaxcraft', 'SyntaxCraft Community', 1284, 'Freemium', 'Demo'),
('syntaxdev', 'SyntaxDevTeam', 326, 'Freemium', 'Demo'),
('sandbox', 'Bot Sandbox', 74, 'Test', 'Demo')
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    member_count = VALUES(member_count),
    plan = VALUES(plan),
    status = VALUES(status);
