<?php

declare(strict_types=1);
namespace SyntaxDevTeam\Cms\Modules\DiscordIntegration;
use SyntaxDevTeam\Cms\Core\ModuleStateRepository;
use SyntaxDevTeam\Cms\Database\CrudApp;
final class DiscordCatalog
{
    public static function routes(string $root,?CrudApp $db=null): array
    {
        $routes = [];
        foreach (self::modules($root, $db) as $module) {
            $routes += $module['routes'];
        }
        return $routes;
    }

    /** @return array<string, array{name: string, routes: array<string, string>}> */
    public static function modules(string $root, ?CrudApp $db = null): array
    {
        $modules = [];
        $states = $db ? new ModuleStateRepository($db) : null;
        foreach (glob($root.'/modules/*/discord.json')?:[] as $file) {
            $manifest=json_decode(file_get_contents(dirname($file).'/info.json'),true,32,JSON_THROW_ON_ERROR);
            $id = $manifest['id'];
            if ($states && !$states->find($id)?->isActive()) continue;
            $routes = $modules[$id]['routes'] ?? [];
            foreach (json_decode(file_get_contents($file),true,8,JSON_THROW_ON_ERROR) as $key=>$label) {
                if (preg_match('/\A[a-z_]+\.[a-z_]+\z/D',$key)!==1 || !str_starts_with($key,$id.'.') || !is_string($label) || isset($routes[$key])) throw new \RuntimeException('Nieprawidłowa deklaracja integracji Discord.');
                $routes[$key]=$label;
            }
            if ($routes !== []) {
                $modules[$id] = ['name' => $manifest['name'], 'routes' => $routes];
            }
        }
        return $modules;
    }
}
