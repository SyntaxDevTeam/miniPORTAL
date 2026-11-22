<?php
declare(strict_types=1);
namespace SyntaxDevTeam\Cms\Modules\Recruitment;
use PDO;
use InvalidArgumentException;
use SyntaxDevTeam\Cms\Core\Request;
use SyntaxDevTeam\Cms\Database\CrudApp;
final class RecruitmentRepository
{
    public function __construct(private readonly CrudApp $db, private readonly ?\Closure $notify=null) {}
    public function settings(): array { return $this->db->query('SELECT * FROM recruitment_settings WHERE id=1')->fetch(PDO::FETCH_ASSOC); }
    public function items(string $type, bool $active = false): array
    {
        $table = self::table($type);
        return $this->db->query('SELECT * FROM '.$table.($active ? ' WHERE active=1' : '').' ORDER BY sort_order,id')->fetchAll(PDO::FETCH_ASSOC);
    }
    private static function table(string $type): string { return match($type) { 'rank'=>'recruitment_ranks','question'=>'recruitment_questions',default=>throw new InvalidArgumentException('Nieznany typ.') }; }
    public function applications(?int $user, int $page, string $status = ''): array
    {
        $where = []; $params = [];
        if ($user !== null) { $where[]='user_id=:uid'; $params[':uid']=$user; }
        if ($status !== '') { $where[]='status=:status'; $params[':status']=$status; }
        return $this->db->query('SELECT id,user_id,rank_name,nickname,status,created_at FROM recruitment_applications'.($where ? ' WHERE '.implode(' AND ',$where) : '').' ORDER BY id DESC LIMIT 25 OFFSET '.(($page-1)*25),$params)->fetchAll(PDO::FETCH_ASSOC);
    }
    public function find(int $id): ?array { return $this->db->query('SELECT * FROM recruitment_applications WHERE id=:id',[':id'=>$id])->fetch(PDO::FETCH_ASSOC) ?: null; }
    public function history(int $id): array { return $this->db->query('SELECT * FROM recruitment_history WHERE application_id=:id ORDER BY id',[':id'=>$id])->fetchAll(PDO::FETCH_ASSOC); }
    private function transaction(callable $fn): mixed
    {
        $pdo=$this->db->connection()->pdo; $pdo->beginTransaction();
        try { $s=$this->db->query('SELECT * FROM recruitment_settings WHERE id=1'.($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':''))->fetch(PDO::FETCH_ASSOC); $v=$fn($s); $pdo->commit(); return $v; }
        catch (\Throwable $e) { $pdo->rollBack(); throw $e; }
    }
    public function submit(Request $r, int $user): int
    {
        return $this->transaction(function(array $s) use($r,$user): int {
            if (!$s['is_open']) throw new InvalidArgumentException('Rekrutacja jest zamknięta.');
            if ($r->postInt('revision') !== (int)$s['revision']) throw new InvalidArgumentException('Formularz zmienił się. Odśwież stronę przed wysłaniem.');
            $last=$this->db->query('SELECT status,created_at FROM recruitment_applications WHERE user_id=:uid ORDER BY id DESC LIMIT 1',[':uid'=>$user])->fetch(PDO::FETCH_ASSOC);
            if ($last && (in_array($last['status'],['new','review','interview'],true) || strtotime($last['created_at'].' UTC') + (int)$s['cooldown_days']*86400 > time())) throw new InvalidArgumentException('Masz aktywne podanie lub nie upłynął okres między zgłoszeniami: '.$s['cooldown_days'].' dni.');
            $ranks=array_column($this->items('rank',true),null,'id'); $rank=$ranks[$r->postInt('rank_id',0)]??null;
            if (!$rank) throw new InvalidArgumentException('Wybierz dostępną rangę.');
            if (!$r->postBool('acknowledged')) throw new InvalidArgumentException('Potwierdź zasady przetwarzania podania.');
            $id=(int)$this->db->create('recruitment_applications',['user_id'=>$user,'rank_name'=>$rank['name'],'nickname'=>RecruitmentInput::text($r,'nickname',64),'answers'=>json_encode(RecruitmentInput::answers($r,$this->items('question',true)),JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),'feedback'=>'']);
            if ($this->notify) ($this->notify)($id,'new',1);
            return $id;
        });
    }
    public function configure(Request $r): void
    {
        $this->transaction(function(array $s) use($r): void {
            if ($r->postInt('revision') !== (int)$s['revision']) throw new InvalidArgumentException('Konfiguracja została zmieniona przez inną osobę. Odśwież stronę.');
            $type=$r->postString('kind');
            if ($type==='settings') {
                $days=$r->postInt('cooldown_days'); if ($days===null || $days<1 || $days>365) throw new InvalidArgumentException('Odstęp: od 1 do 365 dni.');
                $this->db->update('recruitment_settings',['title'=>RecruitmentInput::text($r,'title',120),'intro'=>RecruitmentInput::text($r,'intro',1000),'is_open'=>(int)$r->postBool('is_open'),'cooldown_days'=>$days],['id'=>1]);
            } else {
                $table=self::table($type); $order=$r->postInt('sort_order'); if ($order===null || $order<0 || $order>65535) throw new InvalidArgumentException('Kolejność: 0–65535.');
                $data=['active'=>(int)$r->postBool('active'),'sort_order'=>$order];
                if ($type==='rank') $data+=['name'=>RecruitmentInput::text($r,'name',100),'description'=>RecruitmentInput::text($r,'description',1000,false)];
                else {
                    $fieldType=$r->postString('type'); if (!in_array($fieldType,['text','textarea','scale'],true)) throw new InvalidArgumentException('Nieznany rodzaj pytania.');
                    $data+=['label'=>RecruitmentInput::text($r,'label',300),'help'=>RecruitmentInput::text($r,'help',1000,false),'type'=>$fieldType,'required'=>(int)$r->postBool('required')];
                }
                $id=$r->postInt('id',0); if ($id===null || $id<0) throw new InvalidArgumentException('Nieprawidłowy wpis.');
                if ($id>0) { if (!$this->db->count($table,['id'=>$id])) throw new InvalidArgumentException('Wpis nie istnieje.'); $this->db->update($table,$data,['id'=>$id]); }
                else $this->db->create($table,$data);
            }
            $this->db->query('UPDATE recruitment_settings SET revision=revision+1 WHERE id=1');
        });
    }
    public function review(Request $r, int $actor, bool $ownOnly): void
    {
        $this->transaction(function() use($r,$actor,$ownOnly): void {
            $a=$this->find($r->postInt('id',0)??0);
            if (!$a || ($ownOnly && (int)$a['user_id']!==$actor)) throw new InvalidArgumentException('Podanie nie jest dostępne.');
            if ($r->postInt('revision') !== (int)$a['revision']) throw new InvalidArgumentException('Podanie zmieniło się. Odśwież stronę.');
            $status=$ownOnly?'withdrawn':$r->postString('status');
            if (!isset(RecruitmentInput::statuses()[$status])) throw new InvalidArgumentException('Nieprawidłowy status.');
            if ($ownOnly && !in_array($a['status'],['new','review','interview'],true)) throw new InvalidArgumentException('Podanie jest już zakończone.');
            $note=$ownOnly?'Wycofane przez kandydata.':RecruitmentInput::text($r,'note',4000,false);
            $feedback=$ownOnly?$a['feedback']:RecruitmentInput::text($r,'feedback',4000,false);
            $this->db->update('recruitment_applications',['status'=>$status,'feedback'=>$feedback,'revision'=>(int)$a['revision']+1],['id'=>$a['id']]);
            $this->db->create('recruitment_history',['application_id'=>$a['id'],'actor_id'=>$actor,'status'=>$status,'note'=>$note]);
            if ($this->notify && $status!==$a['status']) ($this->notify)((int)$a['id'],$status,(int)$a['revision']+1);
        });
    }
}
