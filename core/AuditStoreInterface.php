<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Core;

interface AuditStoreInterface
{
    /** @param array<string, mixed> $record */
    public function append(array $record): void;
}
