<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Core;

interface MachineTranslationInterface
{
    public function available(): bool;

    public function translate(
        string $text,
        string $sourceLocale,
        string $targetLocale,
        string $format = 'text',
    ): string;
}
