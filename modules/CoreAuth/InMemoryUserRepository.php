<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\CoreAuth;

final class InMemoryUserRepository implements UserRepositoryInterface
{
    /** @var array<int, User> */
    private array $users;

    public function __construct()
    {
        $this->users = [
            1 => new User(
                1,
                'WieszczY',
                'admin@example.test',
                null,
                'active',
                ['administrator'],
                ['*'],
                [new ExternalIdentity('demo', 'administrator', 'WieszczY')]
            ),
            2 => new User(
                2,
                'Redaktor Demo',
                'editor@example.test',
                null,
                'active',
                ['editor'],
                [
                    'admin.access',
                    'pages.view',
                    'pages.create',
                    'pages.edit',
                    'pages.publish',
                    'articles.view',
                    'articles.create',
                    'articles.edit',
                    'articles.publish',
                ],
                [new ExternalIdentity('demo', 'editor', 'Redaktor Demo')]
            ),
        ];
    }

    public function findById(int $id): ?User
    {
        return $this->users[$id] ?? null;
    }

    public function findByIdentity(string $provider, string $subject): ?User
    {
        foreach ($this->users as $user) {
            foreach ($user->identities as $identity) {
                if ($identity->provider === $provider && $identity->subject === $subject) {
                    return $user;
                }
            }
        }

        return null;
    }

    public function createPendingFromIdentity(ExternalIdentity $identity): User
    {
        $existing = $this->findByIdentity($identity->provider, $identity->subject);
        if ($existing !== null) {
            return $existing;
        }

        $user = new User(
            count($this->users) + 1,
            $identity->login !== '' ? $identity->login : $identity->provider,
            $identity->emailVerified ? $identity->email : null,
            $identity->avatarUrl,
            'pending',
            ['user'],
            [],
            [$identity]
        );
        $this->users[] = $user;

        return $user;
    }

    public function linkIdentity(int $userId, ExternalIdentity $identity): void
    {
        $user = $this->users[$userId] ?? null;

        if ($user === null || $this->findByIdentity($identity->provider, $identity->subject) !== null) {
            return;
        }

        $this->users[$userId] = new User(
            $user->id,
            $user->displayName,
            $user->email,
            $user->avatarUrl,
            $user->status,
            $user->roles,
            $user->permissions,
            [...$user->identities, $identity]
        );
    }

    public function unlinkIdentity(int $userId, string $provider, string $subject): bool
    {
        $user = $this->users[$userId] ?? null;

        if ($user === null || count($user->identities) <= 1) {
            return false;
        }

        $identities = array_values(array_filter(
            $user->identities,
            static fn (ExternalIdentity $identity): bool => !(
                $identity->provider === $provider && $identity->subject === $subject
            )
        ));

        if (count($identities) === count($user->identities)) {
            return false;
        }

        $this->users[$userId] = new User(
            $user->id,
            $user->displayName,
            $user->email,
            $user->avatarUrl,
            $user->status,
            $user->roles,
            $user->permissions,
            $identities
        );

        return true;
    }

    public function touchIdentity(int $userId, string $provider, string $subject): void
    {
    }

    public function updateProfile(int $userId, string $displayName, ?string $email): bool
    {
        $user = $this->users[$userId] ?? null;
        if ($user === null) {
            return false;
        }

        $this->users[$userId] = new User(
            $user->id,
            $displayName,
            $email,
            $user->avatarUrl,
            $user->status,
            $user->roles,
            $user->permissions,
            $user->identities
        );

        return true;
    }

    public function updateAvatar(int $userId, ?string $avatarUrl): bool
    {
        $user = $this->users[$userId] ?? null;
        if ($user === null) {
            return false;
        }

        $this->users[$userId] = new User(
            $user->id,
            $user->displayName,
            $user->email,
            $avatarUrl,
            $user->status,
            $user->roles,
            $user->permissions,
            $user->identities
        );

        return true;
    }
}
