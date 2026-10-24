<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Core;

/** Optional theme contract for consistent homepage section links on public views. */
interface PublicSectionNavigationInterface
{
    /** @param list<array<string, mixed>> $sections Visible homepage sections only. */
    public function set_public_section_navigation(array $sections): void;
}
