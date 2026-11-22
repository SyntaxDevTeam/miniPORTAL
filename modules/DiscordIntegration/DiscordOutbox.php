<?php

declare(strict_types=1);
namespace SyntaxDevTeam\Cms\Modules\DiscordIntegration;
use SyntaxDevTeam\Cms\Core\ModuleStateRepository;
use SyntaxDevTeam\Cms\Database\CrudApp;
final class DiscordOutbox
{
    public function __construct(private readonly CrudApp $db,private readonly DiscordHub $hub) {}
    /** Called inside the source transaction: either the domain write and notification persist, or neither. */
    public function enqueue(string $key,string $event,array $payload): bool
    {
        if (!$this->hub->ready($key)) return false;
        $this->db->create('discord_integration_outbox',['created_at'=>gmdate('Y-m-d H:i:s'),'next_attempt_at'=>gmdate('Y-m-d H:i:s'),'event_key'=>$event,'route_key'=>$key,'destination'=>$this->hub->destination($key),'payload'=>json_encode($payload,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)]);
        return true;
    }
    public static function recruitment(CrudApp $db,string $root,int $id,string $status,int $revision,string $base): void
    {
        $hub=DiscordHub::local($root);if (!$hub || !$hub->ready('recruitment.applications')) return;
        if (parse_url($base,PHP_URL_SCHEME)!=='https' || filter_var($base,FILTER_VALIDATE_URL)===false) return;
        $labels=\SyntaxDevTeam\Cms\Modules\Recruitment\RecruitmentInput::statuses();
        (new self($db,$hub))->enqueue('recruitment.applications','recruitment:'.$id.':'.$revision,[
            'content'=>'Podanie #'.$id.' · '.($labels[$status]??$status)."\n".rtrim($base,'/').'/admin/recruitment/application?id='.$id,
            'allowed_mentions'=>['parse'=>[]],
        ]);
    }
    public function workOne(): bool
    {
        $r=$this->db->query("SELECT * FROM discord_integration_outbox WHERE status='pending' AND next_attempt_at<=:now ORDER BY id LIMIT 1",[':now'=>gmdate('Y-m-d H:i:s')])->fetch(\PDO::FETCH_ASSOC);
        if (!$r) return false;
        $source=explode('.',$r['route_key'])[0];
        if (!(new ModuleStateRepository($this->db))->find($source)?->isActive() || !$this->hub->ready($r['route_key']) || !hash_equals($r['destination'],$this->hub->destination($r['route_key']))) {
            $this->db->update('discord_integration_outbox',['status'=>'skipped'],['id'=>$r['id']]);return true;
        }
        // Mark before I/O. A crash/timeout is uncertain and must not duplicate a private notification.
        $claim=$this->db->update('discord_integration_outbox',['status'=>'uncertain','attempts'=>(int)$r['attempts']+1],['id'=>$r['id'],'status'=>'pending']);
        if (!$claim || $claim->rowCount()!==1) return true;
        try {$result=$this->hub->send($r['destination'],json_decode($r['payload'],true,32,JSON_THROW_ON_ERROR));}catch (\Throwable) {$result=['status'=>0];}
        $http=(int)($result['status']??0);$status=$http>=200 && $http<300?'sent':($http===0?'uncertain':'failed');
        $changes=['status'=>$status,'last_status'=>$http];
        if ($http===429) $changes+=['next_attempt_at'=>gmdate('Y-m-d H:i:s',time()+max(2,(int)($result['retry_after']??30)))];
        if (isset($changes['next_attempt_at'])) {$changes['status']='pending';$changes['attempts']=(int)$r['attempts'];}
        $this->db->update('discord_integration_outbox',$changes,['id'=>$r['id']]);return true;
    }
}
