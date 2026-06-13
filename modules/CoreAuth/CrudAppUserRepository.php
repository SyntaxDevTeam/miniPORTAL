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
                . 'WHERE user_roles.user_id = :user_id ORDER BY roles.id',
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
