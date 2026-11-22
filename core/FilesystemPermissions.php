<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Core;

final class FilesystemPermissions
{
    private const PLATFORM_DIRECTORIES = ['core', 'modules', 'templates', 'bin', 'tools'];

    /** @return list<string> */
    public static function requiredDirectories(): array
    {
        return ['cache', 'cache/build-artifacts', 'cache/platform-updates', 'uploads/branding', 'uploads/media'];
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
            'sudo chgrp -R www-data config cache uploads/branding uploads/media',
            'sudo find config cache -type d -exec chmod 2770 {} \;',
            'sudo find config cache -type f -exec chmod 0660 {} \;',
            'sudo find uploads/branding uploads/media -type d -exec chmod 2775 {} \;',
            'sudo find uploads/branding uploads/media -type f -exec chmod 0664 {} \;',
            'sudo chgrp www-data modules',
            'sudo chmod 2775 modules',
        ]);
    }

    /** @return list<string> */
    public static function platformUpdateIssues(string $root): array
    {
        $root = rtrim($root, '/');
        $issues = [];
        if (!is_dir($root) || !is_writable($root) || !is_executable($root)) {
            $issues[] = '.';
        }
        $protected = LocalRuntime::protectedPaths($root);
        foreach (self::PLATFORM_DIRECTORIES as $directory) {
            $path = $root . '/' . $directory;
            if (!is_dir($path)) {
                continue;
            }
            if (!is_writable($path) || !is_executable($path) || !is_readable($path)) {
                $issues[] = $directory . '/';
                continue;
            }
            $directories = new \RecursiveCallbackFilterIterator(
                new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
                static function (\SplFileInfo $item) use ($directory, $path, $protected, &$issues): bool {
                    $relative = $directory . '/' . substr($item->getPathname(), strlen($path) + 1);
                    if (!$item->isDir() || $item->isLink() || LocalRuntime::isProtected($relative, $protected)) {
                        return false;
                    }
                    if (!$item->isReadable() || !$item->isExecutable()) {
                        $issues[] = $relative;
                        return false;
                    }

                    return true;
                }
            );
            foreach (new \RecursiveIteratorIterator($directories, \RecursiveIteratorIterator::SELF_FIRST) as $item) {
                if (!$item->isWritable()) {
                    $issues[] = $directory . '/' . substr($item->getPathname(), strlen($path) + 1);
                }
            }
        }
        foreach (['config', 'config/keys', 'cache/platform-updates', 'cache/platform-updates/downloads', 'cache/platform-updates/backups'] as $directory) {
            $path = $root . '/' . $directory;
            if (is_dir($path) && (!is_writable($path) || !is_executable($path))) {
                $issues[] = $directory . '/';
            }
        }

        return array_values(array_unique($issues));
    }

    public static function platformUpdateRemediationCommand(string $root): string
    {
        $directories = self::platformUpdateIssues($root);
        if ($directories === []) {
            return '';
        }
        $paths = implode(' ', array_map(
            static fn (string $path): string => escapeshellarg(rtrim($path, '/')),
            $directories
        ));

        return implode("\n", [
            'cd ' . escapeshellarg(rtrim($root, '/')),
            'sudo chgrp www-data -- ' . $paths,
            'sudo chmod g+rwx,g+s -- ' . $paths,
        ]);
    }
}
