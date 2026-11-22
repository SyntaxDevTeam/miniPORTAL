<?php

declare(strict_types=1);
namespace SyntaxDevTeam\Cms\Modules\BuildExplorer;

use RuntimeException;
use SyntaxDevTeam\Cms\Core\Request;

final class ManualBuildInput
{
    public const PLATFORMS = ['paper'=>'Paper','spigot'=>'Spigot','purpur'=>'Purpur','folia'=>'Folia','pufferfish'=>'Pufferfish','leaves'=>'Leaves','bukkit'=>'Bukkit','craftbukkit'=>'CraftBukkit','velocity'=>'Velocity','bungeecord'=>'BungeeCord'];

    public static function versions(array $values): array
    {
        if (count($values) > 100) { throw new RuntimeException('Wybierz najwyżej 100 wersji Minecrafta.'); }
        $result=[];
        foreach ($values as $raw) {
            if (!is_string($raw) || preg_match('/^[1-9][0-9]{0,2}\.[0-9]{1,2}(?:\.[0-9]{1,2})?$/D', trim($raw)) !== 1) { throw new RuntimeException('Nieprawidłowa wersja Minecrafta.'); }
            $parts=array_map('intval',explode('.',trim($raw)));
            $version=$parts[0].'.'.$parts[1].(isset($parts[2]) && $parts[2] !== 0 ? '.'.$parts[2] : '');
            $result[$version]=$version;
        }
        return array_values($result);
    }

    public static function read(Request $request): array
    {
        $platform=strtolower(trim($request->postString('platform')));
        if (!isset(self::PLATFORMS[$platform])) { throw new RuntimeException('Wybierz platformę pliku JAR.'); }
        $platforms=$request->postStringList('compatible_platforms');
        if (count($platforms)>count(self::PLATFORMS)) { throw new RuntimeException('Za dużo platform.'); }
        $proxy=in_array($platform,['velocity','bungeecord'],true);
        foreach ($platforms as $p) {
            if (!isset(self::PLATFORMS[$p]) || in_array($p,['velocity','bungeecord'],true)!==$proxy || ($proxy && $p!==$platform)) { throw new RuntimeException('Zgodne platformy muszą pasować do rodzaju artefaktu.'); }
        }
        $platforms=array_values(array_unique([$platform,...$platforms]));
        $extra=trim($request->postString('extra_game_versions'));
        $versions=self::versions([...$request->postStringList('game_versions'),...($extra===''?[]:preg_split('/[\s,;]+/',$extra))]);
        if (!$proxy && $versions===[]) { throw new RuntimeException('Zaznacz co najmniej jedną zgodną wersję Minecrafta.'); }
        $version=trim($request->postString('version_label'));
        $number=trim($request->postString('build_number'));
        $channel=$request->postString('channel');
        if ($version==='' || mb_strlen($version)>120 || mb_strlen($number)>80 || !in_array($channel,['release','snapshot','dev','wip'],true) || (in_array($channel,['dev','wip'],true) && $number==='')) { throw new RuntimeException('Uzupełnij wersję pluginu i kanał; DEV/WIP wymagają numeru buildu.'); }
        $javaRaw=trim($request->postString('java_version'));
        $java=$javaRaw===''?null:filter_var($javaRaw,FILTER_VALIDATE_INT,['options'=>['min_range'=>8,'max_range'=>99]]);
        if ($java===false) { throw new RuntimeException('Wersja Java musi być liczbą od 8 do 99.'); }
        $changelog=$request->postString('changelog');
        if ($channel === 'release' && $request->postBool('is_published') && trim($changelog) === '') { throw new RuntimeException('Opublikowane wydanie Release wymaga opisu zmian.'); }
        if (mb_strlen($changelog)>10000) { throw new RuntimeException('Opis zmian jest za długi.'); }
        return ['project_id'=>$request->postInt('project_id',0)??0,'platform'=>$platform,'platform_family'=>$proxy?'proxy':'server','compatible_platforms'=>$platforms,'game_versions'=>$versions,'version_label'=>$version,'build_number'=>$number,'channel'=>$channel,'java_version'=>$java,'changelog'=>$changelog,'filename'=>trim($request->postString('filename')),'is_published'=>$request->postBool('is_published')?1:0];
    }

    public static function versionChoices(array $known, array $selected = []): array
    {
        $base=['1.20.6','1.21','1.21.1','1.21.2','1.21.3','1.21.4','1.21.5','1.21.6','1.21.7','1.21.8','1.21.9','1.21.10','1.21.11','26.1','26.1.1','26.1.2','26.2','26.3'];
        $all=array_values(array_unique([...$base,...$known,...$selected]));
        usort($all,static fn($a,$b)=>version_compare($b,$a));
        return array_combine($all,$all)?:[];
    }
}
