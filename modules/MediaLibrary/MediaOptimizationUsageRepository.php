<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\MediaLibrary;

use PDO;
use SyntaxDevTeam\Cms\Database\CrudApp;

final class MediaOptimizationUsageRepository
{
    public function __construct(private readonly CrudApp $database)
    {
    }

    public function currentMonth(): string
    {
        return gmdate('Y-m');
    }

    public function used(string $provider, ?string $periodMonth = null): int
    {
        $periodMonth ??= $this->currentMonth();
        $statement = $this->database->connection()->pdo->prepare(
            'SELECT used_count FROM media_optimization_usage WHERE provider = :provider AND period_month = :period_month LIMIT 1'
        );
        $statement->bindValue(':provider', $provider, PDO::PARAM_STR);
        $statement->bindValue(':period_month', $periodMonth, PDO::PARAM_STR);
        $statement->execute();

        return max(0, (int) ($statement->fetchColumn() ?: 0));
    }

    public function canUse(string $provider, int $limit, ?string $periodMonth = null): bool
    {
        return $this->used($provider, $periodMonth) < $limit;
    }

    public function reserveUse(string $provider, int $limit, ?string $periodMonth = null): bool
    {
        $periodMonth ??= $this->currentMonth();
        $pdo = $this->database->connection()->pdo;
        $pdo->beginTransaction();
        try {
            $insert = $pdo->prepare(
                'INSERT IGNORE INTO media_optimization_usage (provider, period_month, used_count) '
                . 'VALUES (:provider, :period_month, 0)'
            );
            $insert->bindValue(':provider', $provider, PDO::PARAM_STR);
            $insert->bindValue(':period_month', $periodMonth, PDO::PARAM_STR);
            $insert->execute();

            $statement = $pdo->prepare(
                'SELECT used_count FROM media_optimization_usage '
                . 'WHERE provider = :provider AND period_month = :period_month FOR UPDATE'
            );
            $statement->bindValue(':provider', $provider, PDO::PARAM_STR);
            $statement->bindValue(':period_month', $periodMonth, PDO::PARAM_STR);
            $statement->execute();
            $current = $statement->fetchColumn();
            if ($current !== false && (int) $current >= $limit) {
                $pdo->rollBack();
                return false;
            }

            $update = $pdo->prepare(
                'UPDATE media_optimization_usage SET used_count = used_count + 1 '
                . 'WHERE provider = :provider AND period_month = :period_month'
            );
            $update->bindValue(':provider', $provider, PDO::PARAM_STR);
            $update->bindValue(':period_month', $periodMonth, PDO::PARAM_STR);
            $update->execute();
            $pdo->commit();

            return true;
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function releaseUse(string $provider, ?string $periodMonth = null): void
    {
        $periodMonth ??= $this->currentMonth();
        $statement = $this->database->connection()->pdo->prepare(
            'UPDATE media_optimization_usage SET used_count = GREATEST(used_count - 1, 0) '
            . 'WHERE provider = :provider AND period_month = :period_month'
        );
        $statement->bindValue(':provider', $provider, PDO::PARAM_STR);
        $statement->bindValue(':period_month', $periodMonth, PDO::PARAM_STR);
        $statement->execute();
    }

    public function recordUse(string $provider, ?string $periodMonth = null): void
    {
        $periodMonth ??= $this->currentMonth();
        $statement = $this->database->connection()->pdo->prepare(
            'INSERT INTO media_optimization_usage (provider, period_month, used_count) '
            . 'VALUES (:provider, :period_month, 1) '
            . 'ON DUPLICATE KEY UPDATE used_count = used_count + 1'
        );
        $statement->bindValue(':provider', $provider, PDO::PARAM_STR);
        $statement->bindValue(':period_month', $periodMonth, PDO::PARAM_STR);
        $statement->execute();
    }
}
