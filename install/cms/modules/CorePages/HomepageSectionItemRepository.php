<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\CorePages;

use PDO;
use RuntimeException;
use SyntaxDevTeam\Cms\Database\CrudApp;

final class HomepageSectionItemRepository
{
    public function __construct(
        private readonly CrudApp $database,
    ) {
    }

    /**
     * @return list<HomepageSectionItem>
     */
    public function forSection(int $sectionId, bool $visibleOnly = false): array
    {
        $sql = "SELECT i.*, CASE WHEN p.status = 'published' THEN p.slug ELSE '' END AS page_slug "
            . 'FROM homepage_section_items AS i '
            . 'LEFT JOIN core_pages AS p ON p.id = i.page_id '
            . 'WHERE i.section_id = :section_id';
        if ($visibleOnly) {
            $sql .= ' AND i.is_visible = 1';
        }
        $sql .= ' ORDER BY i.sort_order ASC, i.id ASC';
        $statement = $this->database->query($sql, [':section_id' => $sectionId]);

        if ($statement === null) {
            throw new RuntimeException('Nie można pobrać elementów sekcji.');
        }

        return array_map($this->hydrate(...), $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return list<HomepageSectionItem> */
    public function forSectionLocale(int $sectionId, string $locale, bool $visibleOnly = false): array
    {
        if ($locale === 'pl') {
            return $this->forSection($sectionId, $visibleOnly);
        }
        $sql = "SELECT i.id, i.section_id, i.page_id, CASE WHEN p.status = 'published' "
            . "AND pt.status = 'published' THEN p.slug ELSE '' END AS page_slug, "
            . 't.label, t.title, t.content, t.content_format, i.item_kind, i.icon_key, '
            . 't.button_label, i.button_url, i.variant, i.width, i.sort_order, i.is_visible, '
            . 'i.created_at, i.updated_at '
            . 'FROM homepage_section_items i '
            . 'JOIN homepage_section_item_translations t ON t.item_id = i.id '
            . 'LEFT JOIN core_pages p ON p.id = i.page_id '
            . 'LEFT JOIN core_page_translations pt ON pt.page_id = p.id AND pt.locale = :page_locale '
            . 'WHERE i.section_id = :section_id AND t.locale = :locale AND t.status = :status';
        if ($visibleOnly) {
            $sql .= ' AND i.is_visible = 1';
        }
        $sql .= ' ORDER BY i.sort_order ASC, i.id ASC';
        $statement = $this->database->query($sql, [
            ':section_id' => $sectionId,
            ':locale' => $locale,
            ':page_locale' => $locale,
            ':status' => 'published',
        ]);

        return array_map($this->hydrate(...), $statement?->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    public function find(int $id): ?HomepageSectionItem
    {
        $statement = $this->database->query(
            "SELECT i.*, CASE WHEN p.status = 'published' THEN p.slug ELSE '' END AS page_slug "
            . 'FROM homepage_section_items AS i '
            . 'LEFT JOIN core_pages AS p ON p.id = i.page_id '
            . 'WHERE i.id = :id LIMIT 1',
            [':id' => $id]
        );
        $row = $statement?->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function translation(int $itemId, string $locale): ?HomepageSectionItemTranslation
    {
        $statement = $this->database->query(
            'SELECT * FROM homepage_section_item_translations '
            . 'WHERE item_id = :item_id AND locale = :locale LIMIT 1',
            [':item_id' => $itemId, ':locale' => $locale]
        );
        $row = $statement?->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->hydrateTranslation($row) : null;
    }

    /** @param array<string, string> $data */
    public function saveTranslation(int $itemId, string $locale, array $data, string $origin = 'manual'): bool
    {
        $values = [
            'label' => $data['label'],
            'title' => $data['title'],
            'content' => $data['content'],
            'content_format' => $data['content_format'],
            'button_label' => $data['button_label'],
            'status' => 'draft',
            'origin' => $origin,
            'source_updated_at' => $data['source_updated_at'],
        ];
        if ($this->translation($itemId, $locale) === null) {
            return $this->database->insert('homepage_section_item_translations', [
                'item_id' => $itemId,
                'locale' => $locale,
                ...$values,
            ]) !== null;
        }

        return $this->database->update('homepage_section_item_translations', $values, [
            'item_id' => $itemId,
            'locale' => $locale,
        ]) !== null;
    }

    public function setTranslationStatus(int $itemId, string $locale, string $status): bool
    {
        if (!in_array($status, ['draft', 'published'], true)) {
            return false;
        }
        $statement = $this->database->update('homepage_section_item_translations', ['status' => $status], [
            'item_id' => $itemId,
            'locale' => $locale,
        ]);

        return $statement !== null && $statement->rowCount() === 1;
    }

    /**
     * @param array<string, scalar|null> $data
     */
    public function create(int $sectionId, array $data): int
    {
        $data['section_id'] = $sectionId;
        $data['sort_order'] = $this->nextSortOrder($sectionId);

        return (int) $this->database->create('homepage_section_items', $data);
    }

    /**
     * @param array<string, scalar|null> $data
     */
    public function update(int $id, array $data): bool
    {
        return $this->database->update('homepage_section_items', $data, ['id' => $id]) !== null;
    }

    public function move(int $id, string $direction): bool
    {
        $item = $this->find($id);
        if ($item === null || !in_array($direction, ['up', 'down'], true)) {
            return false;
        }

        $operator = $direction === 'up' ? '<' : '>';
        $order = $direction === 'up' ? 'DESC' : 'ASC';
        $statement = $this->database->query(
            "SELECT id, sort_order FROM homepage_section_items
             WHERE section_id = :section_id AND sort_order {$operator} :sort_order
             ORDER BY sort_order {$order}, id {$order} LIMIT 1",
            [
                ':section_id' => $item->sectionId,
                ':sort_order' => $item->sortOrder,
            ]
        );
        $neighbor = $statement?->fetch(PDO::FETCH_ASSOC);

        if (!is_array($neighbor)) {
            return false;
        }

        $pdo = $this->database->connection()->pdo;
        $pdo->beginTransaction();
        try {
            $update = $pdo->prepare(
                'UPDATE homepage_section_items SET sort_order = :sort_order WHERE id = :id'
            );
            $update->execute([':sort_order' => (int) $neighbor['sort_order'], ':id' => $item->id]);
            $update->execute([':sort_order' => $item->sortOrder, ':id' => (int) $neighbor['id']]);
            $pdo->commit();
            return true;
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function toggleVisibility(int $id): bool
    {
        $item = $this->find($id);
        if ($item === null) {
            return false;
        }

        $statement = $this->database->update(
            'homepage_section_items',
            ['is_visible' => $item->isVisible ? 0 : 1],
            ['id' => $id]
        );

        return $statement !== null && $statement->rowCount() === 1;
    }

    public function delete(int $id): bool
    {
        $statement = $this->database->delete('homepage_section_items', ['id' => $id]);

        return $statement !== null && $statement->rowCount() === 1;
    }

    private function nextSortOrder(int $sectionId): int
    {
        $statement = $this->database->query(
            'SELECT COALESCE(MAX(sort_order), 0) + 10
             FROM homepage_section_items WHERE section_id = :section_id',
            [':section_id' => $sectionId]
        );

        return (int) ($statement?->fetchColumn() ?: 10);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): HomepageSectionItem
    {
        return new HomepageSectionItem(
            (int) $row['id'],
            (int) $row['section_id'],
            $row['page_id'] !== null ? (int) $row['page_id'] : null,
            (string) ($row['page_slug'] ?? ''),
            (string) ($row['label'] ?? ''),
            (string) $row['title'],
            (string) $row['content'],
            (string) ($row['content_format'] ?? 'html'),
            (string) ($row['item_kind'] ?? 'card'),
            (string) ($row['icon_key'] ?? ''),
            (string) ($row['button_label'] ?? ''),
            (string) ($row['button_url'] ?? ''),
            (string) $row['variant'],
            (string) $row['width'],
            (int) $row['sort_order'],
            (bool) $row['is_visible'],
            (string) ($row['created_at'] ?? ''),
            (string) ($row['updated_at'] ?? ''),
        );
    }

    private function hydrateTranslation(array $row): HomepageSectionItemTranslation
    {
        return new HomepageSectionItemTranslation(
            (int) $row['item_id'],
            (string) $row['locale'],
            (string) ($row['label'] ?? ''),
            (string) $row['title'],
            (string) $row['content'],
            (string) ($row['content_format'] ?? 'html'),
            (string) ($row['button_label'] ?? ''),
            (string) $row['status'],
            (string) ($row['origin'] ?? 'manual'),
            $row['source_updated_at'] !== null ? (string) $row['source_updated_at'] : null,
            (string) $row['updated_at'],
        );
    }
}
