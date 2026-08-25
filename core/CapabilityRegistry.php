<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Core;

use RuntimeException;

final class CapabilityRegistry
{
    /** @var array<string, array<string, true>> */
    private array $providers = [];
    /** @var array<string, array<string, object>> */
    private array $implementations = [];

    /**
     * @param list<string> $capabilities
     */
    public function register(string $moduleId, array $capabilities): void
    {
        $this->assertModuleId($moduleId);

        $validated = [];
        foreach ($capabilities as $capability) {
            $this->assertCapability($capability);
            $validated[$capability] = true;
        }

        foreach (array_keys($validated) as $capability) {
            $this->providers[$capability][$moduleId] = true;
        }
    }

    public function unregisterModule(string $moduleId): void
    {
        $this->assertModuleId($moduleId);

        foreach ($this->providers as $capability => $providers) {
            unset($providers[$moduleId]);
            if ($providers === []) {
                unset($this->providers[$capability]);
                continue;
            }
            $this->providers[$capability] = $providers;
        }

        foreach ($this->implementations as $capability => $implementations) {
            unset($implementations[$moduleId]);
            if ($implementations === []) {
                unset($this->implementations[$capability]);
                continue;
            }
            $this->implementations[$capability] = $implementations;
        }
    }

    public function bind(string $moduleId, string $capability, object $implementation): void
    {
        $this->assertModuleId($moduleId);
        $this->assertCapability($capability);

        if (!isset($this->providers[$capability][$moduleId])) {
            throw new RuntimeException(
                "Moduł {$moduleId} nie deklaruje capability {$capability} w manifeście."
            );
        }

        $this->implementations[$capability][$moduleId] = $implementation;
    }

    public function implementation(string $capability, ?string $moduleId = null): object
    {
        $this->assertCapability($capability);

        if ($moduleId !== null) {
            $this->assertModuleId($moduleId);
            $implementation = $this->implementations[$capability][$moduleId] ?? null;
            if ($implementation === null) {
                throw new RuntimeException(
                    "Brak aktywnej implementacji capability {$capability} w module {$moduleId}."
                );
            }

            return $implementation;
        }

        $implementations = $this->implementations[$capability] ?? [];
        if (count($implementations) !== 1) {
            throw new RuntimeException(
                $implementations === []
                    ? "Brak aktywnej implementacji capability {$capability}."
                    : "Capability {$capability} ma wielu providerów; wskaż identyfikator modułu."
            );
        }

        return reset($implementations);
    }

    public function hasImplementation(string $capability, ?string $moduleId = null): bool
    {
        $this->assertCapability($capability);
        if ($moduleId !== null) {
            $this->assertModuleId($moduleId);
            return isset($this->implementations[$capability][$moduleId]);
        }

        return ($this->implementations[$capability] ?? []) !== [];
    }

    public function has(string $capability): bool
    {
        $this->assertCapability($capability);

        return isset($this->providers[$capability]) && $this->providers[$capability] !== [];
    }

    /**
     * @return list<string>
     */
    public function providers(string $capability): array
    {
        $this->assertCapability($capability);

        $providers = array_keys($this->providers[$capability] ?? []);
        sort($providers);

        return $providers;
    }

    /**
     * @return list<string>
     */
    public function forModule(string $moduleId): array
    {
        $this->assertModuleId($moduleId);

        $capabilities = [];
        foreach ($this->providers as $capability => $providers) {
            if (isset($providers[$moduleId])) {
                $capabilities[] = $capability;
            }
        }
        sort($capabilities);

        return $capabilities;
    }

    /**
     * @return array<string, list<string>>
     */
    public function all(): array
    {
        $result = [];
        $capabilities = array_keys($this->providers);
        sort($capabilities);

        foreach ($capabilities as $capability) {
            $result[$capability] = $this->providers($capability);
        }

        return $result;
    }

    private function assertModuleId(string $moduleId): void
    {
        if (preg_match('/^[a-z][a-z0-9_]{1,63}$/', $moduleId) !== 1) {
            throw new RuntimeException("Identyfikator modułu {$moduleId} jest nieprawidłowy.");
        }
    }

    private function assertCapability(mixed $capability): void
    {
        if (
            !is_string($capability)
            || preg_match('/^[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)+$/', $capability) !== 1
        ) {
            throw new RuntimeException('Identyfikator capability jest nieprawidłowy.');
        }
    }
}
