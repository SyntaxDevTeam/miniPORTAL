<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Core;

final readonly class LocaleContext
{
    /** @param list<string> $supportedLocales */
    public function __construct(
        public string $locale,
        public string $defaultLocale,
        public array $supportedLocales,
        public string $routePath,
        public string $publicPath,
    ) {
    }

    public function localizePath(string $path, ?string $locale = null): string
    {
        $locale ??= $this->locale;
        if (!in_array($locale, $this->supportedLocales, true)) {
            $locale = $this->defaultLocale;
        }

        $path = '/' . ltrim(trim($path), '/');
        $path = preg_replace('#/+#', '/', $path) ?? '/';
        $path = $path !== '/' ? rtrim($path, '/') : '/';

        if ($path === '/admin' || str_starts_with($path, '/admin/') || str_starts_with($path, '/api/')) {
            return $path;
        }

        return '/' . $locale . ($path === '/' ? '' : $path);
    }

    /** @return array<string, string> */
    public function languageLinks(): array
    {
        $links = [];
        foreach ($this->supportedLocales as $locale) {
            $links[$locale] = $this->localizePath($this->routePath, $locale);
        }

        return $links;
    }
}
