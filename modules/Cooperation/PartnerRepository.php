<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\Cooperation;

use PDO;
use SyntaxDevTeam\Cms\Database\CrudApp;

final class PartnerRepository
{
    public function __construct(private readonly CrudApp $database) {}

    public function all(bool $visibleOnly = false): array
    {
        return $this->database->query('SELECT * FROM cooperation_partners'
            . ($visibleOnly ? ' WHERE is_visible = 1' : '') . ' ORDER BY sort_order, id')->fetchAll(PDO::FETCH_ASSOC);
    }

    public function settings(): array
    {
        $row = $this->database->query('SELECT is_enabled, placement FROM cooperation_settings WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : ['is_enabled' => 1, 'placement' => 'before_contact'];
    }

    public function saveSettings(bool $enabled, string $placement): void
    {
        $this->database->update('cooperation_settings', ['is_enabled' => $enabled ? 1 : 0, 'placement' => $placement], ['id' => 1]);
    }

    public function find(int $id): ?array
    {
        $row = $this->database->query('SELECT * FROM cooperation_partners WHERE id = :id', [':id' => $id])->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function save(array $data, ?int $id = null): int
    {
        if ($id === null) {
            return (int) $this->database->create('cooperation_partners', $data);
        }
        $this->database->update('cooperation_partners', $data, ['id' => $id]);
        return $id;
    }

    public function delete(int $id): bool
    {
        return $this->database->delete('cooperation_partners', ['id' => $id])?->rowCount() === 1;
    }
}
