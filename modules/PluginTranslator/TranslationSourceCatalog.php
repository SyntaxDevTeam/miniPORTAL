<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\PluginTranslator;

use SyntaxDevTeam\Cms\Core\TemplateCacheInterface;

/** Module-owned, explicitly configured public source directories; never executable remote content. */
final class TranslationSourceCatalog
{
    public function __construct(
        private readonly array $sources,
        private readonly GitHubTranslationClientInterface $client,
        private readonly ?TemplateCacheInterface $cache = null,
        private readonly ?\Closure $provider = null,
    ) {
        $this->validate($sources);
    }
    private ?array $definitionCache=null;
    public function refreshDefinitions(): void {$this->definitionCache=null;}
    public function definitions(): array
    {
        if($this->definitionCache!==null)return $this->definitionCache;
        $provider=$this->provider!==null?($this->provider)():null;
        $sources=$provider instanceof \SyntaxDevTeam\Cms\Core\ProjectLanguageSourceProviderInterface?$provider->sources():$this->sources;
        $this->validate($sources);return $this->definitionCache=$sources;
    }
    private function validate(array $sources): void
    {
        foreach($sources as $slug=>$source){
            if(!is_string($slug)||!is_array($source)||preg_match('~\A[A-Za-z0-9][A-Za-z0-9_.-]{0,99}/[A-Za-z0-9][A-Za-z0-9_.-]{0,99}\z~D',(string)($source['repository']??''))!==1||preg_match('~\A[A-Za-z0-9][A-Za-z0-9_./-]{0,149}\z~D',(string)($source['ref']??''))!==1||str_contains($source['ref'],'..'))throw new \InvalidArgumentException('Invalid source configuration.');
            $dirs=$source['directories']??[['path'=>$source['directory']??'']];
            if($dirs===[]||count($dirs)>8)throw new \InvalidArgumentException('Invalid source directories.');
            foreach($dirs as $dir){$path=$dir['path']??'';if(!is_string($path)||preg_match('~\A[A-Za-z0-9_-][A-Za-z0-9_./-]{0,249}\z~D',$path)!==1||str_contains($path,'..')||str_contains($path,'//')||str_ends_with($path,'/'))throw new \InvalidArgumentException('Invalid source directory.');}
        }
    }

    public function supports(PluginTranslationProject $project): bool
    {
        return $project->status === 'active' && $project->slug !== 'nieprzypisane' && isset($this->definitions()[$project->slug]);
    }

    public function directoryUrl(PluginTranslationProject $project): string
    {
        $source = $this->configuration($project);
        return 'https://github.com/' . $source['repository'] . '/tree/' . rawurlencode($source['ref']) . '/' . ($source['directories'][0]['path']??$source['directory']);
    }

    public function reference(PluginTranslationProject $project): string
    {
        return $this->configuration($project)['ref'];
    }

    /** @return list<array{name:string,sha:string,size:int,key:string}> */
    public function files(PluginTranslationProject $project): array
    {
        $source = $this->configuration($project);
        if(!empty($source['private']))throw new \RuntimeException('Private language sources are not activated yet.');
        $load = function () use ($source): string {
            try {
                $commit=method_exists($this->client,'revision')?$this->client->revision($source['repository'],$source['ref']):$source['ref'];
                $dirs=$source['directories']??[['label'=>'Main','path'=>$source['directory']]];$files=[];
                foreach($dirs as $dir){
                    $entries=$this->client->directory($source['repository'],$commit,$dir['path']);
                    if(!array_is_list($entries))throw new \RuntimeException('Source must be a directory.');
                    foreach($entries as $entry){
                        if(!is_array($entry)||($entry['type']??'')!=='file'||isset($entry['target'])||isset($entry['submodule_git_url']))continue;
                        $name=(string)($entry['name']??'');$sha=(string)($entry['sha']??'');$size=$entry['size']??0;
                        if(preg_match('/\A[A-Za-z0-9][A-Za-z0-9_.-]{0,99}\.ya?ml\z/iD',$name)!==1||preg_match('/\A[a-f0-9]{40}\z/D',$sha)!==1||($entry['path']??'')!==$dir['path'].'/'.$name||!is_int($size)||$size<=0||$size>262144)continue;
                        $path=$dir['path'].'/'.$name;
                        $files[]=['name'=>$name,'sha'=>$sha,'size'=>$size,'path'=>$path,'commit'=>$commit,'label'=>$dir['label'],'key'=>$sha.':'.(isset($source['directories'])?$path:$name)];
                    }
                }
                usort($files, static fn (array $a, array $b): int => strnatcasecmp($a['name'], $b['name']));
                return json_encode(['files' => array_slice($files, 0, 128)], JSON_THROW_ON_ERROR);
            } catch (\Throwable) {
                // Cached as well: outages and API rate limits do not trigger a request on every visit.
                return json_encode(['unavailable' => true], JSON_THROW_ON_ERROR);
            }
        };
        $json = $this->cache?->remember('translation_sources_v2', json_encode($source, JSON_THROW_ON_ERROR), $load) ?? $load();
        $data = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($data) || isset($data['unavailable'])) {
            throw new \RuntimeException('GitHub sources are temporarily unavailable. Please try again later or upload your own YAML file.');
        }
        return $data['files'] ?? [];
    }

    /** @return array{content:string,filename:string,sha:string} */
    public function open(PluginTranslationProject $project, string $key): array
    {
        $source = $this->configuration($project);
        $selected = null;
        foreach ($this->files($project) as $file) {
            if (hash_equals($file['key'], $key)) {
                $selected = $file;
                break;
            }
        }
        if ($selected === null) {
            throw new \InvalidArgumentException('This source file is no longer available. Choose a source file again.');
        }
        $load = function () use ($source, $selected): string {
            $blob = $this->client->blob($source['repository'], $selected['sha']);
            $encoded = $blob['content'] ?? '';
            if (($blob['encoding'] ?? '') !== 'base64' || !is_string($encoded) || strlen($encoded) > 400000) {
                throw new \RuntimeException('GitHub returned an invalid source file.');
            }
            $content = base64_decode(preg_replace('/\s+/', '', $encoded), true);
            if (!is_string($content) || strlen($content) !== $selected['size'] || strlen($content) > 262144
                || !mb_check_encoding($content, 'UTF-8') || str_contains($content, "\0")
                || !hash_equals($selected['sha'], sha1('blob ' . strlen($content) . "\0" . $content))) {
                throw new \RuntimeException('The source file failed the GitHub integrity or size check.');
            }
            return $content;
        };
        $content = $this->cache?->remember('translation_source_blobs_v1', $source['repository'] . ':' . $selected['sha'], $load) ?? $load();
        return ['content'=>$content,'filename'=>$selected['name'],'sha'=>$selected['sha'],'origin'=>['repository'=>$source['repository'],'ref'=>$source['ref'],'commit'=>$selected['commit'],'path'=>$selected['path'],'blob'=>$selected['sha'],'sha256'=>hash('sha256',$content)]];
    }

    private function configuration(PluginTranslationProject $project): array
    {
        if (!$this->supports($project)) {
            throw new \InvalidArgumentException('This project has no public GitHub language source.');
        }
        return $this->definitions()[$project->slug];
    }
}
