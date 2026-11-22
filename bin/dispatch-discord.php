<?php

declare(strict_types=1);
use SyntaxDevTeam\Cms\Core\{Autoloader,ModuleStateRepository};
use SyntaxDevTeam\Cms\Database\CrudApp;
use SyntaxDevTeam\Cms\Modules\DiscordIntegration\{DiscordHub,DiscordOutbox};
if (PHP_SAPI!=='cli') {http_response_code(404);exit(1);}
$root=dirname(__DIR__);require $root.'/core/Autoloader.php';Autoloader::register();$db=null;$locked=false;
try {
    $config=require $root.'/config/config.php';$db=CrudApp::make($config['database']);
    if (!(new ModuleStateRepository($db))->find('discord_integration')?->isActive()) exit;
    $hub=DiscordHub::local($root);if (!$hub) exit;
    $locked=(int)$db->query("SELECT GET_LOCK('miniportal-discord-outbox',0)")->fetchColumn()===1;if (!$locked) exit;
    $outbox=new DiscordOutbox($db,$hub);$count=0;while ($count<10 && $outbox->workOne()) $count++;
    echo 'discord processed='.$count.PHP_EOL;
} catch (Throwable $e) {error_log('[miniPORTAL] discord_dispatch_failed '.$e::class);fwrite(STDERR,"Nie można uruchomić kolejki Discord.\n");exit(1);}
finally {if ($locked && $db) $db->query("SELECT RELEASE_LOCK('miniportal-discord-outbox')");}
