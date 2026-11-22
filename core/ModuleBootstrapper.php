<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Core;

use RuntimeException;
use SyntaxDevTeam\Cms\Database\CrudApp;

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
     * @param list<string>|null $onlyDirectories
     */
    public function register(
        array $definitions,
        array $services,
        ModuleRegistry $registry,
        ?array $onlyDirectories = null,
    ): void
    {
        $definitions = LocalRuntime::definitions(dirname(rtrim($this->modulesPath, '/')), $definitions);
        $runtimeServices = $services;
        $runtimeServices['module_registry'] = $registry;
        $runtimeServices['capabilities'] = $registry->capabilities();
        $runtimeServices['event_bus'] = $registry->events();

        // `database` is an existing, intentionally generic runtime service. New Core
        // services may use CrudApp when available, but must not make a previously
        // accepted custom database adapter a bootstrap-time incompatibility.
        $database = ($services['database'] ?? null) instanceof CrudApp
            ? $services['database']
            : null;
        $auditHashKey = is_array($services['auth_config'] ?? null)
            ? (string) (($services['auth_config']['audit_hash_key'] ?? ''))
            : '';
        $runtimeServices['platform_audit'] = new AuditService(
            $database !== null ? new DatabaseAuditStore($database) : null,
            $auditHashKey,
        );
        $runtimeServices['api_tokens'] = new ApiTokenService(
            $database !== null ? new DatabaseApiTokenStore($database) : null,
        );

        $registeredDirectories = [];
        $states = $this->states?->all() ?? [];
        $selectedDirectories = $onlyDirectories !== null
            ? array_fill_keys(array_values(array_unique($onlyDirectories)), true)
            : null;
        foreach ($definitions as $definition) {
            $directory = (string) ($definition['directory'] ?? '');
            $factory = $definition['factory'] ?? null;
            $enabled = $definition['enabled'] ?? true;
            $required = ($definition['required'] ?? false) === true;

            if ($directory === '' || basename($directory) !== $directory || !is_callable($factory)) {
                throw new RuntimeException('Deklaracja modułu jest nieprawidłowa.');
            }
            $registeredDirectories[] = $directory;

            if ($selectedDirectories !== null && !isset($selectedDirectories[$directory])) {
                continue;
            }

            $isEnabled = is_callable($enabled) ? (bool) $enabled($runtimeServices) : $enabled === true;
            if (!$isEnabled) {
                continue;
            }

            $inspection = $this->manifestValidator->inspectRuntime(
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
            $state = $states[$manifest->id] ?? null;
            if (
                $this->states !== null
                && ($state === null || !$state->isActive())
            ) {
                continue;
            }
            try {
                $module = $factory($runtimeServices);
                if (!$module instanceof ModuleInterface) {
                    throw new RuntimeException("Fabryka {$directory} nie zwróciła ModuleInterface.");
                }

                $registry->add($module, $manifest);
            } catch (\Throwable $exception) {
                if ($required) {
                    throw new RuntimeException(
                        "Nie można uruchomić wymaganego modułu {$directory}: " . $exception->getMessage(),
                        0,
                        $exception
                    );
                }
                error_log(
                    '[miniPORTAL] optional_module_bootstrap_failed module=' . $manifest->id
                    . ' exception=' . $exception::class
                    . ' message=' . str_replace(["\r", "\n"], ' ', $exception->getMessage())
                );
            }
        }

        if ($selectedDirectories === null) {
            $this->registerPackageFactories($registeredDirectories, $runtimeServices, $registry, $states);
        }
    }

    /**
     * Ładuje kod pakietu dopiero po jego instalacji i aktywacji.
     *
     * @param list<string> $registeredDirectories
     */
    private function registerPackageFactories(
        array $registeredDirectories,
        array $services,
        ModuleRegistry $registry,
        array $states,
    ): void {
        if ($this->states === null) {
            return;
        }

        foreach (glob(rtrim($this->modulesPath, '/') . '/*/info.json') ?: [] as $file) {
            $directory = dirname($file);
            if (in_array(basename($directory), $registeredDirectories, true)) {
                continue;
            }

            $runtimeInspection = $this->manifestValidator->inspectRuntime($directory);
            $runtimeManifest = $runtimeInspection['manifest'];
            $state = $runtimeManifest !== null ? ($states[$runtimeManifest->id] ?? null) : null;
            if (
                $runtimeManifest === null
                || $runtimeManifest->factoryFile === null
                || $state === null
                || !$state->isActive()
            ) {
                continue;
            }

            $inspection = $this->manifestValidator->inspect($directory);
            $manifest = $inspection['manifest'];
            if (
                $manifest === null
                || $manifest->factoryFile === null
                || !in_array($manifest->signatureStatus, ['verified', 'verified_retired'], true)
            ) {
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
