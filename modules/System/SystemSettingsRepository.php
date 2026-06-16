<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\System;

use RuntimeException;
use SyntaxDevTeam\Cms\Database\CrudApp;

final class SystemSettingsRepository
{
    public function __construct(
        private readonly CrudApp $database,
    ) {
    }

    /**
     * @param array<string, string> $defaults
     * @return array<string, string>
     */
    public function themeSettings(array $defaults): array
    {
        $rows = $this->database->read(
            'system_settings',
            ['setting_key', 'setting_value'],
            ['setting_key' => ['theme', 'public_name', 'public_eyebrow']]
        ) ?? [];
        foreach ($rows as $row) {
            $defaults[(string) $row['setting_key']] = (string) $row['setting_value'];
        }

        return $defaults;
    }

    /**
     * @param array<string, string> $settings
     * @param array<string, string> $availableThemes
     */
    public function saveThemeSettings(array $settings, array $availableThemes, int $actorId): void
    {
        $theme = trim((string) ($settings['theme'] ?? ''));
        $publicName = trim((string) ($settings['public_name'] ?? ''));
        $publicEyebrow = trim((string) ($settings['public_eyebrow'] ?? ''));
        if (!isset($availableThemes[$theme])) {
            throw new RuntimeException('Wybrany motyw nie istnieje.');
        }
        if ($publicName === '' || strlen($publicName) > 80) {
            throw new RuntimeException('Nazwa publiczna jest wymagana i może mieć maksymalnie 80 znaków.');
        }
        if ($publicEyebrow === '' || strlen($publicEyebrow) > 160) {
            throw new RuntimeException('Nadtytuł jest wymagany i może mieć maksymalnie 160 znaków.');
        }

        $pdo = $this->database->connection()->pdo;
        $statement = $pdo->prepare(
            'INSERT INTO system_settings (setting_key, setting_value, updated_by) '
            . 'VALUES (:setting_key, :setting_value, :updated_by) '
            . 'ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), '
            . 'updated_by = VALUES(updated_by), updated_at = CURRENT_TIMESTAMP'
        );
        foreach ([
            'theme' => $theme,
            'public_name' => $publicName,
            'public_eyebrow' => $publicEyebrow,
        ] as $key => $value) {
            $statement->execute([
                ':setting_key' => $key,
                ':setting_value' => $value,
                ':updated_by' => $actorId,
            ]);
        }
    }

    /**
     * @return array<string, string>
     */
    public function publicNavigationAreas(): array
    {
        $row = $this->database->read(
            'system_settings',
            ['setting_value'],
            ['setting_key' => 'public_navigation']
        )[0] ?? null;
        $decoded = is_array($row)
            ? json_decode((string) ($row['setting_value'] ?? '{}'), true)
            : [];
        if (!is_array($decoded)) {
            return [];
        }

        $areas = [];
        foreach ($decoded as $id => $area) {
            if (is_string($id) && is_string($area) && in_array($area, ['none', 'main', 'footer'], true)) {
                $areas[$id] = $area;
            }
        }

        return $areas;
    }

    /**
     * @param array<string, string> $areas
     * @param list<string> $allowedIds
     */
    public function savePublicNavigationAreas(array $areas, array $allowedIds, int $actorId): void
    {
        $allowed = array_fill_keys($allowedIds, true);
        $clean = [];
        foreach ($areas as $id => $area) {
            if (!isset($allowed[$id]) || !in_array($area, ['none', 'main', 'footer'], true)) {
                continue;
            }
            $clean[$id] = $area;
        }

        $this->upsertSettings([
            'public_navigation' => json_encode($clean, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        ], $actorId);
    }

    /**
     * @param array<string, string> $values
     */
    private function upsertSettings(array $values, int $actorId): void
    {
        $pdo = $this->database->connection()->pdo;
        $statement = $pdo->prepare(
            'INSERT INTO system_settings (setting_key, setting_value, updated_by) '
            . 'VALUES (:setting_key, :setting_value, :updated_by) '
            . 'ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), '
            . 'updated_by = VALUES(updated_by), updated_at = CURRENT_TIMESTAMP'
        );
        foreach ($values as $key => $value) {
            $statement->execute([
                ':setting_key' => $key,
                ':setting_value' => $value,
                ':updated_by' => $actorId,
            ]);
        }
    }
}
