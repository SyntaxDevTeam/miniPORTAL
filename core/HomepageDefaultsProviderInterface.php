<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Core;

/** Optional, explicit import of a theme's starter content into CorePages. */
interface HomepageDefaultsProviderInterface
{
    /** @return list<array<string, mixed>> */
    public function homepageDefaults(): array;
}
