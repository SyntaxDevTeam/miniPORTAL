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
    private readonly CapabilityRegistry $capabilityRegistry;
    private readonly EventBus $eventBus;

    public function __construct(
        ?CapabilityRegistry $capabilityRegistry = null,
        ?EventBus $eventBus = null,
    ) {
        $this->capabilityRegistry = $capabilityRegistry ?? new CapabilityRegistry();
        $this->eventBus = $eventBus ?? new EventBus();
    }

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
            $implementations = $module instanceof CapabilityProviderInterface
                ? $module->capabilityProviders()
                : [];
            foreach ($implementations as $capability => $implementation) {
                if (
                    !is_string($capability)
                    || !is_object($implementation)
                    || !in_array($capability, $manifest->providedCapabilities, true)
                ) {
                    throw new RuntimeException("Implementacje capabilities modułu {$id} są nieprawidłowe.");
                }
            }
            foreach ($manifest->providedCapabilities as $capability) {
                if (!isset($implementations[$capability])) {
                    throw new RuntimeException(
                        "Moduł {$id} deklaruje capability {$capability} bez implementacji Internal API."
                    );
                }
            }
            $this->capabilityRegistry->register($id, $manifest->providedCapabilities);
            foreach ($implementations as $capability => $implementation) {
                $this->capabilityRegistry->bind($id, $capability, $implementation);
            }
            $this->manifests[$id] = $manifest;
        }

        $this->modules[$id] = $module;
    }

    public function boot(
        AdminMenuRegistry $menu,
        Router $router,
        ?PublicNavigationRegistry $publicNavigation = null,
        ?AdminSearchRegistry $adminSearch = null,
        ?DashboardRegistry $dashboard = null,
        ?HookRegistry $hooks = null,
        ?SeoIndex $seo = null,
    ): void
    {
        foreach ($this->orderedModules(true) as $module) {
            $missing = array_filter($module->dependencies(), fn (string $id): bool => !$this->has($id));
            if ($missing !== []) {
                $exception = new RuntimeException('Unavailable module dependency.');
                if ($module->isProtected()) {
                    throw $exception;
                }
                $this->skipModule($module->id(), $exception);
                continue;
            }
            $rollbacks = [];
            foreach ([$menu, $router, $publicNavigation, $adminSearch, $dashboard, $hooks] as $registry) {
                if ($registry !== null) {
                    $rollbacks[] = $registry->registrationRollback();
                }
            }
            try {
                $module->registerAdminMenu($menu);
                $module->registerRoutes($router);
                if ($publicNavigation !== null && $module instanceof PublicNavigationProviderInterface) {
                    $module->registerPublicNavigation($publicNavigation);
                }
                if ($adminSearch !== null && $module instanceof AdminSearchProviderInterface) {
                    $module->registerAdminSearch($adminSearch);
                }
                if ($dashboard !== null && $module instanceof DashboardProviderInterface) {
                    $module->registerDashboard($dashboard);
                }
                if ($hooks !== null && $module instanceof HookProviderInterface) {
                    $hooks->isolateModule($module->isProtected() ? null : $module->id());
                    try {
                        $module->registerHooks($hooks);
                    } finally {
                        $hooks->isolateModule(null);
                    }
                }
            } catch (\Throwable $exception) {
                foreach ($rollbacks as $rollback) {
                    $rollback();
                }
                if ($module->isProtected()) {
                    throw $exception;
                }
                $this->skipModule($module->id(), $exception);
            }
        }
        if ($adminSearch !== null) {
            $adminSearch->importMenu($menu->items());
        }
    }

    public function registerSeo(SeoIndex $seo): void
    {
        foreach ($this->orderedModules(true) as $module) {
            if ($module instanceof SeoProviderInterface) {
                $rollback = $seo->registrationRollback();
                try {
                    $module->registerSeo($seo);
                } catch (\Throwable $exception) {
                    $rollback();
                    if ($module->isProtected()) {
                        throw $exception;
                    }
                    error_log('[miniPORTAL] module_seo_failed module=' . $module->id() . ' exception=' . $exception::class);
                }
            }
        }
    }

    private function skipModule(string $id, \Throwable $exception): void
    {
        unset($this->modules[$id], $this->manifests[$id]);
        $this->capabilityRegistry->unregisterModule($id);
        error_log('[miniPORTAL] module_registration_failed module=' . $id . ' exception=' . $exception::class);
    }

    public function has(string $id): bool
    {
        return isset($this->modules[$id]);
    }

    public function manifest(string $id): ?ModuleManifest
    {
        return $this->manifests[$id] ?? null;
    }

    public function capabilities(): CapabilityRegistry
    {
        return $this->capabilityRegistry;
    }

    public function events(): EventBus
    {
        return $this->eventBus;
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
    private function orderedModules(bool $isolateFailures = false): array
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
            try {
                $visit($id);
            } catch (\Throwable $exception) {
                if (!$isolateFailures || $this->modules[$id]->isProtected()) {
                    throw $exception;
                }
                $this->skipModule($id, $exception);
                $state = [];
                $ordered = [];
                return $this->orderedModules($isolateFailures);
            }
        }

        return $ordered;
    }
}
