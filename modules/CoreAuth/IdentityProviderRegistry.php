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
        if (AuthPolicyRuntime::provider()->providerEnabled($name) === false) {
            return null;
        }

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
        $policy = AuthPolicyRuntime::provider();
        $providers = array_values(array_filter(
            $this->providers,
            static fn (IdentityProviderInterface $provider): bool => $provider->isConfigured()
                && $policy->providerEnabled($provider->name()) !== false
                && $policy->providerVisible($provider->name()) !== false
        ));

        $order = array_flip($policy->providerOrder());
        $registrationOrder = array_flip(array_keys($this->providers));
        usort($providers, static function (IdentityProviderInterface $left, IdentityProviderInterface $right) use ($order, $registrationOrder): int {
            $leftName = $left->name();
            $rightName = $right->name();

            return [
                $order[$leftName] ?? PHP_INT_MAX,
                $registrationOrder[$leftName] ?? PHP_INT_MAX,
            ] <=> [
                $order[$rightName] ?? PHP_INT_MAX,
                $registrationOrder[$rightName] ?? PHP_INT_MAX,
            ];
        });

        return $providers;
    }
}
