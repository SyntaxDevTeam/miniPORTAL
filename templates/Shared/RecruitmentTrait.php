<?php
declare(strict_types=1);
namespace SyntaxDevTeam\Cms\Templates\Shared;
trait RecruitmentTrait
{
    public function render_recruitment_configuration(array $model, string $token): void
    {
        $e = static fn(mixed $v): string => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
        echo '<link rel="stylesheet" href="/templates/shared/assets/css/recruitment.css?v='.filemtime(__DIR__.'/../shared/assets/css/recruitment.css').'">';
        require __DIR__.'/views/recruitment-config.php';
    }
    public function render_recruitment_form(array $fields, array $ranks, string $token): void
    {
        $e=static fn(mixed $v): string => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
        echo '<link rel="stylesheet" href="/templates/shared/assets/css/recruitment.css?v='.filemtime(__DIR__.'/../shared/assets/css/recruitment.css').'">';
        require __DIR__.'/views/recruitment.php';
        echo '<script defer src="/templates/shared/assets/js/recruitment.js?v='.filemtime(__DIR__.'/../shared/assets/js/recruitment.js').'"></script>';
    }
}
