<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Core;

interface ModuleDistributionAccessInterface
{
    /**
     * @param list<array<string, mixed>> $entries
     * @return list<array<string, mixed>>
     */
    public function catalogFor(Request $request, array $entries): array;

    /** @param array<string, mixed> $entry */
    public function allowsArchive(Request $request, array $entry): bool;
}
