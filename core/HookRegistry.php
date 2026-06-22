<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Core;

use Closure;
use RuntimeException;

final class HookRegistry
{
    /** @var array<string, list<array{priority: int, order: int, listener: Closure}>> */
    private array $actions = [];

    /** @var array<string, list<array{priority: int, order: int, listener: Closure}>> */
    private array $filters = [];

    private int $registrationOrder = 0;

    public function addAction(string $name, callable $listener, int $priority = 100): void
    {
        $this->add($this->actions, $name, $listener, $priority);
    }

    public function addFilter(string $name, callable $listener, int $priority = 100): void
    {
        $this->add($this->filters, $name, $listener, $priority);
    }

    public function doAction(string $name, mixed ...$arguments): void
    {
        foreach ($this->listeners($this->actions, $name) as $listener) {
            $listener(...$arguments);
        }
    }

    public function applyFilters(string $name, mixed $value, mixed ...$arguments): mixed
    {
        foreach ($this->listeners($this->filters, $name) as $listener) {
            $value = $listener($value, ...$arguments);
        }

        return $value;
    }

    /**
     * @param array<string, list<array{priority: int, order: int, listener: Closure}>> $registry
     */
    private function add(array &$registry, string $name, callable $listener, int $priority): void
    {
        $name = trim($name);
        if (preg_match('/^[a-z][a-z0-9_.-]{1,95}$/', $name) !== 1) {
            throw new RuntimeException("Nazwa hooka {$name} jest nieprawidłowa.");
        }
        if ($priority < -32768 || $priority > 32767) {
            throw new RuntimeException("Priorytet hooka {$name} jest poza dozwolonym zakresem.");
        }

        $registry[$name][] = [
            'priority' => $priority,
            'order' => $this->registrationOrder++,
            'listener' => Closure::fromCallable($listener),
        ];
    }

    /**
     * @param array<string, list<array{priority: int, order: int, listener: Closure}>> $registry
     * @return list<Closure>
     */
    private function listeners(array $registry, string $name): array
    {
        $listeners = $registry[$name] ?? [];
        usort(
            $listeners,
            static fn (array $left, array $right): int => [$left['priority'], $left['order']]
                <=> [$right['priority'], $right['order']]
        );

        return array_column($listeners, 'listener');
    }
}
