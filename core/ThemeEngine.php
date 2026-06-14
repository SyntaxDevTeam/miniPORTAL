<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Core;

use RuntimeException;

final class ThemeEngine
{
    public function __construct(
        private readonly string $templatesPath,
    ) {
    }

    public function load(string $themeName, array $config = []): ThemeInterface
    {
        if (preg_match('/^[a-z][a-z0-9_-]*$/', $themeName) !== 1) {
            throw new RuntimeException('Nazwa motywu zawiera niedozwolone znaki.');
        }

        $themeFile = $this->templatesPath . '/' . $themeName . '/theme.php';

        if (!is_file($themeFile)) {
            throw new RuntimeException("Nie znaleziono pliku motywu: {$themeName}");
        }

        require_once $themeFile;

        $className = $this->className($themeName);

        if (!class_exists($className)) {
            throw new RuntimeException("Motyw {$themeName} nie udostępnia klasy {$className}.");
        }

        $theme = new $className($config);

        if (!$theme instanceof ThemeInterface) {
            throw new RuntimeException("Motyw {$themeName} nie implementuje ThemeInterface.");
        }

        return $theme;
    }

    private function className(string $themeName): string
    {
        $namespacePart = str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $themeName)));

        return "SyntaxDevTeam\\Cms\\Templates\\{$namespacePart}Theme\\Theme";
    }
}
