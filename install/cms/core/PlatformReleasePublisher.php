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
        $configFile = rtrim($this->applicationRoot, '/') . '/config/config.php';
        if (!is_file($configFile) || !is_writable($configFile)) {
            throw new RuntimeException('Źródłowy config/config.php nie jest zapisywalny.');
        }
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
            $item = trim($item);
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
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
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
