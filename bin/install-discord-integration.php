<?php

declare(strict_types=1);
use SyntaxDevTeam\Cms\Core\{Autoloader,ModuleInstaller,ModuleManifestValidator,ModuleStateRepository,FileTemplateCache,Request};
use SyntaxDevTeam\Cms\Database\CrudApp;
use SyntaxDevTeam\Cms\Modules\CoreAuth\AuditLogService;
use SyntaxDevTeam\Cms\Modules\DiscordIntegration\DiscordConfig;
if (PHP_SAPI!=='cli') {http_response_code(404);exit(1);}
$root=rtrim((string)(getopt('',['root:'])['root']??dirname(__DIR__)),'/');
require $root.'/core/Autoloader.php';Autoloader::register();
try {
    $c=require $root.'/config/config.php';$db=CrudApp::make($c['database']);$states=new ModuleStateRepository($db);$installer=new ModuleInstaller($db,$states);$validator=new ModuleManifestValidator((string)$c['app']['version']);
    foreach (['DiscordIntegration','EventCalendar','TaskBoard','MinecraftMonitor','Recruitment','Uptime','CraftSupport'] as $directory) {
        if (!is_dir($root.'/modules/'.$directory)) continue;
        $m=$validator->validate($root.'/modules/'.$directory,false);$state=$states->find($m->id);
        if ($directory==='DiscordIntegration') {
            $states->registerDiscovered($m);if (!$state?->isInstalled()) $installer->install($m);
            elseif (version_compare($m->version,$state->version,'>')) $installer->update($m);
            $states->setActive($m->id,true);
        } elseif ($state?->isInstalled() && version_compare($m->version,$state->version,'>')) $installer->update($m);
        echo $m->id.' '.($states->find($m->id)?->version??'niezainstalowany').PHP_EOL;
    }
    $store=new DiscordConfig($root.'/config/modules/discord-integration.json');
    if (!is_file($store->file)) $store->save(['revision'=>0],[]);
    (new FileTemplateCache($root.'/cache/templates'))->invalidateTags(['navigation','theme']);
    (new AuditLogService($db,(string)($c['auth']['audit_hash_key']??'')))->record(Request::fromArrays([],[],['REQUEST_METHOD'=>'CLI']),'discord_integration_install','success','discord_integration');
} catch (Throwable $e) {error_log('[miniPORTAL] discord_install_failed '.$e::class);fwrite(STDERR,"Nie można zainstalować integracji Discord. Sprawdź dostęp do konfiguracji i bazy.\n");exit(1);}
