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

        return array_map(
            fn (array $row): UserAdminRecord => new UserAdminRecord(
                (int) $row['id'],
                (string) $row['display_name'],
                $row['email'] !== null ? (string) $row['email'] : null,
                (string) $row['status'],
                $this->values(
                    'SELECT roles.name FROM user_roles '
                    . 'JOIN roles ON roles.id = user_roles.role_id '
                    . 'WHERE user_roles.user_id = :user_id ORDER BY roles.id',
                    (int) $row['id']
                ),
                $this->values(
                    'SELECT provider FROM user_identities '
                    . 'WHERE user_id = :user_id ORDER BY provider',
                    (int) $row['id']
                ),
                $row['last_login_at'] !== null ? (string) $row['last_login_at'] : null,
                (string) $row['created_at'],
            ),
            $statement->fetchAll(PDO::FETCH_ASSOC)
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
            . 'ORDER BY roles.is_system DESC, roles.id ASC'
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
        $isAdministrator = in_array('administrator', $roles, true);
        if ($userId === $actorId && ($status !== 'active' || !$isAdministrator)) {
            throw new RuntimeException('Nie można zablokować własnego konta ani odebrać sobie roli administratora.');
        }
        if (
            ($status !== 'active' || !$isAdministrator)
            && $this->isLastActiveAdministrator($userId)
        ) {
            throw new RuntimeException('Nie można zmienić ostatniego aktywnego administratora.');
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
        $permissionIds = $this->permissionIds($permissions);
        if ($name === 'administrator') {
            $permissions = array_keys($this->permissions());
            $permissionIds = $this->permissionIds($permissions);
        }

        $existing = $originalName !== '' ? $this->findRole($originalName) : null;
        if ($existing?->system === true && $name !== $originalName) {
            throw new RuntimeException('Nie można zmienić identyfikatora roli systemowej.');
        }
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

    private function isLastActiveAdministrator(int $userId): bool
    {
        $isAdministrator = (int) $this->database->query(
            'SELECT COUNT(*) FROM user_roles '
            . 'JOIN roles ON roles.id = user_roles.role_id '
            . 'JOIN users ON users.id = user_roles.user_id '
            . 'WHERE users.id = :user_id AND users.status = :status AND roles.name = :role',
            [':user_id' => $userId, ':status' => 'active', ':role' => 'administrator']
        )?->fetchColumn() === 1;
        if (!$isAdministrator) {
            return false;
        }

        return (int) $this->database->query(
            'SELECT COUNT(DISTINCT users.id) FROM users '
            . 'JOIN user_roles ON user_roles.user_id = users.id '
            . 'JOIN roles ON roles.id = user_roles.role_id '
            . 'WHERE users.status = :status AND roles.name = :role',
            [':status' => 'active', ':role' => 'administrator']
        )?->fetchColumn() <= 1;
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
}
