<?php

declare(strict_types=1);
use SyntaxDevTeam\Cms\Core\{Autoloader,ModuleInstaller,ModuleManifestValidator,ModuleStateRepository,InstallationState,Request};
use SyntaxDevTeam\Cms\Database\CrudApp;
use SyntaxDevTeam\Cms\Modules\CoreAuth\AuditLogService;
if(PHP_SAPI!=='cli'){http_response_code(404);exit(1);}
$root=rtrim((string)(getopt('',['root:'])['root']??dirname(__DIR__)),'/');
require $root.'/core/Autoloader.php';Autoloader::register();
try {
    if(!is_readable(InstallationState::environmentFile($root)))throw new RuntimeException('Brak dostępu do konfiguracji instalacji.');
    $c=require $root.'/config/config.php';$db=CrudApp::make($c['database']);$states=new ModuleStateRepository($db);
    $manifest=(new ModuleManifestValidator((string)$c['app']['version']))->validate($root.'/modules/EventCalendar',false);
    $state=$states->find('event_calendar');
    if(!$state?->isInstalled()){echo "Kalendarz nie jest zainstalowany; bez zmian bazy.\n";exit;}
    $before=$db->query('SELECT COUNT(*) FROM event_calendar_events')->fetchColumn();
    $migrations=(new ModuleInstaller($db,$states))->update($manifest);
    if($before!==$db->query('SELECT COUNT(*) FROM event_calendar_events')->fetchColumn())throw new RuntimeException('Niezgodna liczba wpisów po migracji.');
    (new AuditLogService($db,(string)($c['auth']['audit_hash_key']??'')))->record(Request::fromArrays([],[],['REQUEST_METHOD'=>'CLI']),'event_calendar_upgrade','success','event_calendar');
    echo 'Kalendarz '.$manifest->version.'; migracje: '.count($migrations).'; zachowane wpisy: '.$before.PHP_EOL;
} catch(Throwable $e){fwrite(STDERR,"Aktualizacja kalendarza nie powiodła się; sprawdź dostęp do konfiguracji i bazy.\n");exit(1);}
