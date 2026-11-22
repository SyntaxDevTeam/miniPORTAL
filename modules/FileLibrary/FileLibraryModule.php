<?php

declare(strict_types=1);
namespace SyntaxDevTeam\Cms\Modules\FileLibrary;

use SyntaxDevTeam\Cms\Core\{AdminMenuRegistry,HookProviderInterface,HookRegistry,ModuleInterface,Request,Router,Security,TemplateCacheInterface,ThemeInterface};
use SyntaxDevTeam\Cms\Modules\CoreAuth\{AdminAccessGate,AuditLogService,AuthService};

final class FileLibraryModule implements ModuleInterface,HookProviderInterface
{
    public function __construct(private readonly ThemeInterface $theme,private readonly AdminMenuRegistry $menu,private readonly FileRepository $files,private readonly FileStore $store,private readonly AuthService $auth,private readonly AdminAccessGate $access,private readonly Security $security,private readonly AuditLogService $audit,private readonly TemplateCacheInterface $cache,private readonly ?HookRegistry $hooks = null) {}
    public function id(): string {return 'file_library';}
    public function version(): string {return '1.1.0';}
    public function dependencies(): array {return ['core_auth'];}
    public function isProtected(): bool {return false;}
    public function requiredPermissions(): array {return ['files.manage'];}
    public function registerAdminMenu(AdminMenuRegistry $menu): void {$menu->add('Treść','Biblioteka plików','/admin/files','📎','files.manage',42);}
    public function registerRoutes(Router $router): void
    {
        $router->get('/files/download',fn(Request $q)=>$this->download($q));
        $router->get('/admin/files',fn(Request $q)=>$this->admin($q));
        $router->post('/admin/files',fn(Request $q)=>$this->admin($q));
    }
    public function registerHooks(HookRegistry $hooks): void
    {
        $hooks->addAction('articles.admin.attachments',function(int $article): void {
            if(!$this->canManage() || $this->access->check('articles.edit')!==AdminAccessGate::ALLOWED)return;
            $this->theme->start_admin_panel('Załączniki artykułu');
            $this->theme->render_text('Pliki są dostępne czytelnikom dopiero po publikacji artykułu. Odpięcie usuwa powiązanie, zachowując plik w bibliotece.');
            $this->renderAttached($article,true);
            $this->theme->render_button('Wybierz plik z biblioteki','/admin/files?article='.$article);
            $this->uploadForm($article);
            $this->theme->end_admin_panel();
        });
        $hooks->addAction('articles.public.attachments',fn(int $article)=>$this->renderAttached($article,false));
    }
    private function canManage(): bool {return $this->access->check('files.manage')===AdminAccessGate::ALLOWED;}
    private function renderAttached(int $article,bool $admin): void
    {
        $files=$this->files->attached($article);if(!$files)return;
        $this->theme->render_section_heading($admin?'Przypięte pliki':'Pliki do pobrania');
        foreach($files as $file){
            $this->theme->render_button($file['original_name'].' · '.$this->sizeLabel((int)$file['size_bytes']),'/files/download?id='.$file['id'].($admin?'':'&article='.$article));
            if($admin)$this->theme->render_form('/admin/files',[
                ['name'=>'action','type'=>'hidden','value'=>'detach'],['name'=>'id','type'=>'hidden','value'=>$file['id']],['name'=>'article','type'=>'hidden','value'=>$article],
            ],'Odepnij plik',$this->security->csrfToken());
        }
    }
    private function sizeLabel(int $bytes): string {return $bytes<1024?$bytes.' B':number_format($bytes/1024,1,',',' ').' KiB';}
    private function uploadForm(int $article): void
    {
        $this->theme->render_form('/admin/files',[
            ['name'=>'action','type'=>'hidden','value'=>'upload'],['name'=>'article','type'=>'hidden','value'=>$article],
            ['name'=>'attachment','label'=>'Plik / przykład programu','type'=>'file','required'=>true,'help'=>'ZIP, PDF lub tekstowy kod UTF-8, do 20 MiB. ZIP do 2000 wpisów i 100 MiB po rozpakowaniu. Pliki są przeznaczone do pobrania.'],
        ],$article?'Prześlij i dołącz do artykułu':'Dodaj do biblioteki',$this->security->csrfToken());
    }
    private function admin(Request $q): void
    {
        header('Cache-Control: private, no-store');
        if(!$this->canManage()){http_response_code($this->auth->user()?403:401);$this->theme->render_admin_access_state(http_response_code(),'Biblioteka plików','Wymagane aktywne konto i uprawnienie files.manage.','/admin/login','Zaloguj się');return;}
        $user=$this->auth->user();$article=$q->method()==='POST'?($q->postInt('article',0)??0):($q->queryInt('article',0)??0);$message='';
        if($article && ($this->access->check('articles.edit')!==AdminAccessGate::ALLOWED || !$this->files->articleExists($article))){http_response_code(403);$this->theme->render_alert('Brak dostępu do powiązania artykułu.','danger');return;}
        if($q->method()==='POST'){
            if($this->auth->rolePreviewBlocks($q) || !$this->security->validateCsrfToken($q->postString('_token'))){http_response_code(403);$this->theme->render_alert('Podgląd roli lub nieprawidłowy token CSRF blokuje zapis.','danger');return;}
            try {
                $action=$q->postString('action');$id=$q->postInt('id',0)??0;
                $audit=fn(int $file)=>$this->audit->record($q,'file_library_'.$action,'success','file:'.$file.' article:'.$article,$user->id);
                if($action==='upload'){
                    $metadata=$this->store->upload($q->file('attachment')??[]);
                    try{$id=$this->files->create($metadata,$user->id,$audit);}catch(\Throwable $e){$this->store->remove($metadata['storage_key']);throw $e;}
                    if($article)$this->files->link($id,$article,true,$audit);
                } elseif(in_array($action,['attach','detach'],true) && $article>0){$this->files->link($id,$article,$action==='attach',$audit);}
                elseif($action==='delete'){$deleted=$this->files->delete($id,$audit);if($deleted)$this->store->remove($deleted['storage_key']);}
                else throw new \InvalidArgumentException('Nieznana operacja.');
                $this->cache->invalidateTags(['articles']);
                header('Location: '.($article?'/index.php?route=/admin/articles/edit&id='.$article:'/admin/files'),true,303);
                return;
            } catch(\InvalidArgumentException $e){http_response_code(422);$message=$e->getMessage();}
        }
        $this->theme->start_admin_page('Biblioteka plików',$this->menu->visibleFor($user->permissions),'/admin/files',['name'=>$user->displayName,'role'=>$user->primaryRole(),'initials'=>$user->initials(),'avatar_url'=>$user->avatarUrl??'']);
        $this->theme->start_admin_content('Biblioteka plików','Prywatny magazyn kodu, projektów ZIP i dokumentów. Dostęp publiczny wynika z publikacji powiązanego artykułu.');
        if($message!=='')$this->theme->render_alert($message,'danger');
        if($article){$this->theme->render_alert('Wybierz plik do artykułu #'.$article.'.','info');$this->theme->render_button('Wróć do artykułu','/index.php?route=/admin/articles/edit&id='.$article);}
        $this->theme->start_admin_panel('Dodaj plik');$this->uploadForm($article);$this->theme->end_admin_panel();
        $search=mb_substr($q->queryString('q'),0,180);$page=$this->files->page($search,$q->queryInt('page',1)??1);
        $this->theme->render_form('/admin/files',[['name'=>'q','label'=>'Szukaj po nazwie','value'=>$search],['name'=>'article','type'=>'hidden','value'=>$article]],'Szukaj','','get');
        $rows=[];
        foreach($page['files'] as $file){$actions=[['label'=>'Pobierz','href'=>'/files/download?id='.$file['id']]];
            if($article)$actions[]=['label'=>'Dołącz do artykułu','action'=>'/admin/files','fields'=>['action'=>'attach','id'=>$file['id'],'article'=>$article]];
            $actions[]=['label'=>'Usuń plik','action'=>'/admin/files','fields'=>['action'=>'delete','id'=>$file['id'],'article'=>$article],'variant'=>'outline-danger','confirm'=>'Usunąć plik i wszystkie jego powiązania?'];
            $rows[]=['cells'=>[$file['original_name'],$this->sizeLabel((int)$file['size_bytes']),substr($file['sha256'],0,12).'…',$file['created_at']],'actions'=>$actions];
        }
        $this->theme->start_admin_panel('Pliki · '.$page['total']);
        if($rows)$this->theme->render_admin_action_table(['Plik','Rozmiar','SHA-256 (skrót)','Dodano (UTC)'],$rows,$this->security->csrfToken());else $this->theme->render_alert('Brak plików.','info');
        $base='/admin/files?'.http_build_query(['q'=>$search,'article'=>$article]).'&page=';
        if($page['page']>1)$this->theme->render_button('Poprzednia',$base.($page['page']-1));if($page['page']<$page['pages'])$this->theme->render_button('Następna',$base.($page['page']+1));
        $this->theme->end_admin_panel();$this->theme->end_admin_content();$this->theme->end_admin_page();
    }
    private function download(Request $q): void
    {
        header('Cache-Control: private, no-store');header('X-Content-Type-Options: nosniff');header("Content-Security-Policy: sandbox; default-src 'none'");
        $file=$this->files->find($q->queryInt('id',0)??0);
        $article=$q->queryInt('article',0)??0;
        if(!$file || (($article!==0 || !$this->canManage()) && !$this->files->downloadable((int)$file['id'],$article)) || !($path=$this->store->path($file['storage_key']))){http_response_code(404);return;}
        $handle=fopen($path,'rb');if(!$handle){http_response_code(404);return;}
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="download-'.(int)$file['id'].'.'.strtolower(pathinfo($file['original_name'],PATHINFO_EXTENSION)).'"; filename*=UTF-8\'\''.rawurlencode($file['original_name']));
        header('Content-Length: '.fstat($handle)['size']);
        if ($article > 0 && $q->method() === 'GET') {
            $this->hooks?->doAction('articles.public.attachment_downloaded', $article);
        }
        if(session_status()===PHP_SESSION_ACTIVE)session_write_close();
        fpassthru($handle);fclose($handle);
    }
}
