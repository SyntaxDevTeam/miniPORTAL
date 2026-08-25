<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\BuildExplorer;

use RuntimeException;

final class BuildRetentionService
{
    private const FALLBACK_HARD_LIMIT_BYTES = 26843545600;
    private const FALLBACK_RESERVE_BYTES = 5368709120;

    public function __construct(
        private readonly BuildRepository $builds,
        private readonly BuildArtifactStorage $storage,
    ) {}

    /** @return array<string, mixed> */
    public function status(bool $includePreview = true): array
    {
        $settings = $this->builds->retentionSettings();
        $storage = $this->storage->statistics();
        $result = [
            'policy' => [
                'release_keep' => null,
                'snapshot_keep' => $settings['snapshot_keep'],
                'dev_keep' => $settings['dev_keep'],
                'wip_keep' => $settings['wip_keep'],
                'soft_limit_bytes' => $settings['soft_limit_bytes'],
                'hard_limit_bytes' => $settings['hard_limit_bytes'],
                'reserve_bytes' => $settings['reserve_bytes'],
                'cleanup_interval_hours' => $settings['cleanup_interval_hours'],
                'orphan_min_age_hours' => $settings['orphan_min_age_hours'],
            ],
            'storage' => $storage,
            'last_cleanup' => [
                'at' => $settings['last_cleanup_at'],
                'reason' => $settings['last_cleanup_reason'],
                'releases' => $settings['last_cleanup_releases'],
                'artifacts' => $settings['last_cleanup_artifacts'],
                'bytes' => $settings['last_cleanup_bytes'],
                'orphans' => $settings['last_cleanup_orphans'],
            ],
            'soft_limit_exceeded' => $storage['bytes'] >= $settings['soft_limit_bytes'],
            'hard_limit_exceeded' => $storage['bytes'] >= $settings['hard_limit_bytes'],
            'reserve_at_risk' => $storage['disk_free_bytes'] !== null
                && $storage['disk_free_bytes'] <= $settings['reserve_bytes'],
        ];
        if ($includePreview) {
            $result['preview'] = $this->preview();
        }
        return $result;
    }

    /** @return list<array<string, int|string|null>> */
    public function candidates(): array
    {
        $settings = $this->builds->retentionSettings();
        return $this->builds->retentionCandidates(
            $settings['snapshot_keep'],
            $settings['dev_keep'],
            $settings['wip_keep'],
        );
    }

    /** @return list<array<string, int|string|null>> */
    public function lockedReleases(): array
    {
        return $this->builds->retentionLockedReleases();
    }

    /** @return array<string, int> */
    public function preview(): array
    {
        $candidates = $this->candidates();
        $bytes = 0;
        $artifacts = 0;
        foreach ($candidates as $candidate) {
            foreach ($this->builds->v2ReleaseStorageKeys((int) $candidate['id']) as $artifact) {
                $artifacts++;
                $bytes += max(0, (int) $artifact['file_size_bytes']);
            }
        }
        $settings = $this->builds->retentionSettings();
        $orphans = $this->storage->orphanKeys(
            $this->registeredStorageKeys(),
            $settings['orphan_min_age_hours'] * 3600,
        );
        $orphanBytes = 0;
        foreach ($orphans as $key) {
            $orphanBytes += $this->storage->sizeForKey($key);
        }
        return [
            'releases' => count($candidates),
            'artifacts' => $artifacts,
            'release_bytes' => $bytes,
            'orphans' => count($orphans),
            'orphan_bytes' => $orphanBytes,
            'total_bytes' => $bytes + $orphanBytes,
        ];
    }

    public function prepareForUpload(int $incomingBytes): void
    {
        try {
            $this->runDue();
            $settings = $this->builds->retentionSettings();
            $storage = $this->storage->statistics();
            if ($storage['bytes'] + max(0, $incomingBytes) > $settings['soft_limit_bytes']) {
                $this->cleanup('soft_limit');
                $settings = $this->builds->retentionSettings();
            }
            $this->storage->assertCapacity(
                $incomingBytes,
                $settings['hard_limit_bytes'],
                $settings['reserve_bytes'],
            );
        } catch (RuntimeException $exception) {
            if (!str_contains($exception->getMessage(), 'retention settings are unavailable')) {
                throw $exception;
            }
            $this->storage->assertCapacity(
                $incomingBytes,
                self::FALLBACK_HARD_LIMIT_BYTES,
                self::FALLBACK_RESERVE_BYTES,
            );
        }
    }

    /** @return array<string, int>|null */
    public function runDue(): ?array
    {
        $settings = $this->builds->retentionSettings();
        if (!$this->isDue($settings)) {
            return null;
        }
        return $this->cleanup('scheduled', false);
    }

    /** @return array<string, int> */
    public function cleanup(string $reason = 'manual', bool $waitForLock = true): array
    {
        if (!$this->builds->acquireRetentionLock($waitForLock ? 5 : 0)) {
            if ($waitForLock) {
                throw new RuntimeException('Build retention cleanup is already running.');
            }
            return ['releases' => 0, 'artifacts' => 0, 'bytes' => 0, 'orphans' => 0];
        }
        try {
            if ($reason === 'scheduled') {
                $fresh = $this->builds->retentionSettings();
                if (!$this->isDue($fresh)) {
                    return ['releases' => 0, 'artifacts' => 0, 'bytes' => 0, 'orphans' => 0];
                }
            }
            return $this->cleanupLocked($reason);
        } finally {
            $this->builds->releaseRetentionLock();
        }
    }

    public function setRetentionLock(int $releaseId, bool $locked): bool
    {
        return $this->builds->setV2RetentionLock($releaseId, $locked);
    }

    /** @param array<string, mixed> $settings */
    private function isDue(array $settings): bool
    {
        $last = trim((string) ($settings['last_cleanup_at'] ?? ''));
        if ($last === '') {
            return true;
        }
        $timestamp = strtotime($last . ' UTC');
        if ($timestamp === false) {
            return true;
        }
        return time() - $timestamp >= max(1, (int) $settings['cleanup_interval_hours']) * 3600;
    }

    /** @return array<string, int> */
    private function cleanupLocked(string $reason): array
    {
        $settings = $this->builds->retentionSettings();
        $candidates = $this->builds->retentionCandidates(
            $settings['snapshot_keep'],
            $settings['dev_keep'],
            $settings['wip_keep'],
        );
        $deletedReleases = 0;
        $deletedArtifacts = 0;
        $freedBytes = 0;

        foreach ($candidates as $candidate) {
            $releaseId = (int) $candidate['id'];
            $artifacts = $this->builds->v2ReleaseStorageKeys($releaseId);
            if (!$this->builds->deleteV2Release($releaseId)) {
                continue;
            }
            $deletedReleases++;
            foreach ($artifacts as $artifact) {
                $deletedArtifacts++;
                $key = (string) ($artifact['storage_key'] ?? '');
                if ($key !== '' && $this->storage->delete($key)) {
                    $freedBytes += max(0, (int) $artifact['file_size_bytes']);
                }
            }
        }

        $orphans = $this->storage->orphanKeys(
            $this->registeredStorageKeys(),
            $settings['orphan_min_age_hours'] * 3600,
        );
        $deletedOrphans = 0;
        foreach ($orphans as $key) {
            $size = $this->storage->sizeForKey($key);
            if ($this->storage->delete($key)) {
                $deletedOrphans++;
                $freedBytes += $size;
            }
        }

        $result = [
            'releases' => $deletedReleases,
            'artifacts' => $deletedArtifacts,
            'bytes' => $freedBytes,
            'orphans' => $deletedOrphans,
        ];
        $this->builds->markRetentionCleanup($reason, $result);
        return $result;
    }

    /** @return list<string> */
    private function registeredStorageKeys(): array
    {
        $keys = $this->builds->registeredV2StorageKeys();
        foreach ($this->builds->all(false) as $legacyBuild) {
            if ($legacyBuild->storageKey !== '') {
                $keys[] = $legacyBuild->storageKey;
            }
        }
        return array_values(array_unique($keys));
    }
}
