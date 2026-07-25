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

    public function suggestedVersion(string $currentVersion): string
    {
        if (preg_match('/^(\d+)\.(\d+)\.(\d+)$/', $currentVersion, $match) !== 1) {
            return $currentVersion;
        }

        return $match[1] . '.' . $match[2] . '.' . ((int) $match[3] + 1);
    }

    /**
     * @return list<string>
     */
    public function suggestedChangelog(): array
    {
        if (!is_dir(rtrim($this->applicationRoot, '/') . '/.git')) {
            return [];
        }
        try {
            $baseline = trim($this->runProcess([
                'git',
                'log',
                '-1',
                '--format=%H',
                '--',
                'config/config.php',
            ]));
            $range = $baseline !== '' ? $baseline . '..HEAD' : 'HEAD';
            $output = $this->runProcess([
                'git',
                'log',
                '--no-merges',
                '--format=%s',
                $range,
            ]);
        } catch (\Throwable) {
            return [];
        }

        $items = [];
        foreach (preg_split('/\R/u', $output) ?: [] as $subject) {
            $subject = trim($this->normalizeUtf8($subject));
            if (
                $subject === ''
                || preg_match('/^(release|chore\(release\))\s*:/i', $subject) === 1
            ) {
                continue;
            }
            $items[] = strlen($subject) > 500 ? substr($subject, 0, 500) : $subject;
        }

        return array_slice(array_values(array_unique($items)), 0, 50);
    }

    /**
     * @return list<string>
     */
    public function permissionIssues(): array
    {
        $issues = [];
        foreach ($this->requiredWritableOutputFiles() as $path) {
            if (!is_writable($path)) {
                $issues[] = $this->relativePath($path) . ' nie jest zapisywalny przez proces PHP';
            }
        }
        foreach ($this->requiredWritableDirectories() as $path) {
            if (is_dir($path)) {
                if (!is_writable($path)) {
                    $issues[] = $this->relativePath($path) . '/ nie jest zapisywalny przez proces PHP';
                }
                continue;
            }
            $parent = dirname($path);
            if (!is_dir($parent) || !is_writable($parent)) {
                $issues[] = $this->relativePath($path) . '/ nie może zostać utworzony przez proces PHP';
            }
        }

        return $issues;
    }

    public function remediationCommand(): string
    {
        $directories = array_map(
            fn (string $path): string => $this->shellQuote($this->relativePath($path)),
            $this->requiredWritableDirectories()
        );
        $files = array_map(
            fn (string $path): string => $this->shellQuote($this->relativePath($path)),
            $this->requiredWritableOutputFiles()
        );
        $fileCommands = $files === []
            ? ''
            : "sudo chgrp www-data " . implode(' ', $files) . "\n"
                . "sudo chmod 0660 " . implode(' ', $files) . "\n";

        return "cd " . $this->shellQuote($this->applicationRoot) . "\n"
            . "sudo install -d -m 2770 -g www-data " . implode(' ', $directories) . "\n"
            . $fileCommands
            . "sudo chgrp www-data " . implode(' ', $directories) . "\n"
            . "sudo chmod 2770 " . implode(' ', $directories);
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
        $installerSource = rtrim($this->applicationRoot, '/') . '/install/cms-source/Installer.php';
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
        if (is_file($installerSource)) {
            $versionFiles[] = $installerSource;
        }
        $originalSources = [];
        try {
            foreach ($versionFiles as $versionFile) {
                $source = (string) file_get_contents($versionFile);
                if (preg_match("/'version'\\s*=>\\s*'([^']+)'/", $source, $sourceMatch) !== 1) {
                    throw new RuntimeException('Nie można odczytać wersji z ' . $this->relativePath($versionFile) . '.');
                }
                $sourceVersion = $sourceMatch[1];
                if ($sourceVersion !== $currentVersion) {
                    throw new RuntimeException(
                        'Wersje config/config.php i źródła instalatora nie są zgodne.'
                    );
                }
                $updated = preg_replace(
                    "/'version'\\s*=>\\s*'" . preg_quote($currentVersion, '/') . "'/",
                    "'version' => '" . $version . "'",
                    $source,
                    1,
                    $replacements
                );
                if (!is_string($updated) || $replacements !== 1) {
                    throw new RuntimeException('Nie można ustawić wersji w ' . $this->relativePath($versionFile) . '.');
                }
                $originalSources[$versionFile] = $source;
                if (file_put_contents($versionFile, $updated, LOCK_EX) === false) {
                    throw new RuntimeException('Nie można zapisać wersji w ' . $this->relativePath($versionFile) . '.');
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
    private function requiredWritableDirectories(): array
    {
        return array_values(array_unique([
            rtrim($this->applicationRoot, '/') . '/releases',
            rtrim($this->workPath, '/'),
        ]));
    }

    /**
     * @return list<string>
     */
    private function requiredWritableOutputFiles(): array
    {
        $root = rtrim($this->applicationRoot, '/');
        $files = [
            $root . '/config/config.php',
            $root . '/releases/catalog.json',
        ];
        $installerSource = $root . '/install/cms-source/Installer.php';
        if (is_file($installerSource)) {
            $files[] = $installerSource;
        }

        return array_values(array_filter($files, 'is_file'));
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

    /**
     * @param list<string> $command
     */
    private function runProcess(array $command): string
    {
        $process = proc_open(
            $command,
            [['file', '/dev/null', 'r'], ['pipe', 'w'], ['pipe', 'w']],
            $pipes,
            $this->applicationRoot
        );
        if (!is_resource($process)) {
            throw new RuntimeException('Nie można uruchomić procesu pomocniczego.');
        }
        $output = (string) stream_get_contents($pipes[1]);
        $error = trim((string) stream_get_contents($pipes[2]));
        fclose($pipes[1]);
        fclose($pipes[2]);
        $status = proc_close($process);
        if ($status !== 0) {
            throw new RuntimeException($error !== '' ? $error : 'Proces pomocniczy zakończył się błędem.');
        }

        return trim($output);
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
