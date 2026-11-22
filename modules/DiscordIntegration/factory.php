<?php

declare(strict_types=1);
use SyntaxDevTeam\Cms\Modules\DiscordIntegration\DiscordIntegrationModule;
return static fn(array $s): DiscordIntegrationModule => new DiscordIntegrationModule($s['theme'],$s['admin_menu'],$s['auth'],$s['access'],$s['security'],$s['audit'],$s['database'],dirname(__DIR__,2));
