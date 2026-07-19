<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Core;

use RuntimeException;

final class PayloadTooLargeException extends RuntimeException
{
    public function __construct(
        public readonly int $contentLength,
        public readonly int $maxBytes,
    ) {
        parent::__construct('Żądanie przekracza dozwolony rozmiar danych wejściowych.');
    }
}
