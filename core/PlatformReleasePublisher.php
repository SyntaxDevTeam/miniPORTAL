<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Core;

use RuntimeException;

final class PlatformReleasePublisher
{
    public function __construct(
        private readonly string $applicationRoot,
        private readonly string $workPath,
    ) {
    }

    public function available(): bool
    {
        return is_file($this->generatorPath());
    }

    /**
     * @return list<string>
     */
    public function permissionIssues(): array
    {
        $issues = [];
        foreach ($this->requiredWritablePaths() as $path) {
            if (!file_exists($path)) {
                $issues[] = $this->relativePath($path) . ' nie istnieje';
                continue;
            }
            if (!is_writable($path)) {
                $issues[] = $this->relativePath($path) . ' nie jest zapisywalny przez proces PHP';
            }
        }

        return $issues;
    }

    public function remediationCommand(): string
    {
        $paths = array_map(fn (string $path): string => $this->shellQuote($this->relativePath($path)), $this->requiredWritablePaths());

        return "cd " . $this->shellQuote($this->applicationRoot) . "\n"
            . "sudo install -d -m 2770 -g www-data releases cache/platform-updates\n"
            . "sudo chgrp www-data " . implode(' ', $paths) . "\n"
            . "sudo chmod 0660 " . implode(' ', $paths) . "\n"
            . "sudo chgrp -R www-data releases cache/platform-updates\n"
            . "sudo find releases cache/platform-updates -type d -exec chmod 2770 {} \\;\n"
            . "sudo find releases cache/platform-updates -type f -exec chmod 0660 {} \\;";
    }

    /**
     * @param list<string> $changelog
     */
    public function publish(string $version, string $minimumVersion, array $changelog): string
    {
        if (!$this->available()) {
            throw new RuntimeException('Generator wydań jest dostępny wyłącznie w instalacji macierzystej.');
        }
        foreach ([$version, $minimumVersion] as $candidate) {
            if (preg_match('/^\d+\.\d+\.\d+(?:-[a-z0-9.-]+)?$/i', $candidate) !== 1) {
                throw new RuntimeException('Wersja wydania ma nieprawidłowy format SemVer.');
            }
        }
        if (version_compare($minimumVersion, $version, '>')) {
            throw new RuntimeException('Minimalna wersja nie może być wyższa od publikowanego wydania.');
        }
        $issues = $this->permissionIssues();
        if ($issues !== []) {
            throw new RuntimeException(
                "Nie można zbudować wydania, bo proces PHP nie ma wymaganych praw zapisu:\n"
                . implode("\n", array_map(static fn (string $issue): string => '- ' . $issue, $issues))
                . "\n\nWklej na serwerze:\n" . $this->remediationCommand()
            );
        }
        $configFile = rtrim($this->applicationRoot, '/') . '/config/config.php';
        $configSource = (string) file_get_contents($configFile);
        if (preg_match("/'version'\\s*=>\\s*'([^']+)'/", $configSource, $match) !== 1) {
            throw new RuntimeException('Nie można odczytać bieżącej wersji z config/config.php.');
        }
        $currentVersion = $match[1];
        if (version_compare($version, $currentVersion, '<')) {
            throw new RuntimeException('Nie można opublikować wersji starszej od bieżącego kodu.');
        }
        $items = [];
        foreach ($changelog as $item) {
            $item = trim($this->normalizeUtf8($item));
            if ($item === '') {
                continue;
            }
            if (strlen($item) > 500) {
                throw new RuntimeException('Pojedynczy wpis listy zmian może mieć maksymalnie 500 znaków.');
            }
            $items[] = $item;
        }
        if ($items === [] || count($items) > 50) {
            throw new RuntimeException('Lista zmian musi zawierać od 1 do 50 pozycji.');
        }
        if (!is_dir($this->workPath) && !mkdir($this->workPath, 0770, true)) {
            throw new RuntimeException('Nie można utworzyć katalogu roboczego publikacji.');
        }
        $notes = rtrim($this->workPath, '/') . '/notes-' . bin2hex(random_bytes(6)) . '.json';
        file_put_contents($notes, json_encode([
            'minimum_version' => $minimumVersion,
            'changelog' => $items,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR));
        @chmod($notes, 0660);

        $versionFiles = [$configFile];
        $installerSource = rtrim($this->applicationRoot, '/') . '/install/cms-source/Installer.php';
        if (is_file($installerSource) && is_writable($installerSource)) {
            $versionFiles[] = $installerSource;
        }
        $originalSources = [];
        try {
            foreach ($versionFiles as $versionFile) {
                $source = (string) file_get_contents($versionFile);
                $updated = preg_replace(
                    "/'version'\\s*=>\\s*'" . preg_quote($currentVersion, '/') . "'/",
                    "'version' => '" . $version . "'",
                    $source,
                    1,
                    $replacements
                );
                if (!is_string($updated) || $replacements !== 1) {
                    throw new RuntimeException('Nie można ustawić wersji w ' . basename($versionFile) . '.');
                }
                $originalSources[$versionFile] = $source;
                if (file_put_contents($versionFile, $updated, LOCK_EX) === false) {
                    throw new RuntimeException('Nie można zapisać wersji w ' . basename($versionFile) . '.');
                }
            }
            $command = [
                $this->phpBinary(),
                $this->generatorPath(),
                $version,
                $notes,
            ];
            $process = proc_open(
                $command,
                [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']],
                $pipes,
                $this->applicationRoot
            );
            if (!is_resource($process)) {
                throw new RuntimeException('Nie można uruchomić generatora wydania.');
            }
            fclose($pipes[0]);
            $output = trim((string) stream_get_contents($pipes[1]));
            fclose($pipes[1]);
            $error = trim((string) stream_get_contents($pipes[2]));
            fclose($pipes[2]);
            $code = proc_close($process);
            if ($code !== 0) {
                throw new RuntimeException($error !== '' ? $error : 'Generator wydania zakończył się błędem.');
            }

            return $output;
        } catch (\Throwable $exception) {
            foreach ($originalSources as $versionFile => $source) {
                @file_put_contents($versionFile, $source, LOCK_EX);
            }
            throw $exception;
        } finally {
            @unlink($notes);
        }
    }

    private function generatorPath(): string
    {
        return rtrim($this->applicationRoot, '/') . '/bin/build-platform-release.php';
    }

    /**
     * @return list<string>
     */
    private function requiredWritablePaths(): array
    {
        $paths = [
            rtrim($this->applicationRoot, '/') . '/config/config.php',
        ];
        $installerSource = rtrim($this->applicationRoot, '/') . '/install/cms-source/Installer.php';
        if (is_file($installerSource)) {
            $paths[] = $installerSource;
        }

        return $paths;
    }

    private function relativePath(string $path): string
    {
        $root = rtrim($this->applicationRoot, '/') . '/';
        return str_starts_with($path, $root) ? substr($path, strlen($root)) : $path;
    }

    private function shellQuote(string $value): string
    {
        return "'" . str_replace("'", "'\\''", $value) . "'";
    }

    private function normalizeUtf8(string $value): string
    {
        if (preg_match('//u', $value) === 1) {
            return $value;
        }

        $clean = @iconv('UTF-8', 'UTF-8//IGNORE', $value);
        if (is_string($clean) && preg_match('//u', $clean) === 1) {
            return $clean;
        }

        return '';
    }

    private function phpBinary(): string
    {
        $candidates = [
            PHP_BINARY,
            PHP_BINDIR . '/php',
            '/usr/bin/php',
        ];
        foreach ($candidates as $candidate) {
            if (is_string($candidate) && $candidate !== '' && is_file($candidate) && is_executable($candidate)) {
                return $candidate;
            }
        }

        throw new RuntimeException('Nie znaleziono wykonywalnego PHP CLI do zbudowania wydania.');
    }
}
