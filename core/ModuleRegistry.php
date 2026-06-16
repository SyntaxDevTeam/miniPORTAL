<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Core;

use RuntimeException;

final class ModuleRegistry
{
    /** @var array<string, ModuleInterface> */
    private array $modules = [];
    /** @var array<string, ModuleManifest> */
    private array $manifests = [];

    public function add(ModuleInterface $module, ?ModuleManifest $manifest = null): void
    {
        $id = $module->id();

        if (preg_match('/^[a-z][a-z0-9_]{1,63}$/', $id) !== 1) {
            throw new RuntimeException("Identyfikator modułu {$id} jest nieprawidłowy.");
        }

        if (isset($this->modules[$id])) {
            throw new RuntimeException("Moduł {$id} został już zarejestrowany.");
        }

        if ($manifest !== null) {
            if ($manifest->id !== $id || $manifest->version !== $module->version()) {
                throw new RuntimeException("Kod modułu {$id} jest niespójny z manifestem.");
            }
            if ($manifest->requiredModules !== $module->dependencies()) {
                throw new RuntimeException("Zależności modułu {$id} są niespójne z manifestem.");
            }
            if ($manifest->protected !== $module->isProtected()) {
                throw new RuntimeException("Flaga ochrony modułu {$id} jest niespójna z manifestem.");
            }
            $this->manifests[$id] = $manifest;
        }

        $this->modules[$id] = $module;
    }

    public function boot(AdminMenuRegistry $menu, Router $router, ?PublicNavigationRegistry $publicNavigation = null): void
    {
        foreach ($this->orderedModules() as $module) {
            $module->registerAdminMenu($menu);
            $module->registerRoutes($router);
            if ($publicNavigation !== null && $module instanceof PublicNavigationProviderInterface) {
                $module->registerPublicNavigation($publicNavigation);
            }
        }
    }

    /**
     * @return list<string>
     */
    public function ids(): array
    {
        return array_keys($this->modules);
    }

    /**
     * @return array<string, ModuleManifest>
     */
    public function manifests(): array
    {
        return $this->manifests;
    }

    /**
     * @return list<ModuleInterface>
     */
    private function orderedModules(): array
    {
        $ordered = [];
        $state = [];
        $visit = function (string $id) use (&$visit, &$ordered, &$state): void {
            if (($state[$id] ?? 0) === 2) {
                return;
            }
            if (($state[$id] ?? 0) === 1) {
                throw new RuntimeException("Wykryto cykliczną zależność modułu {$id}.");
            }

            $module = $this->modules[$id] ?? null;
            if ($module === null) {
                throw new RuntimeException("Brak wymaganego modułu {$id}.");
            }

            $state[$id] = 1;
            foreach ($module->dependencies() as $dependency) {
                if (!isset($this->modules[$dependency])) {
                    throw new RuntimeException("Moduł {$id} wymaga niezarejestrowanego modułu {$dependency}.");
                }
                $visit($dependency);
            }
            $state[$id] = 2;
            $ordered[] = $module;
        };

        foreach (array_keys($this->modules) as $id) {
            $visit($id);
        }

        return $ordered;
    }
}
