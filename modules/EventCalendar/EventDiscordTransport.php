<?php

declare(strict_types=1);
namespace SyntaxDevTeam\Cms\Modules\EventCalendar;
final class EventDiscordTransport
{
    public function send(string $url,array $payload): array
    {
        if (str_starts_with($url,'discord-route:')) {
            return class_exists(\SyntaxDevTeam\Cms\Modules\DiscordIntegration\DiscordHub::class) ? (\SyntaxDevTeam\Cms\Modules\DiscordIntegration\DiscordHub::local(dirname(__DIR__,2))?->send($url,$payload)??['status'=>410]) : ['status'=>410];
        }

        if(!EventNotificationConfig::safeWebhook($url))throw new \InvalidArgumentException('Nieprawidłowy webhook.');
        $context=stream_context_create(['http'=>['method'=>'POST','header'=>"Content-Type: application/json\r\nAccept: application/json\r\n",'content'=>json_encode($payload,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),'timeout'=>5,'ignore_errors'=>true,'follow_location'=>0],'ssl'=>['verify_peer'=>true,'verify_peer_name'=>true]]);
        $body=@file_get_contents($url.'?wait=true',false,$context,0,65536);$status=0;$retry=0;
        foreach($http_response_header??[] as $header){if(preg_match('~^HTTP/\S+\s+(\d{3})~',$header,$m))$status=(int)$m[1];if(preg_match('~^Retry-After:\s*(\d+(?:\.\d+)?)~i',$header,$m))$retry=(int)ceil((float)$m[1]);}
        $data=is_string($body)?json_decode($body,true):null;if(is_array($data)&&is_numeric($data['retry_after']??null))$retry=max($retry,(int)ceil((float)$data['retry_after']));
        return ['status'=>$body===false?0:$status,'retry_after'=>max(0,min(3600,$retry))];
    }
}
