<?php

declare(strict_types=1);
namespace SyntaxDevTeam\Cms\Modules\EventCalendar;
use PDO;
use SyntaxDevTeam\Cms\Database\CrudApp;

final class EventNotifications
{
    public function __construct(private readonly CrudApp $db,private readonly EventNotificationConfig $config) {}
    public function enqueue(int $eventId,?int $taskId,string $channel,bool $test=false,bool $retry=false): bool
    {
        if(!$this->config->ready($channel))return false;
        $repo=new EventRepository($this->db);$source=$test?['test'=>bin2hex(random_bytes(16))]:($channel==='public'?$repo->publicEvent($eventId):($taskId===null?$this->reminder($eventId):$this->task($eventId,$taskId)));
        if(!$source)return false;
        if(!$test && $channel==='staff' && (($source['status']??'')==='done'))return false;
        $hash=self::fingerprint($source);$destination=$this->config->destination($channel);$key=hash('sha256',$channel.':'.$eventId.':'.($taskId??0).':'.$hash.':'.$destination);
        $existing=($this->db->read('event_calendar_notifications','*',['dedupe_key'=>$key])??[])[0]??null;
        if($existing){if($retry && in_array($existing['status'],['failed','skipped'],true)){$this->db->update('event_calendar_notifications',['status'=>'pending','attempts'=>0,'next_attempt_at'=>null,'last_status'=>null],['id'=>$existing['id']]);return true;}return false;}
        $this->db->create('event_calendar_notifications',['event_id'=>$test?null:$eventId,'task_id'=>$test?null:$taskId,'channel'=>$channel,'is_test'=>(int)$test,'source_hash'=>$hash,'destination_hash'=>$destination,'dedupe_key'=>$key,'status'=>'pending','attempts'=>0,'created_at'=>gmdate('Y-m-d H:i:s')]);return true;
    }
    public function reminder(int $eventId): ?array
    {
        $repo=new EventRepository($this->db);$event=$repo->find($eventId);
        return $event && $event['discord_mode']==='later' ? $repo->publicEvent($eventId) : null;
    }
    public function publicationStatus(int $eventId): string
    {
        $source=(new EventRepository($this->db))->publicEvent($eventId);
        if(!$source)return 'Najpierw opublikuj na stronie';
        $row=$this->db->query("SELECT status FROM event_calendar_notifications WHERE event_id=:id AND channel='public' AND is_test=0 AND source_hash=:hash AND destination_hash=:destination ORDER BY id DESC LIMIT 1",[':id'=>$eventId,':hash'=>self::fingerprint($source),':destination'=>$this->config->destination('public')])->fetchColumn();
        return match($row){'sent'=>'Wysłano na Discord','pending'=>'W kolejce Discord','failed'=>'Błąd wysyłki — ponów publikację',default=>'Przypomnienie: opublikuj na Discordzie'};
    }
    public function task(int $eventId,int $taskId): ?array
    {
        $r=$this->db->query('SELECT t.* FROM event_calendar_tasks t JOIN event_calendar_events e ON e.id=t.event_id WHERE t.id=:task AND t.event_id=:event AND e.state NOT IN (\'cancelled\',\'archived\')',[':task'=>$taskId,':event'=>$eventId])->fetch(PDO::FETCH_ASSOC);
        return is_array($r)?$r:null;
    }
    public static function fingerprint(array $source): string {return hash('sha256',json_encode($source,JSON_THROW_ON_ERROR));}
    public function recent(): array {return $this->db->query('SELECT id,event_id,channel,is_test,status,attempts,last_status,created_at FROM event_calendar_notifications ORDER BY id DESC LIMIT 30')->fetchAll(PDO::FETCH_ASSOC);}
}
