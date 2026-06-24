<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$source = $root . '/install/cms-source';
$target = $root . '/install/cms';
$temporary = $root . '/install/.cms-build-' . bin2hex(random_bytes(4));

$checksum = static function (string $file): string {
    $value = hash_file('sha256', $file);
    if (!is_string($value)) {
        throw new RuntimeException('Nie można obliczyć SHA-256 pliku ' . $file . '.');
    }

    return $value;
};

$remove = static function (string $path) use (&$remove): void {
    if (!file_exists($path) && !is_link($path)) {
        return;
    }
    if (is_dir($path) && !is_link($path)) {
        foreach (new FilesystemIterator($path) as $item) {
            $remove($item->getPathname());
        }
        if (!rmdir($path)) {
            throw new RuntimeException("Nie można usunąć katalogu {$path}.");
        }
        return;
    }
    if (!unlink($path)) {
        throw new RuntimeException("Nie można usunąć pliku {$path}.");
    }
};

$copyDirectory = static function (string $from, string $to): void {
    if (!mkdir($to, 0775, true) && !is_dir($to)) {
        throw new RuntimeException("Nie można utworzyć katalogu {$to}.");
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($from, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($iterator as $item) {
        if ($item->isLink()) {
            throw new RuntimeException('Dystrybucja nie może zawierać dowiązań symbolicznych.');
        }
        $basename = $item->getBasename();
        if ($item->isFile() && (
            $basename === '.env'
            || (str_starts_with($basename, '.env.') && $basename !== '.env.example')
        )) {
            continue;
        }
        $relative = substr($item->getPathname(), strlen($from) + 1);
        if (preg_match('~(^|/)migrations(?:/|$)~', $relative) === 1) {
            continue;
        }
        $destination = $to . '/' . $relative;
        if ($item->isDir()) {
            if (!mkdir($destination, 0775, true) && !is_dir($destination)) {
                throw new RuntimeException("Nie można utworzyć katalogu {$destination}.");
            }
        } elseif (!copy($item->getPathname(), $destination)) {
            throw new RuntimeException("Nie można skopiować pliku {$relative}.");
        }
    }
};

try {
    mkdir($temporary, 0775, true);
    foreach (['bin', 'config', 'core', 'modules', 'templates', 'tools'] as $directory) {
        $copyDirectory($root . '/' . $directory, $temporary . '/' . $directory);
    }
    @unlink($temporary . '/bin/build-cms-distribution.php');
    foreach (['.htaccess', '.env.example', 'index.php'] as $file) {
        if (!copy($root . '/' . $file, $temporary . '/' . $file)) {
            throw new RuntimeException("Nie można skopiować {$file}.");
        }
    }

    mkdir($temporary . '/installer', 0775, true);
    foreach (['install.php', 'INSTALL.md'] as $file) {
        if (!copy($source . '/' . $file, $temporary . '/' . $file)) {
            throw new RuntimeException("Nie można dołączyć {$file}.");
        }
    }
    if (!copy($source . '/Installer.php', $temporary . '/installer/Installer.php')) {
        throw new RuntimeException('Nie można dołączyć silnika instalatora.');
    }
    $migrationBaseline = ['core' => [], 'modules' => []];
    foreach (glob($root . '/core/migrations/*.sql') ?: [] as $migration) {
        $migrationBaseline['core'][basename($migration)] = $checksum($migration);
    }
    foreach (glob($root . '/modules/*/info.json') ?: [] as $manifestFile) {
        $manifest = json_decode((string) file_get_contents($manifestFile), true);
        $moduleId = is_array($manifest) ? ($manifest['id'] ?? null) : null;
        if (!is_string($moduleId) || $moduleId === '') {
            throw new RuntimeException('Nie można zbudować manifestu migracji modułu.');
        }
        $migrationBaseline['modules'][$moduleId] = [];
        foreach (glob(dirname($manifestFile) . '/migrations/*.sql') ?: [] as $migration) {
            $migrationBaseline['modules'][$moduleId][basename($migration)] = $checksum($migration);
        }
        ksort($migrationBaseline['modules'][$moduleId]);
    }
    ksort($migrationBaseline['core']);
    ksort($migrationBaseline['modules']);
    $baselinePhp = "<?php\n\ndeclare(strict_types=1);\n\nreturn "
        . var_export($migrationBaseline, true)
        . ";\n";
    file_put_contents($temporary . '/installer/migration-baseline.php', $baselinePhp);

    foreach (['templates', 'build-artifacts', 'module-quarantine'] as $directory) {
        $path = $temporary . '/cache/' . $directory;
        mkdir($path, 0775, true);
        file_put_contents($path . '/.gitkeep', '');
    }
    mkdir($temporary . '/config/modules', 0775, true);
    file_put_contents($temporary . '/config/modules/.gitkeep', '');
    mkdir($temporary . '/uploads/branding', 0775, true);
    file_put_contents($temporary . '/uploads/branding/.gitkeep', '');
    if (!copy($root . '/uploads/.htaccess', $temporary . '/uploads/.htaccess')) {
        throw new RuntimeException('Nie można dołączyć ochrony katalogu uploads.');
    }
    @unlink($temporary . '/config/installed.env');
    @unlink($temporary . '/config/installed.lock');

    $remove($target);
    if (!rename($temporary, $target)) {
        throw new RuntimeException('Nie można zatwierdzić folderu dystrybucyjnego.');
    }
    echo "Zbudowano czystą dystrybucję: {$target}\n";
} catch (Throwable $exception) {
    $remove($temporary);
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
}
