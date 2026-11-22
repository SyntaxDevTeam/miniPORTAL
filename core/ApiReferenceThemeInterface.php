<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Core;

/** Optional API documentation presentation; third-party themes retain a text fallback. */
interface ApiReferenceThemeInterface
{
    /** @param array{title:string,lead:string,version:string,spec_url:string,guide_url:string,back_url:string} $model */
    public function render_api_reference(array $model): void;
}
