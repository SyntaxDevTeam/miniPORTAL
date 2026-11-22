<?php

declare(strict_types=1);
namespace SyntaxDevTeam\Cms\Core;

/** Optional header capability for readable documents with sharing and download actions. */
interface DocumentHeaderThemeInterface
{
    /** @param array{title:string,lead:string,eyebrow:string,path:string,download_url:string} $model */
    public function render_document_header(array $model): void;
}
