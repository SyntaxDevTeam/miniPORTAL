<?php

declare(strict_types=1);
namespace SyntaxDevTeam\Cms\Modules\EventCalendar;
use PDO;
use SyntaxDevTeam\Cms\Database\CrudApp;

/** Caller holds the single database worker lock for the entire bounded batch. */
final class EventNotificationWorker
{
    public function __construct(private readonly CrudApp $db,private readonly EventNotificationConfig $config,private readonly \Closure $send) {}
    public function workOne(): bool
    {
        $row=$this->db->query("SELECT * FROM event_calendar_notifications WHERE status='pending' AND (next_attempt_at IS NULL OR next_attempt_at<=:now) ORDER BY id LIMIT 1",[':now'=>gmdate('Y-m-d H:i:s')])->fetch(PDO::FETCH_ASSOC);
        if(!$row)return false;$channel=$row['channel'];$id=(int)$row['id'];$webhook=$this->config->webhook($channel);
        if(!$this->config->ready($channel) || !hash_equals($row['destination_hash'],hash('sha256',$webhook))){$this->db->update('event_calendar_notifications',['status'=>'skipped'],['id'=>$id]);return true;}
        $repo=new EventRepository($this->db);$task=null;$event=[];
        if(!$row['is_test']){
            $event=$channel==='public'?$repo->publicEvent((int)$row['event_id']):$repo->find((int)$row['event_id']);
            $queue=new EventNotifications($this->db,$this->config);
            $source=$channel==='public'?$event:($row['task_id']===null?$queue->reminder((int)$row['event_id']):$queue->task((int)$row['event_id'],(int)$row['task_id']));
            if($channel==='public' && ($repo->find((int)$row['event_id'])['discord_mode']??'now')==='later')$source=null;
            if(!$event || !$source || !hash_equals($row['source_hash'],EventNotifications::fingerprint($source))){$this->db->update('event_calendar_notifications',['status'=>'skipped'],['id'=>$id]);return true;}
            if($channel==='staff'){
                if($row['task_id']===null){$task=['title'=>'Przypomnienie: publikacja na Discordzie','description'=>'Wydarzenie jest dostępne na stronie. Publiczne ogłoszenie Discord zostało odłożone. Otwórz plan i użyj przycisku publikacji na Discordzie.','assignee_name'=>'Administracja','status'=>'todo','due_at'=>null];}
                else {$task=$source;$name=$task['assignee_id']===null?'Do przypisania':$this->db->query("SELECT display_name FROM users WHERE id=:id AND status='active'",[':id'=>$task['assignee_id']])->fetchColumn();
                if(!$name || $task['status']==='done'){$this->db->update('event_calendar_notifications',['status'=>'skipped'],['id'=>$id]);return true;}$task['assignee_name']=$name;}
            }
        }
        $payload=EventDiscordPayload::build($channel,$event,$task,$this->config,(bool)$row['is_test']);
        try{$result=($this->send)($webhook,$payload);}catch(\Throwable){$result=['status'=>0];}
        $status=(int)($result['status']??0);$attempt=(int)$row['attempts']+1;
        if($status>=200 && $status<300)$changes=['status'=>'sent','attempts'=>$attempt,'last_status'=>$status,'sent_at'=>gmdate('Y-m-d H:i:s')];
        else {$permanent=$status>=400&&$status<500&&$status!==429;$delay=max(min(3600,max(0,(int)($result['retry_after']??0))),min(3600,30*(2**$attempt)));$changes=['status'=>$permanent||$attempt>=5?'failed':'pending','attempts'=>$attempt,'last_status'=>$status,'next_attempt_at'=>gmdate('Y-m-d H:i:s',time()+$delay)];}
        if ($status===429) {$changes['attempts']=(int)$row['attempts'];$changes['status']='pending';}
        $this->db->update('event_calendar_notifications',$changes,['id'=>$id]);return true;
    }
}
