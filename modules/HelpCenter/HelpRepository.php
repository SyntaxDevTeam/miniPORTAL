<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\HelpCenter;

use PDO;
use SyntaxDevTeam\Cms\Core\ContentRenderer;
use SyntaxDevTeam\Cms\Database\CrudApp;

final class HelpRepository
{
    public function __construct(private readonly CrudApp $database) {}

    public function settings(): array
    {
        return $this->database->query('SELECT * FROM help_center_settings WHERE id = 1')->fetch(PDO::FETCH_ASSOC)
            ?: ['title' => 'Jak możemy Ci pomóc?', 'intro' => 'Wybierz temat lub wpisz pytanie.', 'contact_url' => '', 'contact_label' => 'Skontaktuj się z nami'];
    }

    public function categories(bool $includeStaff = false): array
    {
        return $this->database->query('SELECT c.*, (SELECT COUNT(*) FROM help_center_questions q WHERE q.category_id = c.id AND '
            . self::visibilityWhere('q', $includeStaff) . ') AS question_count FROM help_center_categories c WHERE '
            . self::visibilityWhere('c', $includeStaff) . ' ORDER BY c.sort_order, c.id')->fetchAll(PDO::FETCH_ASSOC);
    }

    public function questions(bool $includeStaff = false): array
    {
        return $this->database->query('SELECT q.*, c.name AS category_name, c.is_visible AS category_visible, c.visibility AS category_visibility FROM help_center_questions q JOIN help_center_categories c ON c.id = q.category_id WHERE '
            . self::visibilityWhere('q', $includeStaff) . ' AND ' . self::visibilityWhere('c', $includeStaff)
            . ' ORDER BY c.sort_order, c.id, q.sort_order, q.id')->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(string $type, int $id, bool $includeStaff = false): ?array
    {
        self::table($type);
        $sql = match ($type) {
            'question' => 'SELECT q.* FROM help_center_questions q JOIN help_center_categories c ON c.id = q.category_id WHERE q.id = :id AND '
                . self::visibilityWhere('q', $includeStaff) . ' AND ' . self::visibilityWhere('c', $includeStaff),
            'category' => 'SELECT c.* FROM help_center_categories c WHERE c.id = :id AND ' . self::visibilityWhere('c', $includeStaff),
            'settings' => 'SELECT * FROM help_center_settings WHERE id = :id',
        };
        $row = $this->database->query($sql, [':id' => $id])->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function save(string $type, array $data, ?int $id = null): int
    {
        $table = self::table($type);
        if ($id === null) { return (int)$this->database->create($table, $data); }
        $this->database->update($table, $data, ['id' => $id]);
        return $id;
    }

    public function delete(string $type, int $id): void
    {
        // The database FK also rejects concurrent inserts into a category being deleted.
        if ($type === 'category' && (int)$this->database->count('help_center_questions', ['category_id' => $id]) > 0) {
            throw new \InvalidArgumentException('Przenieś lub usuń pytania przed usunięciem kategorii.');
        }
        $this->database->delete(self::table($type), ['id' => $id]);
    }

    /** Authorize again under row locks: an editor cannot race a category becoming private. */
    public function mutateAccessible(string $type, ?int $id, string $operation, array $data, bool $includeStaff): int
    {
        $table = self::table($type);
        $pdo = $this->database->connection()->pdo;
        $lock = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
        $pdo->beginTransaction();
        try {
            $record = null;
            if ($id !== null) {
                $record = $this->database->query('SELECT * FROM ' . $table . ' WHERE id = :id' . $lock, [':id' => $id])->fetch(PDO::FETCH_ASSOC);
                if (!is_array($record) || ($type !== 'settings' && !self::allowsVisibility($record['visibility'], $includeStaff))) {
                    throw new \OutOfBoundsException('Nie znaleziono wpisu.');
                }
            } elseif ($operation !== 'save') { throw new \OutOfBoundsException('Nie znaleziono wpisu.'); }
            if ($type === 'question') {
                $categoryIds = array_values(array_unique(array_filter([(int)($record['category_id'] ?? 0), (int)($data['category_id'] ?? 0)])));
                sort($categoryIds);
                foreach ($categoryIds as $categoryId) {
                    $category = $this->database->query('SELECT visibility FROM help_center_categories WHERE id = :id' . $lock, [':id' => $categoryId])->fetch(PDO::FETCH_ASSOC);
                    if (!is_array($category) || !self::allowsVisibility($category['visibility'], $includeStaff)) {
                        throw new \OutOfBoundsException('Nie znaleziono wpisu.');
                    }
                }
            }
            if ($operation === 'save') {
                if ($type !== 'settings' && !self::allowsVisibility($data['visibility'] ?? 'public', $includeStaff)) {
                    throw new \OutOfBoundsException('Nie znaleziono wpisu.');
                }
                $id = $this->save($type, $data, $id);
            } elseif ($operation === 'toggle') {
                $field = $type === 'category' ? 'is_visible' : 'is_published';
                $this->save($type, [$field => (int)!$record[$field]], $id);
            } elseif ($operation === 'delete') { $this->delete($type, $id); }
            else { throw new \InvalidArgumentException('Nieznana operacja.'); }
            $pdo->commit();
            return $id;
        } catch (\Throwable $error) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            throw $error;
        }
    }

    private static function allowsVisibility(string $visibility, bool $includeStaff): bool
    {
        return $visibility === 'public' || ($includeStaff && $visibility === 'staff');
    }

    public function publicModel(bool $includeStaff = false): array
    {
        $settings = $this->settings();
        $groups = [];
        foreach ($this->database->query('SELECT c.id, c.name, c.description, c.icon, c.visibility FROM help_center_categories c WHERE c.is_visible = 1 AND ' . self::visibilityWhere('c', $includeStaff) . ' ORDER BY c.sort_order, c.id')->fetchAll(PDO::FETCH_ASSOC) as $category) {
            $groups[(int)$category['id']] = $category + ['questions' => []];
        }
        $renderer = new ContentRenderer();
        // Filter in SQL: unpublished answer bodies must never enter a public presentation model.
        $rows = $this->database->query('SELECT q.*, c.visibility AS category_visibility FROM help_center_questions q JOIN help_center_categories c ON c.id = q.category_id WHERE q.is_published = 1 AND c.is_visible = 1 AND ' . self::visibilityWhere('q', $includeStaff) . ' AND ' . self::visibilityWhere('c', $includeStaff) . ' ORDER BY q.sort_order, q.id')->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $row['effective_visibility'] = ($row['visibility'] === 'staff' || $row['category_visibility'] === 'staff') ? 'staff' : 'public';
            $row['answer_html'] = $renderer->render($row['answer'], $row['content_format']);
            $groups[(int)$row['category_id']]['questions'][] = $row;
        }
        $settings['categories'] = array_values(array_filter($groups, static fn(array $c): bool => $c['questions'] !== []));
        return $settings;
    }

    private static function visibilityWhere(string $alias, bool $includeStaff): string
    {
        // Only trusted, fixed aliases and access levels are used; unknown stored values fail closed.
        return $alias . ($includeStaff ? ".visibility IN ('public', 'staff')" : ".visibility = 'public'");
    }

    private static function table(string $type): string
    {
        return match ($type) { 'category' => 'help_center_categories', 'question' => 'help_center_questions', 'settings' => 'help_center_settings', default => throw new \InvalidArgumentException('Nieznany typ wpisu.') };
    }
}
