<?php
declare(strict_types=1);
use SyntaxDevTeam\Cms\Core\{Autoloader,ModuleInstaller,ModuleManifestValidator,ModuleStateRepository};
use SyntaxDevTeam\Cms\Database\CrudApp;
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$root=$argv[1]??dirname(__DIR__);
if(!in_array($root,['/var/www/syntaxdevteam.pl','/var/www/moonvale.pl'],true))throw new RuntimeException('Nieznana instalacja.');
require $root.'/core/Autoloader.php';Autoloader::register();
$config=require $root.'/config/config.php';$dbConfig=$config['database'];if(!($dbConfig['enabled']??false))throw new RuntimeException('Baza wyłączona.');unset($dbConfig['enabled']);
$db=CrudApp::make($dbConfig);$states=new ModuleStateRepository($db);$manifest=(new ModuleManifestValidator((string)$config['app']['version']))->validate($root.'/modules/Recruitment',false);
$states->registerDiscovered($manifest);$installer=new ModuleInstaller($db,$states);$state=$states->find($manifest->id);
if(!$state?->isInstalled())$installer->install($manifest);elseif(version_compare($manifest->version,$state->version,'>'))$installer->update($manifest);
$states->setActive($manifest->id,true);
(new \SyntaxDevTeam\Cms\Core\FileTemplateCache($root.'/cache/templates'))->invalidateTags(['navigation','seo']);
echo $root.": recruitment ".$states->find('recruitment')->version." aktywny\n";
