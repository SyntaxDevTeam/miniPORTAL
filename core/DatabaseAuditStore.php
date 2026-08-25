<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Core;

use SyntaxDevTeam\Cms\Database\CrudApp;

final class DatabaseAuditStore implements AuditStoreInterface
{
    public function __construct(private readonly CrudApp $database)
    {
    }

    public function append(array $record): void
    {
        $this->database->create('core_audit_events', $record);
    }
}
