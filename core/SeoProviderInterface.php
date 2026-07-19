<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Core;

interface SeoProviderInterface
{
    public function registerSeo(SeoIndex $seo): void;
}
