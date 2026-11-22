<?php

declare(strict_types=1);

use SyntaxDevTeam\Cms\Modules\Cooperation\CooperationModule;
use SyntaxDevTeam\Cms\Modules\Cooperation\PartnerRepository;

return static fn (array $services): CooperationModule => new CooperationModule(
    $services['theme'], $services['admin_menu'], new PartnerRepository($services['database']),
    $services['auth'], $services['access'], $services['security'], $services['audit'], $services['template_cache'],
);
