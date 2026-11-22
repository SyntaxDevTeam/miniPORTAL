<?php

declare(strict_types=1);
namespace SyntaxDevTeam\Cms\Core;

interface ProjectLanguageSourceProviderInterface
{
    public function sources(): array;
    public function credential(string $repository): ?string;
}
