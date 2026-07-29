<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Core;

final class InstallationState
{
    public const LEGACY_ENVIRONMENT_FILE = '/etc/miniportal/miniportal.env';
    public const LOCAL_INSTALLATION_MARKER = 'config/local-installation.required';

    public static function environmentFile(
        string $root,
        string $legacyFile = self::LEGACY_ENVIRONMENT_FILE,
    ): string {
        $explicit = getenv('MINIPORTAL_ENV_FILE');
        $explicit = is_string($explicit) ? trim($explicit) : '';
        if ($explicit !== '') {
            return $explicit;
        }

        $local = rtrim($root, '/') . '/config/installed.env';
        if (is_readable($local)) {
            return $local;
        }

        if (is_file(rtrim($root, '/') . '/' . self::LOCAL_INSTALLATION_MARKER)) {
            return $local;
        }

        return $legacyFile;
    }

    public static function hasConfiguration(
        string $root,
        string $legacyFile = self::LEGACY_ENVIRONMENT_FILE,
    ): bool {
        $file = self::environmentFile($root, $legacyFile);
        if (!is_readable($file)) {
            return false;
        }

        $values = parse_ini_file($file, false, INI_SCANNER_RAW);
        return is_array($values)
            && filter_var($values['DB_ENABLED'] ?? false, FILTER_VALIDATE_BOOL)
            && trim((string) ($values['DB_NAME'] ?? '')) !== ''
            && trim((string) ($values['DB_USER'] ?? '')) !== '';
    }

    public static function isInstalled(
        string $root,
        string $legacyFile = self::LEGACY_ENVIRONMENT_FILE,
    ): bool {
        return is_file(rtrim($root, '/') . '/config/installed.lock')
            || self::hasConfiguration($root, $legacyFile);
    }
}
