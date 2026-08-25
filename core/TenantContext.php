<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Core;

use RuntimeException;

final readonly class TenantContext
{
    private function __construct(
        public ?string $type,
        public ?string $id,
    ) {
    }

    public static function platform(): self
    {
        return new self(null, null);
    }

    public static function scoped(string $type, string $id): self
    {
        $type = trim($type);
        $id = trim($id);

        if (preg_match('/^[a-z][a-z0-9_]{1,63}$/D', $type) !== 1) {
            throw new RuntimeException('Typ tenant context jest nieprawidłowy.');
        }
        if (preg_match('/^[A-Za-z0-9._:-]{1,128}$/D', $id) !== 1) {
            throw new RuntimeException('Identyfikator tenant context jest nieprawidłowy.');
        }

        return new self($type, $id);
    }

    public function isPlatform(): bool
    {
        return $this->type === null && $this->id === null;
    }

    public function allows(self $required): bool
    {
        if ($this->isPlatform()) {
            return true;
        }
        if ($required->isPlatform()) {
            return false;
        }

        return hash_equals((string) $this->type, (string) $required->type)
            && hash_equals((string) $this->id, (string) $required->id);
    }

    public function assertAllows(self $required): void
    {
        if (!$this->allows($required)) {
            throw new RuntimeException('Tenant context nie zezwala na dostęp do wskazanego zakresu.');
        }
    }

    public function key(): string
    {
        return $this->isPlatform() ? 'platform' : $this->type . ':' . $this->id;
    }
}
