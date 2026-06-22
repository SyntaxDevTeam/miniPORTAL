<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Core;

final class LocaleResolver
{
    /**
     * @param list<string> $supportedLocales
     */
    public function __construct(
        private readonly array $supportedLocales = ['pl', 'en', 'de'],
        private readonly string $defaultLocale = 'pl',
    ) {
    }

    public function resolve(Request $request): LocaleContext
    {
        $publicPath = $request->path();
        $segments = array_values(array_filter(explode('/', trim($publicPath, '/')), 'strlen'));
        $candidate = strtolower((string) ($segments[0] ?? ''));
        $locale = in_array($candidate, $this->supportedLocales, true)
            ? $candidate
            : $this->defaultLocale;

        if ($candidate === $locale && in_array($candidate, $this->supportedLocales, true)) {
            array_shift($segments);
            $routePath = $segments === [] ? '/' : '/' . implode('/', $segments);
        } else {
            $routePath = $publicPath;
        }

        return new LocaleContext(
            $locale,
            $this->defaultLocale,
            $this->supportedLocales,
            $routePath,
            $publicPath,
        );
    }
}
