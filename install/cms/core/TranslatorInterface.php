<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Core;

interface TranslatorInterface
{
    public function locale(): string;

    public function defaultLocale(): string;

    /** @return list<string> */
    public function supportedLocales(): array;

    /** @param array<string, scalar|null> $parameters */
    public function translate(string $key, array $parameters = [], string $fallback = ''): string;
}
