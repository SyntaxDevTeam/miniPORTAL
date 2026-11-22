<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\BuildExplorer;

use PDO;
use SyntaxDevTeam\Cms\Database\CrudApp;

/** Read model for the public API. Every query applies publication and project ACL. */
final class PublicBuildRepository
{
    private const JOINS = ' FROM build_releases r JOIN projects p ON p.id=r.project_id '
        . 'LEFT JOIN build_explorer_project_settings s ON s.project_id=p.id ';
    private const VISIBLE = 'p.is_published=1 AND r.is_published=1 AND (COALESCE(s.requires_auth,0)=0 OR :authenticated=1)';

    public function __construct(private readonly CrudApp $database) {}

    public function projects(bool $authenticated, int $limit, int $offset, ?string $slug = null): array
    {
        $from = ' FROM projects p LEFT JOIN build_explorer_project_settings s ON s.project_id=p.id '
            . 'WHERE p.is_published=1 AND (COALESCE(s.requires_auth,0)=0 OR :authenticated=1)';
        $params = [':authenticated' => (int) $authenticated];
        if ($slug !== null) { $from .= ' AND p.slug=:slug'; $params[':slug'] = $slug; }
        $total = (int) $this->database->query('SELECT COUNT(*)' . $from, $params)->fetchColumn();
        $rows = $this->database->query('SELECT p.id,p.name,p.slug,p.summary,p.icon_url,p.lifecycle_status,COALESCE(s.requires_auth,0) AS requires_auth,'
            . '(SELECT COUNT(*) FROM build_releases r WHERE r.project_id=p.id AND r.is_published=1) AS release_count'
            . $from . ' ORDER BY p.sort_order,p.name,p.id LIMIT ' . max(1, min(100, $limit)) . ' OFFSET ' . max(0, $offset), $params)->fetchAll(PDO::FETCH_ASSOC);
        $items = [];
        foreach ($rows as $row) {
            $base = '/api/v2/builds/projects/' . rawurlencode($row['slug']);
            $items[] = ['id' => (int) $row['id'], 'name' => $row['name'], 'slug' => $row['slug'],
                'summary' => $row['summary'], 'icon_url' => $row['icon_url'], 'lifecycle_status' => $row['lifecycle_status'],
                'requires_auth' => (bool) $row['requires_auth'], 'release_count' => (int) $row['release_count'],
                'links' => ['self' => $base, 'versions' => $base . '/versions', 'releases' => $base . '/releases',
                    'artifacts' => $base . '/artifacts', 'latest' => $base . '/latest', 'download_latest' => $base . '/latest/download']];
        }
        return ['items' => $items, 'total' => $total];
    }

    /** A page of complete logical releases, including every platform artifact. */
    public function releases(bool $authenticated, array $filters, int $limit, int $offset): array
    {
        [$where, $params] = $this->where($authenticated, $filters);
        $from = self::JOINS . ' WHERE ' . $where;
        $total = (int) $this->database->query('SELECT COUNT(*)' . $from, $params)->fetchColumn();
        $rows = $this->database->query('SELECT r.id,r.version_label,r.channel,r.build_number,r.changelog,r.published_at,'
            . 'r.ci_build_time,r.ci_build_id,r.commit_sha,r.branch,r.commits_json,p.id AS project_id,p.name,p.slug'
            . $from . ' ORDER BY COALESCE(r.published_at,r.ci_build_time,r.created_at) DESC,r.id DESC LIMIT '
            . max(1, min(100, $limit)) . ' OFFSET ' . max(0, $offset), $params)->fetchAll(PDO::FETCH_ASSOC);
        $artifacts = $this->artifactsFor($rows);
        $items = [];
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            $base = '/api/v2/builds/projects/' . rawurlencode($row['slug']);
            $items[] = ['id' => $id, 'project' => ['id' => (int) $row['project_id'], 'name' => $row['name'], 'slug' => $row['slug']],
                'version' => $row['version_label'], 'channel' => $row['channel'], 'build_number' => $row['build_number'],
                'published_at' => $this->utc($row['published_at'] ?? $row['ci_build_time']),
                'changelog' => $row['changelog'], 'changelog_format' => 'text',
                'ci' => ['id' => $row['ci_build_id'] === null ? null : (int) $row['ci_build_id'],
                    'time' => $this->utc($row['ci_build_time']), 'commit_sha' => $row['commit_sha'], 'branch' => $row['branch'],
                    'commits' => json_decode($row['commits_json'] ?? '[]', true) ?: []],
                'artifacts' => $artifacts[$id] ?? [],
                'links' => ['self' => $base . '/releases/' . $id,
                    'explore' => '/builds/v2/release?' . http_build_query(['project' => $row['slug'], 'release' => $id])]];
        }
        return ['items' => $items, 'total' => $total];
    }

    public function versions(bool $authenticated, array $filters, int $limit, int $offset): array
    {
        [$where, $params] = $this->where($authenticated, $filters);
        $from = self::JOINS . ' WHERE ' . $where;
        $total = (int) $this->database->query('SELECT COUNT(DISTINCT r.version_label)' . $from, $params)->fetchColumn();
        $rows = $this->database->query('SELECT r.version_label AS version,COUNT(*) AS release_count,'
            . 'MAX(COALESCE(r.published_at,r.ci_build_time,r.created_at)) AS updated_at'
            . $from . ' GROUP BY r.version_label ORDER BY updated_at DESC,r.version_label DESC LIMIT '
            . max(1, min(100, $limit)) . ' OFFSET ' . max(0, $offset), $params)->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) { $row['release_count'] = (int) $row['release_count']; $row['updated_at'] = $this->utc($row['updated_at']); }
        return ['items' => $rows, 'total' => $total];
    }

    /** Artifacts are paginated independently; latest chooses only matching files. */
    public function artifacts(bool $authenticated, array $filters, int $limit, int $offset): array
    {
        [$where, $params] = $this->where($authenticated, $filters, 'a');
        $from = self::JOINS . ' JOIN build_artifacts a ON a.release_id=r.id WHERE ' . $where;
        $total = (int) $this->database->query('SELECT COUNT(*)' . $from, $params)->fetchColumn();
        $rows = $this->database->query('SELECT a.id,r.id AS release_id,r.version_label,r.channel,r.build_number,p.slug,p.name'
            . $from . ' ORDER BY COALESCE(r.published_at,r.ci_build_time,r.created_at) DESC,r.id DESC,'
            . 'CASE WHEN a.platform=:preferred_platform THEN 0 ELSE 1 END,a.id DESC LIMIT '
            . max(1, min(100, $limit)) . ' OFFSET ' . max(0, $offset), $params + [':preferred_platform' => $filters['platform'] ?? ''])->fetchAll(PDO::FETCH_ASSOC);
        $releases = array_map(static fn(array $r): array => ['id' => $r['release_id'], 'slug' => $r['slug']], $rows);
        $groups = $this->artifactsFor($releases);
        $byId = [];
        foreach ($groups as $group) { foreach ($group as $artifact) { $byId[$artifact['id']] = $artifact; } }
        $items = [];
        foreach ($rows as $row) {
            $items[] = $byId[(int) $row['id']] + ['project' => ['slug' => $row['slug'], 'name' => $row['name']],
                'version' => $row['version_label'], 'channel' => $row['channel'], 'build_number' => $row['build_number']];
        }
        return ['items' => $items, 'total' => $total];
    }

    /** Highest compatible project version first; publication order selects its latest build. */
    public function friendlyArtifact(bool $authenticated, array $filters): ?array
    {
        if (isset($filters['version'])) {
            return $this->artifacts($authenticated, $filters, 1, 0)['items'][0] ?? null;
        }
        [$where, $params] = $this->where($authenticated, $filters, 'a');
        $statement = $this->database->query('SELECT a.id,r.version_label' . self::JOINS
            . ' JOIN build_artifacts a ON a.release_id=r.id WHERE ' . $where
            . ' ORDER BY COALESCE(r.published_at,r.ci_build_time,r.created_at) DESC,r.id DESC,'
            . 'CASE WHEN a.platform=:preferred_platform THEN 0 ELSE 1 END,a.id DESC',
            $params + [':preferred_platform' => $filters['platform']]);
        $best = null;
        $bestVersion = null;
        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            // Numeric-aware comparison supports project labels such as 1.9.0-R0.3-SNAPSHOT.
            // A leading v and SemVer build metadata do not affect version precedence.
            $version = strtolower(preg_replace('/^v(?=[0-9])/i', '', explode('+', trim($row['version_label']), 2)[0]));
            if ($best === null || version_compare($version, $bestVersion) > 0) {
                $best = (int) $row['id'];
                $bestVersion = $version;
            }
        }
        if ($best === null) { return null; }
        // Reapply publication and ACL before returning the selected artifact's safe projection.
        return $this->artifacts($authenticated, $filters + ['artifact' => $best], 1, 0)['items'][0] ?? null;
    }

    public function capabilities(bool $authenticated, string $slug): array
    {
        [$where, $params] = $this->where($authenticated, ['project' => $slug]);
        $from = self::JOINS . ' JOIN build_artifacts a ON a.release_id=r.id ';
        $result = [];
        foreach (['channels' => ['r.channel', ''], 'platforms' => ['cp.platform', ' JOIN build_artifact_compatible_platforms cp ON cp.artifact_id=a.id '],
            'game_versions' => ['gv.game_version', ' JOIN build_artifact_game_versions gv ON gv.artifact_id=a.id '],
            'platform_families' => ['a.platform_family', ''], 'java_versions' => ['a.java_version', '']] as $key => [$column, $join]) {
            $values = $this->database->query('SELECT DISTINCT ' . $column . $from . $join . ' WHERE ' . $where . ' AND ' . $column . ' IS NOT NULL ORDER BY ' . $column, $params)->fetchAll(PDO::FETCH_COLUMN);
            $result[$key] = array_map($key === 'java_versions' ? intval(...) : strval(...), $values);
        }
        usort($result['game_versions'], static fn(string $a, string $b): int => version_compare($b, $a));
        return $result;
    }

    private function where(bool $authenticated, array $filters, ?string $artifactAlias = null): array
    {
        $where = self::VISIBLE;
        $params = [':authenticated' => (int) $authenticated];
        foreach (['project' => 'p.slug', 'version' => 'r.version_label', 'channel' => 'r.channel', 'build_number' => 'r.build_number', 'release' => 'r.id'] as $key => $column) {
            if (isset($filters[$key])) { $where .= ' AND ' . $column . '=:' . $key; $params[':' . $key] = $filters[$key]; }
        }
        $conditions = [];
        $alias = $artifactAlias ?? 'matching';
        foreach (['artifact' => 'id', 'platform_family' => 'platform_family', 'java_version' => 'java_version'] as $key => $column) {
            if (isset($filters[$key])) { $conditions[] = $alias . '.' . $column . '=:' . $key; $params[':' . $key] = $filters[$key]; }
        }
        foreach (['platform' => ['build_artifact_compatible_platforms', 'platform'], 'game_version' => ['build_artifact_game_versions', 'game_version']] as $key => [$table, $column]) {
            if (isset($filters[$key])) {
                $conditions[] = 'EXISTS (SELECT 1 FROM ' . $table . ' c WHERE c.artifact_id=' . $alias . '.id AND c.' . $column . '=:' . $key . ')';
                $params[':' . $key] = $filters[$key];
            }
        }
        if ($conditions !== []) {
            $condition = implode(' AND ', $conditions);
            $where .= ' AND ' . ($artifactAlias === null ? 'EXISTS (SELECT 1 FROM build_artifacts matching WHERE matching.release_id=r.id AND ' . $condition . ')' : $condition);
        }
        return [$where, $params];
    }

    private function artifactsFor(array $releases): array
    {
        if ($releases === []) { return []; }
        $slugs = [];
        foreach ($releases as $row) { $slugs[(int) $row['id']] = $row['slug']; }
        // IDs come from ACL-filtered SQL above, never from unchecked request data.
        $ids = implode(',', array_keys($slugs));
        $rows = $this->database->query('SELECT id,release_id,platform,platform_family,filename,checksum_sha256,file_size_bytes,java_version FROM build_artifacts WHERE release_id IN (' . $ids . ') ORDER BY platform,id')->fetchAll(PDO::FETCH_ASSOC);
        $sets = [];
        foreach (['compatible_platforms' => ['build_artifact_compatible_platforms', 'platform'], 'game_versions' => ['build_artifact_game_versions', 'game_version']] as $key => [$table, $column]) {
            $values = $this->database->query('SELECT c.artifact_id,c.' . $column . ' AS value FROM ' . $table . ' c JOIN build_artifacts a ON a.id=c.artifact_id WHERE a.release_id IN (' . $ids . ') ORDER BY c.' . $column)->fetchAll(PDO::FETCH_ASSOC);
            foreach ($values as $value) { $sets[(int) $value['artifact_id']][$key][] = $value['value']; }
        }
        $result = [];
        foreach ($rows as $row) {
            $id = (int) $row['id']; $release = (int) $row['release_id'];
            $versions = $sets[$id]['game_versions'] ?? [];
            usort($versions, static fn(string $a, string $b): int => version_compare($b, $a));
            $result[$release][] = ['id' => $id, 'release_id' => $release, 'platform' => $row['platform'],
                'platform_family' => $row['platform_family'], 'compatible_platforms' => $sets[$id]['compatible_platforms'] ?? [$row['platform']],
                'game_versions' => $versions, 'java_version' => $row['java_version'] === null ? null : (int) $row['java_version'],
                'filename' => $row['filename'], 'size' => $row['file_size_bytes'] === null ? null : (int) $row['file_size_bytes'],
                'sha256' => $row['checksum_sha256'], 'links' => ['self' => '/api/v2/builds/projects/' . rawurlencode($slugs[$release]) . '/artifacts/' . $id,
                    'download' => '/builds/download/v2?artifact=' . $id]];
        }
        return $result;
    }

    private function utc(?string $value): ?string
    {
        return $value === null ? null : (new \DateTimeImmutable($value, new \DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z');
    }
}
