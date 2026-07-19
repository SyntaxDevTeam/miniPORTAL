<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\MediaLibrary;

use PDO;
use SyntaxDevTeam\Cms\Database\CrudApp;

final class MediaAssetRepository
{
    public const CATEGORIES = [
        'project_icon' => 'Ikona projektu',
        'logo' => 'Logo',
        'screenshot' => 'Zrzut ekranu',
        'presentation' => 'Grafika prezentacyjna',
        'content' => 'Treść',
        'other' => 'Inne',
    ];

    public function __construct(private readonly CrudApp $database)
    {
    }

    /** @return list<MediaAsset> */
    public function all(string $category = ''): array
    {
        $parameters = [];
        $where = '';
        if (isset(self::CATEGORIES[$category])) {
            $where = ' WHERE category = :category';
            $parameters[':category'] = $category;
        }
        $statement = $this->query('SELECT * FROM media_assets' . $where . ' ORDER BY category ASC, title ASC', $parameters);

        return array_map($this->hydrate(...), $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    public function find(int $id): ?MediaAsset
    {
        $row = $this->query('SELECT * FROM media_assets WHERE id = :id LIMIT 1', [':id' => $id])->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function findByPublicPath(string $publicPath): ?MediaAsset
    {
        $publicPath = trim($publicPath);
        if ($publicPath === '') {
            return null;
        }

        try {
            $row = $this->query('SELECT * FROM media_assets WHERE public_path = :public_path LIMIT 1', [':public_path' => $publicPath])->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return null;
        }

        return is_array($row) ? $this->hydrate($row) : null;
    }

    /** @return array<string, string> */
    public function options(string $category = ''): array
    {
        try {
            $items = $this->all($category);
        } catch (\Throwable) {
            return [];
        }
        $options = [];
        foreach ($items as $asset) {
            $options[$asset->publicPath] = $asset->title . ' - ' . self::CATEGORIES[$asset->category];
        }

        return $options;
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $title = $this->bounded((string) ($data['title'] ?? ''), 180);
        if ($title === '') {
            $title = pathinfo((string) ($data['original_name'] ?? 'grafika'), PATHINFO_FILENAME) ?: 'Grafika';
        }
        $slug = $this->uniqueSlug($this->slugify($title));
        $category = (string) ($data['category'] ?? 'other');
        if (!isset(self::CATEGORIES[$category])) {
            $category = 'other';
        }

        return (int) $this->database->create('media_assets', [
            'title' => $title,
            'slug' => $slug,
            'category' => $category,
            'alt_text' => $this->bounded((string) ($data['alt_text'] ?? ''), 255),
            'original_name' => $this->bounded((string) ($data['original_name'] ?? ''), 255),
            'stored_name' => $this->bounded((string) ($data['stored_name'] ?? ''), 255),
            'public_path' => $this->bounded((string) ($data['public_path'] ?? ''), 500),
            'mime_type' => $this->bounded((string) ($data['mime_type'] ?? ''), 120),
            'file_size' => max(0, (int) ($data['file_size'] ?? 0)),
            'width' => isset($data['width']) ? (int) $data['width'] : null,
            'height' => isset($data['height']) ? (int) $data['height'] : null,
            'created_by' => isset($data['created_by']) ? (int) $data['created_by'] : null,
        ]);
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): bool
    {
        $category = (string) ($data['category'] ?? 'other');
        if (!isset(self::CATEGORIES[$category])) {
            $category = 'other';
        }
        $statement = $this->database->update('media_assets', [
            'title' => $this->bounded((string) ($data['title'] ?? ''), 180),
            'category' => $category,
            'alt_text' => $this->bounded((string) ($data['alt_text'] ?? ''), 255),
        ], ['id' => $id]);

        return $statement !== null;
    }

    public function delete(int $id): ?MediaAsset
    {
        $asset = $this->find($id);
        if (!$asset instanceof MediaAsset) {
            return null;
        }
        $statement = $this->database->delete('media_assets', ['id' => $id]);

        return $statement !== null && $statement->rowCount() === 1 ? $asset : null;
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): MediaAsset
    {
        return new MediaAsset(
            (int) $row['id'],
            (string) $row['title'],
            (string) $row['slug'],
            (string) $row['category'],
            (string) $row['alt_text'],
            (string) $row['original_name'],
            (string) $row['stored_name'],
            (string) $row['public_path'],
            (string) $row['mime_type'],
            (int) $row['file_size'],
            $row['width'] !== null ? (int) $row['width'] : null,
            $row['height'] !== null ? (int) $row['height'] : null,
            $row['created_by'] !== null ? (int) $row['created_by'] : null,
            (string) $row['created_at'],
        );
    }

    /** @param array<string, scalar|null> $parameters */
    private function query(string $sql, array $parameters = []): \PDOStatement
    {
        $statement = $this->database->connection()->pdo->prepare($sql);
        foreach ($parameters as $name => $value) {
            $statement->bindValue($name, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $statement->execute();

        return $statement;
    }

    private function uniqueSlug(string $base): string
    {
        $base = $base !== '' ? $base : 'grafika';
        $slug = $base;
        $i = 2;
        while ((int) $this->query('SELECT COUNT(*) FROM media_assets WHERE slug = :slug', [':slug' => $slug])->fetchColumn() > 0) {
            $slug = substr($base, 0, 180) . '-' . $i++;
        }

        return $slug;
    }

    private function slugify(string $value): string
    {
        $value = strtolower(trim($value));
        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;

        return substr(trim(preg_replace('/[^a-z0-9]+/', '-', $value) ?? '', '-'), 0, 191);
    }

    private function bounded(string $value, int $max): string
    {
        return mb_substr(trim(str_replace("\0", '', $value)), 0, $max);
    }
}
