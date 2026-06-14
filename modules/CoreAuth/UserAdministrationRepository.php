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

    public function updateAccount(
        int $userId,
        string $status,
        string $role,
        int $actorId,
    ): void {
        if (!in_array($status, ['active', 'blocked', 'pending'], true)) {
            throw new RuntimeException('Wybrano nieprawidłowy status użytkownika.');
        }
        if ($this->database->count('users', ['id' => $userId]) !== 1) {
            throw new RuntimeException('Nie znaleziono użytkownika.');
        }
        $roleId = $this->database->query(
            'SELECT id FROM roles WHERE name = :name LIMIT 1',
            [':name' => $role]
        )?->fetchColumn();
        if ($roleId === false || $roleId === null) {
            throw new RuntimeException('Wybrana rola nie istnieje.');
        }
        if ($userId === $actorId && ($status !== 'active' || $role !== 'administrator')) {
            throw new RuntimeException('Nie można zablokować własnego konta ani odebrać sobie roli administratora.');
        }
        if (
            ($status !== 'active' || $role !== 'administrator')
            && $this->isLastActiveAdministrator($userId)
        ) {
            throw new RuntimeException('Nie można zmienić ostatniego aktywnego administratora.');
        }

        $pdo = $this->database->connection()->pdo;
        $pdo->beginTransaction();
        try {
            $update = $pdo->prepare('UPDATE users SET status = :status WHERE id = :user_id');
            $update->execute([':status' => $status, ':user_id' => $userId]);
            $delete = $pdo->prepare('DELETE FROM user_roles WHERE user_id = :user_id');
            $delete->execute([':user_id' => $userId]);
            $insert = $pdo->prepare(
                'INSERT INTO user_roles (user_id, role_id) VALUES (:user_id, :role_id)'
            );
            $insert->execute([':user_id' => $userId, ':role_id' => (int) $roleId]);
            $pdo->commit();
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
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

    /**
     * @return list<string>
     */
    private function values(string $sql, int $userId): array
    {
        $statement = $this->database->query($sql, [':user_id' => $userId]);

        return array_map('strval', $statement?->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }
}
