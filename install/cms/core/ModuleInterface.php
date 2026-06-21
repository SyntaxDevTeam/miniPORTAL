<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Core;

interface ModuleInterface
{
    public function id(): string;

    public function version(): string;

    /**
     * @return list<string>
     */
    public function dependencies(): array;

    public function isProtected(): bool;

    /**
     * @return list<string>
     */
    public function requiredPermissions(): array;

    public function registerAdminMenu(AdminMenuRegistry $menu): void;

    public function registerRoutes(Router $router): void;
}
