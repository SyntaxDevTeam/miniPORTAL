<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Core;

interface DashboardProviderInterface
{
    public function registerDashboard(DashboardRegistry $dashboard): void;
}
