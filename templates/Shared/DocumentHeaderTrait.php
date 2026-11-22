<?php

declare(strict_types=1);
namespace SyntaxDevTeam\Cms\Templates\Shared;

trait DocumentHeaderTrait
{
    public function render_document_header(array $model): void
    {
        $escape=static fn(mixed $value): string=>htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8');
        $path=(string)($model['path']??'');
        if(!preg_match('~\A/article/[a-z0-9-]+\z~D',$path))$path='/articles';
        $url=$this->publicUrl.$path;
        $download=(string)($model['download_url']??'');
        if($download!==$path.'/pdf')$download='';
        echo '<link rel="stylesheet" href="/templates/shared/assets/css/document-header.css?v='.filemtime(__DIR__.'/../shared/assets/css/document-header.css').'">';
        require __DIR__.'/views/document-header.php';
        echo '<script defer src="/templates/shared/assets/js/document-header.js?v='.filemtime(__DIR__.'/../shared/assets/js/document-header.js').'"></script>';
    }
}
