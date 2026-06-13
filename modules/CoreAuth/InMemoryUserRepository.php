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
}
