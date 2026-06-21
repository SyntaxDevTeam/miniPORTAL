<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Core;

use RuntimeException;

final class ModuleManagerService
{
    public function __construct(
        private readonly string $modulesPath,
        private readonly ModuleManifestValidator $validator,
        private readonly ModuleStateRepository $states,
        private readonly ModuleInstaller $installer,
        private readonly array $registeredDirectories,
    ) {
    }

    /**
     * @return array<string, ModuleManifest>
     */
    public function manifests(): array
    {
        $manifests = [];
        foreach (glob(rtrim($this->modulesPath, '/') . '/*/info.json') ?: [] as $file) {
            $inspection = $this->validator->inspect(dirname($file));
            $manifest = $inspection['manifest'];
            if ($manifest === null) {
                continue;
            }
            $this->states->registerDiscovered($manifest);
            $manifests[$manifest->id] = $manifest;
        }
        ksort($manifests);

        return $manifests;
    }

    /**
     * @return list<array{
     *     manifest: ?ModuleManifest,
     *     state: ?ModuleState,
     *     directory: string,
     *     error: ?string,
     *     pending: list<string>,
     *     loadable: bool,
     *     update_available: bool
     * }>
     */
    public function modules(): array
    {
        $result = [];
        foreach (glob(rtrim($this->modulesPath, '/') . '/*/info.json') ?: [] as $file) {
            $directory = dirname($file);
            $inspection = $this->validator->inspect($directory);
            $manifest = $inspection['manifest'];
            if ($manifest === null) {
                $result[] = [
                    'manifest' => null,
                    'state' => null,
                    'directory' => basename($directory),
                    'error' => $inspection['error'] ?? 'Nieznany błąd manifestu.',
                    'pending' => [],
                    'loadable' => false,
                    'update_available' => false,
                ];
                continue;
            }

            $state = null;
            try {
                $this->states->registerDiscovered($manifest);
                $state = $this->states->find($manifest->id);
                if ($state === null) {
                    throw new RuntimeException("Nie można odczytać stanu modułu {$manifest->id}.");
                }
                $result[] = [
                    'manifest' => $manifest,
                    'state' => $state,
                    'directory' => basename($directory),
                    'error' => null,
                    'pending' => $state->isInstalled()
                        ? $this->installer->pendingMigrations($manifest)
                        : [],
                    'loadable' => $this->isLoadable($manifest),
                    'update_available' => $state->isInstalled()
                        && version_compare($manifest->version, $state->version, '>'),
                ];
            } catch (\Throwable $exception) {
                $result[] = [
                    'manifest' => $manifest,
                    'state' => $state,
                    'directory' => basename($directory),
                    'error' => $exception->getMessage(),
                    'pending' => [],
                    'loadable' => false,
                    'update_available' => false,
                ];
            }
        }
        usort(
            $result,
            static fn (array $left, array $right): int => strcmp($left['directory'], $right['directory'])
        );

        return $result;
    }

    public function install(string $moduleId): void
    {
        $manifest = $this->manifest($moduleId);
        $this->assertLoadable($manifest);
        foreach ($manifest->requiredModules as $dependency) {
            if (!$this->states->find($dependency)?->isActive()) {
                throw new RuntimeException("Najpierw aktywuj wymagany moduł {$dependency}.");
            }
        }
        $this->installer->install($manifest);
    }

    /**
     * @return list<string>
     */
    public function migrate(string $moduleId): array
    {
        $manifest = $this->manifest($moduleId);
        $this->assertLoadable($manifest);

        return $this->installer->migrate($manifest);
    }

    /**
     * @return list<string>
     */
    public function update(string $moduleId): array
    {
        $manifest = $this->manifest($moduleId);
        $this->assertLoadable($manifest);
        foreach ($manifest->requiredModules as $dependency) {
            if (!$this->states->find($dependency)?->isActive()) {
                throw new RuntimeException("Najpierw aktywuj wymagany moduł {$dependency}.");
            }
        }

        return $this->installer->update($manifest);
    }

    public function uninstall(string $moduleId, bool $preserveData): void
    {
        $manifest = $this->manifest($moduleId);
        $this->assertLoadable($manifest);
        $state = $this->states->find($moduleId);
        if ($state === null || !$state->isInstalled()) {
            throw new RuntimeException("Moduł {$moduleId} nie jest zainstalowany.");
        }
        if ($manifest->protected) {
            throw new RuntimeException("Moduł {$moduleId} jest chroniony i nie może zostać usunięty.");
        }
        if ($state->isActive()) {
            throw new RuntimeException("Przed odinstalowaniem wyłącz moduł {$moduleId}.");
        }
        foreach ($this->manifests() as $candidate) {
            if (
                in_array($moduleId, $candidate->requiredModules, true)
                && $this->states->find($candidate->id)?->isInstalled()
            ) {
                throw new RuntimeException("Zainstalowany moduł {$candidate->id} wymaga {$moduleId}.");
            }
        }

        $this->installer->uninstall($manifest, $preserveData);
    }

    /**
     * @return array{path: string, filename: string, mime: string}
     */
    public function exportPackage(string $moduleId): array
    {
        $manifest = $this->manifest($moduleId);
        $state = $this->states->find($moduleId);
        if ($state === null || !$state->isInstalled()) {
            throw new RuntimeException("Moduł {$moduleId} nie jest zainstalowany.");
        }
        if ($manifest->type !== 'extension' || $manifest->protected) {
            throw new RuntimeException('Eksportować można wyłącznie zainstalowane moduły typu rozszerzenie.');
        }

        $targetDirectory = dirname(rtrim($this->modulesPath, '/')) . '/cache/module-exports';

        return (new ModulePackageExporter())->exportZip($manifest, $targetDirectory);
    }

    public function toggle(string $moduleId, bool $active): void
    {
        $manifest = $this->manifest($moduleId);
        $this->assertLoadable($manifest);
        $state = $this->states->find($moduleId);
        if ($state === null || !$state->isInstalled()) {
            throw new RuntimeException("Moduł {$moduleId} nie jest zainstalowany.");
        }
        if (!$active && $manifest->protected) {
            throw new RuntimeException("Moduł {$moduleId} jest chroniony i nie może zostać wyłączony.");
        }

        if ($active) {
            foreach ($manifest->requiredModules as $dependency) {
                if (!$this->states->find($dependency)?->isActive()) {
                    throw new RuntimeException("Najpierw aktywuj wymagany moduł {$dependency}.");
                }
            }
        } else {
            foreach ($this->manifests() as $candidate) {
                if (
                    in_array($moduleId, $candidate->requiredModules, true)
                    && $this->states->find($candidate->id)?->isActive()
                ) {
                    throw new RuntimeException("Moduł {$candidate->id} wymaga aktywnego {$moduleId}.");
                }
            }
        }

        if (!$this->states->setActive($moduleId, $active)) {
            throw new RuntimeException("Nie udało się zmienić stanu modułu {$moduleId}.");
        }
    }

    /**
     * @return array{
     *     manifest: ModuleManifest,
     *     state: ModuleState,
     *     migrations: list<array{
     *         migration: string,
     *         checksum: string,
     *         executed_at: string,
     *         current_checksum: ?string,
     *         status: string
     *     }>
     * }
     */
    public function migrationHistory(string $moduleId): array
    {
        $manifest = $this->manifest($moduleId);
        $state = $this->states->find($moduleId);
        if ($state === null) {
            throw new RuntimeException("Nie znaleziono stanu modułu {$moduleId}.");
        }

        $migrations = [];
        foreach ($this->states->migrationHistory($moduleId) as $record) {
            $file = $manifest->directory . '/migrations/' . $record['migration'];
            $currentChecksum = is_file($file) ? hash_file('sha256', $file) : null;
            $migrations[] = $record + [
                'current_checksum' => is_string($currentChecksum) ? $currentChecksum : null,
                'status' => $currentChecksum === null
                    ? 'Brak pliku'
                    : (hash_equals($record['checksum'], $currentChecksum) ? 'Zgodna' : 'Zmieniona'),
            ];
        }

        return ['manifest' => $manifest, 'state' => $state, 'migrations' => $migrations];
    }

    private function manifest(string $moduleId): ModuleManifest
    {
        $manifest = $this->manifests()[$moduleId] ?? null;
        if ($manifest === null) {
            throw new RuntimeException("Nie znaleziono modułu {$moduleId}.");
        }

        return $manifest;
    }

    private function assertLoadable(ModuleManifest $manifest): void
    {
        if (!$this->isLoadable($manifest)) {
            if (
                !in_array(basename($manifest->directory), $this->registeredDirectories, true)
                && !in_array($manifest->signatureStatus, ['verified', 'verified_retired'], true)
            ) {
                throw new RuntimeException(
                    "Zewnętrzny moduł {$manifest->id} wymaga podpisu zweryfikowanego zaufanym kluczem."
                );
            }
            throw new RuntimeException(
                "Moduł {$manifest->id} nie ma zarejestrowanej fabryki wykonawczej."
            );
        }
    }

    private function isLoadable(ModuleManifest $manifest): bool
    {
        if (in_array(basename($manifest->directory), $this->registeredDirectories, true)) {
            return true;
        }

        return $manifest->factoryFile !== null
            && in_array($manifest->signatureStatus, ['verified', 'verified_retired'], true);
    }
}
