<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\CoreAuth;

use PDO;
use RuntimeException;
use SyntaxDevTeam\Cms\Database\CrudApp;

final class UserAdministrationRepository
{
    public function __construct(
        private readonly CrudApp $database,
    ) {
    }

    /**
     * @return list<UserAdminRecord>
     */
    public function all(): array
    {
        $statement = $this->database->query(
            'SELECT id, display_name, email, status, last_login_at, created_at '
            . 'FROM users ORDER BY created_at DESC, id DESC'
        );
        if ($statement === null) {
            throw new RuntimeException('Nie można pobrać użytkowników.');
        }
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        $userIds = array_map(static fn (array $row): int => (int) $row['id'], $rows);
        $rolesByUser = $this->valuesByUser(
            'SELECT user_roles.user_id, roles.name AS value FROM user_roles '
            . 'JOIN roles ON roles.id = user_roles.role_id '
            . 'WHERE user_roles.user_id IN (%s) ORDER BY user_roles.user_id, '
            . $this->roleOrderSql() . ', roles.id',
            $userIds
        );
        $identitiesByUser = $this->valuesByUser(
            'SELECT user_id, provider AS value FROM user_identities '
            . 'WHERE user_id IN (%s) ORDER BY user_id, provider',
            $userIds
        );

        return array_map(
            fn (array $row): UserAdminRecord => new UserAdminRecord(
                (int) $row['id'],
                (string) $row['display_name'],
                $row['email'] !== null ? (string) $row['email'] : null,
                (string) $row['status'],
                $rolesByUser[(int) $row['id']] ?? [],
                $identitiesByUser[(int) $row['id']] ?? [],
                $row['last_login_at'] !== null ? (string) $row['last_login_at'] : null,
                (string) $row['created_at'],
            ),
            $rows
        );
    }

    /**
     * @return array<string, string>
     */
    public function roles(): array
    {
        $rows = $this->database->read(
            'roles',
            ['name', 'label'],
            ['ORDER' => ['id' => 'ASC']]
        ) ?? [];
        $roles = [];
        foreach ($rows as $row) {
            $roles[(string) $row['name']] = (string) $row['label'];
        }
        uksort($roles, fn (string $left, string $right): int => [
            $this->rolePriority($left), $left,
        ] <=> [
            $this->rolePriority($right), $right,
        ]);

        return $roles;
    }

    /**
     * @return array<string, string>
     */
    public function permissions(): array
    {
        $rows = $this->database->read(
            'permissions',
            ['name', 'label'],
            ['ORDER' => ['name' => 'ASC']]
        ) ?? [];
        $permissions = [];
        foreach ($rows as $row) {
            $permissions[(string) $row['name']] = (string) $row['label'];
        }

        return $permissions;
    }

    /**
     * @return list<RoleAdminRecord>
     */
    public function roleRecords(): array
    {
        $statement = $this->database->query(
            'SELECT roles.id, roles.name, roles.label, roles.is_system, '
            . 'COUNT(DISTINCT user_roles.user_id) AS users_count '
            . 'FROM roles LEFT JOIN user_roles ON user_roles.role_id = roles.id '
            . 'GROUP BY roles.id, roles.name, roles.label, roles.is_system '
            . 'ORDER BY ' . $this->roleOrderSql() . ', roles.is_system DESC, roles.id ASC'
        );
        if ($statement === null) {
            throw new RuntimeException('Nie można pobrać ról.');
        }

        return array_map(
            fn (array $row): RoleAdminRecord => new RoleAdminRecord(
                (int) $row['id'],
                (string) $row['name'],
                (string) $row['label'],
                (bool) $row['is_system'],
                $this->rolePermissions((int) $row['id']),
                (int) $row['users_count'],
            ),
            $statement->fetchAll(PDO::FETCH_ASSOC)
        );
    }

    public function findRole(string $name): ?RoleAdminRecord
    {
        foreach ($this->roleRecords() as $role) {
            if ($role->name === $name) {
                return $role;
            }
        }

        return null;
    }

    /**
     * @param list<string> $roles
     */
    public function createAccount(
        string $displayName,
        ?string $email,
        string $status,
        array $roles,
        string $provider = '',
        string $providerSubject = '',
        ?int $actorId = null,
    ): int {
        $displayName = trim($displayName);
        $email = $email !== null ? trim($email) : null;
        if ($displayName === '' || strlen($displayName) > 120) {
            throw new RuntimeException('Nazwa użytkownika jest wymagana i może mieć maksymalnie 120 znaków.');
        }
        if ($email !== null && $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException('Adres e-mail jest nieprawidłowy.');
        }
        $this->assertStatus($status);
        $roleIds = $this->roleIds($roles);
        $this->assertActorMayAssignRoles($actorId, $roles);
        $provider = strtolower(trim($provider));
        $providerSubject = trim($providerSubject);
        if (($provider === '') !== ($providerSubject === '')) {
            throw new RuntimeException('Dostawca i jego identyfikator muszą być podane razem.');
        }
        if ($provider !== '' && preg_match('/^[a-z][a-z0-9_-]{1,31}$/', $provider) !== 1) {
            throw new RuntimeException('Identyfikator dostawcy jest nieprawidłowy.');
        }
        if (strlen($providerSubject) > 191) {
            throw new RuntimeException('Identyfikator użytkownika u dostawcy jest zbyt długi.');
        }

        $pdo = $this->database->connection()->pdo;
        $pdo->beginTransaction();
        try {
            $statement = $pdo->prepare(
                'INSERT INTO users (display_name, email, status) VALUES (:display_name, :email, :status)'
            );
            $statement->execute([
                ':display_name' => $displayName,
                ':email' => $email !== '' ? $email : null,
                ':status' => $status,
            ]);
            $userId = (int) $pdo->lastInsertId();
            $this->replaceUserRoles($pdo, $userId, $roleIds);
            if ($provider !== '') {
                $identity = $pdo->prepare(
                    'INSERT INTO user_identities '
                    . '(user_id, provider, provider_subject, provider_login, provider_email, email_verified) '
                    . 'VALUES (:user_id, :provider, :subject, :login, :email, 0)'
                );
                $identity->execute([
                    ':user_id' => $userId,
                    ':provider' => $provider,
                    ':subject' => $providerSubject,
                    ':login' => $displayName,
                    ':email' => $email !== '' ? $email : null,
                ]);
            }
            $pdo->commit();

            return $userId;
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function updateAccount(
        int $userId,
        string $status,
        array $roles,
        int $actorId,
    ): void {
        $this->assertStatus($status);
        if ($this->database->count('users', ['id' => $userId]) !== 1) {
            throw new RuntimeException('Nie znaleziono użytkownika.');
        }
        $roleIds = $this->roleIds($roles);
        $targetIsOwner = $this->hasRole($userId, 'owner');
        $actorIsOwner = $this->hasRole($actorId, 'owner');
        $actorIsAdministrator = $this->hasRole($actorId, 'administrator');
        $wantsOwner = in_array('owner', $roles, true);
        $targetIsAdministrator = $this->hasRole($userId, 'administrator');
        $wantsAdministrator = in_array('administrator', $roles, true);
        $targetIsMaintainer = $this->hasRole($userId, 'maintainer');
        $wantsMaintainer = in_array('maintainer', $roles, true);
        if (($targetIsOwner || $wantsOwner) && !$actorIsOwner) {
            throw new RuntimeException('Tylko Owner może zarządzać kontem lub rolą Owner.');
        }
        if (($targetIsAdministrator || $wantsAdministrator || $targetIsMaintainer || $wantsMaintainer)
            && !$actorIsOwner && !$actorIsAdministrator) {
            throw new RuntimeException('Tylko Owner lub Administrator może zarządzać rolami Administrator i Maintainer.');
        }
        if ($userId === $actorId && $status !== 'active') {
            throw new RuntimeException('Nie można zablokować własnego konta.');
        }
        if ($userId === $actorId && $targetIsOwner && !$wantsOwner) {
            throw new RuntimeException('Owner nie może odebrać sobie roli Owner.');
        }
        if ($userId === $actorId && $targetIsAdministrator && !$wantsAdministrator) {
            throw new RuntimeException('Administrator nie może odebrać sobie roli administratora.');
        }
        if (
            ($status !== 'active' || !$wantsOwner)
            && $this->isLastActiveOwner($userId)
        ) {
            throw new RuntimeException('Nie można zmienić ostatniego aktywnego Ownera.');
        }

        $pdo = $this->database->connection()->pdo;
        $pdo->beginTransaction();
        try {
            $update = $pdo->prepare('UPDATE users SET status = :status WHERE id = :user_id');
            $update->execute([':status' => $status, ':user_id' => $userId]);
            $this->replaceUserRoles($pdo, $userId, $roleIds);
            $pdo->commit();
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    /**
     * @param list<string> $permissions
     */
    public function saveRole(
        string $originalName,
        string $name,
        string $label,
        array $permissions,
    ): string {
        $name = strtolower(trim($name));
        $label = trim($label);
        if (preg_match('/^[a-z][a-z0-9_]{1,63}$/', $name) !== 1) {
            throw new RuntimeException('Identyfikator roli musi zawierać małe litery, cyfry lub podkreślenia.');
        }
        if ($label === '' || strlen($label) > 120) {
            throw new RuntimeException('Etykieta roli jest wymagana i może mieć maksymalnie 120 znaków.');
        }
        $existing = $originalName !== '' ? $this->findRole($originalName) : null;
        if ($existing?->system === true) {
            throw new RuntimeException('Definicje ról systemowych są zarządzane przez migracje Core.');
        }
        if (in_array('*', $permissions, true)) {
            throw new RuntimeException('Pełny dostęp jest zarezerwowany dla systemowej roli Owner.');
        }
        $permissionIds = $this->permissionIds($permissions);
        if ($existing === null && $this->findRole($name) !== null) {
            throw new RuntimeException('Rola o takim identyfikatorze już istnieje.');
        }

        $pdo = $this->database->connection()->pdo;
        $pdo->beginTransaction();
        try {
            if ($existing === null) {
                $insert = $pdo->prepare(
                    'INSERT INTO roles (name, label, is_system) VALUES (:name, :label, 0)'
                );
                $insert->execute([':name' => $name, ':label' => $label]);
                $roleId = (int) $pdo->lastInsertId();
            } else {
                $update = $pdo->prepare(
                    'UPDATE roles SET name = :name, label = :label WHERE id = :id'
                );
                $update->execute([':name' => $name, ':label' => $label, ':id' => $existing->id]);
                $roleId = $existing->id;
            }

            $delete = $pdo->prepare('DELETE FROM role_permissions WHERE role_id = :role_id');
            $delete->execute([':role_id' => $roleId]);
            $insertPermission = $pdo->prepare(
                'INSERT INTO role_permissions (role_id, permission_id) VALUES (:role_id, :permission_id)'
            );
            foreach ($permissionIds as $permissionId) {
                $insertPermission->execute([
                    ':role_id' => $roleId,
                    ':permission_id' => $permissionId,
                ]);
            }
            $pdo->commit();

            return $name;
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function deleteRole(string $name): void
    {
        $role = $this->findRole($name);
        if ($role === null) {
            throw new RuntimeException('Wybrana rola nie istnieje.');
        }
        if ($role->system) {
            throw new RuntimeException('Roli systemowej nie można usunąć.');
        }
        if ($role->usersCount > 0) {
            throw new RuntimeException('Nie można usunąć roli przypisanej do użytkowników.');
        }
        $this->database->delete('roles', ['id' => $role->id]);
    }

    private function isLastActiveOwner(int $userId): bool
    {
        $isOwner = (int) $this->database->query(
            'SELECT COUNT(*) FROM user_roles '
            . 'JOIN roles ON roles.id = user_roles.role_id '
            . 'JOIN users ON users.id = user_roles.user_id '
            . 'WHERE users.id = :user_id AND users.status = :status AND roles.name = :role',
            [':user_id' => $userId, ':status' => 'active', ':role' => 'owner']
        )?->fetchColumn() === 1;
        if (!$isOwner) {
            return false;
        }

        return (int) $this->database->query(
            'SELECT COUNT(DISTINCT users.id) FROM users '
            . 'JOIN user_roles ON user_roles.user_id = users.id '
            . 'JOIN roles ON roles.id = user_roles.role_id '
            . 'WHERE users.status = :status AND roles.name = :role',
            [':status' => 'active', ':role' => 'owner']
        )?->fetchColumn() <= 1;
    }

    private function hasRole(int $userId, string $role): bool
    {
        return (int) $this->database->query(
            'SELECT COUNT(*) FROM user_roles '
            . 'JOIN roles ON roles.id = user_roles.role_id '
            . 'WHERE user_roles.user_id = :user_id AND roles.name = :role',
            [':user_id' => $userId, ':role' => $role]
        )?->fetchColumn() > 0;
    }

    /** @param list<string> $roles */
    private function assertActorMayAssignRoles(?int $actorId, array $roles): void
    {
        $isOwner = $actorId !== null && $this->hasRole($actorId, 'owner');
        $isAdministrator = $actorId !== null && $this->hasRole($actorId, 'administrator');
        if (in_array('owner', $roles, true) && !$isOwner) {
            throw new RuntimeException('Tylko Owner może nadać rolę Owner.');
        }
        if ((in_array('administrator', $roles, true) || in_array('maintainer', $roles, true))
            && !$isOwner && !$isAdministrator) {
            throw new RuntimeException('Tylko Owner lub Administrator może nadać rolę Administrator lub Maintainer.');
        }
    }

    private function assertStatus(string $status): void
    {
        if (!in_array($status, ['active', 'blocked', 'pending'], true)) {
            throw new RuntimeException('Wybrano nieprawidłowy status użytkownika.');
        }
    }

    /**
     * @param list<string> $roles
     * @return list<int>
     */
    private function roleIds(array $roles): array
    {
        if ($roles === []) {
            throw new RuntimeException('Użytkownik musi mieć co najmniej jedną rolę.');
        }
        $available = $this->roles();
        foreach ($roles as $role) {
            if (!isset($available[$role])) {
                throw new RuntimeException("Rola {$role} nie istnieje.");
            }
        }
        $placeholders = implode(',', array_fill(0, count($roles), '?'));
        $statement = $this->database->connection()->pdo->prepare(
            "SELECT id FROM roles WHERE name IN ({$placeholders}) ORDER BY id"
        );
        $statement->execute(array_values($roles));

        return array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * @param list<string> $permissions
     * @return list<int>
     */
    private function permissionIds(array $permissions): array
    {
        if ($permissions === []) {
            return [];
        }
        $available = $this->permissions();
        foreach ($permissions as $permission) {
            if (!isset($available[$permission])) {
                throw new RuntimeException("Uprawnienie {$permission} nie istnieje.");
            }
        }
        $placeholders = implode(',', array_fill(0, count($permissions), '?'));
        $statement = $this->database->connection()->pdo->prepare(
            "SELECT id FROM permissions WHERE name IN ({$placeholders}) ORDER BY id"
        );
        $statement->execute(array_values($permissions));

        return array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * @param list<int> $roleIds
     */
    private function replaceUserRoles(\PDO $pdo, int $userId, array $roleIds): void
    {
        $delete = $pdo->prepare('DELETE FROM user_roles WHERE user_id = :user_id');
        $delete->execute([':user_id' => $userId]);
        $insert = $pdo->prepare(
            'INSERT INTO user_roles (user_id, role_id) VALUES (:user_id, :role_id)'
        );
        foreach ($roleIds as $roleId) {
            $insert->execute([':user_id' => $userId, ':role_id' => $roleId]);
        }
    }

    /**
     * @return list<string>
     */
    private function rolePermissions(int $roleId): array
    {
        $statement = $this->database->query(
            'SELECT permissions.name FROM role_permissions '
            . 'JOIN permissions ON permissions.id = role_permissions.permission_id '
            . 'WHERE role_permissions.role_id = :role_id ORDER BY permissions.name',
            [':role_id' => $roleId]
        );

        return array_map('strval', $statement?->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }

    /**
     * @return list<string>
     */
    private function values(string $sql, int $userId): array
    {
        $statement = $this->database->query($sql, [':user_id' => $userId]);

        return array_map('strval', $statement?->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }

    /**
     * @param list<int> $userIds
     * @return array<int, list<string>>
     */
    private function valuesByUser(string $sqlTemplate, array $userIds): array
    {
        $userIds = array_values(array_unique(array_filter($userIds, static fn (int $id): bool => $id > 0)));
        if ($userIds === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $statement = $this->database->connection()->pdo->prepare(sprintf($sqlTemplate, $placeholders));
        $statement->execute($userIds);
        $values = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $userId = (int) ($row['user_id'] ?? 0);
            if ($userId < 1) {
                continue;
            }
            $values[$userId][] = (string) ($row['value'] ?? '');
        }

        return $values;
    }

    private function roleOrderSql(): string
    {
        return "CASE roles.name WHEN 'owner' THEN 1 WHEN 'administrator' THEN 2 "
            . "WHEN 'maintainer' THEN 3 WHEN 'editor' THEN 4 WHEN 'auditor' THEN 5 "
            . "WHEN 'support' THEN 6 WHEN 'user' THEN 7 ELSE 100 END";
    }

    private function rolePriority(string $role): int
    {
        $position = array_search(
            $role,
            ['owner', 'administrator', 'maintainer', 'editor', 'auditor', 'support', 'user'],
            true
        );
        return $position === false ? 100 : $position + 1;
    }
}
