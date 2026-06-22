<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Core;

interface HookProviderInterface
{
    public function registerHooks(HookRegistry $hooks): void;
}
