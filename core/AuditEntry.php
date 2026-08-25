<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Core;

use RuntimeException;

final readonly class AuditEntry
{
    /**
     * @param array<string, bool|int|float|string|null> $metadata
     */
    public function __construct(
        public string $moduleId,
        public string $action,
        public string $result,
        public string $actorType = 'system',
        public ?string $actorId = null,
        public ?TenantContext $tenant = null,
        public ?string $targetType = null,
        public ?string $targetId = null,
        public ?string $correlationId = null,
        public array $metadata = [],
    ) {
        if (preg_match('/^[a-z][a-z0-9_]{1,63}$/D', $moduleId) !== 1) {
            throw new RuntimeException('Identyfikator modułu wpisu audytu jest nieprawidłowy.');
        }
        if (preg_match('/^[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)+$/D', $action) !== 1 || strlen($action) > 128) {
            throw new RuntimeException('Akcja wpisu audytu jest nieprawidłowa.');
        }
        if (preg_match('/^[a-z][a-z0-9_]{1,31}$/D', $result) !== 1) {
            throw new RuntimeException('Wynik wpisu audytu jest nieprawidłowy.');
        }
        if (preg_match('/^[a-z][a-z0-9_]{1,31}$/D', $actorType) !== 1) {
            throw new RuntimeException('Typ aktora wpisu audytu jest nieprawidłowy.');
        }
        if ($actorId !== null && ($actorId === '' || strlen($actorId) > 128)) {
            throw new RuntimeException('Identyfikator aktora wpisu audytu jest nieprawidłowy.');
        }
        if ($targetType !== null && preg_match('/^[a-z][a-z0-9_]{1,63}$/D', $targetType) !== 1) {
            throw new RuntimeException('Typ zasobu wpisu audytu jest nieprawidłowy.');
        }
        if ($targetId !== null && ($targetId === '' || strlen($targetId) > 128)) {
            throw new RuntimeException('Identyfikator zasobu wpisu audytu jest nieprawidłowy.');
        }
        if ($correlationId !== null && ($correlationId === '' || strlen($correlationId) > 128)) {
            throw new RuntimeException('Correlation ID wpisu audytu jest nieprawidłowy.');
        }
        if (count($metadata) > 32) {
            throw new RuntimeException('Wpis audytu nie może zawierać więcej niż 32 pól metadata.');
        }
        foreach ($metadata as $key => $value) {
            if (
                preg_match('/^[a-z][a-z0-9_.-]{0,63}$/D', $key) !== 1
                || (!is_scalar($value) && $value !== null)
                || (is_string($value) && strlen($value) > 1000)
            ) {
                throw new RuntimeException('Metadata wpisu audytu zawiera nieprawidłową wartość.');
            }
        }
    }
}
