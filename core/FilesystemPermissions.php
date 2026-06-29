<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Core;

final class FilesystemPermissions
{
    private const PLATFORM_DIRECTORIES = ['core', 'modules', 'templates', 'bin', 'tools'];
    private const PLATFORM_FILES = ['index.php', '.htaccess'];

    /** @return list<string> */
    public static function requiredDirectories(): array
    {
        return ['cache', 'cache/build-artifacts', 'cache/platform-updates', 'uploads/branding'];
    }

    /** @return list<string> */
    public static function installerDirectories(): array
    {
        return ['config', 'config/modules', 'modules', ...self::requiredDirectories()];
    }

    /** @return list<string> */
    public static function missing(string $root): array
    {
        $missing = [];
        foreach (self::requiredDirectories() as $directory) {
            $path = rtrim($root, '/') . '/' . $directory;
            if (!is_dir($path) || !is_writable($path)) {
                $missing[] = $directory;
            }
        }
        return $missing;
    }

    public static function remediationCommand(string $root): string
    {
        return implode("\n", [
            'cd ' . rtrim($root, '/'),
            'sudo chgrp -R www-data config cache uploads/branding',
            'sudo find config cache uploads/branding -type d -exec chmod 2770 {} \;',
            'sudo find config cache uploads/branding -type f -exec chmod 0660 {} \;',
            'sudo chgrp www-data modules',
            'sudo chmod 2775 modules',
        ]);
    }

    /** @return list<string> */
    public static function platformUpdateIssues(string $root): array
    {
        $root = rtrim($root, '/');
        $issues = [];
        if (!is_dir($root) || !is_writable($root)) {
            $issues[] = '.';
        }
        foreach (self::PLATFORM_FILES as $file) {
            $path = $root . '/' . $file;
            if (!is_file($path) || !is_writable($path)) {
                $issues[] = $file;
            }
        }
        foreach (self::PLATFORM_DIRECTORIES as $directory) {
            $path = $root . '/' . $directory;
            if (!is_dir($path) || !is_writable($path)) {
                $issues[] = $directory . '/';
                continue;
            }
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $item) {
                $relativePath = $directory . '/' . substr($item->getPathname(), strlen($path) + 1);
                if ($item->isFile() && self::isLocalSecret($relativePath)) {
                    continue;
                }
                if (($item->isFile() || $item->isDir()) && !$item->isWritable()) {
                    $issues[] = $relativePath;
                    if (count($issues) >= 20) {
                        return $issues;
                    }
                }
            }
        }
        foreach (glob($root . '/config/*') ?: [] as $path) {
            $name = basename($path);
            if ($name === 'installed.env' || $name === 'installed.lock' || $name === 'modules') {
                continue;
            }
            if (!is_writable($path)) {
                $issues[] = 'config/' . $name;
            }
        }

        return array_values(array_unique($issues));
    }

    public static function platformUpdateRemediationCommand(string $root): string
    {
        return implode("\n", [
            'cd ' . rtrim($root, '/'),
            'sudo chgrp www-data . index.php .htaccess',
            'sudo chmod 2775 .',
            'sudo chmod 0664 index.php .htaccess',
            'sudo chgrp -R www-data core modules templates bin tools',
            'sudo find core modules templates bin tools -type d -exec chmod 2775 {} \;',
            'sudo find core modules templates bin tools -type f ! -name ".env" -exec chmod 0664 {} \;',
            'sudo find config -maxdepth 1 -type f ! -name "installed.env" ! -name "installed.lock" -exec chgrp www-data {} \;',
            'sudo find config -maxdepth 1 -type f ! -name "installed.env" ! -name "installed.lock" -exec chmod 0660 {} \;',
            'sudo install -d -m 2770 -o $(stat -c %U .) -g www-data cache/platform-updates',
        ]);
    }

    private static function isLocalSecret(string $relativePath): bool
    {
        $relativePath = str_replace('\\', '/', $relativePath);

        return str_starts_with($relativePath, 'modules/') && str_ends_with($relativePath, '/.env');
    }
}
