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
     *     required?: bool,
     *     enabled?: bool|callable(array<string, mixed>): bool,
     *     factory: callable(array<string, mixed>): ModuleInterface
     * }> $definitions
     * @param array<string, mixed> $services
     */
    public function register(array $definitions, array $services, ModuleRegistry $registry): void
    {
        $registeredDirectories = [];
        foreach ($definitions as $definition) {
            $directory = (string) ($definition['directory'] ?? '');
            $factory = $definition['factory'] ?? null;
            $enabled = $definition['enabled'] ?? true;
            $required = ($definition['required'] ?? false) === true;

            if ($directory === '' || basename($directory) !== $directory || !is_callable($factory)) {
                throw new RuntimeException('Deklaracja modułu jest nieprawidłowa.');
            }
            $registeredDirectories[] = $directory;

            $isEnabled = is_callable($enabled) ? (bool) $enabled($services) : $enabled === true;
            if (!$isEnabled) {
                continue;
            }

            $inspection = $this->manifestValidator->inspect(
                rtrim($this->modulesPath, '/') . '/' . $directory
            );
            $manifest = $inspection['manifest'];
            if ($manifest === null) {
                if ($required) {
                    throw new RuntimeException(
                        "Wymagany moduł {$directory} ma nieprawidłowy pakiet: "
                        . ($inspection['error'] ?? 'nieznany błąd manifestu')
                    );
                }
                continue;
            }
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

        $this->registerPackageFactories($registeredDirectories, $services, $registry);
    }

    /**
     * Ładuje kod pakietu dopiero po jego instalacji i aktywacji.
     *
     * @param list<string> $registeredDirectories
     * @param array<string, mixed> $services
     */
    private function registerPackageFactories(
        array $registeredDirectories,
        array $services,
        ModuleRegistry $registry,
    ): void {
        if ($this->states === null) {
            return;
        }

        foreach (glob(rtrim($this->modulesPath, '/') . '/*/info.json') ?: [] as $file) {
            $directory = dirname($file);
            if (in_array(basename($directory), $registeredDirectories, true)) {
                continue;
            }

            $inspection = $this->manifestValidator->inspect($directory);
            $manifest = $inspection['manifest'];
            if ($manifest === null || $manifest->factoryFile === null) {
                continue;
            }

            $this->states->registerDiscovered($manifest);
            $state = $this->states->find($manifest->id);
            if ($state === null || !$state->isActive()) {
                continue;
            }

            try {
                $factory = require $manifest->directory . '/' . $manifest->factoryFile;
                if (!is_callable($factory)) {
                    throw new RuntimeException(
                        "Fabryka pakietu {$manifest->id} nie zwróciła funkcji tworzącej moduł."
                    );
                }
                $module = $factory($services);
                if (!$module instanceof ModuleInterface) {
                    throw new RuntimeException(
                        "Fabryka pakietu {$manifest->id} nie zwróciła ModuleInterface."
                    );
                }
                $registry->add($module, $manifest);
            } catch (\Throwable) {
                // Rozszerzenie nie może przerwać startu Core ani panelu.
                continue;
            }
        }
    }
}
