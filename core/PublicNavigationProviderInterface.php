<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Core;

interface PublicNavigationProviderInterface
{
    public function registerPublicNavigation(PublicNavigationRegistry $navigation): void;
}
