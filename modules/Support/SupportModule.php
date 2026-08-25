<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\Support;

use SyntaxDevTeam\Cms\Core\AdminMenuRegistry;
use SyntaxDevTeam\Cms\Core\ModuleInterface;
use SyntaxDevTeam\Cms\Core\Router;

final class SupportModule implements ModuleInterface
{
    public function id(): string
    {
        return 'support';
    }

    public function version(): string
    {
        return '0.1.0';
    }

    public function dependencies(): array
    {
        return [];
    }

    public function isProtected(): bool
    {
        return false;
    }

    public function requiredPermissions(): array
    {
        return [];
    }

    public function registerAdminMenu(AdminMenuRegistry $menu): void
    {
    }

    public function registerRoutes(Router $router): void
    {
    }
}
