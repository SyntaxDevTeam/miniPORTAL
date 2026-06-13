<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Core;

use RuntimeException;

final class ModuleRegistry
{
    /** @var array<string, ModuleInterface> */
    private array $modules = [];

    public function add(ModuleInterface $module): void
    {
        $id = $module->id();

        if (preg_match('/^[a-z][a-z0-9_]{1,63}$/', $id) !== 1) {
            throw new RuntimeException("Identyfikator modułu {$id} jest nieprawidłowy.");
        }

        if (isset($this->modules[$id])) {
            throw new RuntimeException("Moduł {$id} został już zarejestrowany.");
        }

        $this->modules[$id] = $module;
    }

    public function boot(AdminMenuRegistry $menu, Router $router): void
    {
        foreach ($this->modules as $module) {
            $module->registerAdminMenu($menu);
            $module->registerRoutes($router);
        }
    }

    /**
     * @return list<string>
     */
    public function ids(): array
    {
        return array_keys($this->modules);
    }
}
