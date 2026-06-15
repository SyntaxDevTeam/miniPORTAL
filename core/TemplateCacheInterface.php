<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Core;

interface TemplateCacheInterface
{
    /**
     * @param callable(): string $renderer
     * @param list<string> $tags
     */
    public function remember(string $namespace, string $key, callable $renderer, array $tags = []): string;

    /**
     * @param list<string> $tags
     */
    public function invalidateTags(array $tags): int;

    public function clear(): int;

    /**
     * @return array{enabled: bool, entries: int, bytes: int, directory: string}
     */
    public function stats(): array;
}
