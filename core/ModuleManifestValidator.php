<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Core;

use JsonException;
use RuntimeException;

final class ModuleManifestValidator
{
    public function __construct(
        private readonly string $miniportalVersion,
        private readonly array $trustedPublishers = [],
    ) {
    }

    public function validate(string $directory): ModuleManifest
    {
        $file = rtrim($directory, '/') . '/info.json';
        if (!is_file($file)) {
            throw new RuntimeException("Moduł nie posiada pliku info.json: {$directory}");
        }

        try {
            $data = json_decode((string) file_get_contents($file), true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException("Nieprawidłowy JSON w {$file}.", 0, $exception);
        }

        if (!is_array($data)) {
            throw new RuntimeException("Manifest {$file} musi być obiektem JSON.");
        }

        foreach (['id', 'name', 'version', 'type', 'author', 'requires', 'protected'] as $key) {
            if (!array_key_exists($key, $data)) {
                throw new RuntimeException("Manifest {$file} nie zawiera pola {$key}.");
            }
        }

        $id = (string) $data['id'];
        $version = (string) $data['version'];
        $type = (string) $data['type'];
        $requires = is_array($data['requires']) ? $data['requires'] : [];
        $modules = $requires['modules'] ?? [];
        $factory = $data['factory'] ?? null;
        $install = $data['install'] ?? null;
        $uninstall = $data['uninstall'] ?? null;
        $origin = is_array($data['origin'] ?? null) ? $data['origin'] : [];
        $signatureFile = $data['signature'] ?? null;

        if (preg_match('/^[a-z][a-z0-9_]{1,63}$/', $id) !== 1) {
            throw new RuntimeException("Manifest {$file} zawiera nieprawidłowy identyfikator.");
        }
        if (!is_string($data['name']) || trim($data['name']) === '') {
            throw new RuntimeException("Manifest {$file} wymaga nazwy modułu.");
        }
        if (preg_match('/^\d+\.\d+\.\d+(?:-[a-z0-9.-]+)?$/i', $version) !== 1) {
            throw new RuntimeException("Manifest {$file} zawiera nieprawidłową wersję semantyczną.");
        }
        if (!in_array($type, ['core', 'extension', 'system'], true)) {
            throw new RuntimeException("Manifest {$file} zawiera nieobsługiwany typ modułu.");
        }
        if (!is_bool($data['protected'])) {
            throw new RuntimeException("Pole protected w {$file} musi być wartością logiczną.");
        }
        if (!is_array($modules)) {
            throw new RuntimeException("Lista zależności modułów w {$file} jest nieprawidłowa.");
        }

        $requiredModules = [];
        foreach ($modules as $moduleId) {
            if (!is_string($moduleId) || preg_match('/^[a-z][a-z0-9_]{1,63}$/', $moduleId) !== 1) {
                throw new RuntimeException("Manifest {$file} zawiera nieprawidłową zależność modułu.");
            }
            $requiredModules[] = $moduleId;
        }

        $phpConstraint = (string) ($requires['php'] ?? '');
        $miniportalConstraint = (string) ($requires['miniportal'] ?? '');
        $this->assertConstraint($phpConstraint, PHP_VERSION, 'PHP', $file);
        $this->assertConstraint($miniportalConstraint, $this->miniportalVersion, 'miniPORTAL', $file);

        if ($factory !== null) {
            $this->assertPhpFile($directory, $factory, $id, $file);
        }
        if ($install !== null) {
            $this->assertSqlFile($directory, $install, 'instalacyjny', $id, $file);
        }
        if ($uninstall !== null) {
            $this->assertSqlFile($directory, $uninstall, 'deinstalacyjny', $id, $file);
        }
        $originType = trim((string) ($origin['type'] ?? 'unspecified'));
        $originUrl = trim((string) ($origin['url'] ?? ''));
        if (!in_array($originType, ['bundled', 'repository', 'archive', 'unspecified'], true)) {
            throw new RuntimeException("Manifest {$file} zawiera nieobsługiwany typ pochodzenia.");
        }
        if ($originUrl !== '' && filter_var($originUrl, FILTER_VALIDATE_URL) === false) {
            throw new RuntimeException("Manifest {$file} zawiera nieprawidłowy adres pochodzenia.");
        }
        if (strlen($originUrl) > 2048) {
            throw new RuntimeException("Adres pochodzenia w {$file} jest zbyt długi.");
        }
        $signatureKeyId = null;
        $signatureStatus = 'unsigned';
        if ($signatureFile !== null) {
            if (!is_string($signatureFile)) {
                throw new RuntimeException("Pole signature w {$file} musi wskazywać plik JSON.");
            }
            if ($originType === 'unspecified' || $originUrl === '') {
                throw new RuntimeException("Podpisany pakiet {$id} wymaga jawnego typu i URL pochodzenia.");
            }
            $verification = (new ModulePackageVerifier($this->trustedPublishers))->verify(
                $directory,
                $signatureFile,
                $id,
                $version,
                $originType,
                $originUrl
            );
            $signatureKeyId = $verification['key_id'];
            $signatureStatus = $verification['status'];
        }

        return new ModuleManifest(
            $id,
            trim((string) $data['name']),
            $version,
            $type,
            trim((string) $data['author']),
            $phpConstraint,
            $miniportalConstraint,
            array_values(array_unique($requiredModules)),
            $data['protected'],
            $factory,
            $install,
            $uninstall,
            $originType,
            $originUrl,
            $signatureKeyId,
            $signatureStatus,
            rtrim($directory, '/'),
        );
    }

    private function assertPhpFile(
        string $directory,
        mixed $name,
        string $moduleId,
        string $manifestFile,
    ): void {
        if (!is_string($name) || basename($name) !== $name || !str_ends_with($name, '.php')) {
            throw new RuntimeException("Plik fabryki w {$manifestFile} jest nieprawidłowy.");
        }
        if (!is_file(rtrim($directory, '/') . '/' . $name)) {
            throw new RuntimeException("Nie znaleziono pliku fabryki {$name} dla {$moduleId}.");
        }
    }

    /**
     * Waliduje pojedynczy pakiet bez przenoszenia jego błędu na skan pozostałych modułów.
     *
     * @return array{manifest: ?ModuleManifest, error: ?string}
     */
    public function inspect(string $directory): array
    {
        try {
            return ['manifest' => $this->validate($directory), 'error' => null];
        } catch (\Throwable $exception) {
            return ['manifest' => null, 'error' => $exception->getMessage()];
        }
    }

    private function assertSqlFile(
        string $directory,
        mixed $name,
        string $purpose,
        string $moduleId,
        string $manifestFile,
    ): void {
        if (!is_string($name) || basename($name) !== $name || !str_ends_with($name, '.sql')) {
            throw new RuntimeException("Plik {$purpose} w {$manifestFile} jest nieprawidłowy.");
        }
        if (!is_file(rtrim($directory, '/') . '/' . $name)) {
            throw new RuntimeException("Nie znaleziono pliku {$purpose} {$name} dla {$moduleId}.");
        }
    }

    private function assertConstraint(string $constraint, string $current, string $component, string $file): void
    {
        if ($constraint === '') {
            return;
        }
        if (preg_match('/^(>=|>|=|<=|<)\s*(\d+\.\d+(?:\.\d+)?)$/', $constraint, $matches) !== 1) {
            throw new RuntimeException("Manifest {$file} ma nieobsługiwane wymaganie {$component}: {$constraint}.");
        }
        if (!version_compare($current, $matches[2], $matches[1])) {
            throw new RuntimeException("Moduł wymaga {$component} {$constraint}; dostępna wersja to {$current}.");
        }
    }
}
