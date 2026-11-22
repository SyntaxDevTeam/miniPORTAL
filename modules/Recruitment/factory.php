<?php
declare(strict_types=1);
use SyntaxDevTeam\Cms\Modules\Recruitment\{RecruitmentModule, RecruitmentRepository};
return static fn(array $s): RecruitmentModule => new RecruitmentModule($s['theme'], $s['admin_menu'], new RecruitmentRepository($s['database'],static function(int $id,string $status,int $revision)use($s):void {
    if (class_exists(\SyntaxDevTeam\Cms\Modules\DiscordIntegration\DiscordOutbox::class)) \SyntaxDevTeam\Cms\Modules\DiscordIntegration\DiscordOutbox::recruitment($s['database'],dirname(__DIR__,2),$id,$status,$revision,(string)($s['config']['app']['public_url']??''));
}), $s['auth'], $s['access'], $s['security'], $s['audit']);
