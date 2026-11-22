<?php

declare(strict_types=1);
namespace SyntaxDevTeam\Cms\Modules\TaskBoard;

use InvalidArgumentException;
use PDO;
use SyntaxDevTeam\Cms\Database\CrudApp;
use SyntaxDevTeam\Cms\Modules\CoreAuth\User;
use SyntaxDevTeam\Cms\Modules\MediaLibrary\MediaAssetRepository;

/** Read-only projections of the other modules; Task Board never owns their data. */
final class TaskIntegrations
{
    public function __construct(private readonly CrudApp $database) {}

    public function projects(): array
    {
        return $this->database->query('SELECT id, name, slug, wiki_project_id FROM projects WHERE is_published = 1 ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
    }

    public function users(): array
    {
        return $this->database->query("SELECT id, display_name FROM users WHERE status = 'active' ORDER BY display_name, id")->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    public function wiki(): array
    {
        return $this->database->query("SELECT w.id, w.title, w.slug, p.slug AS project_slug, pr.id AS portal_project_id FROM wiki_pages w JOIN wiki_projects p ON p.id = w.project_id JOIN projects pr ON pr.wiki_project_id = p.id WHERE w.status = 'published' AND p.status = 'published' AND pr.is_published = 1 ORDER BY p.name, w.title")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function validate(array $data, ?array $previous = null): void
    {
        if ($data['project_id'] !== null && !in_array((int)$data['project_id'], array_map(static fn ($p) => (int)$p['id'], $this->projects()), true)) {
            // A removed/unpublished project may be detached, never newly selected.
            if ($previous === null || $previous['project_id'] !== $data['project_id'] || $data['visibility'] === 'public') throw new InvalidArgumentException('Wybierz istniejący opublikowany projekt.');
        }
        foreach ($data['assignee_ids'] as $id) if (!isset($this->users()[$id])) throw new InvalidArgumentException('Przypisz wyłącznie aktywne konta.');
        if ($data['wiki_page_id'] !== null) {
            $valid = false;
            foreach ($this->wiki() as $page) if ((int)$page['id'] === $data['wiki_page_id'] && (int)$page['portal_project_id'] === $data['project_id']) $valid = true;
            if (!$valid) throw new InvalidArgumentException('Wybierz opublikowaną dokumentację należącą do wybranego projektu.');
        }
    }

    public function releaseExists(array $task): bool
    {
        return (bool)$this->database->query('SELECT r.id FROM build_releases r WHERE r.project_id = :project AND r.version_label = :version AND r.channel = :channel AND r.is_published = 1 AND EXISTS (SELECT 1 FROM build_artifacts a WHERE a.release_id = r.id) LIMIT 1', [':project' => $task['project_id'], ':version' => $task['release_version'], ':channel' => $task['release_channel']])->fetchColumn();
    }

    public function mediaOptions(?User $user): array
    {
        if (!TaskPolicy::allows($user, 'media.manage')) return [];
        $options = [];
        foreach ((new MediaAssetRepository($this->database))->all() as $asset) $options[$asset->id] = $asset->title;
        return $options;
    }

    public function media(int $id): ?array
    {
        $asset = (new MediaAssetRepository($this->database))->find($id);
        if (!$asset || !str_starts_with($asset->publicPath, '/uploads/media/') || !in_array($asset->mimeType, ['image/png', 'image/jpeg', 'image/webp', 'image/gif', 'image/svg+xml'], true)) return null;
        return ['name' => $asset->title, 'url' => $asset->publicPath, 'mime_type' => $asset->mimeType];
    }

    public function links(array $task): array
    {
        $links = [];
        foreach ($this->projects() as $project) {
            if ((int)$project['id'] !== $task['project_id']) continue;
            $links[] = ['label' => 'Projekt: ' . $project['name'], 'url' => '/projects/' . rawurlencode($project['slug'])];
            $links[] = ['label' => 'Wydania w Build Explorer v2', 'url' => '/builds/' . rawurlencode($project['slug']) . '/explore'];
        }
        foreach ($this->wiki() as $page) {
            if ((int)$page['id'] === $task['wiki_page_id'] && (int)$page['portal_project_id'] === $task['project_id']) $links[] = ['label' => 'Dokumentacja: ' . $page['title'], 'url' => '/wiki/page/' . rawurlencode($page['project_slug']) . '/' . rawurlencode($page['slug'])];
        }
        return $links;
    }
}
