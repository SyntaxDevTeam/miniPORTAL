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
}
