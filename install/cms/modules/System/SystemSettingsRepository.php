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
                'public_url',
                'public_name',
                'public_default_title',
                'public_eyebrow',
                'public_meta_description',
                'public_meta_keywords',
                'public_meta_author',
                'public_meta_robots',
                'public_locale',
                'public_social_image_url',
                'public_social_image_alt',
                'public_twitter_site',
                'public_theme_color',
                'public_google_site_verification',
                'public_bing_site_verification',
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
        $this->saveBrandingSettings($settings, $actorId);
        $this->saveSeoSettings($settings, $actorId);
    }

    /** @param array<string, string> $settings */
    public function saveBrandingSettings(array $settings, int $actorId): void
    {
        $publicName = trim((string) ($settings['public_name'] ?? ''));
        $publicEyebrow = trim((string) ($settings['public_eyebrow'] ?? ''));
        $themeColor = strtolower(trim((string) ($settings['public_theme_color'] ?? '')));
        $footerText = trim((string) ($settings['public_footer_text'] ?? ''));
        if ($publicName === '' || strlen($publicName) > 80) {
            throw new RuntimeException('Nazwa publiczna jest wymagana i może mieć maksymalnie 80 znaków.');
        }
        if ($publicEyebrow === '' || strlen($publicEyebrow) > 160) {
            throw new RuntimeException('Nadtytuł jest wymagany i może mieć maksymalnie 160 znaków.');
        }
        if (preg_match('/^#[0-9a-f]{6}$/', $themeColor) !== 1) {
            throw new RuntimeException('Kolor motywu musi mieć format HEX, np. #080c12.');
        }
        if ($footerText === '' || strlen($footerText) > 160) {
            throw new RuntimeException('Tekst stopki jest wymagany i może mieć maksymalnie 160 znaków.');
        }

        $this->upsertSettings([
            'public_name' => $publicName,
            'public_eyebrow' => $publicEyebrow,
            'public_theme_color' => $themeColor,
            'public_footer_text' => $footerText,
        ], $actorId);
    }

    /** @param array<string, string> $settings */
    public function saveSeoSettings(array $settings, int $actorId): void
    {
        $publicUrl = rtrim(trim((string) ($settings['public_url'] ?? '')), '/');
        $defaultTitle = trim((string) ($settings['public_default_title'] ?? ''));
        $metaDescription = trim((string) ($settings['public_meta_description'] ?? ''));
        $metaKeywords = trim((string) ($settings['public_meta_keywords'] ?? ''));
        $metaAuthor = trim((string) ($settings['public_meta_author'] ?? ''));
        $metaRobots = trim((string) ($settings['public_meta_robots'] ?? ''));
        $locale = trim((string) ($settings['public_locale'] ?? ''));
        $socialImageUrl = trim((string) ($settings['public_social_image_url'] ?? ''));
        $socialImageAlt = trim((string) ($settings['public_social_image_alt'] ?? ''));
        $twitterSite = ltrim(trim((string) ($settings['public_twitter_site'] ?? '')), '@');
        $googleVerification = trim((string) ($settings['public_google_site_verification'] ?? ''));
        $bingVerification = trim((string) ($settings['public_bing_site_verification'] ?? ''));

        if (filter_var($publicUrl, FILTER_VALIDATE_URL) === false || !str_starts_with($publicUrl, 'https://')
            || parse_url($publicUrl, PHP_URL_QUERY) !== null || parse_url($publicUrl, PHP_URL_FRAGMENT) !== null
            || !in_array((string) parse_url($publicUrl, PHP_URL_PATH), ['', '/'], true)
            || strlen($publicUrl) > 255) {
            throw new RuntimeException('Bazowy adres strony musi być poprawnym adresem HTTPS bez zapytania i fragmentu.');
        }
        if ($defaultTitle === '' || strlen($defaultTitle) > 120) {
            throw new RuntimeException('Domyślny tytuł jest wymagany i może mieć maksymalnie 120 znaków.');
        }
        if ($metaDescription === '' || strlen($metaDescription) > 320) {
            throw new RuntimeException('Opis meta jest wymagany i może mieć maksymalnie 320 znaków.');
        }
        if (strlen($metaKeywords) > 255 || strlen($metaAuthor) > 80) {
            throw new RuntimeException('Słowa kluczowe lub autor przekraczają dozwolony limit.');
        }
        if (!in_array($metaRobots, [
            'index, follow',
            'index, follow, max-image-preview:large',
            'noindex, nofollow',
        ], true)) {
            throw new RuntimeException('Wybrano nieobsługiwaną politykę indeksowania.');
        }
        if (preg_match('/^[a-z]{2}_[A-Z]{2}$/', $locale) !== 1) {
            throw new RuntimeException('Locale musi mieć format język_KRAJ, np. pl_PL.');
        }
        if ($socialImageUrl !== '' && !$this->isSafePublicUrl($socialImageUrl)) {
            throw new RuntimeException('Obraz społecznościowy musi używać lokalnej ścieżki /... albo adresu HTTPS.');
        }
        if (strlen($socialImageUrl) > 500 || strlen($socialImageAlt) > 200) {
            throw new RuntimeException('Adres lub opis obrazu społecznościowego jest zbyt długi.');
        }
        if ($socialImageUrl !== '' && $socialImageAlt === '') {
            throw new RuntimeException('Niestandardowy obraz społecznościowy wymaga krótkiego opisu.');
        }
        if ($twitterSite !== '' && preg_match('/^[A-Za-z0-9_]{1,15}$/', $twitterSite) !== 1) {
            throw new RuntimeException('Nazwa konta X/Twitter ma nieprawidłowy format.');
        }
        foreach ([$googleVerification, $bingVerification] as $verification) {
            if (strlen($verification) > 255 || ($verification !== ''
                && preg_match('/^[A-Za-z0-9._=+-]+$/', $verification) !== 1)) {
                throw new RuntimeException('Token weryfikacyjny zawiera niedozwolone znaki.');
            }
        }

        $this->upsertSettings([
            'public_url' => $publicUrl,
            'public_default_title' => $defaultTitle,
            'public_meta_description' => $metaDescription,
            'public_meta_keywords' => $metaKeywords,
            'public_meta_author' => $metaAuthor,
            'public_meta_robots' => $metaRobots,
            'public_locale' => $locale,
            'public_social_image_url' => $socialImageUrl,
            'public_social_image_alt' => $socialImageAlt,
            'public_twitter_site' => $twitterSite,
            'public_google_site_verification' => $googleVerification,
            'public_bing_site_verification' => $bingVerification,
        ], $actorId);
    }

    private function isSafePublicUrl(string $url): bool
    {
        if (str_starts_with($url, '/') && !str_starts_with($url, '//')) {
            return !str_contains($url, "\0") && !str_contains($url, '\\');
        }

        return filter_var($url, FILTER_VALIDATE_URL) !== false && str_starts_with($url, 'https://');
    }

    /**
     * @return array<string, string|array{label: string, main: bool, footer: bool, order?: int}>
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
            $setting = [
                'label' => strlen($label) <= 80 ? $label : '',
                'main' => (bool) ($value['main'] ?? false),
                'footer' => (bool) ($value['footer'] ?? false),
            ];
            if (isset($value['order'])) {
                $setting['order'] = max(0, min(65535, (int) $value['order']));
            }
            $settings[$id] = $setting;
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

    /** @return array<string, bool> */
    public function dashboardWidgetSettings(): array
    {
        $row = $this->database->read(
            'system_settings',
            ['setting_value'],
            ['setting_key' => 'dashboard_widgets']
        )[0] ?? null;
        $decoded = is_array($row) ? json_decode((string) ($row['setting_value'] ?? '{}'), true) : [];
        if (!is_array($decoded)) {
            return [];
        }
        $settings = [];
        foreach ($decoded as $id => $enabled) {
            if (is_string($id) && preg_match('/^[a-z][a-z0-9_.-]{1,95}$/', $id) === 1) {
                $settings[$id] = (bool) $enabled;
            }
        }
        return $settings;
    }

    /** @param array<string, bool> $settings */
    public function saveDashboardWidgetSettings(array $settings, int $actorId): void
    {
        $clean = [];
        foreach ($settings as $id => $enabled) {
            if (preg_match('/^[a-z][a-z0-9_.-]{1,95}$/', $id) === 1) {
                $clean[$id] = (bool) $enabled;
            }
        }
        $this->upsertSettings([
            'dashboard_widgets' => json_encode($clean, JSON_THROW_ON_ERROR),
        ], $actorId);
    }

    /**
     * @param array<string, array{label: string, main: bool, footer: bool, order: int}> $settings
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
                'order' => max(0, min(65535, (int) ($setting['order'] ?? 100))),
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
