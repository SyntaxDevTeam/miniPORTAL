<?php
declare(strict_types=1);
namespace SyntaxDevTeam\Cms\Templates\EquestriaTheme;

/** Presentation labels only; unknown/custom identifiers retain a readable fallback. */
final class InventoryText
{
    public static function item(string $id): string
    {
        $key=str_starts_with($id,'minecraft:')?substr($id,10):$id;
        $materials=['netherite'=>['Netherytowy','Netherytowa','Netherytowe'],'diamond'=>['Diamentowy','Diamentowa','Diamentowe'],
            'iron'=>['Żelazny','Żelazna','Żelazne'],'golden'=>['Złoty','Złota','Złote'],'stone'=>['Kamienny','Kamienna','Kamienne'],
            'wooden'=>['Drewniany','Drewniana','Drewniane'],'leather'=>['Skórzany','Skórzana','Skórzane'],'chainmail'=>['Kolczugowy','Kolczugowa','Kolczugowe']];
        $parts=['sword'=>['miecz',0],'pickaxe'=>['kilof',0],'axe'=>['siekiera',1],'shovel'=>['łopata',1],'hoe'=>['motyka',1],
            'helmet'=>['hełm',0],'chestplate'=>['napierśnik',0],'leggings'=>['nogawice',2],'boots'=>['buty',2]];
        foreach($materials as $material=>$forms)foreach($parts as $part=>[$label,$form])if($key===$material.'_'.$part)return $forms[$form].' '.$label;
        return ['elytra'=>'Elytra','shield'=>'Tarcza','bow'=>'Łuk','crossbow'=>'Kusza','trident'=>'Trójząb','diamond'=>'Diament',
            'emerald'=>'Szmaragd','chest'=>'Skrzynia','ender_chest'=>'Skrzynia Endu','golden_apple'=>'Złote jabłko','enchanted_book'=>'Zaklęta książka'][$key]
            ??mb_convert_case(str_replace('_',' ',$key),MB_CASE_TITLE,'UTF-8');
    }

    public static function enchantment(string $line): array
    {
        if(!preg_match('/^(?:minecraft:)?([a-z_]+) ([0-9]{1,5})$/D',$line,$match))return ['text'=>$line,'curse'=>false];
        $key=$match[1];$level=(int)$match[2];
        $names=['protection'=>'Ochrona','fire_protection'=>'Ochrona przed ogniem','blast_protection'=>'Ochrona przed wybuchem',
            'projectile_protection'=>'Ochrona przed pociskami','feather_falling'=>'Łagodny upadek','unbreaking'=>'Niezniszczalność','mending'=>'Naprawa',
            'sharpness'=>'Ostrość','smite'=>'Pogromca nieumarłych','bane_of_arthropods'=>'Zmora stawonogów','efficiency'=>'Wydajność',
            'fortune'=>'Szczęście','silk_touch'=>'Jedwabny dotyk','looting'=>'Grabież','knockback'=>'Odrzut','fire_aspect'=>'Zaklęty ogień',
            'power'=>'Moc','punch'=>'Uderzenie','flame'=>'Płomień','infinity'=>'Nieskończoność','respiration'=>'Oddychanie','aqua_affinity'=>'Wydajność pod wodą',
            'thorns'=>'Ciernie','depth_strider'=>'Głębinowy wędrowiec','frost_walker'=>'Mroźny piechur','soul_speed'=>'Prędkość dusz',
            'swift_sneak'=>'Szybkie skradanie','binding_curse'=>'Klątwa uwiązania','vanishing_curse'=>'Klątwa znikania'];
        $roman=[1=>'I',2=>'II',3=>'III',4=>'IV',5=>'V',6=>'VI',7=>'VII',8=>'VIII',9=>'IX',10=>'X'];
        $single=in_array($key,['mending','silk_touch','aqua_affinity','flame','infinity','binding_curse','vanishing_curse'],true);
        $name=$names[$key]??mb_convert_case(str_replace('_',' ',$key),MB_CASE_TITLE,'UTF-8');
        return ['text'=>$name.($level===1&&$single?'':' '.($roman[$level]??$level)),'curse'=>str_ends_with($key,'_curse')];
    }
}
