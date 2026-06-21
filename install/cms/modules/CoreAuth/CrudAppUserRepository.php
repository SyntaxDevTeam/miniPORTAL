<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\CoreAuth;

use PDO;
use RuntimeException;
use SyntaxDevTeam\Cms\Database\CrudApp;

final class CrudAppUserRepository implements UserRepositoryInterface
{
    public function __construct(
        private readonly CrudApp $database,
    ) {
    }

    public function findById(int $id): ?User
    {
        $statement = $this->database->query(
            'SELECT id, display_name, email, avatar_url, status FROM users WHERE id = :id LIMIT 1',
            [':id' => $id]
        );
        $row = $statement?->fetch(PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            return null;
        }

        return $this->hydrate($row);
    }

    public function findByIdentity(string $provider, string $subject): ?User
    {
        $statement = $this->database->query(
            'SELECT user_id FROM user_identities '
            . 'WHERE provider = :provider AND provider_subject = :subject LIMIT 1',
            [
                ':provider' => $provider,
                ':subject' => $subject,
            ]
        );
        $userId = $statement?->fetchColumn();

        return $userId === false || $userId === null
            ? null
            : $this->findById((int) $userId);
    }

    public function createPendingFromIdentity(ExternalIdentity $identity): User
    {
        $existing = $this->findByIdentity($identity->provider, $identity->subject);
        if ($existing !== null) {
            return $existing;
        }

        $pdo = $this->database->connection()->pdo;
        $pdo->beginTransaction();
        try {
            $user = $pdo->prepare(
                'INSERT INTO users (display_name, email, avatar_url, status) '
                . 'VALUES (:display_name, :email, :avatar_url, :status)'
            );
            $user->execute([
                ':display_name' => $identity->login !== '' ? $identity->login : $identity->provider,
                ':email' => $identity->emailVerified ? $identity->email : null,
                ':avatar_url' => $identity->avatarUrl,
                ':status' => 'pending',
            ]);
            $userId = (int) $pdo->lastInsertId();
            $identityInsert = $pdo->prepare(
                'INSERT INTO user_identities '
                . '(user_id, provider, provider_subject, provider_login, provider_email, email_verified) '
                . 'VALUES (:user_id, :provider, :subject, :login, :email, :verified)'
            );
            $identityInsert->execute([
                ':user_id' => $userId,
                ':provider' => $identity->provider,
                ':subject' => $identity->subject,
                ':login' => $identity->login,
                ':email' => $identity->email,
                ':verified' => $identity->emailVerified ? 1 : 0,
            ]);
            $role = $pdo->prepare(
                'INSERT INTO user_roles (user_id, role_id) '
                . 'SELECT :user_id, id FROM roles WHERE name = :role'
            );
            $role->execute([':user_id' => $userId, ':role' => 'user']);
            $pdo->commit();

            $created = $this->findById($userId);
            if ($created === null) {
                throw new RuntimeException('Nie można odczytać utworzonego konta oczekującego.');
            }

            return $created;
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function linkIdentity(int $userId, ExternalIdentity $identity): void
    {
        if ($this->findByIdentity($identity->provider, $identity->subject) !== null) {
            throw new RuntimeException('Ta tożsamość jest już połączona z kontem.');
        }

        $this->database->insert('user_identities', [
            'user_id' => $userId,
            'provider' => $identity->provider,
            'provider_subject' => $identity->subject,
            'provider_login' => $identity->login,
            'provider_email' => $identity->email,
            'email_verified' => $identity->emailVerified ? 1 : 0,
        ]);
    }

    public function unlinkIdentity(int $userId, string $provider, string $subject): bool
    {
        $count = $this->database->count('user_identities', ['user_id' => $userId]);

        if ($count <= 1) {
            return false;
        }

        $statement = $this->database->delete('user_identities', [
            'user_id' => $userId,
            'provider' => $provider,
            'provider_subject' => $subject,
        ]);

        return $statement !== null && $statement->rowCount() === 1;
    }

    public function touchIdentity(int $userId, string $provider, string $subject): void
    {
        $this->database->update('user_identities', [
            'last_used_at' => date('Y-m-d H:i:s'),
        ], [
            'user_id' => $userId,
            'provider' => $provider,
            'provider_subject' => $subject,
        ]);
        $this->database->update('users', [
            'last_login_at' => date('Y-m-d H:i:s'),
        ], ['id' => $userId]);
    }

    public function updateProfile(int $userId, string $displayName, ?string $email): bool
    {
        $statement = $this->database->update('users', [
            'display_name' => $displayName,
            'email' => $email,
        ], ['id' => $userId]);

        return $statement !== null;
    }

    public function updateAvatar(int $userId, ?string $avatarUrl): bool
    {
        $statement = $this->database->update('users', [
            'avatar_url' => $avatarUrl,
        ], ['id' => $userId]);

        return $statement !== null;
    }

    private function hydrate(array $row): User
    {
        $userId = (int) $row['id'];

        return new User(
            $userId,
            (string) $row['display_name'],
            $row['email'] !== null ? (string) $row['email'] : null,
            $row['avatar_url'] !== null ? (string) $row['avatar_url'] : null,
            (string) $row['status'],
            $this->columnList(
                'SELECT roles.name FROM user_roles '
                . 'JOIN roles ON roles.id = user_roles.role_id '
                . "WHERE user_roles.user_id = :user_id ORDER BY CASE roles.name "
                . "WHEN 'owner' THEN 1 WHEN 'administrator' THEN 2 WHEN 'maintainer' THEN 3 "
                . "WHEN 'editor' THEN 4 WHEN 'auditor' THEN 5 WHEN 'support' THEN 6 "
                . "WHEN 'user' THEN 7 ELSE 100 END, roles.id",
                $userId
            ),
            $this->columnList(
                'SELECT DISTINCT permissions.name FROM user_roles '
                . 'JOIN role_permissions ON role_permissions.role_id = user_roles.role_id '
                . 'JOIN permissions ON permissions.id = role_permissions.permission_id '
                . 'WHERE user_roles.user_id = :user_id ORDER BY permissions.name',
                $userId
            ),
            $this->identities($userId)
        );
    }

    /**
     * @return list<string>
     */
    private function columnList(string $query, int $userId): array
    {
        $statement = $this->database->query($query, [':user_id' => $userId]);

        if ($statement === null) {
            throw new RuntimeException('Zapytanie repozytorium użytkowników nie zostało wykonane.');
        }

        return array_values(array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN)));
    }

    /**
     * @return list<ExternalIdentity>
     */
    private function identities(int $userId): array
    {
        $statement = $this->database->query(
            'SELECT provider, provider_subject, provider_login, provider_email, email_verified '
            . 'FROM user_identities WHERE user_id = :user_id ORDER BY id',
            [':user_id' => $userId]
        );

        if ($statement === null) {
            throw new RuntimeException('Nie można pobrać tożsamości użytkownika.');
        }

        return array_map(
            static fn (array $identity): ExternalIdentity => new ExternalIdentity(
                (string) $identity['provider'],
                (string) $identity['provider_subject'],
                (string) ($identity['provider_login'] ?? ''),
                $identity['provider_email'] !== null ? (string) $identity['provider_email'] : null,
                (bool) $identity['email_verified']
            ),
            $statement->fetchAll(PDO::FETCH_ASSOC)
        );
    }
}
