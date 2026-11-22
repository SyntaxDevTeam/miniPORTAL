<?php

declare(strict_types=1);

use SyntaxDevTeam\Cms\Modules\HelpCenter\{HelpCenterModule, HelpRepository};

return static fn(array $services): HelpCenterModule => new HelpCenterModule(
    $services['theme'], $services['admin_menu'], new HelpRepository($services['database']),
    $services['auth'], $services['access'], $services['security'], $services['audit'], $services['template_cache'],
);
