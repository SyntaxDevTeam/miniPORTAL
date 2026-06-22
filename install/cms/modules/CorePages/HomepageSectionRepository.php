<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\CorePages;

use PDO;
use RuntimeException;
use SyntaxDevTeam\Cms\Database\CrudApp;

final class HomepageSectionRepository
{
    public function __construct(
        private readonly CrudApp $database,
    ) {
    }

    /**
     * @return list<HomepageSection>
     */
    public function all(): array
    {
        return $this->fetch(
            'SELECT * FROM homepage_sections ORDER BY sort_order ASC, id ASC'
        );
    }

    /**
     * @return list<HomepageSection>
     */
    public function visible(): array
    {
        return $this->fetch(
            'SELECT * FROM homepage_sections WHERE is_visible = 1 ORDER BY sort_order ASC, id ASC'
        );
    }

    public function find(int $id): ?HomepageSection
    {
        $statement = $this->database->query(
            'SELECT * FROM homepage_sections WHERE id = :id LIMIT 1',
            [':id' => $id]
        );
        $row = $statement?->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function keyExists(string $sectionKey, ?int $exceptId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM homepage_sections WHERE section_key = :section_key';
        $parameters = [':section_key' => $sectionKey];

        if ($exceptId !== null) {
            $sql .= ' AND id <> :id';
            $parameters[':id'] = $exceptId;
        }

        return (int) $this->database->query($sql, $parameters)?->fetchColumn() > 0;
    }

    /**
     * @param array<string, scalar|null> $data
     */
    public function create(array $data): int
    {
        $data['sort_order'] = $this->nextSortOrder();

        return (int) $this->database->create('homepage_sections', $data);
    }

    /**
     * @param array<string, scalar|null> $data
     */
    public function update(int $id, array $data): bool
    {
        $statement = $this->database->update('homepage_sections', $data, ['id' => $id]);

        return $statement !== null;
    }

    public function toggleVisibility(int $id): bool
    {
        $section = $this->find($id);

        if ($section === null) {
            return false;
        }

        $statement = $this->database->update(
            'homepage_sections',
            ['is_visible' => $section->isVisible ? 0 : 1],
            ['id' => $id]
        );

        return $statement !== null && $statement->rowCount() === 1;
    }

    public function move(int $id, string $direction): bool
    {
        if (!in_array($direction, ['up', 'down'], true)) {
            return false;
        }

        $operator = $direction === 'up' ? '<' : '>';
        $order = $direction === 'up' ? 'DESC' : 'ASC';
        $pdo = $this->database->connection()->pdo;

        if (!$pdo instanceof PDO) {
            return false;
        }

        $pdo->beginTransaction();

        try {
            $current = $pdo->prepare(
                'SELECT id, sort_order FROM homepage_sections WHERE id = :id FOR UPDATE'
            );
            $current->execute([':id' => $id]);
            $currentRow = $current->fetch(PDO::FETCH_ASSOC);

            if (!is_array($currentRow)) {
                $pdo->rollBack();
                return false;
            }

            $neighbor = $pdo->prepare(
                "SELECT id, sort_order FROM homepage_sections
                 WHERE sort_order {$operator} :sort_order
                 ORDER BY sort_order {$order}, id {$order} LIMIT 1 FOR UPDATE"
            );
            $neighbor->execute([':sort_order' => (int) $currentRow['sort_order']]);
            $neighborRow = $neighbor->fetch(PDO::FETCH_ASSOC);

            if (!is_array($neighborRow)) {
                $pdo->rollBack();
                return false;
            }

            $update = $pdo->prepare(
                'UPDATE homepage_sections SET sort_order = :sort_order WHERE id = :id'
            );
            $update->execute([
                ':sort_order' => (int) $neighborRow['sort_order'],
                ':id' => (int) $currentRow['id'],
            ]);
            $update->execute([
                ':sort_order' => (int) $currentRow['sort_order'],
                ':id' => (int) $neighborRow['id'],
            ]);
            $pdo->commit();

            return true;
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    public function delete(int $id): bool
    {
        $statement = $this->database->delete('homepage_sections', ['id' => $id]);

        return $statement !== null && $statement->rowCount() === 1;
    }

    private function nextSortOrder(): int
    {
        $statement = $this->database->query(
            'SELECT COALESCE(MAX(sort_order), 0) + 10 FROM homepage_sections'
        );

        return (int) ($statement?->fetchColumn() ?: 10);
    }

    /**
     * @return list<HomepageSection>
     */
    private function fetch(string $sql): array
    {
        $statement = $this->database->query($sql);

        if ($statement === null) {
            throw new RuntimeException('Nie można pobrać sekcji strony głównej.');
        }

        return array_map($this->hydrate(...), $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): HomepageSection
    {
        return new HomepageSection(
            (int) $row['id'],
            (string) $row['section_key'],
            (string) $row['section_type'],
            (string) ($row['eyebrow'] ?? ''),
            (string) ($row['acrostic_words'] ?? ''),
            (string) $row['title'],
            (string) $row['content_html'],
            (string) ($row['content_format'] ?? 'html'),
            (string) $row['layout'],
            (string) ($row['button_label'] ?? ''),
            (string) ($row['button_url'] ?? ''),
            (int) $row['sort_order'],
            (bool) $row['is_visible'],
            (int) $row['author_id'],
            (string) $row['created_at'],
            (string) $row['updated_at'],
        );
    }
}
