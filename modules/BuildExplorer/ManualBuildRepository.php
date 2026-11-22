<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\BuildExplorer;

use PDO;
use RuntimeException;
use SyntaxDevTeam\Cms\Database\CrudApp;

/** Manual uploads share the v2 catalogue; CI releases remain immutable here. */
final class ManualBuildRepository
{
    public function __construct(private readonly CrudApp $database) {}

    public function find(int $artifactId): ?array
    {
        $row = $this->database->query(
            'SELECT a.*, a.id AS artifact_id, r.project_id, r.version_label, r.channel, r.build_number, '
            . 'r.changelog, r.is_published, r.published_at, r.legacy_build_id, r.retention_lock '
            . 'FROM build_artifacts a JOIN build_releases r ON r.id=a.release_id '
            . 'WHERE a.id=:id AND r.ci_build_id IS NULL', [':id' => $artifactId]
        )?->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) { return null; }
        $row['game_versions'] = $this->database->query('SELECT game_version FROM build_artifact_game_versions WHERE artifact_id=:id ORDER BY game_version', [':id' => $artifactId])->fetchAll(PDO::FETCH_COLUMN);
        $row['compatible_platforms'] = $this->database->query('SELECT platform FROM build_artifact_compatible_platforms WHERE artifact_id=:id ORDER BY platform', [':id' => $artifactId])->fetchAll(PDO::FETCH_COLUMN);
        $row['revision'] = hash('sha256', json_encode($row, JSON_THROW_ON_ERROR));
        return $row;
    }

    public function legacyArtifact(int $legacyId): ?int
    {
        $id = $this->database->query('SELECT a.id FROM build_artifacts a JOIN build_releases r ON r.id=a.release_id WHERE r.legacy_build_id=:id LIMIT 1', [':id' => $legacyId])?->fetchColumn();
        return $id ? (int) $id : null;
    }

    public function list(array $filters): array
    {
        $where = ['r.ci_build_id IS NULL']; $params = [];
        if (($filters['project_id'] ?? 0) > 0) { $where[]='r.project_id=:project'; $params[':project']=$filters['project_id']; }
        if (in_array($filters['channel'] ?? '', ['release','snapshot','dev','wip'], true)) { $where[]='r.channel=:channel'; $params[':channel']=$filters['channel']; }
        if (in_array($filters['published'] ?? '', ['0','1'], true)) { $where[]='r.is_published=:published'; $params[':published']=(int)$filters['published']; }
        if (($filters['search'] ?? '') !== '') { $where[]='(p.name LIKE :search OR r.version_label LIKE :search OR a.filename LIKE :search)'; $params[':search']='%'.$filters['search'].'%'; }
        return $this->database->query('SELECT a.id AS artifact_id,a.filename,a.platform,r.version_label,r.channel,r.is_published,p.name AS project_name '
            . 'FROM build_artifacts a JOIN build_releases r ON r.id=a.release_id JOIN projects p ON p.id=r.project_id WHERE '
            . implode(' AND ', $where) . ' ORDER BY r.id DESC,a.id DESC LIMIT 100', $params)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function knownGameVersions(): array
    {
        return $this->database->query('SELECT DISTINCT game_version FROM build_artifact_game_versions')->fetchAll(PDO::FETCH_COLUMN);
    }

    /** All records and compatibility sets change in one transaction under the cleanup lock. */
    public function save(array $release, array $artifact, array $versions, array $platforms, ?int $artifactId = null, string $revision = '', ?int $legacyId = null): int
    {
        $builds = new BuildRepository($this->database);
        if (!$builds->acquireRetentionLock(5)) { throw new RuntimeException('Trwa czyszczenie magazynu. Spróbuj ponownie.'); }
        $pdo = $this->database->connection()->pdo;
        try {
            $pdo->beginTransaction();
            if ($artifactId !== null) {
                $current = $this->find($artifactId);
                if ($current === null || !hash_equals($current['revision'], $revision)) { throw new RuntimeException('Build został zmieniony lub usunięty. Otwórz formularz ponownie.'); }
                $releaseId = (int) $current['release_id'];
                if ((int)$this->database->query('SELECT COUNT(*) FROM build_artifacts WHERE release_id=:id', [':id'=>$releaseId])->fetchColumn() !== 1) { throw new RuntimeException('To wydanie ma wiele platform; nie można go zmienić tym formularzem.'); }
                $this->database->update('build_releases', $release, ['id' => $releaseId]);
                $this->database->update('build_artifacts', $artifact, ['id' => $artifactId]);
            } else {
                if ($legacyId !== null) {
                    $legacy = $builds->find($legacyId);
                    if ($legacy === null || $legacy->ciBuildId !== null) { throw new RuntimeException('Nie znaleziono ręcznego buildu do przeniesienia.'); }
                    // Lock the source record against legacy edits/deletions during promotion.
                    $suffix = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
                    $source = $this->database->query('SELECT * FROM project_builds WHERE id=:id' . $suffix, [':id'=>$legacyId])->fetch(PDO::FETCH_ASSOC);
                    if (!is_array($source)) { throw new RuntimeException('Źródłowy build został usunięty.'); }
                    $fresh = $builds->find($legacyId);
                    if ($revision === '' || $fresh === null || !hash_equals(hash('sha256', json_encode($fresh, JSON_THROW_ON_ERROR)), $revision)) { throw new RuntimeException('Źródłowy build został zmieniony.'); }
                    $release['legacy_build_id'] = $legacyId;
                }
                $releaseId = (int)$this->database->create('build_releases', $release);
                if ($releaseId < 1) { throw new RuntimeException('Nie można zapisać wydania.'); }
                $artifactId = (int)$this->database->create('build_artifacts', ['release_id'=>$releaseId] + $artifact);
                if ($artifactId < 1) { throw new RuntimeException('Nie można zapisać artefaktu.'); }
                if ($legacyId !== null && !$builds->delete($legacyId)) { throw new RuntimeException('Nie można zakończyć przenoszenia buildu.'); }
            }
            $builds->replaceV2GameVersions($artifactId, $versions);
            $builds->replaceV2CompatiblePlatforms($artifactId, $platforms);
            $pdo->commit();
            return $artifactId;
        } catch (\Throwable $error) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            throw $error;
        } finally { $builds->releaseRetentionLock(); }
    }

    public function delete(int $artifactId, string $revision): ?string
    {
        $builds = new BuildRepository($this->database);
        if (!$builds->acquireRetentionLock(5)) { throw new RuntimeException('Trwa czyszczenie magazynu.'); }
        $pdo = $this->database->connection()->pdo;
        try {
            $pdo->beginTransaction();
            $current = $this->find($artifactId);
            if ($current === null || !hash_equals($current['revision'], $revision)) { throw new RuntimeException('Build został zmieniony lub usunięty.'); }
            if ((int)$this->database->query('SELECT COUNT(*) FROM build_artifacts WHERE release_id=:id', [':id'=>(int)$current['release_id']])->fetchColumn() !== 1) { throw new RuntimeException('Nie można usunąć wieloplatformowego wydania tym formularzem.'); }
            $this->database->delete('build_releases', ['id'=>(int)$current['release_id'], 'ci_build_id'=>null]);
            $pdo->commit();
            return $current['storage_key'];
        } catch (\Throwable $error) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            throw $error;
        } finally { $builds->releaseRetentionLock(); }
    }

    public function storageReferenced(string $key): bool
    {
        return (int)$this->database->query('SELECT (SELECT COUNT(*) FROM build_artifacts WHERE storage_key=:v2) + (SELECT COUNT(*) FROM project_builds WHERE storage_key=:v1)', [':v2'=>$key, ':v1'=>$key])->fetchColumn() > 0;
    }
}
