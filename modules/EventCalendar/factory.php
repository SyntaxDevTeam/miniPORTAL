<?php

declare(strict_types=1);
use SyntaxDevTeam\Cms\Modules\EventCalendar\{EventCalendarModule,EventRepository,EventNotificationConfig};
return static function(array $s): EventCalendarModule {
    $app=$s['config']['app']??[];
    $config=new EventNotificationConfig(dirname(__DIR__,2).'/config/modules/event-calendar-notifications.json',(string)($app['public_url']??''),($app['theme']??'')==='equestria'?'/templates/equestria/assets/img/moonvale-events-festival-v1.webp':'');
    return new EventCalendarModule($s['theme'],$s['admin_menu'],new EventRepository($s['database'],$config),$s['auth'],$s['security'],$s['audit'],$config,$s['database']);
};
