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

        $executed = [];
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

            $this->executeFile($file);
            $this->states->recordMigration($manifest->id, $name, $checksum);
            $executed[] = $name;
        }
        $this->states->markVersion($manifest->id, $manifest->version);

        return $executed;
    }

    /**
     * @return list<string>
     */
    public function pendingMigrations(ModuleManifest $manifest): array
    {
        $pending = [];
        foreach ($this->migrationFiles($manifest) as $file) {
            $name = basename($file);
            $checksum = hash_file('sha256', $file);
            $recordedChecksum = $this->states->migrationChecksum($manifest->id, $name);
            if (is_string($checksum) && $recordedChecksum !== $checksum) {
                $pending[] = $name;
            }
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
