<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\CoreAuth;

use RuntimeException;

final class IdentityProviderRegistry
{
    /** @var array<string, IdentityProviderInterface> */
    private array $providers = [];

    public function add(IdentityProviderInterface $provider): void
    {
        $name = $provider->name();

        if (preg_match('/^[a-z][a-z0-9_-]{1,31}$/', $name) !== 1) {
            throw new RuntimeException('Identyfikator dostawcy tożsamości jest nieprawidłowy.');
        }

        if (isset($this->providers[$name])) {
            throw new RuntimeException("Dostawca {$name} został już zarejestrowany.");
        }

        $this->providers[$name] = $provider;
    }

    public function get(string $name): ?IdentityProviderInterface
    {
        return $this->providers[$name] ?? null;
    }

    /**
     * @return list<IdentityProviderInterface>
     */
    public function all(): array
    {
        return array_values($this->providers);
    }

    /**
     * @return list<IdentityProviderInterface>
     */
    public function configured(): array
    {
        return array_values(array_filter(
            $this->providers,
            static fn (IdentityProviderInterface $provider): bool => $provider->isConfigured()
        ));
    }
}
