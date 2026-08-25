<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\BuildExplorer;

use RuntimeException;

final class BuildStorageCapacityException extends RuntimeException
{
    /** @param array<string, int|null> $status */
    public function __construct(
        string $message,
        public readonly array $status = [],
    ) {
        parent::__construct($message);
    }
}
