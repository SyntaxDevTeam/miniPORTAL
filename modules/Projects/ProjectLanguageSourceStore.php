<?php

declare(strict_types=1);
namespace SyntaxDevTeam\Cms\Modules\Projects;

use SyntaxDevTeam\Cms\Core\ProjectLanguageSourceProviderInterface;

/** Stores only source metadata. Never stores, returns to forms, or exports credentials. */
final class ProjectLanguageSourceStore implements ProjectLanguageSourceProviderInterface
{
    public function __construct(private readonly string $file, private readonly ProjectRepository $projects, private readonly string $readToken = '') {}

    private function read(): array
    {
        if (!is_file($this->file)) return [];
        $data=json_decode((string)file_get_contents($this->file),true,32,JSON_THROW_ON_ERROR);
        if(!is_array($data))throw new \RuntimeException('Nieprawidłowa konfiguracja źródeł.');
        return $data;
    }
    public function settings(int $projectId): array
    {
        return ($this->read()[(string)$projectId]??[]) + ['repository'=>'','ref'=>'main','directories'=>[],'enabled'=>false,'private'=>false];
    }
    public function hasCredential(): bool {return $this->readToken!=='';}
    public static function normalize(array $input): array
    {
        $repository=trim((string)($input['repository']??''));
        if(preg_match('~\Ahttps://github\.com/([^/?#]+/[^/?#]+?)(?:\.git)?/?\z~D',$repository,$m))$repository=$m[1];
        if($repository!==''&&preg_match('~\A[A-Za-z0-9][A-Za-z0-9_.-]{0,99}/[A-Za-z0-9][A-Za-z0-9_.-]{0,99}\z~D',$repository)!==1)throw new \InvalidArgumentException('Repozytorium: owner/repository lub HTTPS github.com.');
        $ref=trim((string)($input['ref']??'main'));
        if(preg_match('~\A[A-Za-z0-9][A-Za-z0-9_./-]{0,149}\z~D',$ref)!==1||str_contains($ref,'..')||str_contains($ref,'//')||str_ends_with($ref,'/'))throw new \InvalidArgumentException('Nieprawidłowa gałąź lub tag.');
        $directories=[];
        foreach(preg_split('/\r?\n/',trim((string)($input['paths']??'')))?:[] as $line){
            if(trim($line)==='')continue;
            $parts=explode('|',$line,2);$label=count($parts)===2?trim($parts[0]):'Main';$path=trim($parts[count($parts)-1]);
            if($label===''||mb_strlen($label)>60||preg_match('~\A[A-Za-z0-9_-][A-Za-z0-9_./-]{0,249}\z~D',$path)!==1||str_contains($path,'..')||str_contains($path,'//')||str_ends_with($path,'/'))throw new \InvalidArgumentException('Katalogi: Etykieta | ścieżka/do/lang, bez .. i końcowego ukośnika.');
            $directories[$path]=['label'=>$label,'path'=>$path];
        }
        if(count($directories)>8)throw new \InvalidArgumentException('Maksymalnie 8 katalogów na projekt.');
        $enabled=!empty($input['enabled']);
        if($enabled&&($repository===''||$directories===[]))throw new \InvalidArgumentException('Włączone źródło wymaga repozytorium i katalogu.');
        return ['repository'=>$repository,'ref'=>$ref,'directories'=>array_values($directories),'enabled'=>$enabled,'private'=>!empty($input['private']),'notes'=>mb_substr(trim((string)($input['notes']??'')),0,300)];
    }
    public function save(int $projectId,array $input): void
    {
        if(!$this->projects->find($projectId))throw new \InvalidArgumentException('Projekt nie istnieje.');
        $value=self::normalize($input);$directory=dirname($this->file);
        if(!is_dir($directory)&&!mkdir($directory,0770,true))throw new \RuntimeException('Nie można utworzyć katalogu konfiguracji.');
        $lock=fopen($this->file.'.lock','c');if(!$lock||!flock($lock,LOCK_EX))throw new \RuntimeException('Nie można zablokować konfiguracji.');
        chmod($this->file.'.lock',0600);
        try{
            $data=$this->read();$data[(string)$projectId]=$value;
            $tmp=tempnam($directory,'.project-sources-');if($tmp===false)throw new \RuntimeException('Błąd przygotowania zapisu.');
            try{if(file_put_contents($tmp,json_encode($data,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES),LOCK_EX)===false)throw new \RuntimeException('Błąd zapisu.');chmod($tmp,0600);if(!rename($tmp,$this->file))throw new \RuntimeException('Błąd aktywacji.');}
            finally{if(is_file($tmp))unlink($tmp);}
        }finally{flock($lock,LOCK_UN);fclose($lock);}
    }
    public function sources(): array
    {
        $data=$this->read();$result=[];
        foreach($this->projects->all(true) as $project){
            $source=$data[(string)$project->id]??null;
            if(!is_array($source)||empty($source['enabled']))continue;
            $result[$project->slug]=$source+['name'=>$project->name,'page_id'=>$project->pageId,'project_id'=>$project->id];
        }
        return $result;
    }
    public function credential(string $repository): ?string
    {
        foreach($this->sources() as $source){if($source['repository']===$repository&&!empty($source['private'])){
            if($this->readToken==='')throw new \RuntimeException('Private language sources require configured server-side GitHub read access.');
            return $this->readToken;
        }}
        return null;
    }
}
