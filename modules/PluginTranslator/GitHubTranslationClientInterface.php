<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\PluginTranslator;

interface GitHubTranslationClientInterface
{
    public function directory(string $repository, string $ref, string $directory): array;
    public function blob(string $repository, string $sha): array;
}
