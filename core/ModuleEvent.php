<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Core;

use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

final readonly class ModuleEvent
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public string $name,
        public string $module,
        public array $payload = [],
        public ?string $correlationId = null,
        public ?string $causationId = null,
        ?string $eventId = null,
        ?DateTimeImmutable $occurredAt = null,
    ) {
        if (preg_match('/^[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)+$/', $name) !== 1) {
            throw new RuntimeException("Nazwa zdarzenia {$name} jest nieprawidłowa.");
        }
        if (preg_match('/^[a-z][a-z0-9_]{1,63}$/', $module) !== 1) {
            throw new RuntimeException("Identyfikator modułu {$module} jest nieprawidłowy.");
        }

        $this->eventId = $eventId ?? bin2hex(random_bytes(16));
        $this->occurredAt = $occurredAt ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    public string $eventId;

    public DateTimeImmutable $occurredAt;
}
