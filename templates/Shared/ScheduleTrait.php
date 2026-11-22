<?php

declare(strict_types=1);
namespace SyntaxDevTeam\Cms\Templates\Shared;

trait ScheduleTrait
{
    private bool $scheduleNoticeEnabled=false;
    private bool $scheduleNoticeRendered=false;
    public function set_schedule_notice(bool $enabled): void {$this->scheduleNoticeEnabled=$enabled;}
    private function render_schedule_notice(): void
    {
        if(!$this->scheduleNoticeEnabled || $this->scheduleNoticeRendered)return;
        $path=parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
        if(preg_match('~^/(?:admin/)?events(?:/|$)~', (string)$path))return;
        $this->scheduleNoticeRendered=true;
        $version=filemtime(__DIR__.'/../shared/assets/css/event-notice.css');
        echo '<link rel="stylesheet" href="/templates/shared/assets/css/event-notice.css?v='.$version.'">';
        require __DIR__.'/views/event-notice.php';
        echo '<script defer src="/templates/shared/assets/js/event-notice.js?v='.filemtime(__DIR__.'/../shared/assets/js/event-notice.js').'"></script>';
    }

    public function render_schedule(array $model): void
    {
        $escape = static fn(mixed $v): string => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
        $renderBody = static function(string $content,string $format) use ($escape): string {
            return $format==='markdown' ? (new \SyntaxDevTeam\Cms\Core\ContentRenderer())->render($content,'markdown') : $escape($content);
        };
        echo '<link rel="stylesheet" href="/templates/shared/assets/css/schedule.css?v=' . filemtime(__DIR__.'/../shared/assets/css/schedule.css') . '">';
        require __DIR__.'/views/schedule.php';
    }
}
