<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\PluginTranslator;

use PDO;
use RuntimeException;
use SyntaxDevTeam\Cms\Database\CrudApp;

final class PluginTranslationRepository
{
    private const SELECT_COLUMNS = 'id, user_id, author_name, author_email, title, source_filename, target_language, source_yaml, '
        . 'translations_json, output_yaml, total_items, translated_items, progress_percent, status, reviewer_id, '
        . 'review_note, reviewed_at, created_at, updated_at';

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
            'SELECT ' . self::SELECT_COLUMNS . ' FROM plugin_translation_submissions '
            . "ORDER BY FIELD(status, 'ready_for_review', 'draft', 'rejected', 'approved'), updated_at DESC, id DESC"
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
            'SELECT ' . self::SELECT_COLUMNS . ' FROM plugin_translation_submissions '
            . 'WHERE status = :status ORDER BY reviewed_at DESC, id DESC LIMIT ' . $limit,
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
            'SELECT ' . self::SELECT_COLUMNS . ' FROM plugin_translation_submissions WHERE id = :id LIMIT 1',
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
            'SELECT ' . self::SELECT_COLUMNS . ' FROM plugin_translation_submissions '
            . 'WHERE user_id = :user_id ORDER BY updated_at DESC, id DESC',
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
            'SELECT ' . self::SELECT_COLUMNS . ' FROM plugin_translation_submissions '
            . 'WHERE id = :id AND user_id = :user_id LIMIT 1',
            [':id' => $id, ':user_id' => $userId]
        );
        $row = $statement?->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->hydrate($row) : null;
    }

    /**
     * @param array<string, string> $translations
     */
    public function create(
        ?int $userId,
        string $authorName,
        string $authorEmail,
        string $title,
        string $sourceFilename,
        string $targetLanguage,
        string $sourceYaml,
        array $translations,
        string $outputYaml,
        int $totalItems,
        int $translatedItems,
        string $status,
    ): int {
        return (int) $this->database->create('plugin_translation_submissions', [
            'user_id' => $userId,
            'author_name' => $authorName,
            'author_email' => $authorEmail,
            'title' => $title,
            'source_filename' => $sourceFilename,
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
        string $authorName,
        string $authorEmail,
        string $title,
        string $targetLanguage,
        array $translations,
        string $outputYaml,
        int $totalItems,
        int $translatedItems,
        string $status,
    ): bool {
        $statement = $this->database->update('plugin_translation_submissions', [
            'author_name' => $authorName,
            'author_email' => $authorEmail,
            'title' => $title,
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
            $row['user_id'] !== null ? (int) $row['user_id'] : null,
            (string) $row['author_name'],
            (string) $row['author_email'],
            (string) $row['title'],
            (string) $row['source_filename'],
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
}
