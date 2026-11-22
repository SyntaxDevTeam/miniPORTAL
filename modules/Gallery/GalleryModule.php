<?php
declare(strict_types=1);
namespace SyntaxDevTeam\Cms\Modules\Gallery;
use SyntaxDevTeam\Cms\Core\{ModuleInterface,PublicNavigationProviderInterface,PublicNavigationRegistry,PhotoCollectionThemeInterface,ThemeInterface,AdminMenuRegistry,Request,Router,Security};
use SyntaxDevTeam\Cms\Modules\CoreAuth\{AuthService,AdminAccessGate,AuditLogService};
final class GalleryModule implements ModuleInterface,PublicNavigationProviderInterface
{
    public function __construct(private readonly ThemeInterface $theme,private readonly AdminMenuRegistry $menu,private readonly GalleryRepository $repo,private readonly GalleryStorage $storage,private readonly AuthService $auth,private readonly AdminAccessGate $access,private readonly Security $security,private readonly AuditLogService $audit, private readonly ?GalleryPublicationService $publication = null) {}
    public function id(): string{return 'gallery';}
    public function version(): string{return '1.4.0';}
    public function dependencies(): array{return ['core_auth'];}
    public function isProtected(): bool{return false;}
    public function requiredPermissions(): array{return ['gallery.manage'];}
    public function registerAdminMenu(AdminMenuRegistry $menu): void{$menu->add('Treść','Galeria','/admin/gallery','GA','gallery.manage',44);}
    public function registerPublicNavigation(PublicNavigationRegistry $nav): void{$nav->add('gallery','Galeria','/gallery','main',45);}
    private function manages(): bool{return $this->access->check('gallery.manage')===AdminAccessGate::ALLOWED;}
    public function registerRoutes(Router $router): void {
        $router->get('/gallery',fn(Request $r)=>$this->page($r));
        $router->get('/account/gallery',fn(Request $r)=>$this->page($r,'mine'));
        $router->get('/admin/gallery',fn(Request $r)=>$this->page($r,'moderate'));
        $router->get('/gallery/image',fn(Request $r)=>$this->image($r));
        foreach(['upload','review','category','delete-category','delete','withdraw','batch-upload','describe','approve'] as $action)$router->post('/gallery/'.$action,fn(Request $r)=>$this->mutate($r,$action));
    }
    private function authorize(string $scope): bool {
        if($scope==='moderate'&&!$this->manages()){http_response_code($this->auth->user()?403:401);$this->theme->render_admin_access_state(http_response_code(),'Galeria','Wymagane uprawnienie do moderacji galerii.','/gallery','Wróć do galerii');return false;}
        if($scope==='mine'&&!$this->auth->user()?->isActive()){header('Location: /index.php?route=/admin/login&return_to='.rawurlencode('/account/gallery'),true,303);return false;}
        return true;
    }
    private function page(Request $r,string $scope='public',string $error='', ?array $batchIds=null): void {
        if(!$this->authorize($scope))return;
        $user=$this->auth->user();$admin=$this->manages();
        $category=max(0,$r->queryInt('category',0)??0);
        $status=$r->queryString('status','pending');if(!in_array($status,['pending','published','rejected','all'],true))$status='pending';
        $listing=$this->repo->listing($scope,$user?->id??0,$category,max(1,$r->queryInt('page',1)??1),$status);
        foreach($listing['photos'] as &$photo){$photo['url']='/index.php?route=/gallery/image&id='.$photo['id'];$photo['thumbnail']=$photo['url'].'&size=thumb';}unset($photo);
        $edit=null;
        if($scope==='moderate'&&($id=($r->queryInt('edit',0)?: (in_array($r->path(),['/gallery/review','/gallery/delete'],true)?$r->postInt('id',0):0)))>0){$edit=$this->repo->find($id);if($error!==''&&$edit&&$r->postString('title')!=='')$edit=array_replace($edit,['title'=>$r->postString('title'),'description'=>$r->postString('description'),'category_id'=>$r->postInt('category_id',0)??0,'moderation_note'=>$r->postString('moderation_note')]);}
        $batch = [];
        if ($scope === 'moderate') {
            $ids = $batchIds ?? self::batchIds($r->queryString('batch', $r->postString('batch')));
            $batch = $this->repo->byIds($ids);
            if ($error !== '' && $r->postArray('photos') !== []) {
                $submitted = $r->postArray('photos');
                foreach ($batch as &$item) {
                    $v = $submitted[$item['id']] ?? null;
                    if (is_array($v)) foreach (['title', 'description', 'category_id', 'revision'] as $key) if (isset($v[$key]) && is_scalar($v[$key])) $item[$key] = $v[$key];
                }
                unset($item);
            }
        }
        $adminView = $r->queryString('view', $r->postString('view', 'photos'));
        if (!in_array($adminView, ['photos', 'upload', 'categories'], true)) $adminView = 'photos';
        if ($scope === 'moderate') {
            if (in_array($r->path(), ['/gallery/category', '/gallery/delete-category'], true)) $adminView = 'categories';
            if ($r->path() === '/gallery/batch-upload') $adminView = 'upload';
            if ($batch !== []) $adminView = 'batch';
            if ($edit !== null) $adminView = 'edit';
        }
        $cover = $scope === 'public' ? array_map(static fn(array $photo): array => [
            'url' => '/index.php?route=/gallery/image&size=thumb&id=' . (int) $photo['id'],
        ], $this->repo->coverPhotos($category)) : [];
        $moderationNotice = $_SESSION['_gallery_moderation_notice'] ?? '';
        unset($_SESSION['_gallery_moderation_notice']);
        $model=$listing+['moderation_notice'=>$moderationNotice,'admin_view'=>$adminView,'counts'=>$scope==='moderate'?$this->repo->statusCounts():[],'category_counts'=>$scope==='moderate'?$this->repo->categoryCounts():[]]+['cover'=>$cover,'batch'=>$batch,'navigation_manage'=>$this->access->check('settings.manage')===AdminAccessGate::ALLOWED]+['scope'=>$scope,'status'=>$status,'categories'=>$this->repo->categories(),'category'=>$category,'admin'=>$admin,'authenticated'=>$user?->isActive()??false,'token'=>$this->security->csrfToken(),'error'=>$error,'saved'=>$r->queryInt('saved',0)===1,'edit'=>$edit,'values'=>$error!==''?['title'=>$r->postString('title'),'description'=>$r->postString('description'),'category_id'=>$r->postInt('category_id',0)??0]:[]];
        header('Cache-Control: private, no-store');
        if($scope==='moderate'){
            $this->theme->start_admin_page('Galeria',$this->menu->visibleFor($user?->permissions??[]),'/admin/gallery',['name'=>$user?->displayName??'','role'=>$user?->primaryRole()??'','initials'=>$user?->initials()??'','logout_action'=>'/index.php?route=/admin/logout','logout_token'=>$this->security->csrfToken()]);
            $this->theme->start_admin_content('Galeria','Zarządzaj zdjęciami, publikacją i kategoriami.',[
                ['label'=>'Panel','href'=>'/index.php?route=/admin'],['label'=>'Galeria','href'=>'/index.php?route=/admin/gallery'],
            ]);
        }else{$this->theme->start_page('Galeria','MoonVale oczami graczy — wspólne chwile, stajnie i niezwykłe miejsca.',$scope==='public');}
        if($this->theme instanceof PhotoCollectionThemeInterface)$this->theme->render_photo_collection($model);
        else{$this->theme->start_section();$this->theme->start_header('Galeria','Zdjęcia społeczności');$this->theme->end_header();foreach($listing['photos'] as $p)$this->theme->render_image($p['url'],$p['title']);$this->theme->end_section();}
        if($scope==='moderate'){$this->theme->end_admin_content();$this->theme->end_admin_page();}else $this->theme->end_page();
    }
    public static function metadata(Request $r): array {
        $title=trim($r->postString('title'));$description=trim($r->postString('description'));
        if($title===''||mb_strlen($title)>120||mb_strlen($description)>500)throw new \InvalidArgumentException('Podaj tytuł do 120 znaków i opcjonalny opis do 500 znaków.');
        return ['title'=>$title,'description'=>$description,'category_id'=>$r->postInt('category_id',0)??0];
    }
    private function mutate(Request $r,string $action): void {
        $adminAction=in_array($action,['review','category','delete-category','delete','batch-upload','describe','approve'],true);$scope=$adminAction?'moderate':'mine';
        $json = ($action === 'batch-upload' && $r->header('X-Requested-With') === 'GalleryQueue')
            || ($action === 'approve' && $r->header('X-Requested-With') === 'GalleryModeration');
        if ($json && !$this->manages()) { $this->json(403, ['error' => 'Brak uprawnień do operacji administracyjnej w Galerii.']); return; }
        if(!$this->authorize($scope))return;
        if($this->auth->rolePreviewBlocks($r)||!$this->security->validateCsrfToken($r->postString('_token'))){if($json){$this->json(403,['error'=>'Sesja wygasła lub zapis jest zablokowany. Odśwież stronę.']);return;}http_response_code(403);$this->page($r,$scope,'Nie można zapisać zmiany. Odśwież stronę i spróbuj ponownie.');return;}
        $user=$this->auth->user();$stored=null;$id=$r->postInt('id',0)??0;
        try {
            if ($action === 'approve') { $this->approveBatch($r, $json); return; }
            if ($action === 'batch-upload') { $this->uploadBatch($r, $json); return; }
            if ($action === 'describe') {
                $rows = $this->descriptionRows($r);
                $this->repo->describe($rows, $user->id);
                foreach ($rows as $row) $this->audit->record($r, 'gallery_describe', 'success', 'gallery:' . $row['id'], $user->id);
                header('Location: /index.php?route=/admin/gallery&batch=' . implode(',', array_column($rows, 'id')) . '&saved=1#gallery-describe', true, 303);
                return;
            }
            if($action==='upload'){
                $data=self::metadata($r);if(!$this->repo->category($data['category_id']))throw new \InvalidArgumentException('Wybierz kategorię zdjęcia.');
                $stored=$this->storage->store($r->file('photo')??[]);
                try{$id=($this->publication ?? new GalleryPublicationService($this->repo,$this->storage))->submit($user->id,$data,$stored,$this->manages());}catch(\Throwable $e){$this->storage->delete($stored);throw $e;}
            }elseif($action==='review'){
                if(!$this->repo->find($id))throw new \InvalidArgumentException('Nie znaleziono zdjęcia.');
                $data=self::metadata($r);if(!$this->repo->category($data['category_id']))throw new \InvalidArgumentException('Wybierz kategorię zdjęcia.');
                $status=$r->postString('status');$note=trim($r->postString('moderation_note'));
                if(!in_array($status,['pending','published','rejected'],true)||mb_strlen($note)>500||($status==='rejected'&&$note===''))throw new \InvalidArgumentException('Wybierz stan i przy odrzuceniu podaj krótki powód (do 500 znaków).');
                ($this->publication ?? new GalleryPublicationService($this->repo,$this->storage))->review($id,$r->postInt('revision',0)??0,$user->id,$data+['status'=>$status,'moderation_note'=>$note]);
            }elseif($action==='delete'){
                $photo=$this->repo->find($id);if(!$photo)throw new \InvalidArgumentException('Nie znaleziono zdjęcia.');
                if(!$r->postBool('confirm'))throw new \InvalidArgumentException('Potwierdź usunięcie zdjęcia.');
                $this->repo->delete($id,$r->postInt('revision',0)??0);$this->storage->delete($photo['stored_name']);
            }elseif($action==='withdraw'){
                $photo=$this->repo->find($id);
                if(!$r->postBool('confirm'))throw new \InvalidArgumentException('Potwierdź usunięcie zdjęcia.');
                if(!$photo||(int)$photo['user_id']!==$user->id){http_response_code(404);return;}
                $this->repo->withdraw($id,$user->id);$this->storage->delete($photo['stored_name']);
            }elseif($action==='category'){
                $name=trim($r->postString('name'));if($name===''||mb_strlen($name)>80)throw new \InvalidArgumentException('Nazwa kategorii musi mieć od 1 do 80 znaków.');
                $this->repo->saveCategory($name,$id);
            }else $this->repo->deleteCategory($id);
            $this->audit->record($r,'gallery_'.$action,'success','gallery:'.$id,$user->id);
            $returnQuery = in_array($action,['category','delete-category'],true) ? '&view=categories' : '';
            if ($action === 'review') $returnQuery = '&status=' . rawurlencode($r->postString('status'));
            header('Location: /index.php?route='.($adminAction?'/admin/gallery':'/account/gallery').$returnQuery.'&saved=1',true,303);
        }catch(\InvalidArgumentException $e){if($json){$this->json(422,['error'=>$e->getMessage()]);return;}http_response_code(422);$this->page($r,$scope,$e->getMessage());}
        catch(\Throwable $e){error_log('[Gallery] '.$action.' '.$e::class);if($json){$this->json(500,['error'=>'Nie udało się zapisać zdjęcia. Spróbuj ponownie.']);return;}http_response_code(500);$this->page($r,$scope,'Nie udało się zapisać zmiany. Sprawdź dane i spróbuj ponownie.');}
    }
    private function approveBatch(Request $request, bool $json): void
    {
        $values = $request->postStringList('ids');
        if ($values === [] || count($values) > ($json ? 1 : 200)) {
            throw new \InvalidArgumentException('Zaznacz zdjęcia oczekujące na zatwierdzenie.');
        }
        $revisions = $request->postArray('revisions');
        $selected = [];
        // Validate every submitted decision before spending any provider quota.
        foreach ($values as $value) {
            if (!preg_match('/^[1-9][0-9]{0,17}$/D', $value)) throw new \InvalidArgumentException('Niepoprawne zaznaczenie zdjęć.');
            $id = (int) $value;
            $revision = filter_var($revisions[$id] ?? null, FILTER_VALIDATE_INT);
            $photo = $this->repo->find($id);
            if (!$photo || !$revision || $revision < 1) throw new \InvalidArgumentException('Nie znaleziono zdjęcia lub brakuje jego wersji. Odśwież kolejkę.');
            if ($photo['status'] !== 'published' && ($photo['status'] !== 'pending' || (int) $photo['revision'] !== $revision)) {
                throw new \InvalidArgumentException('Jedno ze zdjęć zostało już zmienione. Odśwież kolejkę przed zatwierdzeniem.');
            }
            $selected[] = ['photo' => $photo, 'revision' => $revision];
        }
        $published = 0; $already = 0; $failed = 0;
        $publisher = $this->publication ?? new GalleryPublicationService($this->repo, $this->storage);
        foreach ($selected as $selection) {
            $photo = $selection['photo'];
            if ($photo['status'] === 'published') { $already++; continue; }
            try {
                $publisher->review((int) $photo['id'], $selection['revision'], $this->auth->user()->id, [
                    'status' => 'published', 'moderation_note' => '',
                ]);
                $this->audit->record($request, 'gallery_approve', 'success', 'gallery:' . $photo['id'], $this->auth->user()->id);
                $published++;
            } catch (\Throwable $error) {
                if ($json) throw $error;
                error_log('[Gallery] batch_approval_failed ' . $error::class);
                $failed++;
            }
        }
        if ($json) { $this->json(200, ['id' => (int) $selected[0]['photo']['id'], 'status' => 'published']); return; }
        $_SESSION['_gallery_moderation_notice'] = 'Opublikowano: ' . $published . '. Już opublikowane: ' . $already . '.'
            . ($failed ? ' Nie udało się zatwierdzić: ' . $failed . '. Sprawdź pozostałe zdjęcia.' : '');
        header('Location: /index.php?route=/admin/gallery&status=pending&category=' . max(0, $request->postInt('category', 0) ?? 0), true, 303);
    }

    /** @return list<int> */
    public static function batchIds(string $value): array
    {
        if (strlen($value) > 4200 || !preg_match('/^(?:[1-9][0-9]{0,17})(?:,[1-9][0-9]{0,17}){0,199}$/D', $value)) return [];
        return array_values(array_unique(array_map('intval', explode(',', $value))));
    }

    private function descriptionRows(Request $request): array
    {
        $ids = self::batchIds($request->postString('batch'));
        $values = $request->postArray('photos');
        if ($ids === [] || count($values) !== count($ids)) throw new \InvalidArgumentException('Niekompletna partia zdjęć. Odśwież stronę.');
        $rows = [];
        foreach ($ids as $id) {
            $value = $values[$id] ?? null;
            if (!is_array($value)) throw new \InvalidArgumentException('Niekompletne dane zdjęcia.');
            $data = self::metadata(Request::fromArrays([], $value, []));
            $revision = filter_var($value['revision'] ?? null, FILTER_VALIDATE_INT);
            if (!$revision || $revision < 1 || !$this->repo->category($data['category_id'])) throw new \InvalidArgumentException('Wybierz poprawną kategorię i odśwież dane zdjęcia.');
            $rows[] = ['id' => $id, 'revision' => $revision, 'data' => $data];
        }
        return $rows;
    }

    private function uploadBatch(Request $request, bool $json): void
    {
        $category = $request->postInt('category_id', 0) ?? 0;
        if (!$this->repo->category($category)) throw new \InvalidArgumentException('Wybierz kategorię dla wgrywanych zdjęć.');
        $files = $json ? array_filter([$request->file('photo')]) : $request->files('photos');
        if ($files === [] || count($files) > ($json ? 1 : 20)) throw new \InvalidArgumentException('Wybierz zdjęcia. Bez JavaScript można przesłać do 20 zdjęć jednocześnie.');
        if (!$json && array_sum(array_column($files, 'size')) > 47185920) throw new \InvalidArgumentException('Zestaw może mieć maksymalnie 45 MiB. Włącz JavaScript, aby przesyłać duże partie kolejką.');
        $actor = $this->auth->user()->id;
        $key = $request->postString('upload_key');
        if ($json && preg_match('/^[a-zA-Z0-9-]{24,96}$/D', $key) !== 1) throw new \InvalidArgumentException('Niepoprawny identyfikator uploadu.');
        $sessionKey = $actor . ':' . $key;
        $previous = $_SESSION['_gallery_uploads'][$sessionKey] ?? null;
        if ($json && is_array($previous)) {
            $photo = $this->repo->find((int) $previous['id']);
            if (!$photo || (int) $photo['user_id'] !== $actor) throw new \InvalidArgumentException('To zdjęcie zostało już przesłane i następnie usunięte. Odśwież kolejkę.');
            $this->json(200, ['id' => (int) $photo['id']]); return;
        }
        $ids = []; $errors = [];
        foreach ($files as $file) {
            try {
                $title = trim(pathinfo(str_replace('\\', '/', (string) $file['name']), PATHINFO_FILENAME));
                $title = mb_substr($title !== '' ? $title : 'Zdjęcie z serwera', 0, 120);
                $stored = $this->storage->store($file);
                try { $id = ($this->publication ?? new GalleryPublicationService($this->repo,$this->storage))->submit($actor, ['title' => $title, 'description' => '', 'category_id' => $category], $stored, true); }
                catch (\Throwable $error) { $this->storage->delete($stored); throw $error; }
                $ids[] = $id;
                if ($json) {
                    $_SESSION['_gallery_uploads'][$sessionKey] = ['id' => $id];
                    $_SESSION['_gallery_uploads'] = array_slice($_SESSION['_gallery_uploads'], -400, null, true);
                }
                $this->audit->record($request, 'gallery_admin_upload', 'success', 'gallery:' . $id, $actor);
            } catch (\InvalidArgumentException $error) { $errors[] = $error->getMessage(); }
            catch (\Throwable $error) {
                error_log('[Gallery] batch_upload ' . $error::class);
                $errors[] = 'Nie udało się zapisać jednego ze zdjęć. Spróbuj ponownie.';
            }
        }
        if ($json) {
            if (!$ids) throw new \InvalidArgumentException($errors[0] ?? 'Nie udało się przesłać zdjęcia.');
            $this->json(201, ['id' => $ids[0]]); return;
        }
        if ($errors !== []) {
            $this->page($request, 'moderate', 'Zapisano: ' . count($ids) . '. Niektóre zdjęcia nie zostały przesłane: ' . implode(' ', array_unique($errors)), $ids);
            return;
        }
        header('Location: /index.php?route=/admin/gallery&batch=' . implode(',', $ids) . '&saved=1#gallery-describe', true, 303);
    }

    private function json(int $status, array $data): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: private, no-store');
        echo json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    private function image(Request $r): void {
        $photo=$this->repo->find($r->queryInt('id',0)??0);$user=$this->auth->user();
        if(!$photo||($photo['status']!=='published'&&(!$user?->isActive()||((int)$photo['user_id']!==$user->id&&!$this->manages())))){http_response_code(404);return;}
        $path=$this->storage->path($photo['stored_name'],$r->queryString('size')==='thumb');
        if(!is_file($path)){http_response_code(404);return;}
        header('Content-Type: image/webp');header('X-Content-Type-Options: nosniff');header('Cache-Control: private, no-store');header('Content-Length: '.filesize($path));readfile($path);
    }
}
