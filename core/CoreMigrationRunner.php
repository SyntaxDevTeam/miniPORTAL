<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Core;

use PDO;
use RuntimeException;
use SyntaxDevTeam\Cms\Database\CrudApp;

final class CoreMigrationRunner
{
    public function __construct(
        private readonly CrudApp $database,
        private readonly string $migrationsPath,
    ) {
    }

    /**
     * @return list<string>
     */
    public function run(): array
    {
        $pdo = $this->database->connection()->pdo;
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS core_migrations (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                migration VARCHAR(191) NOT NULL,
                checksum CHAR(64) NOT NULL,
                executed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_core_migrations_name (migration)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
        );
        $files = glob(rtrim($this->migrationsPath, '/') . '/*.sql') ?: [];
        sort($files, SORT_STRING);
        $executed = [];
        foreach ($files as $file) {
            $name = basename($file);
            $checksum = hash_file('sha256', $file);
            if (!is_string($checksum)) {
                throw new RuntimeException("Nie można obliczyć sumy kontrolnej {$name}.");
            }
            $statement = $pdo->prepare(
                'SELECT checksum FROM core_migrations WHERE migration = :migration LIMIT 1'
            );
            $statement->execute([':migration' => $name]);
            $recorded = $statement->fetchColumn();
            if (is_string($recorded)) {
                if (!hash_equals($recorded, $checksum)) {
                    throw new RuntimeException("Wykonana migracja Core {$name} została zmieniona.");
                }
                continue;
            }
            $sql = trim((string) file_get_contents($file));
            if ($sql !== '') {
                $pdo->exec($sql);
            }
            $insert = $pdo->prepare(
                'INSERT INTO core_migrations (migration, checksum) VALUES (:migration, :checksum)'
            );
            $insert->execute([':migration' => $name, ':checksum' => $checksum]);
            $executed[] = $name;
        }

        return $executed;
    }
}
