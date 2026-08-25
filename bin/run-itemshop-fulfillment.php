<?php
declare(strict_types=1);
use SyntaxDevTeam\Cms\Core\Autoloader;
use SyntaxDevTeam\Cms\Database\CrudApp;
use SyntaxDevTeam\Cms\Modules\ItemShop\{ItemShopFulfillmentWorker,ItemShopSecretVault};
require_once dirname(__DIR__).'/core/Autoloader.php';
Autoloader::register();
$config=require dirname(__DIR__).'/config/config.php';$databaseConfig=is_array($config['database']??null)?$config['database']:[];
if(($databaseConfig['enabled']??false)!==true){fwrite(STDERR,"Baza danych nie jest skonfigurowana.\n");exit(1);}
$key=trim((string)($config['modules']['itemshop_secret_key']??''));if($key===''){fwrite(STDERR,"ITEMSHOP_SECRET_KEY nie jest skonfigurowany.\n");exit(1);}
$worker=new ItemShopFulfillmentWorker(CrudApp::make($databaseConfig),new ItemShopSecretVault($key));$limit=max(1,min(100,(int)($argv[1]??20)));$processed=[];
for($i=0;$i<$limit;$i++){$result=$worker->workOne(gethostname().':'.getmypid());if($result===null)break;$processed[]=$result;}
echo json_encode(['processed'=>count($processed),'results'=>$processed],JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES).PHP_EOL;
