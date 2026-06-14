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
            $manifest = $this->validator->validate(dirname($file));
            $this->states->registerDiscovered($manifest);
            $manifests[$manifest->id] = $manifest;
        }
        ksort($manifests);

        return $manifests;
    }

    /**
     * @return list<array{
     *     manifest: ModuleManifest,
     *     state: ModuleState,
     *     pending: list<string>,
     *     loadable: bool
     * }>
     */
    public function modules(): array
    {
        $result = [];
        foreach ($this->manifests() as $manifest) {
            $state = $this->states->find($manifest->id);
            if ($state === null) {
                continue;
            }
            $result[] = [
                'manifest' => $manifest,
                'state' => $state,
                'pending' => $state->isInstalled() ? $this->installer->pendingMigrations($manifest) : [],
                'loadable' => $this->isLoadable($manifest),
            ];
        }

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
            throw new RuntimeException(
                "Moduł {$manifest->id} nie ma zarejestrowanej fabryki wykonawczej."
            );
        }
    }

    private function isLoadable(ModuleManifest $manifest): bool
    {
        return in_array(basename($manifest->directory), $this->registeredDirectories, true);
    }
}
