<?php

declare(strict_types=1);
namespace SyntaxDevTeam\Cms\Modules\EventCalendar;

use InvalidArgumentException;
use SyntaxDevTeam\Cms\Core\{ThemeInterface,ScheduleThemeInterface,ScheduleNoticeThemeInterface,ModuleInterface,PublicNavigationProviderInterface,PublicNavigationRegistry,AdminMenuRegistry,Router,Request,Security,HookProviderInterface,HookRegistry};
use SyntaxDevTeam\Cms\Modules\CoreAuth\{AuthService,AuditLogService};

final class EventCalendarModule implements ModuleInterface,PublicNavigationProviderInterface,HookProviderInterface
{
    public function __construct(private readonly ThemeInterface $theme,private readonly AdminMenuRegistry $menu,private readonly EventRepository $events,private readonly AuthService $auth,private readonly Security $security,private readonly AuditLogService $audit,private readonly ?EventNotificationConfig $notificationConfig=null,private readonly ?\SyntaxDevTeam\Cms\Database\CrudApp $database=null) {}
    public function id(): string {return 'event_calendar';}
    public function version(): string {return '3.1.0';}
    public function dependencies(): array {return ['core_auth'];}
    public function isProtected(): bool {return false;}
    public function requiredPermissions(): array {return ['events.view','events.manage','events.publish'];}
    public function registerAdminMenu(AdminMenuRegistry $menu): void {$menu->add('Dedykowane','Kalendarz wydarzeń','/admin/events','EV','events.view',125);}
    public function registerPublicNavigation(PublicNavigationRegistry $nav): void {$nav->add('event_calendar.index','Wydarzenia','/events','main',65);}
    public function registerRoutes(Router $r): void
    {
        $r->get('/events/notice',fn()=>$this->notice());
        $r->get('/admin/events/settings',fn(Request $q)=>$this->settings($q));
        $r->post('/admin/events/settings',fn(Request $q)=>$this->settings($q));
        $r->get('/events',fn(Request $q)=>$this->listing($q));
        $r->get('/events/view',fn(Request $q)=>$this->detail($q));
        $r->get('/events/export',fn(Request $q)=>$this->detail($q,true));
        $r->get('/admin/events',fn(Request $q)=>$this->admin($q));
        $r->post('/admin/events',fn(Request $q)=>$this->admin($q));
    }
    public function registerHooks(HookRegistry $hooks): void
    {
        if($this->theme instanceof ScheduleNoticeThemeInterface)$this->theme->set_schedule_notice(true);
    }
    private function notice(): void
    {
        $this->noStore();if(!headers_sent())header('Content-Type: application/json; charset=utf-8');
        if($this->notificationConfig!==null && !$this->notificationConfig->settings()['pin_enabled']){echo '{"event":null}';return;}
        $source=$this->events->notice(gmdate('Y-m-d H:i:s'));
        $card=$source?EventPresenter::card($source,gmdate('Y-m-d H:i:s')):null;
        echo json_encode(['event'=>$card?array_intersect_key($card,array_flip(['id','title','summary','category','range','url','phase','phase_label','start_iso','end_iso'])):null],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_HEX_TAG);
    }
    private function requireLogin(Request $q): bool
    {
        if($this->auth->actualUser()!==null)return true;
        $next=$q->path()==='/admin/events/settings'?'/admin/events/settings':'/admin/events';
        $params=[];
        if($next==='/admin/events'){
            $id=$q->method()==='POST'?($q->postInt('id',0)??0):($q->queryInt('id',0)??0);
            if($id>0)$params['id']=$id;
            if($q->method()==='GET'){
                foreach(['task','page'] as $key){$value=$q->queryInt($key,0)??0;if($value>0)$params[$key]=$value;}
                foreach(['new','new_task','state','kind','q','mine','tab'] as $key){$value=mb_substr($q->queryString($key),0,180);if($value!=='')$params[$key]=$value;}
            }
        }
        if($params!==[])$next.='?'.http_build_query($params,'','&',PHP_QUERY_RFC3986);
        $login='/admin/login?next='.rawurlencode($next);
        if($q->method()==='GET'){
            if(!headers_sent())header('Location: '.$login,true,303);
            else http_response_code(303);
        }else{
            http_response_code(401);
            $this->theme->render_public_error(401,'Zaloguj się do kalendarza','Sesja wygasła lub nie jesteś zalogowany. Zaloguj się i ponownie sprawdź formularz przed zapisem.','Zaloguj się',$login);
        }
        return false;
    }
    private function settingsAllowed(): bool
    {
        $u=$this->auth->actualUser();return $u?->isActive() && $this->auth->rolePreview()===null && (in_array('owner',$u->roles,true)||in_array('administrator',$u->roles,true));
    }
    private function settings(Request $q): void
    {
        $this->noStore();if(!$this->requireLogin($q))return;if(!$this->settingsAllowed() || !$this->notificationConfig || !$this->database){$this->error(403,'Ustawienia są dostępne dla Ownera i systemowego Administratora.');return;}
        $u=$this->auth->actualUser();$config=$this->notificationConfig;$queue=new EventNotifications($this->database,$config);$message='';$variant='success';
        if($q->method()==='POST'){
            if(!$this->security->validateCsrfToken($q->postString('_token'))||$this->auth->rolePreviewBlocks($q)){$this->error(403,'Nieprawidłowy token CSRF lub podgląd roli.');return;}
            try{
                $action=$q->postString('action');
                if($action==='settings'){
                    $input=['pin_enabled'=>$q->postBool('pin_enabled')];foreach(['public','staff'] as $channel){$input[$channel.'_webhook']=$q->postString($channel.'_webhook');$input[$channel.'_enabled']=$q->postBool($channel.'_enabled');$input[$channel.'_clear']=$q->postBool($channel.'_clear');}
                    $config->save($input);$message='Zapisano ustawienia. Puste pola zachowują wcześniej zapisane webhooki.';
                }elseif(in_array($action,['test_public','test_staff'],true)){
                    $channel=$action==='test_public'?'public':'staff';
                    if(!$queue->enqueue(0,null,$channel,true))throw new InvalidArgumentException('Najpierw skonfiguruj i włącz kanał Discord.');
                    $message='Dodano fikcyjny komunikat TEST do kolejki. Worker wyśle go na wybrany kanał. Nie utworzono wydarzenia ani zadania.';
                }else throw new InvalidArgumentException('Nieznana operacja.');
                $this->audit->record($q,'event_calendar_'.$action,'success','event_calendar',$u->id);
                if(!headers_sent()){header('Location: /admin/events/settings?saved='.($action==='settings'?'1':'test'),true,303);}return;
            }catch(InvalidArgumentException $e){http_response_code(422);$message=$e->getMessage();$variant='warning';}
        }
        if($q->queryString('saved')!=='')$message=$q->queryString('saved')==='test'?'Dodano przykładowy komunikat TEST do kolejki. Stan dostarczenia sprawdzisz poniżej.':'Zapisano ustawienia powiadomień i pineski.';
        $s=$config->settings();
        $this->theme->start_admin_page('Powiadomienia wydarzeń',$this->menu->visibleFor($u->permissions),'/admin/events',['name'=>$u->displayName,'role'=>$u->primaryRole(),'initials'=>$u->initials(),'avatar_url'=>$u->avatarUrl??'']);
        $this->theme->start_admin_content('Powiadomienia i pineska','Dwa niezależne kanały Discord oraz informacja o wydarzeniu na stronach portalu.');$this->theme->render_button('Wróć do kalendarza','/admin/events','outline-light');
        if($message!=='')$this->theme->render_alert($message,$variant);
        $fields=[['name'=>'action','type'=>'hidden','value'=>'settings'],['name'=>'pin_enabled','type'=>'checkbox','label'=>'Pineska na stronie głównej','checked'=>$s['pin_enabled'],'help'=>'Trwające wydarzenie, następnie najbliższe, a jeśli ich brak — ostatnie zakończone. Zwinięta na starcie.']];
        foreach(['public'=>'Publiczne wydarzenia','staff'=>'Zadania administracji'] as $channel=>$label){
            if ($config->centrallyManaged($channel)) {$this->theme->render_button($label.' — Integracja Discord','/admin/discord','secondary');continue;}
            $fields[]=['name'=>$channel.'_enabled','type'=>'checkbox','label'=>$label.' — włącz powiadomienia','checked'=>$s[$channel]['enabled']];
            $fields[]=['name'=>$channel.'_webhook','type'=>'password','label'=>$label.' — adres webhooka','value'=>'','autocomplete'=>'new-password','help'=>($s[$channel]['configured']?'Webhook zapisany. ':'Webhook nie jest ustawiony. ').'Puste pole zachowuje sekret. '.($channel==='staff'?'Wybierz osobny, prywatny kanał tylko dla administracji.':'Ogłoszenie wysyłane po publikacji lub zmianie publicznej zapowiedzi.')];
            $fields[]=['name'=>$channel.'_clear','type'=>'checkbox','label'=>'Usuń zapisany webhook: '.$label,'checked'=>false];
        }
        $this->theme->start_admin_panel('Kanały i widoczność');$this->theme->render_form('/admin/events/settings',$fields,'Zapisz ustawienia',$this->security->csrfToken());$this->theme->end_admin_panel();
        $this->theme->start_admin_panel('Testy komunikatów — tymczasowe przyciski');$this->theme->render_text('Przykłady mają fikcyjne dane i oznaczenie TEST. Trafiają do kolejki wybranego webhooka, bez tworzenia wydarzeń i zadań.');
        foreach(['public'=>'Wyślij przykładowy event','staff'=>'Wyślij przykładowe zadanie'] as $channel=>$label)$this->theme->render_form('/admin/events/settings',[['name'=>'action','type'=>'hidden','value'=>'test_'.$channel]],$label,$this->security->csrfToken());$this->theme->end_admin_panel();
        $rows=[];foreach($queue->recent() as $r)$rows[]=['cells'=>[$r['id'],$r['channel']==='public'?'Publiczny':'Administracja',$r['is_test']?'Przykład TEST':'#'.$r['event_id'],['pending'=>'W kolejce','sent'=>'Wysłano','failed'=>'Błąd dostarczenia','skipped'=>'Pominięto — dane lub kanał zmienione'][$r['status']]??$r['status'],$r['attempts'],$r['last_status']??'—'],'actions'=>[]];
        $this->theme->start_admin_panel('Ostatnie powiadomienia');$this->theme->render_admin_action_table(['ID','Kanał','Komunikat','Stan','Próby','HTTP'],$rows,$this->security->csrfToken());$this->theme->render_text('Wysyłkę realizuje worker kolejki. Zmiany webhooka, wycofanie publikacji i zmiana przypisania unieważniają starsze oczekujące komunikaty.');$this->theme->end_admin_panel();
        $this->theme->end_admin_content();$this->theme->end_admin_page();
    }
    private function allows(string $permission): bool {$u=$this->auth->user();return $u?->isActive() && (in_array('*',$u->permissions,true)||in_array($permission,$u->permissions,true));}
    private function noStore(): void {if(!headers_sent()){header('Cache-Control: private, no-store');header('Cloudflare-CDN-Cache-Control: no-store');}}
    private function error(int $code,string $message): void {http_response_code($code);$this->theme->render_public_error($code,'Kalendarz wydarzeń',$message,'Wydarzenia','/events');}
    private function schedule(array $model): void
    {
        if($this->theme instanceof ScheduleThemeInterface) {$this->theme->render_schedule($model);return;}
        if(isset($model['wizard']))$this->theme->render_form('/admin/events',$model['wizard']['fields'],'Utwórz wpis',$model['wizard']['token']);
        foreach($model['toolbar']??[] as $link)$this->theme->render_button($link['label'],$link['url']);
        foreach($model['groups']??[] as $label=>$events){$this->theme->render_section_heading($label);foreach($events as $e){$this->theme->start_card($e['title'],$e['range']);$this->theme->render_text($e['summary']);$this->theme->render_button('Szczegóły',$e['url']);$this->theme->end_card();}}
        if(isset($model['detail'])){$e=$model['detail'];$this->theme->start_card($e['title'],$e['range']);foreach(['description','rules','rewards'] as $key)$this->body($e[$key],$e['content_format']??'plain');$this->theme->end_card();}
    }
    private function body(string $content,string $format): void
    {
        if($format==='markdown')$this->theme->render_rich_content($content,'markdown');
        else $this->theme->render_text($content);
    }
    private function listing(Request $q): void
    {
        $this->noStore();$now=gmdate('Y-m-d H:i:s');$view=$q->queryString('view');if(!in_array($view,['month','archive'],true))$view='upcoming';
        $month=EventPresenter::month($q->queryString('month'));
        $page=$this->events->publicPage($view,$now,$q->queryInt('page',1)??1,$month['from'],$month['to']);
        $cards=array_map(static fn($e)=>EventPresenter::card($e,$now),$page['events']);$model=['hero'=>true,'view'=>$view,'featured'=>$view==='upcoming' && $page['page']===1?($cards[0]??null):null];
        if($view==='month') {
            $days=[];$date=$month['date'];$week=['pon.','wt.','śr.','czw.','pt.','sob.','niedz.'];
            for($i=0;$i<(int)$date->format('t');$i++){$day=$date->modify('+'.$i.' days');$start=$day->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');$end=$day->modify('+1 day')->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
                $days[]=['label'=>$week[(int)$day->format('N')-1].' '.$day->format('d.m'),'today'=>$day->format('Y-m-d')===(new \DateTimeImmutable('now',new \DateTimeZone('Europe/Warsaw')))->format('Y-m-d'),'events'=>array_values(array_filter($cards,static fn($e)=>$e['starts_at']<$end && $e['ends_at']>$start))];}
            $model+=array_intersect_key($month,array_flip(['month','previous','next']))+['days'=>$days];
        } elseif($view==='archive') $model['groups']=['Minione wydarzenia'=>$cards];
        else {$model['groups']=['Właśnie trwają'=>[],'Najbliższe i nadchodzące'=>[]];foreach($cards as $c)$model['groups'][$c['phase']==='live'?'Właśnie trwają':'Najbliższe i nadchodzące'][]=$c;}
        $this->theme->start_page('Kalendarz wydarzeń','Nadchodzące i trwające wydarzenia na serwerze.');$this->schedule($model);
        if($page['pages']>1){$base='/events?view='.$view.'&month='.urlencode($q->queryString('month')).'&page=';if($page['page']>1)$this->theme->render_button('Poprzednia strona',$base.($page['page']-1));if($page['page']<$page['pages'])$this->theme->render_button('Następna strona',$base.($page['page']+1));}
        $this->theme->end_page();
    }
    private function detail(Request $q,bool $export=false): void
    {
        $this->noStore();$event=$this->events->publicEvent($q->queryInt('id',0)??0);if(!$event){$this->error(404,'Nie znaleziono opublikowanego wydarzenia.');return;}
        if($export){if(!headers_sent()){header('Content-Type: text/calendar; charset=utf-8');header('Content-Disposition: attachment; filename="event-'.$event['id'].'.ics"');header('X-Content-Type-Options: nosniff');}echo EventPresenter::ics($event);return;}
        $this->theme->start_page($event['public_title'],$event['summary']);$this->schedule(['public_detail'=>true,'detail'=>EventPresenter::card($event,gmdate('Y-m-d H:i:s'))]);$this->theme->end_page();
    }
    private function admin(Request $q): void
    {
        $this->noStore();if(!$this->requireLogin($q))return;if(!$this->allows('events.view')){$this->error(403,'Brak uprawnień do kalendarza zespołu.');return;}
        $user=$this->auth->user();$id=$q->method()==='POST'?($q->postInt('id',0)??0):($q->queryInt('id',0)??0);$event=$id>0?$this->events->find($id):null;
        if($id>0 && !$event){$this->error(404,'Nie znaleziono wydarzenia.');return;}
        $message='';$variant='warning';$submitted=null;
        if($q->method()==='POST') {
            if($this->auth->rolePreviewBlocks($q) || !$this->security->validateCsrfToken($q->postString('_token'))){$this->error(403,'Podgląd roli lub nieprawidłowy token CSRF blokuje zapis.');return;}
            $action=$q->postString('action');$manage=$this->allows('events.manage');$publish=$this->allows('events.publish');
            $permitted=match($action){'publish','unpublish','discord','schedule','cancel_schedule'=>$publish,'save','create','task','delete_task'=>$manage,'progress','comment'=>true,default=>false};
            if(!$permitted){$this->error(403,'Brak uprawnienia do tej operacji.');return;}
            try {
                $data=[];
                if(in_array($action,['save','create'],true)) {
                    $data=EventInput::event($q,$event);$submitted=$data;
                    if($action==='create') {
                        if($id!==0)throw new InvalidArgumentException('Kreator tworzy wyłącznie nowe wpisy.');
                        $publication=EventInput::publication($q,$data);
                        if($publication['mode']!=='private' && !$publish){$this->error(403,'Publikowanie i planowanie publikacji wymaga events.publish.');return;}
                        $data['_publication']=$publication;
                        if($data['entry_kind']==='task') {
                            $state=$q->postString('task_status','todo');if(!isset(EventInput::TASKS[$state]))throw new InvalidArgumentException('Wybierz stan zadania.');
                            $data['_task']=['title'=>$data['title'],'description'=>$data['plan'],'assignee_id'=>$q->postInt('assignee_id',0)?:null,'due_at'=>EventInput::date($q->postString('due_at'),false),'status'=>$state];
                        }
                    }
                    if($event && $event['scheduled_payload']!==null && in_array($data['state'],['cancelled','archived'],true) && !$publish){$this->error(403,'Anulowanie zaplanowanej publikacji wymaga events.publish.');return;}
                    if($event && $event['published_payload']!==null && in_array($data['state'],['cancelled','archived'],true) && !$publish){$this->error(403,'Wycofanie publicznego wydarzenia wymaga events.publish.');return;}
                } elseif($action==='schedule') {
                    if(!$event)throw new InvalidArgumentException('Najpierw zapisz wydarzenie.');
                    $data=EventInput::publication($q,$event);
                    if($data['mode']!=='scheduled')throw new InvalidArgumentException('Wybierz automatyczną publikację.');
                } elseif($action==='publish') {
                    $mode=$q->postString('discord_mode',$event['discord_mode']??'now');
                    if(!in_array($mode,['now','later'],true))throw new InvalidArgumentException('Wybierz termin publikacji Discord.');
                    $data=['discord_mode'=>$mode];
                } elseif($action==='task') {
                    $state=$q->postString('task_status');if(!isset(EventInput::TASKS[$state]))throw new InvalidArgumentException('Wybierz status zadania.');
                    $data=['task_id'=>$q->postInt('task_id',0)??0,'title'=>EventInput::text($q->postString('task_title'),180,true),'description'=>($event['content_format']??'plain')==='markdown'?EventInput::editorText($q,'task_description',12000):EventInput::text($q->postString('task_description'),12000),'status'=>$state,'assignee_id'=>$q->postInt('assignee_id',0)?:null,'due_at'=>EventInput::date($q->postString('due_at'),false)];
                } elseif($action==='progress') {
                    $state=$q->postString('task_status');if(!isset(EventInput::TASKS[$state]))throw new InvalidArgumentException('Wybierz status zadania.');
                    $data=['task_id'=>$q->postInt('task_id',0)??0,'status'=>$state,'manager'=>$manage];
                } elseif($action==='delete_task') $data=['task_id'=>$q->postInt('task_id',0)??0];
                elseif($action==='comment')$data=['body'=>($event['content_format']??'plain')==='markdown'?EventInput::text(EventInput::editorText($q,'body',6000),6000,true):EventInput::text($q->postString('body'),6000,true)];
                $id=$this->events->mutate($id,$q->postInt('revision',0)??0,$user->id,$action,$data,fn($eid)=>$this->audit->record($q,'event_calendar_'.$action,'success','event:'.$eid,$user->id));
                if(!headers_sent()){header('Location: /admin/events?id='.$id.'&tab='.match($action){'create'=>'overview','save'=>$q->postString('section','basics'),'task','progress','delete_task'=>'tasks','comment'=>'discussion',default=>'publication'},true,303);}return;
            } catch(EventConflict $e) {http_response_code(409);$message=$e->getMessage();}
            catch(InvalidArgumentException $e) {http_response_code(422);$message=$e->getMessage();}
        }
        $this->theme->start_admin_page('Kalendarz wydarzeń',$this->menu->visibleFor($user->permissions),'/admin/events',['name'=>$user->displayName,'role'=>$user->primaryRole(),'initials'=>$user->initials(),'avatar_url'=>$user->avatarUrl??'']);
        $this->theme->start_admin_content('Kalendarz wydarzeń','Od pierwszego pomysłu do wspólnego wydarzenia. Notatki i zadania są dostępne wyłącznie zespołowi.');
        $tools=[['label'=>'Lista wydarzeń','url'=>'/admin/events'],['label'=>'Widok gracza','url'=>'/events']];
        if($this->allows('events.manage'))$tools[]=['label'=>'Dodaj wpis · kreator','url'=>'/admin/events?new=1'];
        if($this->settingsAllowed())$tools[]=['label'=>'Powiadomienia i pineska','url'=>'/admin/events/settings'];
        $this->schedule(['toolbar'=>$tools]);
        if($message!=='')$this->theme->render_alert($message,$variant);
        if($event || $q->queryString('new')==='1' || $q->method()==='POST') $this->editor($event,$submitted,$q);
        else $this->adminList($q,$user->id);
        $this->theme->end_admin_content();$this->theme->end_admin_page();
    }
    private function form(array $fields,string $label,int $id,int $revision,string $action): void
    {
        $this->theme->render_form('/admin/events',array_merge([
            ['name'=>'id','type'=>'hidden','value'=>(string)$id],['name'=>'revision','type'=>'hidden','value'=>(string)$revision],['name'=>'action','type'=>'hidden','value'=>$action]],$fields),$label,$this->security->csrfToken());
    }
    private function adminList(Request $q,int $actor): void
    {
        $filters=['kind'=>$q->queryString('kind'),'state'=>$q->queryString('state'),'q'=>mb_substr($q->queryString('q'),0,180),'mine'=>$q->queryString('mine')==='1','page'=>$q->queryInt('page',1)??1];
        $this->theme->render_form('/admin/events',[
            ['name'=>'q','label'=>'Tytuł','value'=>$filters['q']],['name'=>'kind','type'=>'select','label'=>'Rodzaj wpisu','options'=>[''=>'Wszystkie']+EventInput::KINDS,'value'=>$filters['kind']],['name'=>'state','type'=>'select','label'=>'Stan','options'=>[''=>'Wszystkie']+EventInput::STATES,'value'=>$filters['state']],['name'=>'mine','type'=>'select','label'=>'Zakres','options'=>['0'=>'Wszystkie','1'=>'Moja koordynacja i zadania'],'value'=>$filters['mine']?'1':'0']], 'Filtruj', '', 'get');
        $page=$this->events->adminPage($filters,$actor);$rows=[];$queue=$this->database && $this->notificationConfig?new EventNotifications($this->database,$this->notificationConfig):null;$users=$this->events->users();
        foreach($page['events'] as $e)$rows[]=['cells'=>[$e['title'],EventInput::KINDS[$e['entry_kind']],EventInput::STATES[$e['state']],($e['published_payload']!==null?'Opublikowane':'Tylko administracja').($e['publish_at']?' · Publikacja: '.str_replace('T',' ',EventInput::local($e['publish_at'])):''),$queue?->publicationStatus((int)$e['id'])??'Discord nie skonfigurowany',str_replace('T',' ',EventInput::local($e['starts_at']))?:'Do ustalenia',$users[$e['owner_id']]??'Nieprzypisany',$e['task_done'].' / '.$e['task_count']],
            'actions'=>[['label'=>'Otwórz wpis','href'=>'/admin/events?id='.$e['id']]]];
        $this->theme->start_admin_panel('Wydarzenia i pomysły · zadania · '.$page['total']);
        if(!$rows)$this->theme->render_alert('Brak wydarzeń pasujących do filtrów. Zacznij od nowego pomysłu.','info');else $this->theme->render_admin_action_table(['Wpis','Rodzaj','Przygotowanie','Publikacja','Discord','Początek (Warszawa)','Koordynator','Zadania'],$rows,$this->security->csrfToken());
        $base='/admin/events?'.http_build_query(array_diff_key($filters,['page'=>true])).'&page=';
        if($page['page']>1)$this->theme->render_button('Poprzednia',$base.($page['page']-1));if($page['page']<$page['pages'])$this->theme->render_button('Następna',$base.($page['page']+1));$this->theme->end_admin_panel();
    }
    private function publicationFields(bool $scheduleOnly=false): array
    {
        return [
            ['name'=>'publication_mode','type'=>$scheduleOnly?'hidden':'select','label'=>'Kiedy gracze zobaczą wydarzenie?','value'=>$scheduleOnly?'scheduled':'private','options'=>['private'=>'Tylko administracja — decyzję podejmę później','now'=>'Opublikuj od razu po utworzeniu','scheduled'=>'Zaplanuj automatyczną publikację'],'help'=>'Zapis wewnętrzny niczego nie ogłasza. Przykład: przygotuj turniej dzisiaj, ale pokaż go graczom dopiero tydzień przed startem.'],
            ['name'=>'publication_timing','type'=>'select','label'=>'Sposób ustalenia terminu','value'=>'days','options'=>['days'=>'Określoną liczbę dni przed startem','date'=>'Dokładna data i godzina'],'help'=>'Dni liczymy według kalendarza Europe/Warsaw, o tej samej godzinie co start. Przykład: start 20 grudnia o 18:00, 7 dni wcześniej → 13 grudnia o 18:00.'],
            ['name'=>'publication_days','type'=>'number','label'=>'Ile dni przed wydarzeniem?','value'=>'7','help'=>'Od 1 do 365. Tydzień to 7 dni, dwa tygodnie to 14. Obliczony termin musi być w przyszłości.'],
            ['name'=>'publication_date','type'=>'datetime-local','label'=>'Dokładny termin publikacji · Europe/Warsaw','help'=>'Wybierz przy opcji dokładnej daty. Musi wypadać przed startem wydarzenia.'],
        ];
    }
    private function wizard(Request $q): void
    {
        $fields=[
            ['name'=>'entry_kind','type'=>'select','label'=>'Co chcesz dodać?','value'=>'event','options'=>EventInput::KINDS,'help'=>'Wydarzenie: np. sobotni turniej dla graczy. Zadanie: np. przygotowanie areny przez moderatora. Plan: np. pomysł na zimowy sezon, bez ogłoszenia i bez ustalonej daty.'],
            ['name'=>'title','label'=>'Nazwa wpisu dla administracji','required'=>true,'maxlength'=>180,'help'=>'Krótko opisz cel, np. „Turniej grudniowy”, „Sprawdź nagrody” albo „Pomysły na nowy sezon”. Ta nazwa jest wewnętrzna.'],
            ['name'=>'owner_id','type'=>'select','label'=>'Koordynator wpisu','options'=>[0=>'Przypiszę później']+$this->events->users(),'help'=>'Osoba pilnująca całości. Przypisanie nie przyznaje uprawnień do modułu.'],
            ['name'=>'priority','type'=>'select','label'=>'Priorytet','value'=>'normal','options'=>['low'=>'Niski','normal'=>'Zwykły','high'=>'Wysoki','urgent'=>'Pilny'],'help'=>'Pilny wybierz dla spraw wymagających szybkiej reakcji, np. naprawy błędu przed eventem.'],
            ['name'=>'starts_at','type'=>'datetime-local','label'=>'Początek wydarzenia · Europe/Warsaw','help'=>'Dla wydarzenia podaj początek i koniec albo pozostaw oba puste jako szkic. Plan może nie mieć jeszcze daty.'],
            ['name'=>'ends_at','type'=>'datetime-local','label'=>'Koniec wydarzenia · Europe/Warsaw','help'=>'Musi być późniejszy niż początek. Przykład: sobota 18:00–20:00.'],
            ['name'=>'assignee_id','type'=>'select','label'=>'Osoba odpowiedzialna za zadanie','options'=>[0=>'Przypiszę później']+$this->events->users(),'help'=>'Ta osoba zobaczy zadanie w swoich przypisaniach i zmieni jego postęp, jeżeli ma events.view.'],
            ['name'=>'due_at','type'=>'datetime-local','label'=>'Termin wykonania zadania · Europe/Warsaw','help'=>'Opcjonalny termin ukończenia, np. piątek o 20:00 przed sobotnim turniejem.'],
            ['name'=>'task_status','type'=>'select','label'=>'Postęp zadania','value'=>'todo','options'=>EventInput::TASKS,'help'=>'„Zablokowane” oznacza przeszkodę, np. brak mapy. „Gotowe” oznacza zakończenie pracy.'],
            ['name'=>'plan','type'=>'textarea','rows'=>5,'maxlength'=>40000,'label'=>'Opis wewnętrzny / kryteria ukończenia','help'=>'Widoczny tylko zespołowi. Przykład zadania: „Przygotuj 3 nagrody i sprawdź odbiór na koncie testowym”. Możesz użyć Markdown.'],
            ['name'=>'public_title','label'=>'Tytuł dla graczy','maxlength'=>180,'help'=>'Nazwa na stronie, np. „Zimowy turniej skoków”. Wymagana przy publikacji.'],
            ['name'=>'summary','type'=>'textarea','rows'=>2,'maxlength'=>600,'label'=>'Krótka zapowiedź dla graczy','help'=>'Opcjonalne 1–2 zdania na liście wydarzeń, np. „Dołącz w sobotę i zawalcz o zimowe nagrody!”.'],
            ['name'=>'description','type'=>'textarea','rows'=>6,'maxlength'=>30000,'label'=>'Opis dla graczy','help'=>'Przy publikacji wymagany. Wyjaśnij, co się odbędzie, dla kogo i jak dołączyć. Obsługuje Markdown, np. **Ważne** i listy.'],
            ['name'=>'location','label'=>'Miejsce / komenda dołączenia','maxlength'=>180,'help'=>'Np. „Arena zimowa, /warp event”. Nie wpisuj prywatnych adresów ani danych dostępowych.'],
        ];
        $fields=array_merge($fields,$this->allows('events.publish')?$this->publicationFields():[['name'=>'publication_mode','type'=>'hidden','value'=>'private']],[
            ['name'=>'discord_mode','type'=>'select','label'=>'Discord przy publikacji','value'=>'now','options'=>['now'=>'Ogłoś razem ze stroną','later'=>'Strona teraz, Discord później'],'help'=>'Działa po włączeniu kanałów w ustawieniach powiadomień. Wewnętrzny wpis niczego nie wysyła graczom.'],
            ['name'=>'state','type'=>'hidden','value'=>'planning'],['name'=>'category','type'=>'hidden','value'=>'Wydarzenie'],['name'=>'content_format','type'=>'hidden','value'=>'markdown'],
            ['name'=>'id','type'=>'hidden','value'=>'0'],['name'=>'revision','type'=>'hidden','value'=>'0'],['name'=>'action','type'=>'hidden','value'=>'create'],
        ]);
        if($q->method()==='POST')foreach($fields as &$field)if(!in_array($field['name'],['id','revision','action'],true))$field['value']=$q->postString($field['name'],(string)($field['value']??''));unset($field);
        $this->schedule(['workspace'=>['title'=>'Dodaj wpis','subtitle'=>'Wybierz cel. Kreator pokaże tylko potrzebne pola i wyjaśni, kto zobaczy wpis.','tabs'=>[],'id'=>0,'active'=>''],'wizard'=>['fields'=>$fields,'token'=>$this->security->csrfToken()]]);
    }
    private function editor(?array $event,?array $submitted,Request $q): void
    {
        if(!$event && $this->allows('events.manage')){$this->wizard($q);return;}
        $e=$submitted??$event??[];$id=(int)($event['id']??0);$revision=(int)($event['revision']??0);$users=[0=>'Nieprzypisany']+$this->events->users();$manage=$this->allows('events.manage');
        $tabs=['overview'=>'Przegląd'];
        if($manage)$tabs+=['basics'=>'Organizacja'];
        $tabs+=['plan'=>'Plan zespołu'];
        if($manage)$tabs+=['announcement'=>'Treść dla graczy'];
        $tabs+=['tasks'=>'Zadania','discussion'=>'Dyskusja i historia'];
        if(($event['entry_kind']??'event')==='event')$tabs+=['publication'=>'Publikacja'];
        else unset($tabs['announcement']);
        $tab=$q->queryString('tab',$id?'overview':'basics');
        if($q->queryInt('task',0))$tab='tasks';
        if($q->method()==='POST')$tab=match($q->postString('action')){'create'=>'overview','save'=>$q->postString('section','basics'),'task','progress','delete_task'=>'tasks','comment'=>'discussion',default=>'publication'};
        if(!isset($tabs[$tab]))$tab='overview';
        if(!$id)$tab='basics';
        $this->schedule(['workspace'=>['title'=>$event['title']??'Nowe wydarzenie','subtitle'=>$event?EventInput::KINDS[$event['entry_kind']].' · '.EventInput::STATES[$event['state']].' · '.($event['published_payload']!==null?'Zapowiedź opublikowana':'Widoczne tylko zespołowi'):'Zacznij od nazwy i organizacji. Treść oraz zadania dodasz po zapisaniu.', 'tabs'=>$id?$tabs:[], 'active'=>$tab,'id'=>$id]]);
        if($manage && isset(EventInput::SECTIONS[$tab])) {
            $fields=[['name'=>'section','type'=>'hidden','value'=>$tab]];
            foreach(['title'=>['Nazwa wewnętrzna','text',180],'discord_mode'=>['Ogłoszenie na Discordzie przy publikacji','select',0],'content_format'=>['Format opisów i planów','select',0],'state'=>['Stan przygotowania','select',0],'priority'=>['Priorytet','select',0],'owner_id'=>['Koordynator','select',0],'category'=>['Rodzaj wydarzenia','text',80],'starts_at'=>['Początek · Europe/Warsaw','datetime-local',0],'ends_at'=>['Koniec · Europe/Warsaw','datetime-local',0],
                'plan'=>['Pomysły, scenariusz i plan realizacji (prywatne)','textarea',40000],'resources'=>['Zasoby, budżet i wymagania (prywatne)','textarea',16000],'risks'=>['Ryzyka i plan awaryjny (prywatne)','textarea',16000],
                'public_title'=>['Tytuł dla graczy','text',180],'summary'=>['Krótka zapowiedź dla graczy','textarea',600],'description'=>['Pełny opis dla graczy','textarea',30000],'location'=>['Miejsce / komenda dołączenia','text',180],'rules'=>['Zasady uczestnictwa','textarea',12000],'rewards'=>['Nagrody','textarea',6000]] as $key=>[$label,$type,$max]){
                if(!in_array($key,EventInput::SECTIONS[$tab],true))continue;
                if(($event['entry_kind']??'event')!=='event' && in_array($key,['discord_mode','category','starts_at','ends_at'],true))continue;
                $value=$e[$key]??match($key){'discord_mode'=>'now','content_format'=>$id?'plain':'markdown','state'=>'idea','priority'=>'normal','category'=>'Wydarzenie',default=>''};if(in_array($key,['starts_at','ends_at'],true))$value=EventInput::local($e[$key]??null);
                // Keep raw values on validation errors so the user can correct them.
                if($submitted===null && $q->method()==='POST' && $q->postString('action')==='save')$value=$q->postString($key);
                $format=$e['content_format']??($id?'plain':'markdown');
                if($type==='textarea' && $key!=='summary' && $format==='markdown')$type='richtext';
                $f=['rows'=>$key==='summary'?3:5,'format_value'=>$q->method()==='POST'?$q->postString($key.'_format','markdown'):'markdown','name'=>$key,'label'=>$label,'type'=>$type,'value'=>(string)$value,'required'=>$key==='title'];if($max)$f['maxlength']=$max;
                if($key==='discord_mode'){$f['options']=['now'=>'Opublikuj na Discordzie razem ze stroną','later'=>'Na razie tylko strona — przypomnij administracji'];$f['help']='Zapis szkicu niczego nie wysyła. Zapowiedź można wysłać później osobnym przyciskiem.';}
                if($key==='content_format'){$f['options']=['plain'=>'Zwykły tekst','markdown'=>'Edytor wizualny / Markdown'];$f['help']='Włącza edytor formatowania opisów, planów, zadań i komentarzy. Zmiana formatu dotyczy całego szkicu. Tytuły i krótka zapowiedź są zwykłym tekstem.';}
                if($key==='state')$f['options']=EventInput::STATES;if($key==='priority')$f['options']=['low'=>'Niski','normal'=>'Zwykły','high'=>'Wysoki','urgent'=>'Pilny'];if($key==='owner_id')$f['options']=$users;$fields[]=$f;
            }
            $this->theme->start_admin_panel($tabs[$tab]??'Nowe wydarzenie');$this->theme->render_text($tab==='announcement'?'Przygotuj zapowiedź. Zapis zachowuje szkic; publikację wykonasz w osobnej zakładce.':($tab==='plan'?'Prywatne notatki organizacyjne. Te treści są dostępne wyłącznie zespołowi.':'Pomysł nie wymaga terminu. Podaj obie daty, gdy ustalisz termin wydarzenia.'));
            $this->form($fields,$id?'Zapisz sekcję':'Utwórz wydarzenie',$id,$revision,'save');$this->theme->end_admin_panel();
        } elseif($event && $tab==='plan') {
            $this->theme->start_admin_panel($event['title']);foreach(['plan'=>'Plan','resources'=>'Zasoby','risks'=>'Ryzyka'] as $key=>$label){$this->theme->render_section_heading($label);$this->body($event[$key],$event['content_format']??'plain');}$this->theme->end_admin_panel();
        }
        if(!$event)return;
        if($tab==='overview') {
            $this->theme->start_admin_panel('Organizacja wpisu');
            $this->theme->render_text('Koordynator: '.($users[$event['owner_id']]??'Nieprzypisany').' · Priorytet: '.(['low'=>'Niski','normal'=>'Zwykły','high'=>'Wysoki','urgent'=>'Pilny'][$event['priority']]));
            $this->theme->render_text('Termin: '.(EventInput::local($event['starts_at'])?str_replace('T',' ',EventInput::local($event['starts_at'])).' — '.str_replace('T',' ',EventInput::local($event['ends_at'])):'Do ustalenia').' · Europe/Warsaw');
            $this->theme->render_alert($event['published_payload']!==null?'Gracze widzą opublikowaną zapowiedź. Zapis sekcji zmienia wyłącznie szkic.':(($event['entry_kind']??'event')==='event'?'Wydarzenie jest widoczne tylko zespołowi. Przygotuj treść dla graczy, a następnie przejdź do publikacji.':'Ten wpis jest wewnętrzny. Zadania i plany nie są publikowane dla graczy.'),'info');
            $this->theme->end_admin_panel();
        }
        if($tab==='publication') {
        $this->theme->start_admin_panel('Publikacja i podgląd');$this->theme->render_alert($event['published_payload']!==null?'Gracze widzą ostatnią opublikowaną wersję. Zmiany robocze wymagają ponownej publikacji.':'Wydarzenie jest wewnętrzne i niewidoczne dla graczy.','info');
        if($event['starts_at'] && $event['ends_at'] && $event['public_title']!=='')$this->schedule(['detail'=>EventPresenter::card(['id'=>$id]+array_intersect_key($event,array_flip(['public_title','summary','description','rules','rewards','location','category','starts_at','ends_at','content_format'])),gmdate('Y-m-d H:i:s'))]);
        if($event['publish_at'])$this->theme->render_alert('Automatyczna publikacja: '.str_replace('T',' ',EventInput::local($event['publish_at'])).' · Europe/Warsaw. Opublikowana zostanie zatwierdzona kopia treści; późniejsze zmiany szkicu jej nie zmieniają.','info');
        if($this->allows('events.publish')){$this->form([['name'=>'discord_mode','type'=>'select','label'=>'Publikacja na Discordzie','value'=>$event['discord_mode'],'options'=>['now'=>'Opublikuj teraz także na Discordzie','later'=>'Tylko strona — Discord później, z przypomnieniem']]],'Opublikuj zapisaną wersję dla graczy',$id,$revision,'publish');if($event['published_payload']!==null)$this->form([],'Wycofaj publikację',$id,$revision,'unpublish');}
        if($this->allows('events.publish')) { $this->form($this->publicationFields(true),'Zaplanuj publikację zapisanej wersji',$id,$revision,'schedule');if($event['publish_at'])$this->form([],'Anuluj automatyczną publikację',$id,$revision,'cancel_schedule');}
        if($event['published_payload']!==null && $this->database && $this->notificationConfig){$this->theme->render_alert((new EventNotifications($this->database,$this->notificationConfig))->publicationStatus($id),'info');if($this->allows('events.publish'))$this->form([],'Opublikuj zapowiedź na Discordzie / ponów po błędzie',$id,$revision,'discord');}
        if($event['published_payload']!==null)$this->theme->render_button('Zobacz opublikowane wydarzenie','/events/view?id='.$id);$this->theme->end_admin_panel();
        }
        $tasks=$this->events->tasks($id);$done=count(array_filter($tasks,static fn($t)=>$t['status']==='done'));$overdue=count(array_filter($tasks,static fn($t)=>$t['status']!=='done' && $t['due_at']!==null && $t['due_at']<gmdate('Y-m-d H:i:s')));
        if(in_array($tab,['overview','tasks'],true))$this->schedule(['metrics'=>['Zadań'=>count($tasks),'Ukończonych'=>$done,'Po terminie'=>$overdue,'Gotowość'=>count($tasks)?round(100*$done/count($tasks)).'%':'—']]);
        if($tab==='overview'){$this->theme->render_button('Przejdź do zadań','/admin/events?id='.$id.'&tab=tasks');return;}
        if($tab==='tasks') {
        $selected=null;$rows=[];
        foreach($tasks as $t) {
            if((int)$t['id']===($q->queryInt('task',0)??0))$selected=$t;
            $rows[]=['cells'=>[$t['title'],EventInput::TASKS[$t['status']],$users[$t['assignee_id']]??'Nieprzypisany',str_replace('T',' ',EventInput::local($t['due_at']))?:'Brak terminu'],'actions'=>[['label'=>'Otwórz','href'=>'/admin/events?id='.$id.'&tab=tasks&task='.$t['id']]]];
        }
        $this->theme->start_admin_panel('Zadania zespołu');
        if($manage)$this->theme->render_button('Nowe zadanie','/admin/events?id='.$id.'&tab=tasks&new_task=1');
        if($rows)$this->theme->render_admin_action_table(['Zadanie','Stan','Osoba','Termin'],$rows,$this->security->csrfToken());
        else $this->theme->render_text('Nie dodano jeszcze zadań.');
        $this->theme->end_admin_panel();
        if($selected){
            $this->theme->start_admin_panel($selected['title']);$this->body($selected['description'],$event['content_format']??'plain');
            if($manage || (int)$selected['assignee_id']===$this->auth->user()->id)$this->form([['name'=>'task_id','type'=>'hidden','value'=>$selected['id']],['name'=>'task_status','id'=>'event-task-status-'.$selected['id'],'type'=>'select','label'=>'Postęp','options'=>EventInput::TASKS,'value'=>$selected['status']]],'Zapisz postęp',$id,$revision,'progress');
            if($manage)$this->theme->render_admin_action_table(['Zadanie'],[['cells'=>[$selected['title']],'actions'=>[['label'=>'Usuń','action'=>'/admin/events','fields'=>['id'=>$id,'revision'=>$revision,'action'=>'delete_task','task_id'=>$selected['id']],'confirm'=>'Usunąć to zadanie?']]]],$this->security->csrfToken());
            $this->theme->end_admin_panel();
        }
        $taskFailed=$q->method()==='POST' && $q->postString('action')==='task' && http_response_code()>=400;
        if($taskFailed)$selected=['id'=>$q->postInt('task_id',0)??0,'title'=>$q->postString('task_title'),'description'=>$q->postString('task_description'),'assignee_id'=>$q->postInt('assignee_id',0)??0,'status'=>$q->postString('task_status'),'due_at'=>null];
        if($manage && ($selected || $taskFailed || $q->queryString('new_task')==='1')){$this->theme->start_admin_panel(!empty($selected['id'])?'Edytuj zadanie':'Dodaj zadanie');$this->form([
            ['name'=>'task_id','type'=>'hidden','value'=>$selected['id']??0],['name'=>'task_title','label'=>'Zadanie','required'=>true,'value'=>$selected['title']??'','maxlength'=>180],['name'=>'task_description','format_value'=>$taskFailed?$q->postString('task_description_format','markdown'):'markdown','type'=>($event['content_format']??'plain')==='markdown'?'richtext':'textarea','label'=>'Opis i kryteria ukończenia','value'=>$selected['description']??'','maxlength'=>12000],
            ['name'=>'assignee_id','type'=>'select','label'=>'Osoba odpowiedzialna','options'=>$users,'value'=>$selected['assignee_id']??0],['name'=>'due_at','type'=>'datetime-local','label'=>'Termin · Europe/Warsaw','value'=>$taskFailed?$q->postString('due_at'):EventInput::local($selected['due_at']??null)],['name'=>'task_status','type'=>'select','label'=>'Stan','options'=>EventInput::TASKS,'value'=>$selected['status']??'todo']],$selected?'Zapisz zadanie':'Dodaj zadanie',$id,$revision,'task');$this->theme->end_admin_panel();}
        }
        if($tab!=='discussion')return;
        $this->theme->start_admin_panel('Dyskusja i historia zmian');$this->form([['name'=>'body','format_value'=>$q->method()==='POST'?$q->postString('body_format','markdown'):'markdown','type'=>($event['content_format']??'plain')==='markdown'?'richtext':'textarea','label'=>'Wewnętrzny komentarz','required'=>true,'maxlength'=>6000,'value'=>$q->method()==='POST' && $q->postString('action')==='comment'?$q->postString('body'):'']],'Dodaj komentarz',$id,$revision,'comment');
        $labels=['create'=>'Utworzenie wpisu','schedule'=>'Zaplanowanie publikacji','cancel_schedule'=>'Anulowanie harmonogramu','release'=>'Automatyczna publikacja','save'=>'Zapis planu','discord'=>'Publikacja Discord','publish'=>'Publikacja','unpublish'=>'Wycofanie','task'=>'Zapis zadania','progress'=>'Zmiana postępu','delete_task'=>'Usunięcie zadania','comment'=>'Komentarz'];
        foreach($this->events->history($id) as $h){$this->theme->render_section_heading(($labels[$h['action']]??$h['action']).' · '.$h['actor'].' · '.str_replace('T',' ',EventInput::local($h['created_at'])));if($h['body']!==''){if($h['action']==='comment')$this->body($h['body'],$event['content_format']??'plain');else $this->theme->render_text($h['body']);}}$this->theme->end_admin_panel();
    }
}
