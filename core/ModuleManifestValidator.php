<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Core;

use JsonException;
use RuntimeException;

final class ModuleManifestValidator
{
    public function __construct(
        private readonly string $miniportalVersion,
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
        $install = $data['install'] ?? null;

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

        if ($install !== null) {
            if (!is_string($install) || basename($install) !== $install || !str_ends_with($install, '.sql')) {
                throw new RuntimeException("Plik instalacyjny w {$file} jest nieprawidłowy.");
            }
            if (!is_file(rtrim($directory, '/') . '/' . $install)) {
                throw new RuntimeException("Nie znaleziono pliku instalacyjnego {$install} dla {$id}.");
            }
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
            $install,
            rtrim($directory, '/'),
        );
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
