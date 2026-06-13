<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\CoreAuth;

use RuntimeException;
use SyntaxDevTeam\Cms\Database\CrudApp;

final class FirstAdminBootstrapper
{
    public function __construct(
        private readonly CrudApp $database,
    ) {
    }

    public function bootstrap(ExternalIdentity $identity, string $displayName): User
    {
        $displayName = trim($displayName);

        if ($identity->provider === '' || $identity->subject === '' || $displayName === '') {
            throw new RuntimeException('Bootstrap wymaga dostawcy, subject i nazwy użytkownika.');
        }

        $lock = $this->database->query(
            'SELECT GET_LOCK(:name, 10)',
            [':name' => 'miniportal:first-admin-bootstrap']
        )?->fetchColumn();

        if ((int) $lock !== 1) {
            throw new RuntimeException('Nie można uzyskać blokady bootstrapu administratora.');
        }

        try {
            if ($this->database->count('users') !== 0) {
                throw new RuntimeException('Bootstrap jest dostępny wyłącznie dla pustej tabeli users.');
            }

            $administratorRole = $this->database->query(
                'SELECT id FROM roles WHERE name = :name LIMIT 1',
                [':name' => 'administrator']
            )?->fetchColumn();

            if ($administratorRole === false || $administratorRole === null) {
                throw new RuntimeException('Migracja nie zawiera roli administrator.');
            }

            $userId = null;
            $this->database->action(function ($database) use (
                $identity,
                $displayName,
                $administratorRole,
                &$userId
            ): void {
                $database->insert('users', [
                    'display_name' => $displayName,
                    'email' => $identity->email,
                    'avatar_url' => $identity->avatarUrl,
                    'status' => 'active',
                ]);
                $userId = (int) $database->id();

                $database->insert('user_identities', [
                    'user_id' => $userId,
                    'provider' => $identity->provider,
                    'provider_subject' => $identity->subject,
                    'provider_login' => $identity->login,
                    'provider_email' => $identity->email,
                    'email_verified' => $identity->emailVerified ? 1 : 0,
                ]);
                $database->insert('user_roles', [
                    'user_id' => $userId,
                    'role_id' => (int) $administratorRole,
                ]);
                $database->insert('auth_events', [
                    'user_id' => $userId,
                    'provider' => $identity->provider,
                    'event_type' => 'admin_bootstrap',
                    'result' => 'success',
                ]);
            });

            if (!is_int($userId) || $userId < 1) {
                throw new RuntimeException('Nie udało się utworzyć pierwszego administratora.');
            }

            $user = (new CrudAppUserRepository($this->database))->findById($userId);

            if ($user === null) {
                throw new RuntimeException('Utworzone konto administratora nie może zostać odczytane.');
            }

            return $user;
        } finally {
            $this->database->query(
                'SELECT RELEASE_LOCK(:name)',
                [':name' => 'miniportal:first-admin-bootstrap']
            );
        }
    }
}
