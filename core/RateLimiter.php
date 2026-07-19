<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Core;

final class RateLimiter
{
    public function __construct(
        private readonly string $path,
    ) {
    }

    /**
     * @return array{limited: bool, retry_after: int}
     */
    public function hit(string $scope, string $key, int $limit, int $windowSeconds): array
    {
        $limit = max(1, $limit);
        $windowSeconds = max(1, $windowSeconds);
        $bucket = $this->bucketFile($scope, $key);
        $now = time();
        $windowStart = $now - $windowSeconds;
        $handle = fopen($bucket, 'c+');
        if ($handle === false) {
            return ['limited' => false, 'retry_after' => 0];
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                return ['limited' => false, 'retry_after' => 0];
            }
            $contents = stream_get_contents($handle);
            $timestamps = $contents !== '' ? array_map('intval', explode("\n", trim($contents))) : [];
            $timestamps = array_values(array_filter(
                $timestamps,
                static fn (int $timestamp): bool => $timestamp >= $windowStart && $timestamp <= $now
            ));
            if (count($timestamps) >= $limit) {
                $oldest = min($timestamps);

                return ['limited' => true, 'retry_after' => max(1, ($oldest + $windowSeconds) - $now)];
            }
            $timestamps[] = $now;
            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, implode("\n", $timestamps));
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }

        return ['limited' => false, 'retry_after' => 0];
    }

    private function bucketFile(string $scope, string $key): string
    {
        if (!is_dir($this->path)) {
            @mkdir($this->path, 0770, true);
        }
        $scope = preg_replace('/[^a-z0-9_.-]+/i', '-', $scope) ?: 'default';

        return rtrim($this->path, '/') . '/' . strtolower($scope) . '-' . sha1($key) . '.rate';
    }
}
