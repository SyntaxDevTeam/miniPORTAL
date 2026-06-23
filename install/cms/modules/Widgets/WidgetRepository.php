<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\Widgets;

use PDO;
use RuntimeException;
use SyntaxDevTeam\Cms\Database\CrudApp;

final class WidgetRepository
{
    private const COLUMNS = 'id, widget_key, name, widget_type, placement, target_section_key, theme_name, '
        . 'title, content, button_label, button_url, sort_order, is_visible, created_at, updated_at';

    public function __construct(private readonly CrudApp $database)
    {
    }

    /** @return list<Widget> */
    public function all(): array
    {
        $statement = $this->database->query(
            'SELECT ' . self::COLUMNS . ' FROM widgets ORDER BY placement ASC, sort_order ASC, name ASC'
        );
        if ($statement === null) {
            throw new RuntimeException('Nie można pobrać listy widgetów.');
        }

        return array_map($this->hydrate(...), $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return list<Widget> */
    public function visibleForTheme(string $theme): array
    {
        $statement = $this->database->query(
            'SELECT ' . self::COLUMNS . ' FROM widgets '
            . "WHERE is_visible = 1 AND (theme_name = '*' OR theme_name = :theme) "
            . 'ORDER BY (theme_name = :theme_order) DESC, sort_order ASC, id ASC',
            [':theme' => $theme, ':theme_order' => $theme]
        );
        if ($statement === null) {
            throw new RuntimeException('Nie można pobrać publicznych widgetów.');
        }

        return array_map($this->hydrate(...), $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    public function find(int $id): ?Widget
    {
        $statement = $this->database->query(
            'SELECT ' . self::COLUMNS . ' FROM widgets WHERE id = :id LIMIT 1',
            [':id' => $id]
        );
        $row = $statement?->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function keyExists(string $key, string $theme, ?int $exceptId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM widgets WHERE widget_key = :widget_key AND theme_name = :theme_name';
        $parameters = [':widget_key' => $key, ':theme_name' => $theme];
        if ($exceptId !== null) {
            $sql .= ' AND id <> :id';
            $parameters[':id'] = $exceptId;
        }

        return (int) $this->database->query($sql, $parameters)?->fetchColumn() > 0;
    }

    /** @param array<string, scalar|null> $data */
    public function create(array $data): int
    {
        $id = (int) $this->database->create('widgets', $data);
        if ($id < 1) {
            throw new RuntimeException('Nie można utworzyć widgetu.');
        }

        return $id;
    }

    /** @param array<string, scalar|null> $data */
    public function update(int $id, array $data): bool
    {
        return $this->database->update('widgets', $data, ['id' => $id]) !== null;
    }

    public function delete(int $id): bool
    {
        $statement = $this->database->delete('widgets', ['id' => $id]);

        return $statement !== null && $statement->rowCount() === 1;
    }

    /** @return array{all:int,visible:int} */
    public function stats(): array
    {
        return [
            'all' => $this->database->count('widgets'),
            'visible' => $this->database->count('widgets', ['is_visible' => 1]),
        ];
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): Widget
    {
        return new Widget(
            (int) $row['id'],
            (string) $row['widget_key'],
            (string) $row['name'],
            (string) $row['widget_type'],
            (string) $row['placement'],
            (string) $row['target_section_key'],
            (string) $row['theme_name'],
            (string) $row['title'],
            (string) $row['content'],
            (string) $row['button_label'],
            (string) $row['button_url'],
            (int) $row['sort_order'],
            (bool) $row['is_visible'],
            (string) $row['created_at'],
            (string) $row['updated_at'],
        );
    }
}
