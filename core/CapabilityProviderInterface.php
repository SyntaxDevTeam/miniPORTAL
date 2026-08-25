<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Core;

interface CapabilityProviderInterface
{
    /**
     * Concrete Internal API implementations keyed by manifest capability.
     *
     * @return array<string, object>
     */
    public function capabilityProviders(): array;
}
