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
        private readonly ?\SyntaxDevTeam\Cms\Core\HookRegistry $hooks = null,
    ) {
    }

    public function findById(int $id): ?User
    {
        $statement = $this->database->query(
            'SELECT * FROM users WHERE id = :id LIMIT 1',
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

            $defaultRole = AuthPolicyRuntime::provider()->defaultRole() ?? 'user';
            if (preg_match('/^[a-z][a-z0-9_]{1,63}$/', $defaultRole) !== 1) {
                $defaultRole = 'user';
            }
            $role = $pdo->prepare(
                'INSERT INTO user_roles (user_id, role_id) '
                . 'SELECT :user_id, id FROM roles WHERE name = :role'
            );
            $role->execute([':user_id' => $userId, ':role' => $defaultRole]);
            if ($role->rowCount() !== 1 && $defaultRole !== 'user') {
                $role->execute([':user_id' => $userId, ':role' => 'user']);
            }
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

    public function activateWithTermsAcceptance(
        int $userId,
        string $termsVersion,
        string $termsUrl,
        string $privacyUrl,
        ?string $ipHash,
        ?string $userAgent,
    ): bool {
        $pdo = $this->database->connection()->pdo;
        $pdo->beginTransaction();
        try {
            $lock = $pdo->prepare('SELECT status FROM users WHERE id = :id FOR UPDATE');
            $lock->execute([':id' => $userId]);
            $status = $lock->fetchColumn();
            if ($status !== 'pending') {
                $pdo->rollBack();
                return $status === 'active';
            }

            $acceptance = $pdo->prepare(
                'INSERT INTO user_terms_acceptances '
                . '(user_id, terms_version, terms_url, privacy_url, ip_hash, user_agent) '
                . 'VALUES (:user_id, :terms_version, :terms_url, :privacy_url, :ip_hash, :user_agent) '
                . 'ON DUPLICATE KEY UPDATE terms_url = VALUES(terms_url), privacy_url = VALUES(privacy_url)'
            );
            $acceptance->execute([
                ':user_id' => $userId,
                ':terms_version' => $termsVersion,
                ':terms_url' => $termsUrl,
                ':privacy_url' => $privacyUrl,
                ':ip_hash' => $ipHash,
                ':user_agent' => $userAgent,
            ]);
            $activate = $pdo->prepare("UPDATE users SET status = 'active' WHERE id = :id AND status = 'pending'");
            $activate->execute([':id' => $userId]);
            $pdo->commit();

            return $activate->rowCount() === 1;
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

    public function touchSession(int $userId, string $sessionHash): void
    {
        if ($userId <= 0 || preg_match('/^[a-f0-9]{64}$/', $sessionHash) !== 1) {
            return;
        }
        $this->database->query(
            'INSERT INTO auth_active_sessions (session_hash, user_id, last_seen_at) '
            . 'VALUES (:session_hash, :user_id, CURRENT_TIMESTAMP) '
            . 'ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), last_seen_at = CURRENT_TIMESTAMP',
            [':session_hash' => $sessionHash, ':user_id' => $userId]
        );
        $this->database->query(
            'DELETE FROM auth_active_sessions WHERE last_seen_at < :cutoff',
            [':cutoff' => gmdate('Y-m-d H:i:s', time() - 2592000)]
        );
    }

    public function forgetSession(string $sessionHash): void
    {
        if (preg_match('/^[a-f0-9]{64}$/', $sessionHash) !== 1) {
            return;
        }
        $this->database->delete('auth_active_sessions', ['session_hash' => $sessionHash]);
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
        $derived = $row['status']==='active' && $this->hooks!==null
            ? (new SupplementalRoleResolver($this->database))->forUser($this->hooks, $userId)
            : ['roles'=>[], 'permissions'=>[]];

        $user = new User(
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
            $this->identities($userId),
            (int) ($row['session_version'] ?? 0),
        );
        if ($derived['roles']===[]) return $user;
        $roles = array_values(array_unique(array_merge(
            array_values(array_filter($user->roles, static fn ($role) => $role!=='user')),
            $derived['roles'],
            array_values(array_filter($user->roles, static fn ($role) => $role==='user')),
        )));
        return new User($user->id, $user->displayName, $user->email, $user->avatarUrl, $user->status,
            $roles, array_values(array_unique(array_merge($user->permissions, $derived['permissions']))), $user->identities, $user->sessionVersion);
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
