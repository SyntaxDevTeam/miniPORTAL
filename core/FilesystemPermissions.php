<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Core;

final class FilesystemPermissions
{
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
}
