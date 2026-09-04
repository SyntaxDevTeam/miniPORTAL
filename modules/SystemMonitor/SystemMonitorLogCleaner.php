<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\SystemMonitor;

use RuntimeException;
use SyntaxDevTeam\Cms\Core\SystemLogAgentInterface;
use Throwable;

/**
 * Thin, non-privileged facade for system log maintenance.
 *
 * miniPORTAL never deletes host logs itself. Read and mutation operations are
 * delegated to the local Node Agent over its Unix socket.
 */
final class SystemMonitorLogCleaner
{
    private const DEFAULT_RETENTION_DAYS = 14;
    private const MIN_RETENTION_DAYS = 1;
    private const MAX_RETENTION_DAYS = 3650;

    public function __construct(
        private readonly ?SystemLogAgentInterface $agent,
        private readonly string $applicationRoot,
    ) {
    }

    /** @return array<string, mixed> */
    public function preview(?int $retentionDays = null): array
    {
        $retentionDays = $this->retentionDays($retentionDays);

        if ($this->agent === null) {
            return $this->unavailablePreview($retentionDays);
        }

        try {
            $snapshot = $this->agent->systemLogs($retentionDays);
            $snapshot['available'] = true;
            $snapshot['retention_days'] = $retentionDays;
            $snapshot['execution'] = 'node_agent';

            return $snapshot;
        } catch (Throwable $exception) {
            return [
                'available' => false,
                'retention_days' => $retentionDays,
                'execution' => 'node_agent',
                'agent_required' => true,
                'candidate_files' => 0,
                'candidate_bytes' => 0,
                'reclaimable_bytes' => 0,
                'protected_bytes' => 0,
                'candidates' => [],
                'error' => $this->agentError($exception),
            ];
        }
    }

    /** @return array<string, mixed> */
    public function clean(?int $retentionDays = null, bool $includeJournal = true): array
    {
        $retentionDays = $this->retentionDays($retentionDays);
        if ($this->agent === null) {
            throw new RuntimeException('Node Agent nie jest zainstalowany w tej instancji miniPORTAL.');
        }

        return $this->agent->submitJob(
            self::uuid(),
            'system.logs.clean',
            '',
            [
                'retention_days' => $retentionDays,
                'include_journal' => $includeJournal,
            ]
        );
    }

    /** @return array<string, mixed> */
    public function job(string $jobId): array
    {
        if ($this->agent === null) {
            throw new RuntimeException('Node Agent nie jest zainstalowany w tej instancji miniPORTAL.');
        }
        $job = $this->agent->job($jobId);
        if ((string) ($job['status'] ?? '') === 'succeeded') {
            $this->invalidateInfrastructureCache();
        }

        return $job;
    }

    private function invalidateInfrastructureCache(): void
    {
        $root = realpath($this->applicationRoot) ?: $this->applicationRoot;
        $cacheFile = rtrim(sys_get_temp_dir(), '/') . '/miniportal-system-monitor-'
            . substr(hash('sha256', $root), 0, 16)
            . '.json';
        if (is_file($cacheFile)) {
            @unlink($cacheFile);
        }
    }

    private function retentionDays(?int $requested): int
    {
        if ($requested === null) {
            return self::DEFAULT_RETENTION_DAYS;
        }

        return max(self::MIN_RETENTION_DAYS, min(self::MAX_RETENTION_DAYS, $requested));
    }

    private function agentError(Throwable $exception): string
    {
        $message = trim($exception->getMessage());
        if ($message !== '') {
            return $message;
        }

        return 'Node Agent nie udostępnia jeszcze obsługi logów systemowych.';
    }

    /** @return array<string, mixed> */
    private function unavailablePreview(int $retentionDays): array
    {
        return [
            'available' => false,
            'retention_days' => $retentionDays,
            'execution' => 'node_agent',
            'agent_required' => true,
            'candidate_files' => 0,
            'candidate_bytes' => 0,
            'reclaimable_bytes' => 0,
            'protected_bytes' => 0,
            'candidates' => [],
            'error' => 'Node Agent nie jest zainstalowany w tej instancji miniPORTAL.',
        ];
    }

    private static function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);

        return substr($hex, 0, 8)
            . '-' . substr($hex, 8, 4)
            . '-' . substr($hex, 12, 4)
            . '-' . substr($hex, 16, 4)
            . '-' . substr($hex, 20);
    }
}
