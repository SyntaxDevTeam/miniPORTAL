<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\BuildExplorer;

use PDO;
use SyntaxDevTeam\Cms\Core\Request;
use SyntaxDevTeam\Cms\Modules\CoreAuth\AuditLogService;
use RuntimeException;
use SyntaxDevTeam\Cms\Database\CrudApp;

/** CLI control plane only. HTTP downloads continue using their existing IDs and ACL. */
final class GitHubBuildStorage
{
    public function __construct(
        private readonly CrudApp $database,
        private readonly BuildArtifactStorage $storage,
        private readonly array $projects,
        private readonly ?AuditLogService $audit = null,
    ) {}

    public function candidates(int $limit = 500): array
    {
        $result = [];
        foreach (['v2', 'legacy'] as $kind) {
            foreach ($this->rows($kind) as $row) {
                if (!$this->allowed($row)) { continue; }
                $path = $this->storage->path((string) $row['storage_key']);
                if ($path === null || filesize($path) !== (int) $row['file_size_bytes']) {
                    throw new RuntimeException('Missing or inconsistent source artifact: ' . $kind . ':' . $row['id']);
                }
                $row['kind'] = $kind;
                $row['repository'] = $this->projects[$row['slug']]['repository'];
                $row['path'] = $path;
                $result[] = $row;
                if (count($result) >= max(1, min(1000, $limit))) { return $result; }
            }
        }
        return $result;
    }

    /** Full user-facing metadata for local and already exported, explicitly allowed JARs. */
    public function publications(): array
    {
        $result = [];
        foreach (['v2', 'legacy'] as $kind) {
            $sql = $kind === 'v2'
                ? 'SELECT a.id,r.id release_id,p.slug,p.name,p.summary project_summary,a.filename,a.storage_key,a.download_url,a.checksum_sha256,a.file_size_bytes,r.version_label,r.channel,r.build_number,r.commit_sha,r.changelog,r.commits_json,r.published_at,r.ci_build_time,a.platform,a.java_version,r.is_published published,p.is_published project_published,COALESCE(s.requires_auth,0) requires_auth FROM build_artifacts a JOIN build_releases r ON r.id=a.release_id JOIN projects p ON p.id=r.project_id LEFT JOIN build_explorer_project_settings s ON s.project_id=p.id'
                : 'SELECT a.id,a.id release_id,p.slug,p.name,p.summary project_summary,a.filename,a.storage_key,a.download_url,a.checksum_sha256,a.file_size_bytes,a.version_label,a.channel,a.build_number,NULL commit_sha,a.changelog,a.commits_json,a.published_at,a.ci_build_time,a.server_type platform,NULL java_version,a.is_published published,p.is_published project_published,COALESCE(s.requires_auth,0) requires_auth FROM project_builds a JOIN projects p ON p.id=a.project_id LEFT JOIN build_explorer_project_settings s ON s.project_id=p.id';
            foreach ($this->database->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $row) {
                if (!$this->allowed($row, false)) { continue; }
                $row['kind'] = $kind;
                $row['repository'] = $this->projects[$row['slug']]['repository'];
                $prefix = 'https://github.com/' . $row['repository'] . '/releases/download/build-explorer-';
                if ((string) $row['storage_key'] === '' && !str_starts_with((string) $row['download_url'], $prefix)) { continue; }
                $result[] = $row;
            }
        }
        return $result;
    }

    /** Switch a verified remote mirror without changing any public artifact ID or metadata. */
    public function relocate(array $expected, string $url): array
    {
        $kind = $expected['kind'] ?? '';
        if (!in_array($kind, ['v2', 'legacy'], true)) { throw new RuntimeException('Invalid artifact kind.'); }
        $repository = (string) ($this->projects[$expected['slug'] ?? '']['repository'] ?? '');
        $prefix = 'https://github.com/' . $repository . '/releases/download/build-explorer-';
        if ($repository === '' || !str_starts_with($url, $prefix)
            || preg_match('~\Ahttps://github\.com/[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+/releases/download/[A-Za-z0-9._-]+/[A-Za-z0-9][A-Za-z0-9._-]*\z~D', $url) !== 1
            || !in_array(basename($url), [$expected['filename'], self::collisionName($expected)], true)) { throw new RuntimeException('Invalid grouped destination.'); }
        $builds = new BuildRepository($this->database);
        if (!$builds->acquireRetentionLock(5)) { throw new RuntimeException('Build storage is busy.'); }
        $pdo = $this->database->connection()->pdo;
        try {
            $pdo->beginTransaction();
            $table = $kind === 'v2' ? 'build_artifacts' : 'project_builds';
            $suffix = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
            $row = $this->database->query('SELECT * FROM ' . $table . ' WHERE id=:id' . $suffix, [':id' => (int) $expected['id']])->fetch(PDO::FETCH_ASSOC);
            if (!$row || (string) $row['storage_key'] !== '' || !str_starts_with((string) $row['download_url'], $prefix)) { throw new RuntimeException('Remote source changed.'); }
            foreach (['download_url', 'checksum_sha256', 'file_size_bytes', 'filename'] as $key) {
                if ((string) $row[$key] !== (string) $expected[$key]) { throw new RuntimeException('Remote artifact changed: ' . $key); }
            }
            $this->database->update($table, ['download_url' => $url], ['id' => (int) $expected['id']]);
            $this->audit?->record(Request::fromArrays([], [], ['HTTP_USER_AGENT' => 'miniportal-github-build-storage']), 'build_github_regrouped', 'success', $kind . ':' . $expected['id']);
            $pdo->commit();
            return ['relocated' => true, 'id' => (int) $expected['id']];
        } catch (\Throwable $error) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            throw $error;
        } finally { $builds->releaseRetentionLock(); }
    }

    /** All owned remote references, including drafts, must be restored before archive removal. */
    public function restorePlan(): array
    {
        $result = [];
        foreach (['v2', 'legacy'] as $kind) {
            $sql = $kind === 'v2'
                ? 'SELECT a.*,p.slug FROM build_artifacts a JOIN build_releases r ON r.id=a.release_id JOIN projects p ON p.id=r.project_id'
                : 'SELECT a.*,p.slug FROM project_builds a JOIN projects p ON p.id=a.project_id';
            foreach ($this->database->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $repo = $this->projects[$row['slug']]['repository'] ?? '';
                if ($repo === '' || (string) $row['storage_key'] !== '' || !str_starts_with((string) $row['download_url'], 'https://github.com/' . $repo . '/releases/download/build-explorer-')) { continue; }
                $row['kind'] = $kind;
                $row['repository'] = $repo;
                $result[] = $row;
            }
        }
        return $result;
    }

    public function verifyLocal(array $proofs): array
    {
        $verified = 0;
        foreach ($proofs as $proof) {
            $expected = $proof['artifact'];
            $table = ($expected['kind'] ?? '') === 'v2' ? 'build_artifacts' : 'project_builds';
            $row = $this->database->query('SELECT * FROM ' . $table . ' WHERE id=:id', [':id' => (int) $expected['id']])->fetch(PDO::FETCH_ASSOC);
            if (!$row) { throw new RuntimeException('Restored record is missing.'); }
            if ($row['storage_key'] !== $proof['storage_key'] || (string) $row['download_url'] !== '') { throw new RuntimeException('Local pointer verification failed.'); }
            foreach (['filename', 'checksum_sha256', 'file_size_bytes'] as $field) {
                if ((string) $row[$field] !== (string) $expected[$field]) { throw new RuntimeException('Local metadata verification failed.'); }
            }
            $path = $this->storage->path($proof['storage_key']);
            if ($path === null || filesize($path) !== (int) $row['file_size_bytes'] || !hash_equals($row['checksum_sha256'], (string) hash_file('sha256', $path))) { throw new RuntimeException('Local bytes verification failed.'); }
            $verified++;
        }
        return ['verified' => $verified];
    }

    public function restoreLocal(array $expected, string $key): array
    {
        $kind = $expected['kind'] ?? '';
        if (!in_array($kind, ['v2', 'legacy'], true) || !str_ends_with($key, '.jar')) { throw new RuntimeException('Invalid restore identity.'); }
        $builds = new BuildRepository($this->database);
        if (!$builds->acquireRetentionLock(5)) { throw new RuntimeException('Build storage is busy.'); }
        $pdo = $this->database->connection()->pdo;
        try {
            $pdo->beginTransaction();
            $table = $kind === 'v2' ? 'build_artifacts' : 'project_builds';
            $suffix = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
            $row = $this->database->query('SELECT * FROM ' . $table . ' WHERE id=:id' . $suffix, [':id' => (int) $expected['id']])->fetch(PDO::FETCH_ASSOC);
            if (!$row || (string) $row['storage_key'] !== '') { throw new RuntimeException('Restore source changed.'); }
            foreach (['download_url', 'checksum_sha256', 'file_size_bytes', 'filename'] as $field) {
                if ((string) $row[$field] !== (string) $expected[$field]) { throw new RuntimeException('Restore metadata changed: ' . $field); }
            }
            $path = $this->storage->path($key);
            if ($path === null || filesize($path) !== (int) $row['file_size_bytes']
                || !hash_equals((string) $row['checksum_sha256'], (string) hash_file('sha256', $path))) { throw new RuntimeException('Restored local file failed verification.'); }
            $this->database->update($table, ['storage_key' => $key, 'download_url' => null], ['id' => (int) $row['id']]);
            $this->audit?->record(Request::fromArrays([], [], ['HTTP_USER_AGENT' => 'miniportal-github-rollback']), 'build_github_restored_local', 'success', $kind . ':' . $row['id']);
            $pdo->commit();
            return ['restored' => true, 'kind' => $kind, 'id' => (int) $row['id'], 'storage_key' => $key];
        } catch (\Throwable $error) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            throw $error;
        } finally { $builds->releaseRetentionLock(); }
    }

    public function check(array $expected): void
    {
        $kind = $expected['kind'] ?? '';
        if (!in_array($kind, ['v2', 'legacy'], true)) { throw new RuntimeException('Invalid artifact kind.'); }
        $row = $this->rows($kind, (int) $expected['id'])[0] ?? null;
        if ($row === null || !$this->allowed($row)) { throw new RuntimeException('Artifact is no longer eligible.'); }
        foreach (['storage_key', 'checksum_sha256', 'file_size_bytes', 'filename', 'slug', 'release_id', 'download_url'] as $key) {
            if ((string) $row[$key] !== (string) ($expected[$key] ?? '')) { throw new RuntimeException('Source manifest changed.'); }
        }
    }

    /** Retired means absent, not merely hidden. Every legacy/v2 reference protects the URL. */
    public function retirementCandidates(array $journals): array
    {
        $ids = ['v2' => [], 'legacy' => []];
        $urls = [];
        foreach (['v2' => 'build_artifacts', 'legacy' => 'project_builds'] as $kind => $table) {
            foreach ($this->database->query('SELECT id, download_url, checksum_sha256 FROM ' . $table)->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $ids[$kind][(int) $row['id']] = $row;
                if ((string) $row['download_url'] !== '') { $urls[(string) $row['download_url']] = true; }
            }
        }
        $result = [];
        foreach ($journals as $journal) {
            $row = $journal['artifact'] ?? [];
            $kind = $row['kind'] ?? '';
            $url = $journal['url'] ?? '';
            $policy = $this->projects[$row['slug'] ?? ''] ?? null;
            $existing = $ids[$kind][(int) ($row['id'] ?? 0)] ?? null;
            $superseded = is_array($existing) && isset($journal['superseded_by'])
                && $existing['download_url'] === $journal['superseded_by']
                && $existing['checksum_sha256'] === ($row['checksum_sha256'] ?? null)
                && $existing['download_url'] !== $url;
            if (!in_array($kind, ['v2', 'legacy'], true) || !is_array($policy)
                || ($row['repository'] ?? '') !== ($policy['repository'] ?? '')
                || !is_string($url) || !str_starts_with($url, 'https://github.com/' . $policy['repository'] . '/releases/download/build-explorer-')
                || !in_array(BuildCiPolicy::extension((string) ($row['filename'] ?? '')), $policy['extensions'] ?? ['jar'], true)
                || ($existing !== null && !$superseded) || isset($urls[$url])) { continue; }
            $result[] = $journal;
        }
        return $result;
    }

    public function releaseReferenced(string $prefix): bool
    {
        foreach (['build_artifacts', 'project_builds'] as $table) {
            foreach ($this->database->query("SELECT download_url FROM {$table} WHERE download_url IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN) as $url) {
                if (str_starts_with((string) $url, $prefix)) { return true; }
            }
        }
        return false;
    }

    /** Called only after the worker downloads and hashes the uploaded public GitHub asset. */
    public function apply(array $expected, string $url): array
    {
        $kind = $expected['kind'] ?? '';
        if (!in_array($kind, ['v2', 'legacy'], true)) { throw new RuntimeException('Invalid artifact kind.'); }
        $repository = (string) ($this->projects[$expected['slug'] ?? '']['repository'] ?? '');
        $prefix = 'https://github.com/' . $repository . '/releases/download/';
        if ($repository === '' || !str_starts_with($url, $prefix)
            || preg_match('~\Ahttps://github\.com/[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+/releases/download/[A-Za-z0-9._-]+/[A-Za-z0-9][A-Za-z0-9._-]*\z~D', $url) !== 1
            || !in_array(basename($url), [$expected['filename'], self::collisionName($expected)], true)) {
            throw new RuntimeException('Invalid pinned GitHub asset URL.');
        }
        $builds = new BuildRepository($this->database);
        if (!$builds->acquireRetentionLock(5)) { throw new RuntimeException('Build storage is busy.'); }
        $pdo = $this->database->connection()->pdo;
        try {
            $pdo->beginTransaction();
            $suffix = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
            $row = $this->rows($kind, (int) $expected['id'], $suffix)[0] ?? null;
            if ($row === null || !$this->allowed($row)) { throw new RuntimeException('Publication or access changed.'); }
            foreach (['storage_key', 'checksum_sha256', 'file_size_bytes', 'filename', 'slug', 'release_id', 'download_url'] as $key) {
                if ((string) $row[$key] !== (string) ($expected[$key] ?? '')) { throw new RuntimeException('Artifact changed: ' . $key); }
            }
            $path = $this->storage->path($row['storage_key']);
            if ($path === null || filesize($path) !== (int) $row['file_size_bytes']
                || !hash_equals($row['checksum_sha256'], (string) hash_file('sha256', $path))) {
                throw new RuntimeException('Local artifact changed after upload.');
            }
            $table = $kind === 'v2' ? 'build_artifacts' : 'project_builds';
            $this->database->update($table, ['storage_key' => null, 'download_url' => $url], ['id' => (int) $row['id']]);
            $this->audit?->record(Request::fromArrays([], [], ['HTTP_USER_AGENT' => 'miniportal-github-build-storage']),
                'build_github_storage', 'success', $kind . ':' . $row['id']);
            $pdo->commit();
            $references = (int) $this->database->query(
                'SELECT (SELECT COUNT(*) FROM build_artifacts WHERE storage_key=:v2)'
                . ' + (SELECT COUNT(*) FROM project_builds WHERE storage_key=:legacy)',
                [':v2' => $row['storage_key'], ':legacy' => $row['storage_key']]
            )->fetchColumn();
            $deleted = $references === 0 && $this->storage->delete($row['storage_key']);
            return ['id' => (int) $row['id'], 'kind' => $kind, 'url' => $url,
                'deleted_local' => $deleted, 'reclaimed_bytes' => $deleted ? (int) $row['file_size_bytes'] : 0];
        } catch (\Throwable $error) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            throw $error;
        } finally { $builds->releaseRetentionLock(); }
    }

    private static function collisionName(array $row): string
    {
        $build = substr((string) preg_replace('/[^A-Za-z0-9._-]/', '-', (string) ($row['build_number'] ?? '')), 0, 32);
        return substr(substr((string) $row['filename'], 0, -4), 0, 180) . '-build-' . $build . '-' . $row['kind'] . '-' . (int) $row['id'] . '.jar';
    }

    private function allowed(array $row, bool $local = true): bool
    {
        $policy = $this->projects[$row['slug']] ?? null;
        if (!is_array($policy) || preg_match('~\A[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+\z~D', (string) ($policy['repository'] ?? '')) !== 1) { return false; }
        return (int) $row['published'] === 1 && (int) $row['project_published'] === 1
            && (!(bool) $row['requires_auth'] || ($policy['allow_authenticated_public_assets'] ?? false) === true)
            && in_array(BuildCiPolicy::extension($row['filename']), $policy['extensions'] ?? ['jar'], true)
            && preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,254}$/D', $row['filename']) === 1
            && preg_match('/^[a-f0-9]{64}$/D', $row['checksum_sha256']) === 1
            && (int) $row['file_size_bytes'] > 0 && (!$local || (string) $row['storage_key'] !== '');
    }

    private function rows(string $kind, ?int $id = null, string $suffix = ''): array
    {
        if ($kind === 'v2') {
            $sql = 'SELECT a.id,r.id release_id,p.slug,p.name,a.filename,a.storage_key,a.download_url,a.checksum_sha256,a.file_size_bytes,'
                . 'r.version_label,r.channel,r.build_number,r.commit_sha,r.is_published published,p.is_published project_published,'
                . 'COALESCE(s.requires_auth,0) requires_auth FROM build_artifacts a JOIN build_releases r ON r.id=a.release_id '
                . 'JOIN projects p ON p.id=r.project_id LEFT JOIN build_explorer_project_settings s ON s.project_id=p.id '
                . "WHERE a.storage_key IS NOT NULL AND a.storage_key<>''" . ($id === null ? '' : ' AND a.id=:id') . ' ORDER BY a.id';
        } else {
            $sql = 'SELECT a.id,a.id release_id,p.slug,p.name,a.filename,a.storage_key,a.download_url,a.checksum_sha256,a.file_size_bytes,'
                . "a.version_label,a.channel,a.build_number,NULL commit_sha,a.is_published published,p.is_published project_published,"
                . 'COALESCE(s.requires_auth,0) requires_auth FROM project_builds a JOIN projects p ON p.id=a.project_id '
                . 'LEFT JOIN build_explorer_project_settings s ON s.project_id=p.id '
                . "WHERE a.storage_key IS NOT NULL AND a.storage_key<>''" . ($id === null ? '' : ' AND a.id=:id') . ' ORDER BY a.id';
        }
        return $this->database->query($sql . $suffix, $id === null ? [] : [':id' => $id])->fetchAll(PDO::FETCH_ASSOC);
    }
}
