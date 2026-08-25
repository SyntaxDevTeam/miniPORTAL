<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Core;

interface StorefrontHostResolverInterface
{
    /** Returns an internal public route or null when this host is not owned by the provider. */
    public function resolve(Request $request): ?string;
}
