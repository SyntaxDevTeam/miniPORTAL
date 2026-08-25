<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Core;

use RuntimeException;

final class EventBus
{
    /** @var array<string, list<callable(ModuleEvent): void>> */
    private array $listeners = [];

    /**
     * @param callable(ModuleEvent): void $listener
     */
    public function subscribe(string $eventName, callable $listener): void
    {
        $this->assertEventName($eventName);
        $this->listeners[$eventName][] = $listener;
    }

    public function publish(ModuleEvent $event): void
    {
        foreach ($this->listeners[$event->name] ?? [] as $listener) {
            $listener($event);
        }
    }

    public function listenerCount(string $eventName): int
    {
        $this->assertEventName($eventName);

        return count($this->listeners[$eventName] ?? []);
    }

    private function assertEventName(string $eventName): void
    {
        if (preg_match('/^[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)+$/', $eventName) !== 1) {
            throw new RuntimeException("Nazwa zdarzenia {$eventName} jest nieprawidłowa.");
        }
    }
}
