<?php

declare(strict_types=1);
namespace SyntaxDevTeam\Cms\Templates\Shared;
trait BotControlTrait
{
    public function render_bot_control(array $model): void
    {
        $escape=static fn(mixed $v):string=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
        echo '<link rel="stylesheet" href="/templates/shared/assets/css/bot-control.css?v='.filemtime(__DIR__.'/../shared/assets/css/bot-control.css').'">';
        require __DIR__.'/views/bot-control.php';
        echo '<script defer src="/templates/shared/assets/js/bot-markdown.js?v='.filemtime(__DIR__.'/../shared/assets/js/bot-markdown.js').'"></script>';
        echo '<script defer src="/templates/shared/assets/js/bot-control.js?v='.filemtime(__DIR__.'/../shared/assets/js/bot-control.js').'"></script>';
    }
}
