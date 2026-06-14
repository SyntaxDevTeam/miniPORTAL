<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Core;

use RuntimeException;

final class ModuleBootstrapper
{
    public function __construct(
        private readonly string $modulesPath,
        private readonly ModuleManifestValidator $manifestValidator,
        private readonly ?ModuleStateRepository $states = null,
    ) {
    }

    /**
     * @param list<array{
     *     directory: string,
     *     enabled?: bool|callable(array<string, mixed>): bool,
     *     factory: callable(array<string, mixed>): ModuleInterface
     * }> $definitions
     * @param array<string, mixed> $services
     */
    public function register(array $definitions, array $services, ModuleRegistry $registry): void
    {
        foreach ($definitions as $definition) {
            $directory = (string) ($definition['directory'] ?? '');
            $factory = $definition['factory'] ?? null;
            $enabled = $definition['enabled'] ?? true;

            if ($directory === '' || basename($directory) !== $directory || !is_callable($factory)) {
                throw new RuntimeException('Deklaracja modułu jest nieprawidłowa.');
            }

            $isEnabled = is_callable($enabled) ? (bool) $enabled($services) : $enabled === true;
            if (!$isEnabled) {
                continue;
            }

            $manifest = $this->manifestValidator->validate(
                rtrim($this->modulesPath, '/') . '/' . $directory
            );
            $this->states?->registerDiscovered($manifest);
            $state = $this->states?->find($manifest->id);
            if (
                $this->states !== null
                && ($state === null || !$state->isActive())
            ) {
                continue;
            }
            $module = $factory($services);
            if (!$module instanceof ModuleInterface) {
                throw new RuntimeException("Fabryka {$directory} nie zwróciła ModuleInterface.");
            }

            $registry->add($module, $manifest);
        }
    }
}
