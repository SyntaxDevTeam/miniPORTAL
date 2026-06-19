<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\PluginTranslator;

use PDO;
use RuntimeException;
use SyntaxDevTeam\Cms\Database\CrudApp;
use Throwable;

final class PluginTranslationRepository
{
    private const SELECT_COLUMNS = 's.id, s.project_id, p.name AS project_name, p.slug AS project_slug, s.user_id, '
        . 's.author_name, s.author_email, s.title, s.source_filename, s.plugin_version, s.submission_kind, '
        . 's.target_language, s.source_yaml, s.translations_json, s.output_yaml, s.total_items, s.translated_items, '
        . 's.progress_percent, s.status, s.reviewer_id, s.review_note, s.reviewed_at, s.created_at, s.updated_at';

    private const SELECT_FROM = ' FROM plugin_translation_submissions s '
        . 'JOIN plugin_translation_projects p ON p.id = s.project_id ';

    public function __construct(
        private readonly CrudApp $database,
    ) {
    }

    /**
     * @return list<PluginTranslationSubmission>
     */
    public function all(): array
    {
        $statement = $this->database->query(
            'SELECT ' . self::SELECT_COLUMNS . self::SELECT_FROM
            . "ORDER BY FIELD(s.status, 'ready_for_review', 'draft', 'rejected', 'approved'), s.updated_at DESC, s.id DESC"
        );
        if ($statement === null) {
            throw new RuntimeException('Nie można pobrać zgłoszeń tłumaczeń.');
        }

        return array_map($this->hydrate(...), $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * @return list<PluginTranslationSubmission>
     */
    public function recentApproved(int $limit = 6): array
    {
        $limit = max(1, min(12, $limit));
        $statement = $this->database->query(
            'SELECT ' . self::SELECT_COLUMNS . self::SELECT_FROM
            . 'WHERE s.status = :status ORDER BY s.reviewed_at DESC, s.id DESC LIMIT ' . $limit,
            [':status' => 'approved']
        );
        if ($statement === null) {
            throw new RuntimeException('Nie można pobrać zatwierdzonych tłumaczeń.');
        }

        return array_map($this->hydrate(...), $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    public function find(int $id): ?PluginTranslationSubmission
    {
        $statement = $this->database->query(
            'SELECT ' . self::SELECT_COLUMNS . self::SELECT_FROM . 'WHERE s.id = :id LIMIT 1',
            [':id' => $id]
        );
        $row = $statement?->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->hydrate($row) : null;
    }

    /**
     * @return list<PluginTranslationSubmission>
     */
    public function forUser(int $userId): array
    {
        $statement = $this->database->query(
            'SELECT ' . self::SELECT_COLUMNS . self::SELECT_FROM
            . 'WHERE s.user_id = :user_id ORDER BY s.updated_at DESC, s.id DESC',
            [':user_id' => $userId]
        );
        if ($statement === null) {
            throw new RuntimeException('Nie można pobrać prac użytkownika.');
        }

        return array_map($this->hydrate(...), $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    public function findForUser(int $id, int $userId): ?PluginTranslationSubmission
    {
        $statement = $this->database->query(
            'SELECT ' . self::SELECT_COLUMNS . self::SELECT_FROM
            . 'WHERE s.id = :id AND s.user_id = :user_id LIMIT 1',
            [':id' => $id, ':user_id' => $userId]
        );
        $row = $statement?->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->hydrate($row) : null;
    }

    /**
     * @param array<string, string> $translations
     */
    public function create(
        int $projectId,
        ?int $userId,
        string $authorName,
        string $authorEmail,
        string $title,
        string $sourceFilename,
        string $pluginVersion,
        string $submissionKind,
        string $targetLanguage,
        string $sourceYaml,
        array $translations,
        string $outputYaml,
        int $totalItems,
        int $translatedItems,
        string $status,
    ): int {
        return (int) $this->database->create('plugin_translation_submissions', [
            'project_id' => $projectId,
            'user_id' => $userId,
            'author_name' => $authorName,
            'author_email' => $authorEmail,
            'title' => $title,
            'source_filename' => $sourceFilename,
            'plugin_version' => $pluginVersion,
            'submission_kind' => $submissionKind,
            'target_language' => $targetLanguage,
            'source_yaml' => $sourceYaml,
            'translations_json' => json_encode($translations, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'output_yaml' => $outputYaml,
            'total_items' => $totalItems,
            'translated_items' => $translatedItems,
            'progress_percent' => $this->progress($totalItems, $translatedItems),
            'status' => $status,
        ]);
    }

    /**
     * @param array<string, string> $translations
     */
    public function updateUserSubmission(
        int $id,
        int $userId,
        int $projectId,
        string $authorName,
        string $authorEmail,
        string $title,
        string $pluginVersion,
        string $targetLanguage,
        array $translations,
        string $outputYaml,
        int $totalItems,
        int $translatedItems,
        string $status,
    ): bool {
        $statement = $this->database->update('plugin_translation_submissions', [
            'project_id' => $projectId,
            'author_name' => $authorName,
            'author_email' => $authorEmail,
            'title' => $title,
            'plugin_version' => $pluginVersion,
            'target_language' => $targetLanguage,
            'translations_json' => json_encode($translations, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'output_yaml' => $outputYaml,
            'total_items' => $totalItems,
            'translated_items' => $translatedItems,
            'progress_percent' => $this->progress($totalItems, $translatedItems),
            'status' => $status,
            'reviewer_id' => null,
            'review_note' => '',
            'reviewed_at' => null,
        ], [
            'id' => $id,
            'user_id' => $userId,
            'status' => ['draft', 'ready_for_review', 'rejected'],
        ]);

        return $statement !== null;
    }

    public function review(int $id, int $reviewerId, string $status, string $note): bool
    {
        if (!in_array($status, ['approved', 'rejected'], true)) {
            return false;
        }

        $statement = $this->database->update('plugin_translation_submissions', [
            'status' => $status,
            'reviewer_id' => $reviewerId,
            'review_note' => $note,
            'reviewed_at' => date('Y-m-d H:i:s'),
        ], ['id' => $id]);

        return $statement !== null && $statement->rowCount() === 1;
    }

    /**
     * @return list<PluginTranslationProject>
     */
    public function projects(bool $includeHidden = false): array
    {
        $where = $includeHidden ? '' : "WHERE p.status = 'active'";
        $statement = $this->database->query(
            'SELECT p.id, p.name, p.slug, p.page_id, cp.title AS page_title, cp.slug AS page_slug, p.status, p.created_by, '
            . 'p.created_at, p.updated_at, '
            . "SUM(CASE WHEN s.status = 'approved' THEN 1 ELSE 0 END) AS approved_files "
            . 'FROM plugin_translation_projects p '
            . 'LEFT JOIN core_pages cp ON cp.id = p.page_id AND cp.status = \'published\' '
            . 'LEFT JOIN plugin_translation_submissions s ON s.project_id = p.id '
            . $where . ' GROUP BY p.id ORDER BY p.name ASC'
        );
        if ($statement === null) {
            throw new RuntimeException('Nie można pobrać katalogu pluginów.');
        }

        return array_map($this->hydrateProject(...), $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    public function project(int $id, bool $includeHidden = false): ?PluginTranslationProject
    {
        foreach ($this->projects($includeHidden) as $project) {
            if ($project->id === $id) {
                return $project;
            }
        }

        return null;
    }

    public function createProject(string $name, string $slug, ?int $pageId, int $createdBy): int
    {
        return (int) $this->database->create('plugin_translation_projects', [
            'name' => $name,
            'slug' => $slug,
            'page_id' => $pageId,
            'status' => 'active',
            'created_by' => $createdBy,
        ]);
    }

    public function updateProject(int $id, string $name, string $slug, ?int $pageId): bool
    {
        $statement = $this->database->update('plugin_translation_projects', [
            'name' => $name,
            'slug' => $slug,
            'page_id' => $pageId,
        ], [
            'id' => $id,
            'slug[!]' => 'nieprzypisane',
        ]);

        return $statement !== null;
    }

    public function setProjectStatus(int $id, string $status): bool
    {
        if (!in_array($status, ['active', 'hidden'], true)) {
            return false;
        }
        $statement = $this->database->update('plugin_translation_projects', ['status' => $status], [
            'id' => $id,
            'slug[!]' => 'nieprzypisane',
        ]);

        return $statement !== null && $statement->rowCount() === 1;
    }

    /**
     * @return array<string, string>
     */
    public function publishedPageOptions(): array
    {
        $statement = $this->database->query(
            "SELECT id, title, slug FROM core_pages WHERE status = 'published' ORDER BY title ASC"
        );
        $options = ['' => 'Bez powiązanej strony'];
        foreach ($statement?->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $options[(string) $row['id']] = (string) $row['title'] . ' (/p/' . (string) $row['slug'] . ')';
        }

        return $options;
    }

    public function publishedPageExists(int $id): bool
    {
        return $id > 0 && $this->database->count('core_pages', ['id' => $id, 'status' => 'published']) === 1;
    }

    public function deleteSubmission(int $id): bool
    {
        $statement = $this->database->delete('plugin_translation_submissions', ['id' => $id]);

        return $statement !== null && $statement->rowCount() === 1;
    }

    public function deleteProject(int $id): bool
    {
        $fallback = $this->database->get('plugin_translation_projects', ['id'], ['slug' => 'nieprzypisane']);
        $fallbackId = is_array($fallback) ? (int) ($fallback['id'] ?? 0) : 0;
        if ($fallbackId <= 0 || $id === $fallbackId) {
            return false;
        }

        $pdo = $this->database->connection()->pdo;
        try {
            $pdo->beginTransaction();
            $this->database->update('plugin_translation_submissions', ['project_id' => $fallbackId], ['project_id' => $id]);
            $statement = $this->database->delete('plugin_translation_projects', ['id' => $id]);
            if ($statement === null || $statement->rowCount() !== 1) {
                $pdo->rollBack();
                return false;
            }
            $pdo->commit();
            return true;
        } catch (Throwable) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return false;
        }
    }

    /**
     * @return list<PluginTranslationSubmission>
     */
    public function approvedForProject(int $projectId): array
    {
        $statement = $this->database->query(
            'SELECT ' . self::SELECT_COLUMNS . self::SELECT_FROM
            . 'WHERE s.project_id = :project_id AND s.status = :status '
            . 'ORDER BY s.target_language ASC, s.plugin_version DESC, s.reviewed_at DESC',
            [':project_id' => $projectId, ':status' => 'approved']
        );
        if ($statement === null) {
            throw new RuntimeException('Nie można pobrać zaakceptowanych plików pluginu.');
        }

        return array_map($this->hydrate(...), $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    private function progress(int $totalItems, int $translatedItems): int
    {
        if ($totalItems <= 0) {
            return 0;
        }

        return max(0, min(100, (int) round(($translatedItems / $totalItems) * 100)));
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): PluginTranslationSubmission
    {
        return new PluginTranslationSubmission(
            (int) $row['id'],
            (int) $row['project_id'],
            (string) $row['project_name'],
            (string) $row['project_slug'],
            $row['user_id'] !== null ? (int) $row['user_id'] : null,
            (string) $row['author_name'],
            (string) $row['author_email'],
            (string) $row['title'],
            (string) $row['source_filename'],
            (string) ($row['plugin_version'] ?? ''),
            (string) ($row['submission_kind'] ?? 'editor'),
            (string) ($row['target_language'] ?? 'EN'),
            (string) $row['source_yaml'],
            (string) $row['translations_json'],
            (string) $row['output_yaml'],
            (int) $row['total_items'],
            (int) $row['translated_items'],
            (int) $row['progress_percent'],
            (string) $row['status'],
            $row['reviewer_id'] !== null ? (int) $row['reviewer_id'] : null,
            (string) ($row['review_note'] ?? ''),
            $row['reviewed_at'] !== null ? (string) $row['reviewed_at'] : null,
            (string) $row['created_at'],
            (string) $row['updated_at'],
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrateProject(array $row): PluginTranslationProject
    {
        return new PluginTranslationProject(
            (int) $row['id'],
            (string) $row['name'],
            (string) $row['slug'],
            $row['page_id'] !== null ? (int) $row['page_id'] : null,
            (string) ($row['page_title'] ?? ''),
            (string) ($row['page_slug'] ?? ''),
            (string) $row['status'],
            $row['created_by'] !== null ? (int) $row['created_by'] : null,
            (int) $row['approved_files'],
            (string) $row['created_at'],
            (string) $row['updated_at'],
        );
    }
}
