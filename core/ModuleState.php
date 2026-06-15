<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Core;

final readonly class ModuleState
{
    public function __construct(
        public string $moduleId,
        public string $version,
        public string $status,
        public bool $protected,
        public bool $dataPreserved,
        public ?string $installedAt,
        public string $updatedAt,
    ) {
    }

    public function isInstalled(): bool
    {
        return in_array($this->status, ['active', 'disabled'], true);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function canRestorePreservedData(): bool
    {
        return $this->status === 'uninstalled' && $this->dataPreserved;
    }
}
