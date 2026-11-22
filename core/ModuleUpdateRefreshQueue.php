<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Core;

use JsonException;
use RuntimeException;

final class ModuleUpdateRefreshQueue
{
    private const STATE_FILE = 'refresh-state.json';
    private const LOCK_FILE = 'refresh-state.lock';

    public function __construct(private readonly string $repositoryPath)
    {
    }

    /** @return array<string, int|string|null> */
    public function request(?int $requestedBy = null): array
    {
        return $this->withLock(function (array $state) use ($requestedBy): array {
            if (in_array($state['status'], ['queued', 'running'], true)) {
                return $state;
            }

            return $this->newJob($requestedBy);
        }, true);
    }

    /** @return array<string, int|string|null>|null */
    public function claim(int $staleAfterSeconds = 1800, ?int $refreshIntervalSeconds = null): ?array
    {
        $claimed = false;
        $state = $this->withLock(function (array $state) use (
            $staleAfterSeconds,
            $refreshIntervalSeconds,
            &$claimed,
        ): array {
            $staleRunning = $state['status'] === 'running'
                && $this->timestampAge((string) ($state['started_at'] ?? '')) >= max(60, $staleAfterSeconds);
            if (
                $refreshIntervalSeconds !== null
                && in_array($state['status'], ['idle', 'completed', 'failed'], true)
                && $this->timestampAge((string) ($state['finished_at'] ?? '')) >= $refreshIntervalSeconds
            ) {
                $state = $this->newJob(null);
            }

            if ($state['status'] !== 'queued' && !$staleRunning) {
                return $state;
            }

            $claimed = true;
            $state['status'] = 'running';
            $state['started_at'] = gmdate(\DateTimeInterface::ATOM);
            $state['finished_at'] = null;
            $state['published_modules'] = 0;
            $state['duration_ms'] = 0;
            $state['error'] = null;

            return $state;
        }, true);

        return $claimed ? $state : null;
    }

    public function complete(string $jobId, int $publishedModules, int $durationMs): void
    {
        $this->finish($jobId, 'completed', $publishedModules, $durationMs, null);
    }

    public function fail(string $jobId, int $durationMs, string $error): void
    {
        $safeError = trim(preg_replace('/\s+/u', ' ', $error) ?? '');
        $this->finish(
            $jobId,
            'failed',
            0,
            $durationMs,
            mb_substr($safeError !== '' ? $safeError : 'Nieznany błąd odświeżania.', 0, 500)
        );
    }

    /** @return array<string, int|string|null> */
    public function status(): array
    {
        return $this->withLock(static fn (array $state): array => $state, false);
    }

    /** @return array<string, int|string|null> */
    private function newJob(?int $requestedBy): array
    {
        return array_replace($this->idleState(), [
            'job_id' => bin2hex(random_bytes(12)),
            'status' => 'queued',
            'requested_at' => gmdate(\DateTimeInterface::ATOM),
            'requested_by' => $requestedBy,
        ]);
    }

    private function finish(
        string $jobId,
        string $status,
        int $publishedModules,
        int $durationMs,
        ?string $error,
    ): void {
        $this->withLock(static function (array $state) use (
            $jobId,
            $status,
            $publishedModules,
            $durationMs,
            $error,
        ): array {
            if ($state['status'] !== 'running' || !hash_equals((string) $state['job_id'], $jobId)) {
                throw new RuntimeException('Zadanie katalogu nie jest już aktywne.');
            }
            $state['status'] = $status;
            $state['finished_at'] = gmdate(\DateTimeInterface::ATOM);
            $state['published_modules'] = max(0, $publishedModules);
            $state['duration_ms'] = max(0, $durationMs);
            $state['error'] = $error;

            return $state;
        }, true);
    }

    /**
     * @param callable(array<string, int|string|null>): array<string, int|string|null> $operation
     * @return array<string, int|string|null>
     */
    private function withLock(callable $operation, bool $write): array
    {
        $this->ensureRepository();
        $lock = fopen($this->repositoryPath . '/' . self::LOCK_FILE, 'c+');
        if (!is_resource($lock) || !flock($lock, LOCK_EX)) {
            if (is_resource($lock)) {
                fclose($lock);
            }
            throw new RuntimeException('Nie można zablokować kolejki katalogu modułów.');
        }

        try {
            $state = $this->readState();
            $result = $operation($state);
            if ($write && $result !== $state) {
                $this->writeState($result);
            }

            return $result;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function ensureRepository(): void
    {
        if (!is_dir($this->repositoryPath) && !mkdir($this->repositoryPath, 0770, true)) {
            throw new RuntimeException('Nie można utworzyć katalogu kolejki aktualizacji modułów.');
        }
        if (!is_writable($this->repositoryPath)) {
            throw new RuntimeException('Katalog kolejki aktualizacji modułów nie jest zapisywalny.');
        }
    }

    /** @return array<string, int|string|null> */
    private function readState(): array
    {
        $file = $this->repositoryPath . '/' . self::STATE_FILE;
        if (!is_file($file)) {
            return $this->idleState();
        }
        try {
            $state = json_decode((string) file_get_contents($file), true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $this->idleState();
        }
        if (!is_array($state) || !in_array($state['status'] ?? null, [
            'idle', 'queued', 'running', 'completed', 'failed',
        ], true)) {
            return $this->idleState();
        }

        return array_replace($this->idleState(), array_intersect_key($state, $this->idleState()));
    }

    /** @param array<string, int|string|null> $state */
    private function writeState(array $state): void
    {
        $file = $this->repositoryPath . '/' . self::STATE_FILE;
        $temporary = $file . '.tmp-' . bin2hex(random_bytes(5));
        $payload = json_encode(
            $state,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        ) . PHP_EOL;
        if (file_put_contents($temporary, $payload, LOCK_EX) === false || !rename($temporary, $file)) {
            @unlink($temporary);
            throw new RuntimeException('Nie można atomowo zapisać stanu kolejki katalogu.');
        }
        @chmod($file, 0660);
    }

    /** @return array<string, int|string|null> */
    private function idleState(): array
    {
        return [
            'job_id' => '',
            'status' => 'idle',
            'requested_at' => null,
            'requested_by' => null,
            'started_at' => null,
            'finished_at' => null,
            'published_modules' => 0,
            'duration_ms' => 0,
            'error' => null,
        ];
    }

    private function timestampAge(string $value): int
    {
        $timestamp = strtotime($value);

        return $timestamp === false ? PHP_INT_MAX : max(0, time() - $timestamp);
    }
}
