<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Core;

use RuntimeException;

final readonly class ApiTokenPrincipal
{
    /**
     * @param list<string> $scopes
     */
    public function __construct(
        public int $id,
        public string $publicId,
        public string $label,
        public array $scopes,
        public ?int $subjectUserId,
        public TenantContext $tenant,
    ) {
        if ($id <= 0) {
            throw new RuntimeException('Identyfikator tokenu API jest nieprawidłowy.');
        }
        if (preg_match('/^[a-f0-9]{16}$/D', $publicId) !== 1) {
            throw new RuntimeException('Publiczny identyfikator tokenu API jest nieprawidłowy.');
        }
        if ($label === '' || strlen($label) > 120) {
            throw new RuntimeException('Etykieta tokenu API jest nieprawidłowa.');
        }
        if ($subjectUserId !== null && $subjectUserId <= 0) {
            throw new RuntimeException('Identyfikator użytkownika tokenu API jest nieprawidłowy.');
        }

        $normalized = ApiScope::normalize($scopes);
        if ($normalized !== $scopes) {
            throw new RuntimeException('Scope tokenu API nie są znormalizowane.');
        }
    }

    public function allows(string $scope): bool
    {
        ApiScope::assertValid($scope);

        return in_array($scope, $this->scopes, true);
    }

    public function allowsTenant(TenantContext $required): bool
    {
        return $this->tenant->allows($required);
    }
}
