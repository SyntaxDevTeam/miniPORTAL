<?php

namespace core\app;

use PDO;

require_once __DIR__ . '/CrudApp.class.php';

class PanelAccess
{
    private CrudApp $db;
    private bool $schemaReady = false;
    /** @var string[] */
    private array $globalOwnerDiscordIds = [];
    /** @var array<string, bool> */
    private array $rolePermissionCache = [];

    private const PERMISSIONS = [
        'global.dashboard.view' => 'Podglad globalnego panelu.',
        'global.pages.manage' => 'Zarzadzanie stronami CMS.',
        'global.guilds.manage' => 'Zarzadzanie wszystkimi serwerami bota.',
        'global.users.manage' => 'Zarzadzanie uzytkownikami panelu.',
        'global.logs.view' => 'Podglad logow systemowych.',
        'guild.dashboard.view' => 'Podglad panelu serwera.',
        'guild.settings.view' => 'Podglad ustawien bota na serwerze.',
        'guild.settings.update' => 'Edycja ustawien bota na serwerze.',
        'guild.members.view' => 'Podglad uzytkownikow serwera.',
        'guild.logs.view' => 'Podglad logow serwera.',
        'guild.economy.manage' => 'Zarzadzanie ekonomia serwera.',
        'guild.shop.manage' => 'Zarzadzanie sklepem serwera.',
        'guild.member.view' => 'Podglad informacji uzytkownika na serwerze.',
        'user.dashboard.view' => 'Podglad panelu uzytkownika.',
        'user.profile.view' => 'Podglad profilu uzytkownika.',
        'user.profile.update' => 'Edycja profilu uzytkownika.',
    ];

    private const ROLES = [
        'owner' => [
            'name' => 'Developer / wlasciciel',
            'scope' => 'global',
            'permissions' => ['*'],
        ],
        'guild_admin' => [
            'name' => 'Administrator serwera',
            'scope' => 'guild',
            'permissions' => [
                'guild.dashboard.view',
                'guild.settings.view',
                'guild.settings.update',
                'guild.members.view',
                'guild.logs.view',
                'guild.economy.manage',
                'guild.shop.manage',
                'user.dashboard.view',
                'user.profile.view',
                'user.profile.update',
            ],
        ],
        'member' => [
            'name' => 'Uzytkownik serwera',
            'scope' => 'guild',
            'permissions' => [
                'guild.member.view',
                'user.dashboard.view',
                'user.profile.view',
                'user.profile.update',
            ],
        ],
    ];

    private const DEMO_GUILDS = [
        [
            'discord_guild_id' => 'syntaxcraft',
            'name' => 'SyntaxCraft Community',
            'member_count' => 1284,
            'plan' => 'Freemium',
            'status' => 'Demo',
        ],
        [
            'discord_guild_id' => 'syntaxdev',
            'name' => 'SyntaxDevTeam',
            'member_count' => 326,
            'plan' => 'Freemium',
            'status' => 'Demo',
        ],
        [
            'discord_guild_id' => 'sandbox',
            'name' => 'Bot Sandbox',
            'member_count' => 74,
            'plan' => 'Test',
            'status' => 'Demo',
        ],
    ];

    public function __construct(CrudApp $db, array $globalOwnerDiscordIds = [])
    {
        $this->db = $db;
        $this->globalOwnerDiscordIds = array_values(array_filter(array_map('strval', $globalOwnerDiscordIds)));
        $this->ensureSchema();
    }

    public function ensureSchema(): void
    {
        if ($this->schemaReady) {
            return;
        }

        $statements = [
            "CREATE TABLE IF NOT EXISTS panel_guilds (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                discord_guild_id VARCHAR(32) NOT NULL UNIQUE,
                name VARCHAR(120) NOT NULL,
                icon_url VARCHAR(255) NULL,
                member_count INT UNSIGNED NULL,
                plan VARCHAR(40) NOT NULL DEFAULT 'Freemium',
                status VARCHAR(40) NOT NULL DEFAULT 'Demo',
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS panel_user_guilds (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS panel_roles (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                role_key VARCHAR(80) NOT NULL UNIQUE,
                name VARCHAR(120) NOT NULL,
                scope ENUM('global', 'guild') NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS panel_permissions (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                permission_key VARCHAR(120) NOT NULL UNIQUE,
                description VARCHAR(255) NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS panel_role_permissions (
                role_id INT UNSIGNED NOT NULL,
                permission_id INT UNSIGNED NOT NULL,
                PRIMARY KEY (role_id, permission_id),
                CONSTRAINT fk_panel_role_permission_role
                    FOREIGN KEY (role_id) REFERENCES panel_roles(id) ON DELETE CASCADE,
                CONSTRAINT fk_panel_role_permission_permission
                    FOREIGN KEY (permission_id) REFERENCES panel_permissions(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS panel_user_roles (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        ];

        foreach ($statements as $statement) {
            $this->pdo()->exec($statement);
        }

        $this->seedDefaults();
        $this->assignExistingOwners($this->globalOwnerDiscordIds);
        $this->schemaReady = true;
    }

    public function syncUserFromDiscord(array $discordUser, array $discordGuilds = [], array $globalOwnerDiscordIds = []): int
    {
        $discordUserId = (string) ($discordUser['id'] ?? '');

        if ($discordUserId === '') {
            throw new \RuntimeException('Brakuje Discord user ID.');
        }

        $adminData = [
            'discord_user_id' => $discordUserId,
            'username' => (string) ($discordUser['username'] ?? 'discord-user'),
            'global_name' => isset($discordUser['global_name']) ? (string) $discordUser['global_name'] : null,
            'avatar_url' => isset($discordUser['avatar_url']) ? (string) $discordUser['avatar_url'] : null,
            'last_login_at' => date('Y-m-d H:i:s'),
        ];

        $admins = $this->db->read('admins', ['id'], [
            'discord_user_id' => $discordUserId,
            'LIMIT' => 1,
        ]) ?? [];
        $admin = $admins[0] ?? null;

        if (is_array($admin)) {
            $adminId = (int) $admin['id'];
            $this->db->update('admins', $adminData, ['id' => $adminId]);
        } else {
            $adminId = (int) $this->db->create('admins', $adminData);
        }

        if ($adminId <= 0) {
            throw new \RuntimeException('Nie udalo sie utworzyc sesji uzytkownika panelu.');
        }

        $ownerIds = array_values(array_filter(array_map('strval', $globalOwnerDiscordIds)));
        if (in_array($discordUserId, $ownerIds, true)) {
            $this->assignRole($adminId, 'owner');
        }

        $this->syncUserGuilds($adminId, $discordGuilds);

        return $adminId;
    }

    public function contextsForUser(int $adminId): array
    {
        $contexts = [];

        if ($this->userHasGlobalOwner($adminId)) {
            $contexts[] = [
                'key' => 'global',
                'type' => 'global',
                'label' => 'Panel globalny',
                'description' => 'Developer / wlasciciel serwisu',
                'role' => 'owner',
                'guild_id' => null,
            ];
        }

        $adminGuilds = [];
        $memberGuilds = [];
        foreach ($this->guildsForUser($adminId) as $guild) {
            $role = (string) ($guild['access_role'] ?? 'member');
            $context = [
                'key' => 'guild:' . (int) $guild['id'],
                'type' => 'guild',
                'label' => (string) $guild['name'],
                'description' => $this->roleLabel($role),
                'role' => $role,
                'guild_id' => (int) $guild['id'],
            ];

            if ($role === 'member') {
                $memberGuilds[] = $context;
            } else {
                $adminGuilds[] = $context;
            }
        }

        $contexts = array_merge($contexts, $adminGuilds);
        $contexts[] = [
            'key' => 'account',
            'type' => 'account',
            'label' => 'Moje konto',
            'description' => 'Profil i ustawienia uzytkownika',
            'role' => 'member',
            'guild_id' => null,
        ];

        return array_merge($contexts, $memberGuilds);
    }

    public function contextFromKey(int $adminId, ?string $key): array
    {
        $contexts = $this->contextsForUser($adminId);

        if ($key !== null && $key !== '') {
            foreach ($contexts as $context) {
                if ($context['key'] === $key) {
                    return $context;
                }
            }
        }

        return $contexts[0] ?? [
            'key' => 'account',
            'type' => 'account',
            'label' => 'Moje konto',
            'description' => 'Profil i ustawienia uzytkownika',
            'role' => 'member',
            'guild_id' => null,
        ];
    }

    public function modulesForContext(int $adminId, array $context, ?string $previewRole = null): array
    {
        $previewRole = $this->normalizedPreviewRole($adminId, $previewRole);
        $modules = [];

        if ($this->userCan($adminId, 'global.pages.manage', ['type' => 'global'], $previewRole)) {
            $modules[] = [
                'key' => 'pages',
                'label' => 'Strony',
                'badge' => 'Global',
                'description' => 'Dodawanie, edycja, publikacja i kolejnosc podstron.',
                'url' => '/admin/pages.php',
                'context_key' => 'global',
            ];
        }

        $botContext = null;
        if (($context['type'] ?? '') === 'guild' && $this->userCan($adminId, 'guild.settings.view', $context, $previewRole)) {
            $botContext = $context;
        } else {
            foreach ($this->guildsForUser($adminId, $previewRole) as $guild) {
                $candidateContext = [
                    'key' => 'guild:' . (int) $guild['id'],
                    'type' => 'guild',
                    'guild_id' => (int) $guild['id'],
                ];

                if ($this->userCan($adminId, 'guild.settings.view', $candidateContext, $previewRole)) {
                    $botContext = $candidateContext;
                    break;
                }
            }
        }

        if ($botContext !== null) {
            $modules[] = [
                'key' => 'bot',
                'label' => 'Bot',
                'badge' => 'Serwer',
                'description' => 'Konfiguracja bota dla wybranego serwera Discord.',
                'url' => '/admin/bot.php?guild=' . (int) $botContext['guild_id'],
                'context_key' => (string) $botContext['key'],
            ];
        }

        if ($this->userCan($adminId, 'user.profile.view', ['type' => 'account'], $previewRole)) {
            $modules[] = [
                'key' => 'account',
                'label' => 'Moje konto',
                'badge' => 'User',
                'description' => 'Profil Discord, serwery i zakres dostepu.',
                'url' => '/admin/account.php',
                'context_key' => 'account',
            ];
        }

        return $modules;
    }

    public function userCan(int $adminId, string $permissionKey, ?array $context = null, ?string $previewRole = null): bool
    {
        if (!array_key_exists($permissionKey, self::PERMISSIONS)) {
            return false;
        }

        $previewRole = $this->normalizedPreviewRole($adminId, $previewRole);
        if ($previewRole !== null && $previewRole !== 'owner') {
            return $this->previewRoleAllows($adminId, $previewRole, $permissionKey, $context);
        }

        if ($this->userHasGlobalOwner($adminId) && $this->roleAllows('owner', $permissionKey)) {
            return true;
        }

        $contextType = (string) ($context['type'] ?? 'account');

        if ($contextType === 'account') {
            return $this->roleAllows('member', $permissionKey);
        }

        if ($contextType !== 'guild') {
            return false;
        }

        $guildId = (int) ($context['guild_id'] ?? 0);
        if ($guildId <= 0) {
            return false;
        }

        $guild = $this->guildForUser($adminId, $guildId);
        if ($guild === null) {
            return false;
        }

        $role = (string) ($guild['access_role'] ?? 'member');

        return $this->roleAllows($role, $permissionKey);
    }

    public function guildsForUser(int $adminId, ?string $previewRole = null): array
    {
        $previewRole = $this->normalizedPreviewRole($adminId, $previewRole);
        if ($previewRole !== null && $previewRole !== 'owner') {
            $guilds = $this->db->read('panel_guilds', '*', [
                'ORDER' => ['name' => 'ASC'],
            ]) ?? [];

            return array_map(static function (array $guild) use ($previewRole): array {
                $guild['access_role'] = $previewRole;
                $guild['user_is_owner'] = $previewRole === 'guild_admin' ? 1 : 0;
                $guild['user_permissions'] = '0';

                return $guild;
            }, $guilds);
        }

        if ($this->userHasGlobalOwner($adminId)) {
            $guilds = $this->db->read('panel_guilds', '*', [
                'ORDER' => ['name' => 'ASC'],
            ]) ?? [];

            return array_map(function (array $guild): array {
                $guild['access_role'] = 'owner';
                $guild['user_is_owner'] = 1;
                $guild['user_permissions'] = '0';

                return $guild;
            }, $guilds);
        }

        $statement = $this->pdo()->prepare(
            "SELECT DISTINCT
                g.*,
                ug.is_owner AS user_is_owner,
                ug.permissions AS user_permissions
            FROM panel_guilds g
            LEFT JOIN panel_user_guilds ug
                ON ug.discord_guild_id = g.discord_guild_id
                AND ug.admin_id = :admin_id_guilds
            LEFT JOIN panel_user_roles ur
                ON ur.guild_id = g.id
                AND ur.admin_id = :admin_id_roles
            LEFT JOIN panel_roles r
                ON r.id = ur.role_id
            WHERE ug.id IS NOT NULL
                OR r.role_key IN ('guild_admin', 'member')
            ORDER BY g.name ASC"
        );
        $statement->execute([
            'admin_id_guilds' => $adminId,
            'admin_id_roles' => $adminId,
        ]);
        $guilds = $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $available = [];
        foreach ($guilds as $guild) {
            $role = $this->guildAccessRole($adminId, $guild);
            if ($role === null) {
                continue;
            }

            $guild['access_role'] = $role;
            $available[] = $guild;
        }

        return $available;
    }

    public function guildForUser(int $adminId, int $guildId, ?string $previewRole = null): ?array
    {
        foreach ($this->guildsForUser($adminId, $previewRole) as $guild) {
            if ((int) $guild['id'] === $guildId) {
                return $guild;
            }
        }

        return null;
    }

    public function guildById(int $guildId): ?array
    {
        $guilds = $this->db->read('panel_guilds', '*', [
            'id' => $guildId,
            'LIMIT' => 1,
        ]) ?? [];
        $guild = $guilds[0] ?? null;

        return is_array($guild) ? $guild : null;
    }

    public function isGlobalOwner(int $adminId): bool
    {
        return $this->userHasGlobalOwner($adminId);
    }

    public function roleLabel(string $roleKey): string
    {
        return match ($roleKey) {
            'owner' => 'Developer / wlasciciel',
            'guild_admin' => 'Administrator serwera',
            default => 'Uzytkownik serwera',
        };
    }

    public function assignRole(int $adminId, string $roleKey, ?int $guildId = null): void
    {
        $roleId = $this->roleId($roleKey);
        if ($roleId === null) {
            throw new \RuntimeException('Nieznana rola panelu: ' . $roleKey);
        }

        $whereGuild = $guildId === null ? 'guild_id IS NULL' : 'guild_id = :guild_id';
        $params = [
            'admin_id' => $adminId,
            'role_id' => $roleId,
        ];

        if ($guildId !== null) {
            $params['guild_id'] = $guildId;
        }

        $select = $this->pdo()->prepare(
            "SELECT id FROM panel_user_roles
            WHERE admin_id = :admin_id
                AND role_id = :role_id
                AND {$whereGuild}
            LIMIT 1"
        );
        $select->execute($params);

        if ($select->fetchColumn() !== false) {
            return;
        }

        $insert = $this->pdo()->prepare(
            "INSERT INTO panel_user_roles (admin_id, role_id, guild_id)
            VALUES (:admin_id, :role_id, :guild_id)"
        );
        $insert->execute([
            'admin_id' => $adminId,
            'role_id' => $roleId,
            'guild_id' => $guildId,
        ]);
    }

    private function seedDefaults(): void
    {
        $permissionStatement = $this->pdo()->prepare(
            "INSERT INTO panel_permissions (permission_key, description)
            VALUES (:permission_key, :description)
            ON DUPLICATE KEY UPDATE description = VALUES(description)"
        );
        foreach (self::PERMISSIONS as $permissionKey => $description) {
            $permissionStatement->execute([
                'permission_key' => $permissionKey,
                'description' => $description,
            ]);
        }

        $roleStatement = $this->pdo()->prepare(
            "INSERT INTO panel_roles (role_key, name, scope)
            VALUES (:role_key, :name, :scope)
            ON DUPLICATE KEY UPDATE name = VALUES(name), scope = VALUES(scope)"
        );
        foreach (self::ROLES as $roleKey => $role) {
            $roleStatement->execute([
                'role_key' => $roleKey,
                'name' => $role['name'],
                'scope' => $role['scope'],
            ]);

            $roleId = $this->roleId($roleKey);
            if ($roleId === null) {
                continue;
            }

            $permissions = $role['permissions'] === ['*']
                ? array_keys(self::PERMISSIONS)
                : $role['permissions'];

            foreach ($permissions as $permissionKey) {
                $permissionId = $this->permissionId($permissionKey);
                if ($permissionId === null) {
                    continue;
                }

                $relation = $this->pdo()->prepare(
                    "INSERT IGNORE INTO panel_role_permissions (role_id, permission_id)
                    VALUES (:role_id, :permission_id)"
                );
                $relation->execute([
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                ]);
            }
        }

        $guildStatement = $this->pdo()->prepare(
            "INSERT INTO panel_guilds (discord_guild_id, name, member_count, plan, status)
            VALUES (:discord_guild_id, :name, :member_count, :plan, :status)
            ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                member_count = VALUES(member_count),
                plan = VALUES(plan),
                status = VALUES(status)"
        );
        foreach (self::DEMO_GUILDS as $guild) {
            $guildStatement->execute($guild);
        }
    }

    private function syncUserGuilds(int $adminId, array $discordGuilds): void
    {
        $statement = $this->pdo()->prepare(
            "INSERT INTO panel_user_guilds (
                admin_id,
                discord_guild_id,
                name,
                icon_url,
                is_owner,
                permissions,
                last_synced_at
            ) VALUES (
                :admin_id,
                :discord_guild_id,
                :name,
                :icon_url,
                :is_owner,
                :permissions,
                :last_synced_at
            ) ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                icon_url = VALUES(icon_url),
                is_owner = VALUES(is_owner),
                permissions = VALUES(permissions),
                last_synced_at = VALUES(last_synced_at)"
        );

        foreach ($discordGuilds as $guild) {
            $discordGuildId = (string) ($guild['id'] ?? '');
            if ($discordGuildId === '') {
                continue;
            }

            $statement->execute([
                'admin_id' => $adminId,
                'discord_guild_id' => $discordGuildId,
                'name' => (string) ($guild['name'] ?? 'Serwer Discord'),
                'icon_url' => $this->discordGuildIconUrl($guild),
                'is_owner' => !empty($guild['owner']) ? 1 : 0,
                'permissions' => (string) ($guild['permissions'] ?? '0'),
                'last_synced_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    private function assignExistingOwners(array $discordUserIds): void
    {
        if ($discordUserIds === []) {
            return;
        }

        $admins = $this->db->read('admins', ['id'], [
            'discord_user_id' => $discordUserIds,
        ]) ?? [];

        foreach ($admins as $admin) {
            $this->assignRole((int) $admin['id'], 'owner');
        }
    }

    private function userHasGlobalOwner(int $adminId): bool
    {
        return $this->userHasRole($adminId, 'owner');
    }

    private function previewRoleAllows(int $adminId, string $previewRole, string $permissionKey, ?array $context = null): bool
    {
        $contextType = (string) ($context['type'] ?? 'account');

        if ($contextType === 'global') {
            return false;
        }

        if ($contextType === 'account') {
            return $this->roleAllows('member', $permissionKey);
        }

        if ($contextType !== 'guild') {
            return false;
        }

        $guildId = (int) ($context['guild_id'] ?? 0);
        if ($guildId <= 0 || $this->guildForUser($adminId, $guildId, $previewRole) === null) {
            return false;
        }

        return $this->roleAllows($previewRole, $permissionKey);
    }

    private function normalizedPreviewRole(int $adminId, ?string $previewRole): ?string
    {
        if ($previewRole === null || $previewRole === '' || $previewRole === 'owner') {
            return $previewRole === 'owner' ? 'owner' : null;
        }

        if (!in_array($previewRole, ['guild_admin', 'member'], true)) {
            return null;
        }

        return $this->userHasGlobalOwner($adminId) ? $previewRole : null;
    }

    private function userHasRole(int $adminId, string $roleKey, ?int $guildId = null): bool
    {
        $guildSql = $guildId === null ? 'ur.guild_id IS NULL' : 'ur.guild_id = :guild_id';
        $params = [
            'admin_id' => $adminId,
            'role_key' => $roleKey,
        ];

        if ($guildId !== null) {
            $params['guild_id'] = $guildId;
        }

        $statement = $this->pdo()->prepare(
            "SELECT COUNT(*)
            FROM panel_user_roles ur
            INNER JOIN panel_roles r ON r.id = ur.role_id
            WHERE ur.admin_id = :admin_id
                AND r.role_key = :role_key
                AND {$guildSql}"
        );
        $statement->execute($params);

        return (int) $statement->fetchColumn() > 0;
    }

    private function guildAccessRole(int $adminId, array $guild): ?string
    {
        $guildId = (int) ($guild['id'] ?? 0);
        if ($guildId <= 0) {
            return null;
        }

        if ($this->userHasGlobalOwner($adminId)) {
            return 'owner';
        }

        if ($this->userHasRole($adminId, 'guild_admin', $guildId)) {
            return 'guild_admin';
        }

        if ($this->userHasRole($adminId, 'member', $guildId)) {
            return 'member';
        }

        if ((int) ($guild['user_is_owner'] ?? 0) === 1) {
            return 'guild_admin';
        }

        $permissions = (string) ($guild['user_permissions'] ?? '');
        if ($permissions !== '' && $this->discordPermissionsAllowGuildManage($permissions)) {
            return 'guild_admin';
        }

        if (array_key_exists('user_permissions', $guild) && $guild['user_permissions'] !== null) {
            return 'member';
        }

        return null;
    }

    private function roleAllows(string $roleKey, string $permissionKey): bool
    {
        $cacheKey = $roleKey . ':' . $permissionKey;
        if (array_key_exists($cacheKey, $this->rolePermissionCache)) {
            return $this->rolePermissionCache[$cacheKey];
        }

        $statement = $this->pdo()->prepare(
            "SELECT COUNT(*)
            FROM panel_role_permissions rp
            INNER JOIN panel_roles r ON r.id = rp.role_id
            INNER JOIN panel_permissions p ON p.id = rp.permission_id
            WHERE r.role_key = :role_key
                AND p.permission_key = :permission_key"
        );
        $statement->execute([
            'role_key' => $roleKey,
            'permission_key' => $permissionKey,
        ]);

        $allowed = (int) $statement->fetchColumn() > 0;
        $this->rolePermissionCache[$cacheKey] = $allowed;

        return $allowed;
    }

    private function roleId(string $roleKey): ?int
    {
        $statement = $this->pdo()->prepare(
            "SELECT id FROM panel_roles WHERE role_key = :role_key LIMIT 1"
        );
        $statement->execute(['role_key' => $roleKey]);
        $id = $statement->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    private function permissionId(string $permissionKey): ?int
    {
        $statement = $this->pdo()->prepare(
            "SELECT id FROM panel_permissions WHERE permission_key = :permission_key LIMIT 1"
        );
        $statement->execute(['permission_key' => $permissionKey]);
        $id = $statement->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    private function discordGuildIconUrl(array $guild): ?string
    {
        if (empty($guild['id']) || empty($guild['icon'])) {
            return null;
        }

        $extension = str_starts_with((string) $guild['icon'], 'a_') ? 'gif' : 'png';

        return sprintf(
            'https://cdn.discordapp.com/icons/%s/%s.%s',
            rawurlencode((string) $guild['id']),
            rawurlencode((string) $guild['icon']),
            $extension
        );
    }

    private function discordPermissionsAllowGuildManage(string $permissions): bool
    {
        $lowBits = $this->decimalStringModulo($permissions, 64);

        return ($lowBits & 8) === 8 || ($lowBits & 32) === 32;
    }

    private function decimalStringModulo(string $value, int $modulo): int
    {
        $result = 0;
        $digits = preg_replace('/\D/', '', $value) ?? '';

        for ($index = 0, $length = strlen($digits); $index < $length; $index++) {
            $result = (($result * 10) + (int) $digits[$index]) % $modulo;
        }

        return $result;
    }

    private function pdo(): PDO
    {
        return $this->db->connection()->pdo;
    }
}
