<?php
declare(strict_types=1);
namespace SyntaxDevTeam\Cms\Modules\Recruitment;
use SyntaxDevTeam\Cms\Core\{ThemeInterface,AdminMenuRegistry,ModuleInterface,PublicNavigationProviderInterface,PublicNavigationRegistry,Request,Router,Security};
use SyntaxDevTeam\Cms\Modules\CoreAuth\{AuthService,AdminAccessGate,AuditLogService};
final class RecruitmentModule implements ModuleInterface, PublicNavigationProviderInterface
{
    public function __construct(private readonly ThemeInterface $theme, private readonly AdminMenuRegistry $menu, private readonly RecruitmentRepository $repo, private readonly AuthService $auth, private readonly AdminAccessGate $access, private readonly Security $security, private readonly AuditLogService $audit) {}
    public function id(): string { return 'recruitment'; }
    public function version(): string { return '1.2.0'; }
    public function dependencies(): array { return ['core_auth']; }
    public function isProtected(): bool { return false; }
    public function requiredPermissions(): array { return ['recruitment.review','recruitment.manage']; }
    public function registerAdminMenu(AdminMenuRegistry $menu): void {
        $permission = $this->access->check('recruitment.review') === 200 ? 'recruitment.review' : 'recruitment.manage';
        $menu->add('Treść','Centrum rekrutacji','/admin/recruitment','RC',$permission,60);
    }
    public function registerPublicNavigation(PublicNavigationRegistry $n): void { $n->add('recruitment.index','Rekrutacja','/recruitment','main',80); }
    public function registerRoutes(Router $router): void {
        $router->get('/recruitment',fn(Request $r)=>$this->run($r,'public'));
        $router->post('/recruitment',fn(Request $r)=>$this->run($r,'submit'));
        $router->get('/recruitment/application',fn(Request $r)=>$this->run($r,'own'));
        $router->post('/recruitment/withdraw',fn(Request $r)=>$this->run($r,'withdraw'));
        $router->get('/admin/recruitment',fn(Request $r)=>$this->run($r,'list'));
        $router->get('/admin/recruitment/application',fn(Request $r)=>$this->run($r,'detail'));
        $router->post('/admin/recruitment/review',fn(Request $r)=>$this->run($r,'review'));
        $router->get('/admin/recruitment/config',fn(Request $r)=>$this->run($r,'config'));
        $router->post('/admin/recruitment/config',fn(Request $r)=>$this->run($r,'save'));
    }
    private function run(Request $r,string $action): void {
        header('Cache-Control: private, no-store'); header('Cloudflare-CDN-Cache-Control: no-store'); header('X-Robots-Tag: noindex, nofollow');
        $admin=in_array($action,['list','detail','review','config','save'],true);
        if ($action === 'list' && $this->access->check('recruitment.review') !== 200 && $this->access->check('recruitment.manage') === 200) {
            header('Location: /admin/recruitment/config', true, 303); return;
        }
        $permission=in_array($action,['config','save'],true)?'recruitment.manage':'recruitment.review';
        if (($admin && $this->access->check($permission)!==200) || ($action!=='public' && !$this->auth->user()) || $this->auth->rolePreviewBlocks($r)) {
            http_response_code($this->auth->user()?403:401); $this->theme->render_admin_access_state($this->auth->user()?403:401,'Wymagany dostęp','Zaloguj się lub poproś o odpowiednie uprawnienia.','/admin/login?next=%2Frecruitment','Zaloguj się'); return;
        }
        $error='';
        if ($r->method()==='POST') {
            if (!$this->security->validateCsrfToken($r->postString('_token'))) { http_response_code(403); $error='Token formularza wygasł. Odśwież stronę.'; }
            else try {
                $target='/admin/recruitment';
                if ($action==='submit') { $id=$this->repo->submit($r,$this->auth->user()->id); $target='/recruitment/application?id='.$id; }
                elseif ($action==='save') { $this->repo->configure($r); $kind = $r->postString('kind'); $tab = in_array($kind, ['rank', 'question'], true) ? $kind : 'settings'; $target='/admin/recruitment/config?tab='.$tab.'&saved=1'; }
                else { $this->repo->review($r,$this->auth->user()->id,$action==='withdraw'); $target=($action==='withdraw'?'/recruitment/application':'/admin/recruitment/application').'?id='.$r->postInt('id'); }
                $this->audit->record($r,'recruitment_'.$action,'success','recruitment:'.($id??$r->postInt('id',0)),$this->auth->user()->id);
                header('Location: '.$target,true,303); return;
            } catch (\InvalidArgumentException $e) { http_response_code(422); $error=$e->getMessage(); }
            catch (\Throwable $e) { error_log('[recruitment] '.$e::class); http_response_code(500); $error='Nie udało się zapisać. Spróbuj ponownie.'; }
        }
        if ($admin) {
            $u=$this->auth->user();
            $this->theme->start_admin_page('Rekrutacja',$this->menu->visibleFor($u->permissions),'/admin/recruitment',['name'=>$u->displayName,'role'=>$u->primaryRole(),'initials'=>$u->initials(),'logout_action'=>'/admin/logout','logout_token'=>$this->security->csrfToken()]);
            $actions = [];
            if ($this->access->check('recruitment.review') === 200) $actions[] = ['label'=>'Podania','href'=>'/admin/recruitment','variant'=>$action === 'list' || $action === 'detail' ? 'primary' : 'secondary'];
            if ($this->access->check('recruitment.manage') === 200) $actions[] = ['label'=>'Konfiguracja','href'=>'/admin/recruitment/config','variant'=>in_array($action,['config','save'],true) ? 'primary' : 'secondary'];
            $actions[] = ['label'=>'Formularz publiczny','href'=>'/recruitment'];
            $this->theme->start_admin_content('Centrum rekrutacji','Zespół zaczyna się od dobrych ludzi.',[],$actions);
        } else { $s=$this->repo->settings(); $this->theme->start_page($s['title'],'Rekrutacja do administracji',false); $this->theme->start_header($s['title'],$s['intro']); $this->theme->end_header(); $this->theme->start_section(); }
        if ($error!=='') $this->theme->render_alert($error,'danger');
        if (in_array($action,['config','save'],true)) $this->config($r);
        elseif (in_array($action,['detail','own','review','withdraw'],true)) $this->detail($r,$admin);
        elseif ($action==='list') $this->listing($r,null);
        else $this->publicForm($r);
        if ($admin) { $this->theme->end_admin_content(); $this->theme->end_admin_page(); }
        else { $this->theme->end_section(); $this->theme->end_page(); }
    }
    private function field(string $name,string $label,string $type='text',mixed $value='',array $extra=[]): array { return ['name'=>$name,'label'=>$label,'type'=>$type,'value'=>(string)$value]+$extra; }
    private function form(string $url,array $fields,string $label): void { $this->theme->render_form($url,$fields,$label,$this->security->csrfToken()); }
    private function publicForm(Request $r): void {
        $s=$this->repo->settings();
        if ($this->auth->user() && $this->repo->applications($this->auth->user()->id,1)!==[]) $this->listing($r,$this->auth->user()->id);
        if (!$s['is_open']) { $this->theme->render_alert('Nabór jest obecnie zamknięty. Wróć podczas następnej rekrutacji.','info'); return; }
        if (!$this->auth->user()) { foreach ($this->repo->items('rank',true) as $rank) { $this->theme->start_card('✦ '.$rank['name']); $this->theme->render_rich_content($rank['description'],'text'); $this->theme->end_card(); } $this->theme->render_alert('Zaloguj się, aby wysłać podanie i śledzić jego status.','info'); $this->theme->render_button('Zaloguj się','/admin/login?next=%2Frecruitment','primary'); return; }
        $ranks=array_column($this->repo->items('rank',true),'name','id');
        if (!$ranks) { $this->theme->render_alert('Obecnie nie ma dostępnych rang.','info'); return; }
        $fields=[$this->field('revision','Wersja','hidden',$s['revision']),$this->field('nickname','Nick Minecraft / Discord','text',$r->postString('nickname'),['required'=>true,'maxlength'=>64]),$this->field('rank_id','Na jaką rangę aplikujesz?','select',$r->postString('rank_id'),['options'=>$ranks,'required'=>true])];
        foreach ($this->repo->items('question',true) as $i=>$q) {
            $extra=['required'=>(bool)$q['required'],'help'=>$q['help'],'maxlength'=>4000,'rows'=>4];
            if ($q['type']==='scale') $extra['options']=[''=>'Wybierz ocenę']+array_combine(range(1,10),array_map('strval',range(1,10)));
            $fields[]=$this->field('q_'.$q['id'],sprintf('%02d · %s',$i+1,$q['label']),$q['type']==='scale'?'select':$q['type'],$r->postString('q_'.$q['id']),$extra);
        }
        $fields[]=$this->field('acknowledged','Rozumiem, że podanie przeczyta uprawniony personel w celu rekrutacji. Nie zamieszczam haseł ani danych wrażliwych.','checkbox','1',['required'=>true,'checked'=>$r->postBool('acknowledged'),'help'=>'Zasady ochrony danych znajdziesz w polityce prywatności dostępnej w stopce.']);
        if ($this->theme instanceof \SyntaxDevTeam\Cms\Core\RecruitmentThemeInterface) { $this->theme->render_recruitment_form($fields,$this->repo->items('rank',true),$this->security->csrfToken()); return; }
        $this->theme->start_card('Twoje podanie','Odpowiedz własnymi słowami. Odpowiedzi są prywatne; możesz śledzić status i wycofać aktywne podanie.');
        $this->form('/recruitment',$fields,'Wyślij podanie'); $this->theme->end_card();
    }
    private function listing(Request $r,?int $user): void {
        $page=max(1,min(100000,$r->queryInt('page',1)??1)); $status=$r->queryString('status'); if (!isset(RecruitmentInput::statuses()[$status])) $status='';
        $this->theme->render_section_heading($user?'Moje podania':'Skrzynka podań','Nowe → ocena → rozmowa → decyzja');
        if ($user===null) $this->theme->render_form('/admin/recruitment',[$this->field('status','Status','select',$status,['options'=>[''=>'Wszystkie']+RecruitmentInput::statuses()])],'Filtruj','','get');
        $rows=$this->repo->applications($user,$page,$status); $base=$user===null?'/admin/recruitment':'/recruitment';
        $this->theme->render_admin_action_table(['Nr','Kandydat','Ranga','Status','Wysłano'],array_map(fn($a)=>['cells'=>[$a['id'],$a['nickname'],$a['rank_name'],RecruitmentInput::statuses()[$a['status']]??$a['status'],$a['created_at']],'actions'=>[['label'=>'Otwórz','href'=>$base.'/application?id='.$a['id']]]],$rows),$this->security->csrfToken());
        if ($page>1) $this->theme->render_button('Poprzednie',$base.'?page='.($page-1).'&status='.urlencode($status));
        if (count($rows)===25) $this->theme->render_button('Następne',$base.'?page='.($page+1).'&status='.urlencode($status));
    }
    private function detail(Request $r,bool $admin): void {
        $id=$r->method()==='POST'?$r->postInt('id',0):$r->queryInt('id',0); $a=$this->repo->find($id??0);
        if (!$a || (!$admin && (int)$a['user_id']!==$this->auth->user()->id)) { http_response_code(404); $this->theme->render_alert('Podanie nie jest dostępne.','danger'); return; }
        $this->theme->render_section_heading('#'.$a['id'].' · '.$a['nickname'],$a['rank_name'].' · '.(RecruitmentInput::statuses()[$a['status']]??$a['status']));
        foreach (json_decode($a['answers'],true,512,JSON_THROW_ON_ERROR) as $answer) { $this->theme->start_card($answer['label']); $this->theme->render_rich_content($answer['answer'],'text'); $this->theme->end_card(); }
        $this->theme->start_card('Wiadomość dla kandydata'); $this->theme->render_rich_content($a['feedback']?:'Oczekuj na kontakt personelu.','text'); $this->theme->end_card();
        $fields=[$this->field('id','ID','hidden',$a['id']),$this->field('revision','Wersja','hidden',$a['revision'])];
        if ($admin) {
            $this->form('/admin/recruitment/review',[...$fields,$this->field('status','Status','select',$a['status'],['options'=>RecruitmentInput::statuses()]),$this->field('feedback','Wiadomość widoczna dla kandydata','textarea',$a['feedback'],['maxlength'=>4000]),$this->field('note','Prywatna notatka personelu','textarea','',['maxlength'=>4000])],'Zapisz ocenę');
            $this->theme->render_admin_action_table(['Data','Autor ID','Status','Prywatna notatka'],array_map(fn($h)=>['cells'=>[$h['created_at'],$h['actor_id'],RecruitmentInput::statuses()[$h['status']]??$h['status'],$h['note']],'actions'=>[]],$this->repo->history((int)$a['id'])),$this->security->csrfToken());
        } elseif (in_array($a['status'],['new','review','interview'],true)) $this->form('/recruitment/withdraw',$fields,'Wycofaj moje podanie');
    }
    private function config(Request $r): void
    {
        $settings = $this->repo->settings();
        $submitted = $r->method() === 'POST';
        $tab = $submitted ? $r->postString('kind') : $r->queryString('tab', $r->queryString('kind', 'settings'));
        if (!in_array($tab, ['settings', 'rank', 'question'], true)) $tab = 'settings';
        $id = $submitted ? $r->postInt('id', 0) : $r->queryInt('id', 0);
        $editing = $tab !== 'settings' && ($submitted || ($id ?? 0) > 0 || $r->queryString('new') === '1');
        $ranks = $this->repo->items('rank');
        $questions = $this->repo->items('question');
        $all = $tab === 'rank' ? $ranks : $questions;
        $item = [];
        if ($editing && ($id ?? 0) > 0) {
            foreach ($all as $candidate) if ((int)$candidate['id'] === $id) $item = $candidate;
            if ($item === []) {
                http_response_code(404);
                $this->theme->render_alert('Ten wpis nie istnieje. Wybierz inny z listy.', 'danger');
                $editing = false;
            }
        }
        if ($submitted) {
            if ($tab === 'settings') {
                foreach (['title', 'intro', 'cooldown_days'] as $key) $settings[$key] = $r->postString($key);
                $settings['is_open'] = $r->postBool('is_open');
            } elseif ($editing) {
                foreach (['name','description','label','help','type','sort_order'] as $key) $item[$key] = $r->postString($key);
                foreach (['active','required'] as $key) $item[$key] = $r->postBool($key);
            }
        }
        $fields = [
            $this->field('revision', 'Wersja', 'hidden', $submitted ? $r->postString('revision') : $settings['revision']),
            $this->field('kind', 'Typ', 'hidden', $tab),
        ];
        if ($tab === 'settings') {
            $fields = [...$fields,
                $this->field('title', 'Nagłówek formularza', 'text', $settings['title'], ['required'=>true,'maxlength'=>120]),
                $this->field('intro', 'Wprowadzenie dla kandydatów', 'textarea', $settings['intro'], ['required'=>true,'maxlength'=>1000,'rows'=>4]),
                $this->field('cooldown_days', 'Odstęp między podaniami (dni)', 'number', $settings['cooldown_days'], ['required'=>true,'min'=>'1','max'=>'365','help'=>'Jedno aktywne podanie na konto. Kolejne można wysłać po upływie tego okresu.']),
                $this->field('is_open', 'Rekrutacja otwarta', 'checkbox', '1', ['checked'=>(bool)$settings['is_open'],'help'=>'Wyłączenie zamyka przyjmowanie nowych podań. Zachowuje zgłoszenia i ich historię.']),
            ];
        } elseif ($editing) {
            $fields[] = $this->field('id', 'ID', 'hidden', $id ?? 0);
            if ($tab === 'rank') {
                $fields = [...$fields,
                    $this->field('name', 'Nazwa rangi', 'text', $item['name'] ?? '', ['required'=>true,'maxlength'=>100]),
                    $this->field('description', 'Opis i wymagania', 'textarea', $item['description'] ?? '', ['maxlength'=>1000,'rows'=>4,'help'=>'Opis widoczny na karcie rangi w formularzu publicznym.']),
                ];
            } else {
                $fields = [...$fields,
                    $this->field('label', 'Treść pytania', 'text', $item['label'] ?? '', ['required'=>true,'maxlength'=>300]),
                    $this->field('help', 'Podpowiedź dla kandydata', 'textarea', $item['help'] ?? '', ['maxlength'=>1000,'rows'=>3]),
                    $this->field('type', 'Rodzaj odpowiedzi', 'select', $item['type'] ?? 'textarea', ['options'=>['text'=>'Krótki tekst','textarea'=>'Dłuższa odpowiedź','scale'=>'Ocena 1–10']]),
                    $this->field('required', 'Odpowiedź wymagana', 'checkbox', '1', ['checked'=>(bool)($item['required'] ?? true)]),
                ];
            }
            $fields[] = $this->field('sort_order', 'Kolejność', 'number', $item['sort_order'] ?? 100, ['required'=>true,'min'=>'0','max'=>'65535','help'=>'Niższa liczba oznacza wcześniejszą pozycję.']);
            $fields[] = $this->field('active', $tab === 'rank' ? 'Ranga dostępna w naborze' : 'Pytanie widoczne w formularzu', 'checkbox', '1', ['checked'=>(bool)($item['active'] ?? true)]);
        }
        $query = mb_substr(trim($r->queryString('q')), 0, 100);
        $state = $r->queryString('state');
        if (!in_array($state, ['active','inactive'], true)) $state = '';
        $filtered = array_values(array_filter($all, static function(array $row) use ($query, $state): bool {
            if ($state !== '' && (bool)$row['active'] !== ($state === 'active')) return false;
            return $query === '' || mb_stripos(($row['name'] ?? $row['label']).' '.($row['description'] ?? $row['help']), $query) !== false;
        }));
        $pages = max(1, (int)ceil(count($filtered) / 10));
        $page = max(1, min($pages, $r->queryInt('page', 1) ?? 1));
        $model = [
            'tab'=>$tab, 'editing'=>$editing, 'id'=>$id ?? 0, 'fields'=>$fields,
            'settings'=>$settings, 'rank_count'=>count($ranks), 'question_count'=>count($questions),
            'active_ranks'=>count(array_filter($ranks, static fn($row)=>(bool)$row['active'])),
            'active_questions'=>count(array_filter($questions, static fn($row)=>(bool)$row['active'])),
            'items'=>array_slice($filtered, ($page-1)*10, 10), 'total'=>count($filtered),
            'query'=>$query, 'state'=>$state, 'page'=>$page, 'pages'=>$pages,
            'saved'=>!$submitted && $r->queryString('saved') === '1',
        ];
        if ($this->theme instanceof \SyntaxDevTeam\Cms\Core\RecruitmentThemeInterface) {
            $this->theme->render_recruitment_configuration($model, $this->security->csrfToken());
            return;
        }
        foreach (['settings'=>'Nabór','rank'=>'Rangi','question'=>'Pytania'] as $key=>$label) $this->theme->render_button($label, '/admin/recruitment/config?tab='.$key, $key === $tab ? 'primary' : 'secondary');
        if ($tab === 'settings' || $editing) {
            $this->theme->start_admin_panel($tab === 'settings' ? 'Ustawienia naboru' : 'Edycja wpisu');
            $this->form('/admin/recruitment/config', $fields, 'Zapisz zmiany');
            $this->theme->end_admin_panel();
        } else {
            $this->theme->render_button('Dodaj wpis', '/admin/recruitment/config?tab='.$tab.'&new=1');
            $this->theme->render_admin_action_table(['Nazwa','Stan','Kolejność'], array_map(fn($row)=>[
                'cells'=>[$row['name'] ?? $row['label'], $row['active'] ? 'Aktywne' : 'Wyłączone', $row['sort_order']],
                'actions'=>[['label'=>'Edytuj','href'=>'/admin/recruitment/config?tab='.$tab.'&id='.$row['id']]],
            ], $model['items']), $this->security->csrfToken());
        }
    }
}
