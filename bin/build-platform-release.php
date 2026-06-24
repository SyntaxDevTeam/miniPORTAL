<?php

declare(strict_types=1);

use SyntaxDevTeam\Cms\Core\Autoloader;

require_once dirname(__DIR__) . '/core/Autoloader.php';
Autoloader::register();

$root = dirname(__DIR__);
$version = $argv[1] ?? '';
$notesFile = $argv[2] ?? '';
if (preg_match('/^\d+\.\d+\.\d+(?:-[a-z0-9.-]+)?$/i', $version) !== 1) {
    fwrite(STDERR, "Użycie: php bin/build-platform-release.php VERSION PLIK_LISTY_ZMIAN_JSON\n");
    exit(1);
}
if (!is_file($notesFile)) {
    fwrite(STDERR, "Nie znaleziono pliku listy zmian.\n");
    exit(1);
}
$notes = json_decode((string) file_get_contents($notesFile), true, 16, JSON_THROW_ON_ERROR);
if (!is_array($notes) || !is_array($notes['changelog'] ?? null)) {
    throw new RuntimeException('Plik listy zmian musi zawierać tablicę changelog.');
}
$config = require $root . '/config/config.php';
$configuredVersion = (string) ($config['app']['version'] ?? '');
if ($configuredVersion !== $version) {
    throw new RuntimeException(
        "Wersja config/config.php ({$configuredVersion}) nie odpowiada budowanemu wydaniu {$version}."
    );
}

$releaseDirectory = $root . '/releases';
$temporary = $releaseDirectory . '/.build-' . bin2hex(random_bytes(4));
$payload = $temporary . '/payload';
$archive = $releaseDirectory . '/miniportal-' . $version . '.zip';
$allowedRoots = ['bin', 'config', 'core', 'modules', 'templates', 'tools'];
$files = [];

$remove = static function (string $path) use (&$remove): void {
    if (!file_exists($path) && !is_link($path)) {
        return;
    }
    if (is_dir($path) && !is_link($path)) {
        foreach (new FilesystemIterator($path) as $item) {
            $remove($item->getPathname());
        }
        rmdir($path);
        return;
    }
    unlink($path);
};

$include = static function (string $relative): bool {
    $relative = str_replace('\\', '/', $relative);
    if (
        str_starts_with($relative, 'config/installed.')
        || str_starts_with($relative, 'config/modules/')
        || str_ends_with($relative, '/.env')
        || in_array($relative, ['bin/build-cms-distribution.php', 'bin/build-platform-release.php'], true)
    ) {
        return false;
    }
    foreach (explode('/', $relative) as $segment) {
        if (str_starts_with($segment, '.') && !in_array($segment, ['.htaccess', '.env.example'], true)) {
            return false;
        }
    }

    return true;
};

$copy = static function (string $source, string $relative) use ($payload, &$files): void {
    $target = $payload . '/' . $relative;
    if (!is_dir(dirname($target)) && !mkdir(dirname($target), 0775, true)) {
        throw new RuntimeException("Nie można utworzyć katalogu dla {$relative}.");
    }
    if (!copy($source, $target)) {
        throw new RuntimeException("Nie można skopiować {$relative}.");
    }
    $checksum = hash_file('sha256', $target);
    if (!is_string($checksum)) {
        throw new RuntimeException("Nie można obliczyć SHA-256 {$relative}.");
    }
    $files[$relative] = $checksum;
};

try {
    if (!is_dir($releaseDirectory) && !mkdir($releaseDirectory, 0775, true)) {
        throw new RuntimeException('Nie można utworzyć katalogu releases/.');
    }
    mkdir($payload, 0775, true);
    foreach (['index.php', '.htaccess'] as $file) {
        $copy($root . '/' . $file, $file);
    }
    foreach ($allowedRoots as $directory) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root . '/' . $directory, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $item) {
            if ($item->isLink()) {
                throw new RuntimeException('Wydanie nie może zawierać dowiązań symbolicznych.');
            }
            if (!$item->isFile()) {
                continue;
            }
            $relative = $directory . '/' . substr(
                $item->getPathname(),
                strlen($root . '/' . $directory) + 1
            );
            if ($include($relative)) {
                $copy($item->getPathname(), $relative);
            }
        }
    }
    ksort($files, SORT_STRING);
    file_put_contents($temporary . '/release.json', json_encode([
        'version' => $version,
        'minimum_version' => (string) ($notes['minimum_version'] ?? '0.1.0'),
        'released_at' => gmdate(\DateTimeInterface::ATOM),
        'files' => $files,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL);

    @unlink($archive);
    if (class_exists(ZipArchive::class)) {
        $zip = new ZipArchive();
        if ($zip->open($archive, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Nie można utworzyć archiwum wydania.');
        }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($temporary, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $item) {
            if ($item->isFile()) {
                $relative = substr($item->getPathname(), strlen($temporary) + 1);
                $zip->addFile($item->getPathname(), str_replace('\\', '/', $relative));
            }
        }
        $zip->close();
    } else {
        $zip = trim((string) shell_exec('command -v zip 2>/dev/null'));
        if ($zip === '') {
            throw new RuntimeException('Brak ZipArchive i narzędzia zip.');
        }
        exec(
            'cd ' . escapeshellarg($temporary) . ' && '
            . escapeshellarg($zip) . ' -qr ' . escapeshellarg($archive) . ' . 2>/dev/null',
            $output,
            $code
        );
        if ($code !== 0) {
            throw new RuntimeException('Nie można utworzyć archiwum wydania.');
        }
    }
    $archiveChecksum = hash_file('sha256', $archive);
    if (!is_string($archiveChecksum)) {
        throw new RuntimeException('Nie można obliczyć SHA-256 wydania.');
    }

    $catalogFile = $releaseDirectory . '/catalog.json';
    $catalog = is_file($catalogFile)
        ? json_decode((string) file_get_contents($catalogFile), true, 32, JSON_THROW_ON_ERROR)
        : ['releases' => []];
    $catalog['releases'] = array_values(array_filter(
        is_array($catalog['releases'] ?? null) ? $catalog['releases'] : [],
        static fn (mixed $release): bool => !is_array($release) || ($release['version'] ?? null) !== $version
    ));
    $catalog['releases'][] = [
        'version' => $version,
        'released_at' => gmdate(\DateTimeInterface::ATOM),
        'minimum_version' => (string) ($notes['minimum_version'] ?? '0.1.0'),
        'filename' => basename($archive),
        'checksum' => $archiveChecksum,
        'changelog' => array_values(array_map('strval', $notes['changelog'])),
    ];
    usort($catalog['releases'], static fn (array $left, array $right): int => version_compare(
        (string) $right['version'],
        (string) $left['version']
    ));
    file_put_contents(
        $catalogFile,
        json_encode($catalog, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL
    );
    @chmod($archive, 0660);
    echo "Zbudowano wydanie miniPORTAL {$version}: {$archive}\n";
} finally {
    $remove($temporary);
}
