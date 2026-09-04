<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Core;

interface SystemLogAgentInterface
{
    /** @return array<string, mixed> */
    public function systemLogs(int $retentionDays): array;

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function submitJob(string $idempotencyKey, string $type, string $instanceId, array $payload = []): array;

    /** @return array<string, mixed> */
    public function job(string $jobId): array;
}
