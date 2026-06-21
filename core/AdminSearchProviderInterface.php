<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Core;

interface AdminSearchProviderInterface
{
    public function registerAdminSearch(AdminSearchRegistry $search): void;
}
