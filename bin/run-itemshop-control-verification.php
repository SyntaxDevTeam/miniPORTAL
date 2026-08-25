<?php
declare(strict_types=1);
use SyntaxDevTeam\Cms\Core\{AuditService,Autoloader,DatabaseAuditStore};
use SyntaxDevTeam\Cms\Database\CrudApp;
use SyntaxDevTeam\Cms\Modules\ItemShop\{ItemShopControlVerificationWorker,ItemShopSecretVault};
require_once dirname(__DIR__).'/core/Autoloader.php';Autoloader::register();
$config=require dirname(__DIR__).'/config/config.php';$databaseConfig=is_array($config['database']??null)?$config['database']:[];
if(($databaseConfig['enabled']??false)!==true){fwrite(STDERR,"Baza danych nie jest skonfigurowana.\n");exit(1);}$key=trim((string)($config['modules']['itemshop_secret_key']??''));if($key===''){fwrite(STDERR,"ITEMSHOP_SECRET_KEY nie jest skonfigurowany.\n");exit(1);}
$db=CrudApp::make($databaseConfig);$worker=new ItemShopControlVerificationWorker($db,new ItemShopSecretVault($key),new AuditService(new DatabaseAuditStore($db)));$limit=max(1,min(100,(int)($argv[1]??20)));$days=max(1,min(30,(int)($argv[2]??7)));$results=[];$stalePlugins=$worker->revokeStalePluginProofs($limit);
for($i=0;$i<$limit;$i++){$result=$worker->workOne($days);if($result===null)break;$results[]=$result;}echo json_encode(['processed'=>count($results),'stale_plugin_revocations'=>$stalePlugins,'results'=>$results],JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES).PHP_EOL;
