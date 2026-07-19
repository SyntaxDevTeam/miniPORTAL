<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Core;

use RuntimeException;
use SyntaxDevTeam\Cms\Database\CrudApp;

final class ModuleInstaller
{
    public function __construct(
        private readonly CrudApp $database,
        private readonly ModuleStateRepository $states,
    ) {
    }

    public function install(ModuleManifest $manifest): void
    {
        $state = $this->states->find($manifest->id);
        if ($state?->isInstalled()) {
            throw new RuntimeException("Moduł {$manifest->id} jest już zainstalowany.");
        }
        if ($state?->canRestorePreservedData()) {
            if (version_compare($manifest->version, $state->version, '<')) {
                throw new RuntimeException(
                    "Nie można przywrócić danych wersji {$state->version} przy użyciu starszego "
                    . "manifestu {$manifest->version}."
                );
            }
            $this->executePendingMigrations($manifest);
            $this->states->markInstalled($manifest);
            return;
        }
        if ($manifest->installFile === null) {
            throw new RuntimeException("Moduł {$manifest->id} nie posiada pliku instalacyjnego.");
        }

        $this->executeFile($manifest->directory . '/' . $manifest->installFile);
        $this->states->markInstalled($manifest);
        foreach ($this->migrationFiles($manifest) as $file) {
            $checksum = hash_file('sha256', $file);
            if (!is_string($checksum)) {
                throw new RuntimeException('Nie można obliczyć sumy kontrolnej migracji ' . basename($file) . '.');
            }
            $this->states->recordMigration($manifest->id, basename($file), $checksum);
        }
    }

    /**
     * @return list<string>
     */
    public function migrate(ModuleManifest $manifest): array
    {
        $state = $this->states->find($manifest->id);
        if ($state === null || !$state->isInstalled()) {
            throw new RuntimeException("Moduł {$manifest->id} nie jest zainstalowany.");
        }
        if ($manifest->version !== $state->version) {
            throw new RuntimeException(
                "Manifest ma wersję {$manifest->version}; użyj kontrolowanej aktualizacji modułu."
            );
        }

        $executed = $this->executePendingMigrations($manifest);

        return $executed;
    }

    /**
     * @return list<string>
     */
    public function update(ModuleManifest $manifest): array
    {
        $state = $this->states->find($manifest->id);
        if ($state === null || !$state->isInstalled()) {
            throw new RuntimeException("Moduł {$manifest->id} nie jest zainstalowany.");
        }
        if (version_compare($manifest->version, $state->version, '<=')) {
            throw new RuntimeException(
                "Aktualizacja wymaga wersji wyższej niż zainstalowana {$state->version}."
            );
        }

        $executed = $this->executePendingMigrations($manifest);
        $this->states->markVersion($manifest->id, $manifest->version);

        return $executed;
    }

    /**
     * @return list<string>
     */
    public function refresh(ModuleManifest $manifest): array
    {
        $state = $this->states->find($manifest->id);
        if ($state === null || !$state->isInstalled()) {
            throw new RuntimeException("Moduł {$manifest->id} nie jest zainstalowany.");
        }
        if ($manifest->version !== $state->version) {
            throw new RuntimeException(
                "Odświeżenie kodu wymaga tej samej wersji manifestu i instalacji ({$state->version})."
            );
        }

        return $this->executePendingMigrations($manifest);
    }

    public function uninstall(ModuleManifest $manifest, bool $preserveData): void
    {
        $state = $this->states->find($manifest->id);
        if ($state === null || !$state->isInstalled()) {
            throw new RuntimeException("Moduł {$manifest->id} nie jest zainstalowany.");
        }
        if (!$preserveData) {
            if ($manifest->uninstallFile === null) {
                throw new RuntimeException(
                    "Moduł {$manifest->id} nie deklaruje bezpiecznego uninstall.sql."
                );
            }
            $this->executeFile($manifest->directory . '/' . $manifest->uninstallFile);
        }

        $this->states->markUninstalled($manifest->id, $preserveData);
    }

    /**
     * @return list<string>
     */
    public function pendingMigrations(ModuleManifest $manifest): array
    {
        return array_map(
            static fn (array $migration): string => $migration['name'],
            $this->migrationPlan($manifest)
        );
    }

    /**
     * Wszystkie sumy są sprawdzane przed pierwszym DDL. Zapobiega to rozpoczęciu
     * aktualizacji, gdy wcześniej wykonany plik migracji został zmodyfikowany.
     *
     * @return list<string>
     */
    private function executePendingMigrations(ModuleManifest $manifest): array
    {
        $plan = $this->migrationPlan($manifest);
        $executed = [];
        foreach ($plan as $migration) {
            $this->executeFile($migration['file']);
            $this->states->recordMigration(
                $manifest->id,
                $migration['name'],
                $migration['checksum']
            );
            $executed[] = $migration['name'];
        }

        return $executed;
    }

    /**
     * @return list<array{file: string, name: string, checksum: string}>
     */
    private function migrationPlan(ModuleManifest $manifest): array
    {
        $pending = [];
        foreach ($this->migrationFiles($manifest) as $file) {
            $name = basename($file);
            $checksum = hash_file('sha256', $file);
            if (!is_string($checksum)) {
                throw new RuntimeException("Nie można obliczyć sumy kontrolnej migracji {$name}.");
            }
            $recordedChecksum = $this->states->migrationChecksum($manifest->id, $name);
            if ($recordedChecksum === $checksum) {
                continue;
            }
            if ($recordedChecksum !== null) {
                throw new RuntimeException("Wykonana migracja {$name} została zmieniona.");
            }
            $pending[] = ['file' => $file, 'name' => $name, 'checksum' => $checksum];
        }

        return $pending;
    }

    /**
     * @return list<string>
     */
    private function migrationFiles(ModuleManifest $manifest): array
    {
        $files = glob($manifest->directory . '/migrations/*.sql') ?: [];
        sort($files, SORT_STRING);

        return array_values($files);
    }

    private function executeFile(string $file): void
    {
        if (!is_file($file) || !is_readable($file)) {
            throw new RuntimeException("Plik SQL {$file} nie jest dostępny.");
        }
        $sql = trim((string) file_get_contents($file));
        if ($sql === '') {
            return;
        }

        $pdo = $this->database->connection()->pdo;
        $pdo->exec($sql);
    }
}
