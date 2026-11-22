<?php

declare(strict_types=1);
namespace SyntaxDevTeam\Cms\Modules\DiscordIntegration;
use SyntaxDevTeam\Cms\Core\ModuleStateRepository;
use SyntaxDevTeam\Cms\Database\CrudApp;

final class DiscordHub
{
    public function __construct(public readonly DiscordConfig $config, private readonly bool $active=true, private readonly ?\Closure $http=null) {}
    public static function local(string $root): ?self
    {
        $file=$root.'/config/modules/discord-integration.json';
        if (!is_file($file)) return null;
        // An explicitly managed route never falls back to a legacy webhook on disable/error.
        try {
            $c=require $root.'/config/config.php';$db=CrudApp::make($c['database']);
            $active=(new ModuleStateRepository($db))->find('discord_integration')?->isActive()??false;
        } catch (\Throwable) {$active=false;}
        return new self(new DiscordConfig($file),$active);
    }
    public function managed(string $key): bool {return isset($this->config->read()['routes'][$key]);}
    public function ready(string $key): bool
    {
        $d=$this->config->read();$r=$d['routes'][$key]??[];
        return $this->active && (($r['mode']??'')==='bot' ? $d['token']!=='' && DiscordConfig::id($d['guild']) && DiscordConfig::id($r['channel']) : (($r['mode']??'')==='webhook' && DiscordConfig::webhook($r['webhook'])));
    }
    public function destination(string $key): string
    {
        return self::destinationFor($key,$this->config->read());
    }
    private static function destinationFor(string $key,array $d): string
    {
        $r=$d['routes'][$key]??[];
        return 'discord-route:'.$key.':'.hash('sha256',json_encode([$r,$d['guild'],hash('sha256',$d['token'])],JSON_THROW_ON_ERROR));
    }
    /** Nonsecret opaque destinations keep existing source/destination queue checks intact. */
    public function send(string $destination, array $payload): array
    {
        if (!preg_match('/\Adiscord-route:([a-z_]+\.[a-z_]+):[a-f0-9]{64}\z/D',$destination,$m)) throw new \InvalidArgumentException('Nieprawidłowy cel Discord.');
        $key=$m[1];
        $d=$this->config->read();$r=$d['routes'][$key]??[];
        if (!$this->active || !in_array($r['mode']??'',['bot','webhook'],true) || !hash_equals(self::destinationFor($key,$d),$destination)) return ['status'=>410,'retry_after'=>0];
        // Shared serialization and cooldown cover all WWW/CLI producers on this installation.
        $lock=fopen(dirname($this->config->file,3).'/cache/discord-send.lock','c+');
        if (!$lock) return ['status'=>503,'retry_after'=>30];
        chmod(dirname($this->config->file,3).'/cache/discord-send.lock',0600);
        try {
            if (!flock($lock,LOCK_EX|LOCK_NB)) return ['status'=>429,'retry_after'=>2];
            $until=(int)stream_get_contents($lock);
            if ($until>time()) return ['status'=>429,'retry_after'=>min(3600,$until-time())];
            $payload = self::withRouteMentions($payload, $r, $d['guild']);
            if ($r['mode']==='bot') {
                $channel=$this->request('GET','/channels/'.$r['channel'],$d['token']);
                if ($channel['status']!==200) return $this->cooldown($lock,$channel);
                $metadata=$channel['data']??[];
                if (($metadata['guild_id']??'')!==$d['guild'] || !in_array($metadata['type']??-1,[0,5],true)) return ['status'=>403,'retry_after'=>0];
                $result=$this->request('POST','/channels/'.$r['channel'].'/messages',$d['token'],$payload);
            } else {$result=self::webhookSend($r['webhook'],$payload,$this->http);}
            return $this->cooldown($lock,$result);
        } finally {flock($lock,LOCK_UN);fclose($lock);}
    }

    private static function withRouteMentions(array $payload, array $route, string $guild): array
    {
        if (empty($route['roles']) && empty($route['users'])) return $payload;

        $content = $payload['content'] ?? '';
        $remaining = 2000 - intdiv(strlen(mb_convert_encoding($content, 'UTF-16LE', 'UTF-8')), 2);
        $mentions = [];
        foreach (['roles' => '<@&', 'users' => '<@'] as $type => $prefix) {
            $allowed = $payload['allowed_mentions'][$type] ?? [];
            foreach ($route[$type] ?? [] as $id) {
                if ($type === 'roles' && $id === $guild) continue;
                if (!in_array($id, $allowed, true) && count($allowed) >= 100) continue;
                $mention = $prefix.$id.'>';
                if (!str_contains($content, $mention)) {
                    $cost = strlen($mention) + (int)($mentions !== [] || $content !== '');
                    if ($cost > $remaining) continue;
                    $mentions[] = $mention;
                    $remaining -= $cost;
                }
                if (!in_array($id, $allowed, true)) $allowed[] = $id;
            }
            if ($allowed !== []) $payload['allowed_mentions'][$type] = $allowed;
        }
        if ($mentions !== []) {
            $payload['content'] = implode(' ', $mentions).($content === '' ? '' : "\n".$content);
        }
        return $payload;
    }

    private function cooldown($lock,array $result): array
    {
        $delay=$result['status']===429?max(1,(int)($result['retry_after']??1)):1;
        rewind($lock);ftruncate($lock,0);fwrite($lock,(string)(time()+$delay));fflush($lock);
        unset($result['data']);return $result;
    }
    public function guilds(): array
    {
        if (!$this->active) return [];
        $r=$this->request('GET','/users/@me/guilds?limit=200',$this->config->read()['token']);
        if ($r['status']!==200) throw new \RuntimeException('Nie można pobrać serwerów bota (HTTP '.$r['status'].').');
        return $r['data']??[];
    }
    public function channels(): array
    {
        $d=$this->config->read();if (!$this->active || !DiscordConfig::id($d['guild'])) return [];
        $r=$this->request('GET','/guilds/'.$d['guild'].'/channels',$d['token']);
        if ($r['status']!==200) throw new \RuntimeException('Nie można pobrać kanałów bota (HTTP '.$r['status'].').');
        return array_values(array_filter($r['data']??[],static fn($c)=>in_array($c['type']??-1,[0,5],true)));
    }
    private function request(string $method,string $path,string $token,array $payload=[]): array
    {
        if ($token==='' || preg_match('/\A[A-Za-z0-9_.-]{30,250}\z/D',$token)!==1) return ['status'=>401,'retry_after'=>0];
        return self::http($method,'https://discord.com/api/v10'.$path,$payload,$token,$this->http);
    }
    public static function webhookSend(string $url,array $payload,?\Closure $http=null): array
    {
        if (!DiscordConfig::webhook($url)) throw new \InvalidArgumentException('Nieprawidłowy webhook Discord.');
        return self::http('POST',$url.'?wait=true',$payload,'',$http);
    }
    private static function http(string $method,string $url,array $payload,string $token,?\Closure $http): array
    {
        // Accept only explicit IDs from the route or trusted module payload builders.
        $payload['allowed_mentions']??=['parse'=>[]];
        $payload['allowed_mentions']['parse']=[];
        unset($payload['username'],$payload['avatar_url'],$payload['tts']);
        if ($http) return $http($method,$url,$payload,$token);
        $headers="Content-Type: application/json\r\nAccept: application/json\r\nUser-Agent: miniPORTAL (https://syntaxdevteam.pl, 1.0)\r\n";
        if ($token!=='') $headers.='Authorization: Bot '.$token."\r\n";
        $options=['method'=>$method,'header'=>$headers,'timeout'=>5,'ignore_errors'=>true,'follow_location'=>0];
        if ($method==='POST') $options['content']=json_encode($payload,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
        $context=stream_context_create(['http'=>$options,'ssl'=>['verify_peer'=>true,'verify_peer_name'=>true]]);
        $body=@file_get_contents($url,false,$context,0,262144);$status=0;$retry=0;
        foreach ($http_response_header??[] as $header) {
            if (preg_match('~^HTTP/\S+\s+(\d{3})~',$header,$m)) $status=(int)$m[1];
            if (preg_match('~^Retry-After:\s*([0-9.]+)~i',$header,$m)) $retry=(int)ceil((float)$m[1]);
        }
        $data=is_string($body)?json_decode($body,true):null;
        if (is_array($data) && is_numeric($data['retry_after']??null)) $retry=max($retry,(int)ceil((float)$data['retry_after']));
        return ['status'=>$body===false?0:$status,'retry_after'=>max(0,min(3600,$retry)),'data'=>is_array($data)?$data:[]];
    }
}
