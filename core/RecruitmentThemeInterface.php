<?php
declare(strict_types=1);
namespace SyntaxDevTeam\Cms\Core;
/** Optional recruitment presentation; modules provide data, never markup. */
interface RecruitmentThemeInterface
{
    public function render_recruitment_configuration(array $model, string $token): void;
    public function render_recruitment_form(array $fields, array $ranks, string $token): void;
}
