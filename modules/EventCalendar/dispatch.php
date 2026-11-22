<?php

declare(strict_types=1);
use SyntaxDevTeam\Cms\Core\{Autoloader,ModuleStateRepository};
use SyntaxDevTeam\Cms\Database\CrudApp;
use SyntaxDevTeam\Cms\Modules\EventCalendar\{EventNotificationConfig,EventNotificationWorker,EventDiscordTransport};
if(PHP_SAPI!=='cli'){http_response_code(404);exit(1);}
$root=dirname(__DIR__,2);require $root.'/core/Autoloader.php';Autoloader::register();$db=null;$locked=false;$lockName='';
try{
    $c=require $root.'/config/config.php';$db=CrudApp::make($c['database']);$states=new ModuleStateRepository($db);
    foreach(['core_auth','event_calendar'] as $id)if(!$states->find($id)?->isActive()){echo "event_calendar disabled\n";exit;}
    $lockName='miniportal-events-'.substr(hash('sha256',(string)$c['database']['database_name']),0,24);
    $locked=(int)$db->query('SELECT GET_LOCK(:name,0)',[':name'=>$lockName])->fetchColumn()===1;if(!$locked){echo "event_calendar busy\n";exit;}
    $app=$c['app'];$settings=new EventNotificationConfig($root.'/config/modules/event-calendar-notifications.json',(string)($app['public_url']??''),($app['theme']??'')==='equestria'?'/templates/equestria/assets/img/moonvale-events-festival-v1.webp':'');
    $users=new \SyntaxDevTeam\Cms\Modules\CoreAuth\CrudAppUserRepository($db);
    $audit=new \SyntaxDevTeam\Cms\Modules\CoreAuth\AuditLogService($db,'');
    $request=\SyntaxDevTeam\Cms\Core\Request::fromArrays([],[],['REQUEST_METHOD'=>'POST']);
    $published=(new \SyntaxDevTeam\Cms\Modules\EventCalendar\EventRepository($db,$settings))->publishDue(gmdate('Y-m-d H:i:s'),static function(int $id)use($users):bool {
        $u=$users->findById($id);return $u!==null && $u->isActive() && (in_array('*',$u->permissions,true) || (in_array('events.view',$u->permissions,true) && in_array('events.publish',$u->permissions,true)));
    },static fn(int $id,int $actor,string $action)=>$audit->record($request,'event_calendar_'.$action,'success','event:'.$id,$actor));
    $worker=new EventNotificationWorker($db,$settings,(new EventDiscordTransport())->send(...));$count=0;while($count<10 && $worker->workOne())$count++;
    echo 'event_calendar published='.$published.' processed='.$count.PHP_EOL;
}catch(Throwable $e){error_log('[miniPORTAL] event_calendar_dispatch_failed '.$e::class);fwrite(STDERR,"Nie można uruchomić kolejki powiadomień wydarzeń.\n");exit(1);}
finally{if($locked && $db)$db->query('SELECT RELEASE_LOCK(:name)',[':name'=>$lockName]);}
