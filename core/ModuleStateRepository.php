<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Core;

use PDO;
use RuntimeException;
use SyntaxDevTeam\Cms\Database\CrudApp;

final class ModuleStateRepository
{
    public function __construct(
        private readonly CrudApp $database,
    ) {
    }

    /**
     * @return array<string, ModuleState>
     */
    public function all(): array
    {
        $statement = $this->database->query(
            'SELECT module_id, version, status, is_protected, data_preserved, installed_at, updated_at '
            . 'FROM modules_config ORDER BY module_id'
        );
        if ($statement === null) {
            throw new RuntimeException('Nie można pobrać konfiguracji modułów.');
        }

        $states = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $state = $this->hydrate($row);
            $states[$state->moduleId] = $state;
        }

        return $states;
    }

    public function find(string $moduleId): ?ModuleState
    {
        $statement = $this->database->query(
            'SELECT module_id, version, status, is_protected, data_preserved, installed_at, updated_at '
            . 'FROM modules_config WHERE module_id = :module_id LIMIT 1',
            [':module_id' => $moduleId]
        );
        $row = $statement?->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function registerDiscovered(ModuleManifest $manifest): void
    {
        $state = $this->find($manifest->id);
        if ($state === null) {
            $this->database->create('modules_config', [
                'module_id' => $manifest->id,
                'version' => $manifest->version,
                'status' => 'discovered',
                'is_protected' => $manifest->protected ? 1 : 0,
                'data_preserved' => 0,
                'installed_at' => null,
            ]);
            return;
        }

        $data = [
            'is_protected' => $manifest->protected ? 1 : 0,
        ];
        $this->database->update('modules_config', $data, ['module_id' => $manifest->id]);
    }

    public function markInstalled(ModuleManifest $manifest): void
    {
        $this->database->update('modules_config', [
            'version' => $manifest->version,
            'status' => 'active',
            'is_protected' => $manifest->protected ? 1 : 0,
            'data_preserved' => 0,
            'installed_at' => date('Y-m-d H:i:s'),
        ], ['module_id' => $manifest->id]);
    }

    public function markVersion(string $moduleId, string $version): void
    {
        $this->database->update('modules_config', ['version' => $version], ['module_id' => $moduleId]);
    }

    public function markUninstalled(string $moduleId, bool $preserveData): void
    {
        if (!$preserveData) {
            $this->database->delete('module_migrations', ['module_id' => $moduleId]);
        }

        $this->database->update('modules_config', [
            'status' => $preserveData ? 'uninstalled' : 'discovered',
            'data_preserved' => $preserveData ? 1 : 0,
            'installed_at' => null,
        ], ['module_id' => $moduleId]);
    }

    public function setActive(string $moduleId, bool $active): bool
    {
        $state = $this->find($moduleId);
        if ($state === null || !$state->isInstalled() || ($state->protected && !$active)) {
            return false;
        }

        $statement = $this->database->update(
            'modules_config',
            ['status' => $active ? 'active' : 'disabled'],
            ['module_id' => $moduleId]
        );

        return $statement !== null;
    }

    public function hasMigration(string $moduleId, string $migration, string $checksum): bool
    {
        return $this->database->count('module_migrations', [
            'module_id' => $moduleId,
            'migration' => $migration,
            'checksum' => $checksum,
        ]) === 1;
    }

    public function migrationChecksum(string $moduleId, string $migration): ?string
    {
        $statement = $this->database->query(
            'SELECT checksum FROM module_migrations '
            . 'WHERE module_id = :module_id AND migration = :migration LIMIT 1',
            [':module_id' => $moduleId, ':migration' => $migration]
        );
        $checksum = $statement?->fetchColumn();

        return is_string($checksum) ? $checksum : null;
    }

    public function recordMigration(string $moduleId, string $migration, string $checksum): void
    {
        $this->database->create('module_migrations', [
            'module_id' => $moduleId,
            'migration' => $migration,
            'checksum' => $checksum,
        ]);
    }

    /**
     * @return list<string>
     */
    public function migrations(string $moduleId): array
    {
        $rows = $this->database->read(
            'module_migrations',
            ['migration'],
            ['module_id' => $moduleId, 'ORDER' => ['migration' => 'ASC']]
        ) ?? [];

        return array_map(static fn (array $row): string => (string) $row['migration'], $rows);
    }

    private function hydrate(array $row): ModuleState
    {
        return new ModuleState(
            (string) $row['module_id'],
            (string) $row['version'],
            (string) $row['status'],
            (bool) $row['is_protected'],
            (bool) ($row['data_preserved'] ?? false),
            $row['installed_at'] !== null ? (string) $row['installed_at'] : null,
            (string) $row['updated_at'],
        );
    }
}
