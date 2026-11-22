<?php

declare(strict_types=1);
namespace SyntaxDevTeam\Cms\Modules\EventCalendar;

use InvalidArgumentException;
use RuntimeException;

final class EventNotificationConfig
{
    private bool $hubResolved=false;
    private ?\SyntaxDevTeam\Cms\Modules\DiscordIntegration\DiscordHub $discordHub=null;
    private function hub(): ?\SyntaxDevTeam\Cms\Modules\DiscordIntegration\DiscordHub
    {
        if (!$this->hubResolved) {
            $this->hubResolved=true;
            if (class_exists(\SyntaxDevTeam\Cms\Modules\DiscordIntegration\DiscordHub::class)) $this->discordHub=\SyntaxDevTeam\Cms\Modules\DiscordIntegration\DiscordHub::local(dirname($this->file,3));
        }
        return $this->discordHub;
    }
    public function centrallyManaged(string $channel): bool { return $this->hub()?->managed('event_calendar.'.$channel)??false; }

    public function __construct(private readonly string $file, public readonly string $baseUrl='',private readonly string $defaultImage='') {}
    public static function safeWebhook(string $url): bool {return preg_match('~\Ahttps://discord\.com/api/webhooks/[0-9]{17,20}/[A-Za-z0-9_-]{40,200}\z~D',$url)===1;}
    public function settings(): array
    {
        $data=$this->read();$result=['pin_enabled'=>$data['pin_enabled']??true];
        foreach(['public','staff'] as $channel)$result[$channel]=['enabled'=>!empty($data[$channel]['enabled']),'configured'=>self::safeWebhook($data[$channel]['webhook']??'')];
        return $result;
    }
    public function ready(string $channel): bool
    {
        if ($this->centrallyManaged($channel)) return $this->hub()->ready('event_calendar.'.$channel) && $this->safeBase();
        if(!in_array($channel,['public','staff'],true))return false;
        $s=$this->settings();return $s[$channel]['enabled'] && $s[$channel]['configured'] && $this->safeBase();
    }
    public function webhook(string $channel): string { if ($this->centrallyManaged($channel)) return $this->hub()->destination('event_calendar.'.$channel);return in_array($channel,['public','staff'],true)?(string)($this->read()[$channel]['webhook']??''):'';}
    public function destination(string $channel): string {return hash('sha256',$this->webhook($channel));}
    public function url(string $path): string {return rtrim($this->baseUrl,'/').$path;}
    public function image(): string
    {
        return $this->defaultImage!=='' && str_starts_with($this->defaultImage,'/') && !str_starts_with($this->defaultImage,'//')?$this->url($this->defaultImage):'';
    }
    public function save(array $input): void
    {
        $directory=dirname($this->file);if(!is_dir($directory) && !mkdir($directory,0700,true) && !is_dir($directory))throw new RuntimeException('Nie można utworzyć katalogu ustawień.');
        $lock=fopen($this->file.'.lock','c');if(!$lock)throw new RuntimeException('Nie można zablokować ustawień.');chmod($this->file.'.lock',0600);
        if(!flock($lock,LOCK_EX)){fclose($lock);throw new RuntimeException('Nie można zablokować ustawień.');}
        try{
            $old=$this->read();$data=['pin_enabled'=>(bool)($input['pin_enabled']??false)];
            foreach(['public','staff'] as $channel){
                if ($this->centrallyManaged($channel)) {$data[$channel]=$old[$channel]??['enabled'=>false,'webhook'=>''];continue;}
                $url=trim((string)($input[$channel.'_webhook']??''));if($url==='')$url=(string)($old[$channel]['webhook']??'');if(!empty($input[$channel.'_clear']))$url='';
                if($url!=='' && !self::safeWebhook($url))throw new InvalidArgumentException('Webhook musi być adresem HTTPS z discord.com/api/webhooks.');
                $enabled=!empty($input[$channel.'_enabled']);if($enabled && (!self::safeWebhook($url)||!$this->safeBase()))throw new InvalidArgumentException('Włączony kanał wymaga poprawnego webhooka i adresu HTTPS strony.');
                $data[$channel]=['enabled'=>$enabled,'webhook'=>$url];
            }
            if(!$this->centrallyManaged('public') && !$this->centrallyManaged('staff') && $data['public']['webhook']!=='' && $data['public']['webhook']===$data['staff']['webhook'])throw new InvalidArgumentException('Kanał administracji musi mieć inny webhook niż publiczne ogłoszenia.');
            $tmp=tempnam($directory,'.event-notify-');if(!$tmp)throw new RuntimeException('Nie można zapisać ustawień.');
            try{if(!chmod($tmp,0600)||file_put_contents($tmp,json_encode($data,JSON_THROW_ON_ERROR),LOCK_EX)===false||!rename($tmp,$this->file))throw new RuntimeException('Nie można zapisać ustawień.');}finally{if(is_file($tmp))unlink($tmp);}
        }finally{flock($lock,LOCK_UN);fclose($lock);}
    }
    private function safeBase(): bool
    {
        return filter_var($this->baseUrl,FILTER_VALIDATE_URL)!==false && parse_url($this->baseUrl,PHP_URL_SCHEME)==='https' && parse_url($this->baseUrl,PHP_URL_USER)===null && parse_url($this->baseUrl,PHP_URL_PASS)===null && parse_url($this->baseUrl,PHP_URL_QUERY)===null && parse_url($this->baseUrl,PHP_URL_FRAGMENT)===null;
    }
    private function read(): array
    {
        if(!is_file($this->file))return [];$raw=file_get_contents($this->file);if(!is_string($raw))throw new RuntimeException('Nie można odczytać ustawień.');$data=json_decode($raw,true,16,JSON_THROW_ON_ERROR);return is_array($data)?$data:[];
    }
}
