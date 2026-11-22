<?php

declare(strict_types=1);
namespace SyntaxDevTeam\Cms\Modules\EventCalendar;

use PDO;
use InvalidArgumentException;
use SyntaxDevTeam\Cms\Database\CrudApp;

final class EventRepository
{
    public function __construct(private readonly CrudApp $db,private readonly ?EventNotificationConfig $notifications=null) {}
    public function find(int $id): ?array
    {
        $row=$this->db->query('SELECT * FROM event_calendar_events WHERE id=:id',[':id'=>$id])->fetch(PDO::FETCH_ASSOC);
        return is_array($row)?$row:null;
    }
    public function users(): array
    {
        $result=[];
        foreach($this->db->query("SELECT id,display_name FROM users WHERE status='active' ORDER BY display_name,id")->fetchAll(PDO::FETCH_ASSOC) as $u) $result[(int)$u['id']]=$u['display_name'];
        return $result;
    }
    public function publicPage(string $view, string $now, int $page=1, ?string $from=null, ?string $to=null): array
    {
        // Explicit projection: private plans, tasks, authors and history never leave this query.
        $where='published_payload IS NOT NULL';$params=[];
        if($view==='archive') {$where.=' AND public_end<=:now';$params[':now']=$now;}
        elseif($view==='month') {$where.=' AND public_start<:end AND public_end>:start';$params=[':start'=>$from,':end'=>$to];}
        else {$where.=' AND public_end>:now';$params[':now']=$now;}
        $total=(int)$this->db->query('SELECT COUNT(*) FROM event_calendar_events WHERE '.$where,$params)->fetchColumn();
        $size=$view==='month'?500:24;$pages=max(1,(int)ceil($total/$size));$page=max(1,min($page,$pages));
        $order=$view==='archive'?'DESC':'ASC';
        $rows=$this->db->query('SELECT id,published_payload FROM event_calendar_events WHERE '.$where.' ORDER BY public_start '.$order.',id '.$order.' LIMIT '.$size.' OFFSET '.(($page-1)*$size),$params)->fetchAll(PDO::FETCH_ASSOC);
        return ['events'=>array_map(static fn($r)=>['id'=>(int)$r['id']]+json_decode($r['published_payload'],true,32,JSON_THROW_ON_ERROR),$rows),'page'=>$page,'pages'=>$pages,'total'=>$total];
    }
    public function publicEvent(int $id): ?array
    {
        $row=$this->db->query('SELECT id,published_payload FROM event_calendar_events WHERE id=:id AND published_payload IS NOT NULL',[':id'=>$id])->fetch(PDO::FETCH_ASSOC);
        return $row?['id'=>(int)$row['id']]+json_decode($row['published_payload'],true,32,JSON_THROW_ON_ERROR):null;
    }
    public function notice(string $now): ?array
    {
        $queries=[
            ["public_start<=:start AND public_end>:end",[':start'=>$now,':end'=>$now],'public_end ASC,id ASC'],
            ["public_start>:now",[':now'=>$now],'public_start ASC,id ASC'],
            ["public_end<=:now",[':now'=>$now],'public_end DESC,id DESC'],
        ];
        foreach($queries as [$where,$params,$order]){
            $r=$this->db->query('SELECT id,published_payload FROM event_calendar_events WHERE published_payload IS NOT NULL AND '.$where.' ORDER BY '.$order.' LIMIT 1',$params)->fetch(PDO::FETCH_ASSOC);
            if($r)return ['id'=>(int)$r['id']]+json_decode($r['published_payload'],true,32,JSON_THROW_ON_ERROR);
        }
        return null;
    }
    public function adminPage(array $f, int $actor): array
    {
        $where=['1=1'];$params=[];
        if(($f['kind']??'')!=='') {$where[]='e.entry_kind=:kind';$params[':kind']=$f['kind'];}
        if($f['state']!=='') {$where[]='e.state=:state';$params[':state']=$f['state'];}
        if($f['q']!=='') {$where[]="e.title LIKE :q ESCAPE '!'";$params[':q']='%'.strtr($f['q'],['!'=>'!!','%'=>'!%','_'=>'!_']).'%';}
        if($f['mine']) {$where[]='(e.owner_id=:owner OR EXISTS (SELECT 1 FROM event_calendar_tasks t WHERE t.event_id=e.id AND t.assignee_id=:assignee))';$params[':owner']=$params[':assignee']=$actor;}
        $from=' FROM event_calendar_events e WHERE '.implode(' AND ',$where);
        $total=(int)$this->db->query('SELECT COUNT(*)'.$from,$params)->fetchColumn();$pages=max(1,(int)ceil($total/25));$page=max(1,min($f['page'],$pages));
        $events=$this->db->query('SELECT e.*, (SELECT COUNT(*) FROM event_calendar_tasks t WHERE t.event_id=e.id) AS task_count, (SELECT COUNT(*) FROM event_calendar_tasks t WHERE t.event_id=e.id AND t.status=\'done\') AS task_done'.$from.' ORDER BY e.updated_at DESC,e.id DESC LIMIT 25 OFFSET '.(($page-1)*25),$params)->fetchAll(PDO::FETCH_ASSOC);
        return compact('events','page','pages','total');
    }
    public function tasks(int $id): array {return $this->db->query('SELECT * FROM event_calendar_tasks WHERE event_id=:id ORDER BY CASE WHEN status=\'done\' THEN 1 ELSE 0 END,due_at,id',[':id'=>$id])->fetchAll(PDO::FETCH_ASSOC);}
    public function history(int $id): array {return $this->db->query("SELECT h.*,COALESCE(u.display_name,'Usunięte konto') AS actor FROM event_calendar_history h LEFT JOIN users u ON u.id=h.actor_id WHERE h.event_id=:id ORDER BY h.id DESC LIMIT 100",[':id'=>$id])->fetchAll(PDO::FETCH_ASSOC);}
    public function mutate(int $id,int $revision,int $actor,string $action,array $data,callable $audit): int
    {
        $pdo=$this->db->connection()->pdo;$pdo->beginTransaction();
        try {
            $old=$id>0?$this->find($id):null;
            if($id>0) {
                $changed=$this->db->update('event_calendar_events',['revision'=>$revision+1,'updated_at'=>gmdate('Y-m-d H:i:s')],['id'=>$id,'revision'=>$revision]);
                if(!$changed || $changed->rowCount()!==1) throw new EventConflict('Wydarzenie zmieniło się w innej karcie. Odśwież stronę przed ponownym zapisem.');
            } elseif(!in_array($action,['save','create'],true)) throw new InvalidArgumentException('Najpierw zapisz wydarzenie.');
            $publication=$data['_publication']??null;$initialTask=$data['_task']??null;
            unset($data['_publication'],$data['_task']);
            if(in_array($action,['save','create'],true)) {
                if($data['owner_id']!==null && !isset($this->users()[$data['owner_id']])) throw new InvalidArgumentException('Wybierz aktywnego koordynatora.');
                if($id===0) $id=(int)$this->db->create('event_calendar_events',$data+['revision'=>1,'created_by'=>$actor,'created_at'=>gmdate('Y-m-d H:i:s'),'updated_at'=>gmdate('Y-m-d H:i:s')]);
                else {
                    if(in_array($data['state'],['cancelled','archived'],true)) $data+=['published_payload'=>null,'public_start'=>null,'public_end'=>null,'scheduled_payload'=>null,'publish_at'=>null,'scheduled_by'=>null];
                    $this->db->update('event_calendar_events',$data,['id'=>$id]);
                }
                if($initialTask!==null) {
                    if($initialTask['assignee_id']!==null && !isset($this->users()[$initialTask['assignee_id']])) throw new InvalidArgumentException('Wybierz aktywną osobę odpowiedzialną.');
                    $taskId=(int)$this->db->create('event_calendar_tasks',$initialTask+['event_id'=>$id]);
                }
                if($publication!==null && $publication['mode']!=='private') $this->setPublication($id,$actor,$publication);
            } elseif($action==='schedule') {
                $this->setPublication($id,$actor,$data);
            } elseif($action==='cancel_schedule') {
                $this->db->update('event_calendar_events',['scheduled_payload'=>null,'publish_at'=>null,'scheduled_by'=>null],['id'=>$id]);
            } elseif($action==='release') {
                if(!$old['scheduled_payload'] || $old['publish_at']>($data['now']??gmdate('Y-m-d H:i:s'))) throw new InvalidArgumentException('Publikacja nie jest jeszcze gotowa.');
                if($old['entry_kind']!=='event' || in_array($old['state'],['cancelled','archived'],true))throw new InvalidArgumentException('Wpis wewnętrzny lub anulowany nie może być opublikowany.');
                $this->setPublication($id,$actor,['mode'=>'now','payload'=>$old['scheduled_payload']]);
            } elseif($action==='publish') {
                $mode=$data['discord_mode']??$old['discord_mode'];
                if(!in_array($mode,['now','later'],true))throw new InvalidArgumentException('Nieprawidłowy tryb publikacji Discord.');
                $this->db->update('event_calendar_events',['discord_mode'=>$mode],['id'=>$id]);
                $public=EventInput::publicData($old);
                $this->db->update('event_calendar_events',['published_payload'=>json_encode($public,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),'public_start'=>$public['starts_at'],'public_end'=>$public['ends_at'],'scheduled_payload'=>null,'publish_at'=>null,'scheduled_by'=>null],['id'=>$id]);
            } elseif($action==='discord') {
                if(!$this->notifications?->ready('public')) throw new InvalidArgumentException('Najpierw włącz publiczny kanał Discord w ustawieniach powiadomień.');
                if(!$this->publicEvent($id)) throw new InvalidArgumentException('Najpierw opublikuj wydarzenie na stronie.');
                $this->db->update('event_calendar_events',['discord_mode'=>'now'],['id'=>$id]);
            } elseif($action==='unpublish') $this->db->update('event_calendar_events',['published_payload'=>null,'public_start'=>null,'public_end'=>null,'scheduled_payload'=>null,'publish_at'=>null,'scheduled_by'=>null],['id'=>$id]);
            elseif($action==='task') {
                $taskId=(int)$data['task_id'];unset($data['task_id']);
                if($data['assignee_id']!==null && !isset($this->users()[$data['assignee_id']])) throw new InvalidArgumentException('Wybierz aktywną osobę.');
                if($taskId>0) {
                    if(!$this->db->count('event_calendar_tasks',['id'=>$taskId,'event_id'=>$id])) throw new InvalidArgumentException('Zadanie nie należy do wydarzenia.');
                    $this->db->update('event_calendar_tasks',$data,['id'=>$taskId,'event_id'=>$id]);
                } else {
                    if($this->db->count('event_calendar_tasks',['event_id'=>$id])>=100) throw new InvalidArgumentException('Limit: 100 zadań na wydarzenie.');
                    $taskId=(int)$this->db->create('event_calendar_tasks',$data+['event_id'=>$id]);
                }
            } elseif($action==='progress') {
                $where=['id'=>$data['task_id'],'event_id'=>$id];
                if(!$data['manager']) $where['assignee_id']=$actor;
                if(!$this->db->count('event_calendar_tasks',$where)) throw new InvalidArgumentException('Brak przypisania do zadania.');
                $this->db->update('event_calendar_tasks',['status'=>$data['status']],$where);
            } elseif($action==='delete_task') $this->db->delete('event_calendar_tasks',['id'=>$data['task_id'],'event_id'=>$id]);
            elseif($action!=='comment') throw new InvalidArgumentException('Nieznana operacja.');
            $body=$action==='comment'?$data['body']:($data['title']??'');
            $this->db->create('event_calendar_history',['event_id'=>$id,'actor_id'=>$actor,'action'=>$action,'body'=>$body,'created_at'=>gmdate('Y-m-d H:i:s')]);
            if($this->notifications!==null){$queue=new EventNotifications($this->db,$this->notifications);if(in_array($action,['publish','discord','release'],true) || ($action==='create' && ($publication['mode']??'')==='now')){$mode=$this->find($id)['discord_mode'];$queue->enqueue($id,null,$mode==='later'?'staff':'public',false,true);}if($action==='task' || $initialTask!==null)$queue->enqueue($id,$taskId,'staff');}
            $audit($id);$pdo->commit();return $id;
        } catch(\Throwable $e) {if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }
    private function setPublication(int $id,int $actor,array $publication): void
    {
        $public=json_decode($publication['payload'],true,32,JSON_THROW_ON_ERROR);
        $changes=$publication['mode']==='scheduled'
            ? ['scheduled_payload'=>$publication['payload'],'publish_at'=>$publication['at'],'scheduled_by'=>$actor]
            : ['published_payload'=>$publication['payload'],'public_start'=>$public['starts_at'],'public_end'=>$public['ends_at'],'scheduled_payload'=>null,'publish_at'=>null,'scheduled_by'=>null];
        $this->db->update('event_calendar_events',$changes,['id'=>$id]);
    }
    /** The dispatcher holds its existing database lock; CAS protects concurrent edits. */
    public function publishDue(string $now,callable $authorized,callable $audit): int
    {
        $rows=$this->db->query('SELECT id,revision,scheduled_by FROM event_calendar_events WHERE publish_at<=:now AND scheduled_payload IS NOT NULL ORDER BY publish_at,id LIMIT 50',[':now'=>$now])->fetchAll(PDO::FETCH_ASSOC);
        $count=0;
        foreach($rows as $row) {
            $actor=(int)$row['scheduled_by'];
            $allowed=$authorized($actor);
            try {
                $action=$allowed?'release':'cancel_schedule';
                $this->mutate((int)$row['id'],(int)$row['revision'],$actor,$action,['now'=>$now],fn($id)=>$audit($id,$actor,$action));
                if($allowed)$count++;
            } catch(EventConflict) { /* An editor changed or cancelled the schedule. Retry next batch. */ }
        }
        return $count;
    }

}
