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
            ['setting_key' => [
                'theme',
                'public_name',
                'public_eyebrow',
                'public_meta_description',
                'public_meta_keywords',
                'public_footer_text',
            ]]
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
        $this->saveThemeChoice((string) ($settings['theme'] ?? ''), $availableThemes, $actorId);
        $this->saveBrandingSeoSettings($settings, $actorId);
    }

    /**
     * @param array<string, string> $availableThemes
     */
    public function saveThemeChoice(string $theme, array $availableThemes, int $actorId): void
    {
        $theme = trim($theme);
        if (!isset($availableThemes[$theme])) {
            throw new RuntimeException('Wybrany motyw nie istnieje.');
        }

        $this->upsertSettings(['theme' => $theme], $actorId);
    }

    /**
     * @param array<string, string> $settings
     */
    public function saveBrandingSeoSettings(array $settings, int $actorId): void
    {
        $publicName = trim((string) ($settings['public_name'] ?? ''));
        $publicEyebrow = trim((string) ($settings['public_eyebrow'] ?? ''));
        $metaDescription = trim((string) ($settings['public_meta_description'] ?? ''));
        $metaKeywords = trim((string) ($settings['public_meta_keywords'] ?? ''));
        $footerText = trim((string) ($settings['public_footer_text'] ?? ''));
        if ($publicName === '' || strlen($publicName) > 80) {
            throw new RuntimeException('Nazwa publiczna jest wymagana i może mieć maksymalnie 80 znaków.');
        }
        if ($publicEyebrow === '' || strlen($publicEyebrow) > 160) {
            throw new RuntimeException('Nadtytuł jest wymagany i może mieć maksymalnie 160 znaków.');
        }
        if ($metaDescription === '' || strlen($metaDescription) > 255) {
            throw new RuntimeException('Opis meta jest wymagany i może mieć maksymalnie 255 znaków.');
        }
        if (strlen($metaKeywords) > 255) {
            throw new RuntimeException('Słowa kluczowe meta mogą mieć maksymalnie 255 znaków.');
        }
        if ($footerText === '' || strlen($footerText) > 160) {
            throw new RuntimeException('Tekst stopki jest wymagany i może mieć maksymalnie 160 znaków.');
        }

        $this->upsertSettings([
            'public_name' => $publicName,
            'public_eyebrow' => $publicEyebrow,
            'public_meta_description' => $metaDescription,
            'public_meta_keywords' => $metaKeywords,
            'public_footer_text' => $footerText,
        ], $actorId);
    }

    /**
     * @return array<string, string|array{label: string, main: bool, footer: bool}>
     */
    public function publicNavigationSettings(): array
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

        $settings = [];
        foreach ($decoded as $id => $value) {
            if (!is_string($id)) {
                continue;
            }
            if (is_string($value) && in_array($value, ['none', 'main', 'footer'], true)) {
                $settings[$id] = $value;
                continue;
            }
            if (!is_array($value)) {
                continue;
            }

            $label = trim((string) ($value['label'] ?? ''));
            $settings[$id] = [
                'label' => strlen($label) <= 80 ? $label : '',
                'main' => (bool) ($value['main'] ?? false),
                'footer' => (bool) ($value['footer'] ?? false),
            ];
        }

        return $settings;
    }

    /**
     * @return array<string, string>
     */
    public function publicNavigationAreas(): array
    {
        $areas = [];
        foreach ($this->publicNavigationSettings() as $id => $setting) {
            if (is_string($setting)) {
                $areas[$id] = $setting;
                continue;
            }
            $areas[$id] = $setting['main'] ? 'main' : ($setting['footer'] ? 'footer' : 'none');
        }

        return $areas;
    }

    /**
     * @param array<string, array{label: string, main: bool, footer: bool}> $settings
     * @param array<string, string> $allowedLinks
     */
    public function savePublicNavigationSettings(array $settings, array $allowedLinks, int $actorId): void
    {
        $clean = [];
        foreach ($settings as $id => $setting) {
            if (!isset($allowedLinks[$id])) {
                continue;
            }
            $label = trim((string) ($setting['label'] ?? ''));
            if ($label === '') {
                $label = $allowedLinks[$id];
            }
            if (strlen($label) > 80) {
                throw new RuntimeException('Etykieta linku publicznego może mieć maksymalnie 80 znaków.');
            }

            $clean[$id] = [
                'label' => $label,
                'main' => (bool) ($setting['main'] ?? false),
                'footer' => (bool) ($setting['footer'] ?? false),
            ];
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
