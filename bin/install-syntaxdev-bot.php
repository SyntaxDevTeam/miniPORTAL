<?php

declare(strict_types=1);
use SyntaxDevTeam\Cms\Core\{Autoloader,ModuleInstaller,ModuleManifestValidator,ModuleStateRepository,InstallationState,Request};
use SyntaxDevTeam\Cms\Database\CrudApp;
use SyntaxDevTeam\Cms\Modules\CoreAuth\AuditLogService;
$root=dirname(__DIR__);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require $root.'/core/Autoloader.php';Autoloader::register();
try {
    if(!is_readable(InstallationState::environmentFile($root)))throw new RuntimeException('Brak konfiguracji instalacji.');
    if(!is_readable($root.'/config/modules/syntaxdev-bot.env'))throw new RuntimeException('Brak chronionej konfiguracji bazy bota.');
    $config=require $root.'/config/config.php';$settings=$config['database']??[];
    if(($settings['enabled']??false)!==true)throw new RuntimeException('Baza portalu jest wyłączona.');unset($settings['enabled']);
    $db=CrudApp::make($settings);$states=new ModuleStateRepository($db);$installer=new ModuleInstaller($db,$states);
    $manifest=(new ModuleManifestValidator((string)$config['app']['version']))->validate($root.'/modules/SyntaxDevBot',false);
    $states->registerDiscovered($manifest);
    $state=$states->find($manifest->id);
    if(!$state?->isInstalled())$installer->install($manifest);
    elseif(version_compare($manifest->version,$state->version,'>'))$installer->update($manifest);
    $states->setActive($manifest->id,true);
    (new AuditLogService($db,(string)($config['auth']['audit_hash_key']??'')))->record(Request::fromArrays([],[],['REQUEST_METHOD'=>'CLI']),'syntaxdev_bot_install','success','syntaxdev_bot');
    echo "SyntaxDevBot 2.3.2 aktywny: /admin/syntaxdev-bot\n";
} catch(Throwable $e) {
    fwrite(STDERR,"Instalacja nie powiodła się; sprawdź dostęp do konfiguracji oraz bazy portalu.\n");exit(1);
}
