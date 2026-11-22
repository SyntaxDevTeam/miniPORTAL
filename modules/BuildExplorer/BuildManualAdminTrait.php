<?php

declare(strict_types=1);
namespace SyntaxDevTeam\Cms\Modules\BuildExplorer;

use SyntaxDevTeam\Cms\Core\{Request,Router};

trait BuildManualAdminTrait
{
    private function registerManualAdminRoutes(Router $router): void
    {
        $router->get('/admin/builds/changelog', fn(Request $r) => $this->guard($r, 'builds.manage', fn() => $this->editReleaseNotes($r)));
        $router->post('/admin/builds/changelog', fn(Request $r) => $this->guard($r, 'builds.manage', fn() => $this->saveReleaseNotes($r)));
        $router->get('/admin/builds/manual/edit', fn(Request $r)=>$this->guard($r,'builds.manage',fn()=>$this->editManualBuild($r)));
        $router->post('/admin/builds/manual/save', fn(Request $r)=>$this->guard($r,'builds.manage',fn()=>$this->saveManualBuild($r)));
        $router->post('/admin/builds/manual/delete', fn(Request $r)=>$this->guard($r,'builds.manage',fn()=>$this->deleteManualBuild($r)));
    }

    private function editReleaseNotes(Request $request, string $message = ''): void
    {
        $id = $request->queryInt('release', 0) ?? 0;
        $row = $this->builds->releaseNotes($id);
        if ($row === null) { http_response_code(404); $this->renderAdmin($request, 'Nie znaleziono wydania.', 'warning'); return; }
        $this->startPage('Opis zmian ' . $row['version_label'], 'Opis całego wydania, wspólny dla wszystkich platform i dostępny w API.');
        if ($message !== '') { $this->theme->render_alert($message, 'warning'); }
        $this->theme->start_admin_panel('Opis zmian wydania', 'Release notes');
        $this->theme->render_form('index.php?route=/admin/builds/changelog', [
            ['name' => 'release', 'type' => 'hidden', 'value' => (string) $id],
            ['name' => 'revision', 'type' => 'hidden', 'value' => $row['revision']],
            ['name' => 'changelog', 'type' => 'textarea', 'label' => 'Opis zmian', 'rows' => 12, 'value' => $row['changelog'],
                'help' => 'Zwykły tekst, maksymalnie 10000 znaków. Release wymaga niepustego opisu.'],
        ], 'Zapisz opis zmian', $this->security->csrfToken());
        $this->theme->end_admin_panel();
        $this->endPage();
    }

    private function saveReleaseNotes(Request $request): void
    {
        $id = $request->postInt('release', 0) ?? 0;
        if (!$this->security->validateCsrfToken($request->postString('_token'))) {
            $this->audit->record($request, 'build_release_notes', 'invalid_csrf', 'release:' . $id, $this->auth->user()?->id);
            http_response_code(403); $this->renderAdmin($request, 'Token CSRF jest nieprawidłowy.', 'danger'); return;
        }
        try {
            $this->builds->saveReleaseNotes($id, $request->postString('changelog'), $request->postString('revision'));
            $this->audit->record($request, 'build_release_notes', 'success', 'release:' . $id, $this->auth->user()?->id);
            header('Location: index.php?route=/admin/builds', true, 303);
        } catch (\Throwable $error) {
            $this->audit->record($request, 'build_release_notes', 'failed', 'release:' . $id, $this->auth->user()?->id);
            http_response_code(422); $this->renderAdmin($request, $error->getMessage(), 'warning');
        }
    }

    private function editManualBuild(Request $request): void
    {
        $id=$request->queryInt('artifact',0)??0;
        $legacy=$request->queryInt('legacy',0)??0;
        $value=$id>0?$this->builds->manualBuilds()->find($id):($legacy>0?$this->legacyManualValues($legacy):null);
        if ($value===null) { $this->renderAdmin($request,'Nie znaleziono ręcznego buildu.','warning'); return; }
        $this->renderManualForm($value);
    }

    private function legacyManualValues(int $id): ?array
    {
        $b=$this->builds->find($id);
        if ($b===null || $b->ciBuildId!==null) { return null; }
        $platform=strtolower($b->serverType);
        return ['artifact_id'=>0,'legacy_id'=>$id,'project_id'=>$b->projectId,'platform'=>$platform,
            'compatible_platforms'=>[$platform],'game_versions'=>[],'version_label'=>$b->versionLabel,
            'channel'=>$b->channel,'build_number'=>$b->buildNumber,'filename'=>$b->filename,'changelog'=>$b->changelog,
            'is_published'=>$b->published?1:0,'published_at'=>$b->publishedAt,'storage_key'=>$b->storageKey,
            'download_url'=>$b->downloadUrl?:null,'checksum_sha256'=>$b->checksumSha256,'file_size_bytes'=>$b->fileSizeBytes,
            'java_version'=>null,'revision'=>hash('sha256',json_encode($b,JSON_THROW_ON_ERROR))];
    }

    private function renderManualForm(array $value=[],string $message='',string $variant='warning'): void
    {
        $this->startPage(($value['artifact_id']??0)>0?'Edytuj ręczny build':'Dodaj ręczny build',
            'Build będzie dostępny w katalogu i wyszukiwaniu według platformy, wersji Minecrafta i kanału.',
            [['label'=>'Wróć do buildów','href'=>'index.php?route=/admin/builds','variant'=>'outline-light']]);
        if ($message!=='') { $this->theme->render_alert($message,$variant); }
        $projects=$this->builds->projectOptions();
        if ($projects===[]) { $this->theme->render_alert('Najpierw dodaj projekt.','warning'); $this->endPage(); return; }
        $versions=ManualBuildInput::versionChoices($this->builds->manualBuilds()->knownGameVersions(),$value['game_versions']??[]);
        $groups=[];
        foreach($versions as $version=>$label) { $major=explode('.',$version)[0]; $groups['Minecraft '.$major][$version]=$label; }
        $existing=($value['artifact_id']??0)>0 || ($value['legacy_id']??0)>0;
        if ($existing) {
            $this->theme->render_admin_fact_grid([
                ['label'=>'Aktualny plik','value'=>(string)$value['filename']],
                ['label'=>'SHA-256','value'=>(string)($value['checksum_sha256']??'Brak')],
                ['label'=>'Rozmiar','value'=>$this->fileSize(isset($value['file_size_bytes'])?(int)$value['file_size_bytes']:null)],
            ]);
        }
        $fields=[
            ['name'=>'artifact_id','type'=>'hidden','value'=>(string)($value['artifact_id']??0)],
            ['name'=>'legacy_id','type'=>'hidden','value'=>(string)($value['legacy_id']??0)],
            ['name'=>'revision','type'=>'hidden','value'=>(string)($value['revision']??'')],
            ['name'=>'project_id','label'=>'Projekt','type'=>'select','value'=>(string)($value['project_id']??array_key_first($projects)),'options'=>$this->stringOptions($projects)],
            ['name'=>'platform','label'=>'Platforma pliku JAR','type'=>'select','value'=>$value['platform']??'paper','options'=>ManualBuildInput::PLATFORMS],
            ['name'=>'compatible_platforms','label'=>'Zgodne platformy','type'=>'checkbox_groups','values'=>$value['compatible_platforms']??[],
                'groups'=>['Serwery'=>array_diff_key(ManualBuildInput::PLATFORMS,['velocity'=>1,'bungeecord'=>1]),'Proxy'=>['velocity'=>'Velocity','bungeecord'=>'BungeeCord']],
                'help'=>'Główna platforma jest zawsze zgodna. Zaznacz tylko sprawdzone dodatkowe platformy; nie łącz serwerów z proxy.'],
            ['name'=>'game_versions','label'=>'Zgodne wersje Minecrafta','type'=>'checkbox_groups','values'=>$value['game_versions']??[],'groups'=>$groups,
                'help'=>'Dla pluginu serwera wybierz co najmniej jedną wersję. Dla proxy można pozostawić puste. Deklaracja zgodności nie zastępuje testów pluginu.'],
            ['name'=>'extra_game_versions','label'=>'Dodatkowe wersje Minecrafta','value'=>$value['extra_game_versions']??'',
                'help'=>'Wersje spoza listy, oddzielone przecinkami, np. 26.4, 26.4.1.'],
            ['name'=>'version_label','label'=>'Wersja pluginu','value'=>$value['version_label']??'','required'=>true],
            ['name'=>'channel','label'=>'Kanał','type'=>'select','value'=>$value['channel']??'release','options'=>self::CHANNELS],
            ['name'=>'build_number','label'=>'Numer buildu','value'=>$value['build_number']??'','help'=>'Wymagany dla DEV i WIP.'],
            ['name'=>'java_version','label'=>'Wymagana Java (opcjonalnie)','type'=>'number','min'=>8,'max'=>99,'value'=>(string)($value['java_version']??'')],
            ['name'=>'filename','label'=>'Nazwa pliku','value'=>$value['filename']??'','help'=>'Puste pole wygeneruje nazwę JAR z danych buildu.'],
            ['name'=>'artifact','label'=>$existing?'Nowy plik JAR (opcjonalnie)':'Plik JAR','type'=>'file','accept'=>'.jar,application/java-archive'],
            ['name'=>'changelog','label'=>'Opis zmian','type'=>'textarea','rows'=>7,'value'=>$value['changelog']??''],
            ['name'=>'is_published','label'=>'Widoczny publicznie','type'=>'checkbox','checked'=>(bool)($value['is_published']??false)],
        ];
        $this->theme->start_admin_panel('Dane buildu i zgodność','Build Explorer');
        $this->theme->render_form('index.php?route=/admin/builds/manual/save',$fields,'Zapisz build',$this->security->csrfToken());
        $this->theme->end_admin_panel(); $this->endPage();
    }

    private function saveManualBuild(Request $request): void
    {
        if (!$this->security->validateCsrfToken($request->postString('_token'))) {
            $this->audit->record($request,'build_manual_save','invalid_csrf','builds',$this->auth->user()?->id);
            http_response_code(403); $this->renderManualForm([],'Token CSRF jest nieprawidłowy lub wygasł.'); return;
        }
        $id=$request->postInt('artifact_id',0)??0; $legacy=$request->postInt('legacy_id',0)??0;
        $repo=$this->builds->manualBuilds();
        $current=$id>0?$repo->find($id):($legacy>0?$this->legacyManualValues($legacy):[]);
        if ($current===null || ($id>0 && $legacy>0)) { http_response_code(404); $this->renderAdmin($request,'Nie znaleziono ręcznego buildu.','warning'); return; }
        $value=array_replace($current,$request->postStrings(),['game_versions'=>$request->postStringList('game_versions'),'compatible_platforms'=>$request->postStringList('compatible_platforms'),'is_published'=>$request->postBool('is_published')?1:0]);
        $stored=null; $committed=false;
        try {
            if ($id>0 || $legacy>0) {
                if (!hash_equals((string)$current['revision'],$request->postString('revision'))) { throw new \RuntimeException('Build został zmieniony. Otwórz formularz ponownie.'); }
            }
            $data=ManualBuildInput::read($request);
            $projects=$this->builds->projectOptions();
            if (!isset($projects[$data['project_id']])) { throw new \RuntimeException('Wybierz istniejący projekt.'); }
            $filename=$data['filename']!==''?$data['filename']:BuildArtifactStorage::filename($projects[$data['project_id']],$data['platform'],$data['version_label'],$data['channel'],$data['build_number']);
            if (preg_match('/^[A-Za-z0-9._-]{1,251}\.jar$/iD',$filename)!==1) { throw new \RuntimeException('Nazwa musi być bezpieczną nazwą pliku .jar bez ścieżki.'); }
            $file=$request->file('artifact');
            if ($file!==null && $file['error']!==UPLOAD_ERR_NO_FILE) {
                // No automatic cleanup from a form save: capacity checks only.
                $policy=$this->builds->retentionSettings();
                $this->storage->assertCapacity((int)$file['size'],$policy['hard_limit_bytes'],$policy['reserve_bytes']);
                $stored=$this->storage->store($file);
            }
            if (!$stored && !$current) { throw new \RuntimeException('Wybierz plik JAR.'); }
            $release=['project_id'=>$data['project_id'],'version_label'=>$data['version_label'],'channel'=>$data['channel'],
                'build_number'=>$data['build_number'],'changelog'=>$data['changelog'],'is_published'=>$data['is_published'],
                'published_at'=>$data['is_published']?($current['published_at']??gmdate('Y-m-d H:i:s')):null];
            $artifact=['platform'=>$data['platform'],'platform_family'=>$data['platform_family'],'filename'=>$filename,
                'storage_key'=>$stored['storage_key']??($current['storage_key']??null),'download_url'=>$stored?null:($current['download_url']??null),
                'checksum_sha256'=>$stored['checksum']??($current['checksum_sha256']??null),
                'file_size_bytes'=>$stored['size']??($current['file_size_bytes']??null),'java_version'=>$data['java_version']];
            $saved=$repo->save($release,$artifact,$data['game_versions'],$data['compatible_platforms'],$id>0?$id:null,$request->postString('revision'),$legacy>0?$legacy:null);
            $committed=true;
            $old=(string)($current['storage_key']??'');
            if ($stored && $old!=='' && !$repo->storageReferenced($old)) { $this->storage->delete($old); }
            $this->audit->record($request,'build_manual_save','success','artifact:'.$saved,$this->auth->user()?->id);
            try { if ($data['is_published'] === 1) { $row=$repo->find($saved); $this->hooks?->doAction('build_explorer.v2.published',$data['project_id'],(int)$row['release_id']); } }
            catch (\Throwable $error) { error_log('[miniPORTAL] build_manual_publication_hook_failed '.$error::class); }
            header('Location: index.php?'.http_build_query(['route'=>'/admin/builds','project_id'=>$data['project_id']]),true,303);
        } catch (\Throwable $error) {
            if ($stored && !$committed) { $this->storage->delete($stored['storage_key']); }
            $this->audit->record($request,'build_manual_save','failed','artifact:'.$id,$this->auth->user()?->id);
            http_response_code(422); $this->renderManualForm($value,$error->getMessage());
        }
    }

    private function deleteManualBuild(Request $request): void
    {
        if (!$this->security->validateCsrfToken($request->postString('_token'))) {
            $this->audit->record($request,'build_manual_delete','invalid_csrf','builds',$this->auth->user()?->id);
            http_response_code(403); $this->renderAdmin($request,'Token CSRF jest nieprawidłowy.','danger'); return;
        }
        try {
            $repo=$this->builds->manualBuilds();
            $id=$request->postInt('artifact_id',0)??0;
            $key=$repo->delete($id,$request->postString('revision'));
            if (is_string($key) && $key!=='' && !$repo->storageReferenced($key)) { $this->storage->delete($key); }
            $this->audit->record($request,'build_manual_delete','success','artifact:'.$id,$this->auth->user()?->id);
            header('Location: index.php?route=/admin/builds',true,303);
        } catch (\Throwable $error) { $this->renderAdmin($request,$error->getMessage(),'warning'); }
    }

    private function renderManualBuilds(array $filters): void
    {
        $repo=$this->builds->manualBuilds();
        $rows=[];
        foreach($repo->list($filters) as $item) {
            $id=(int)$item['artifact_id']; $record=$repo->find($id); $actions=[];
            if ($this->hasPermission('builds.manage')) {
                $actions[]=['label'=>'Edytuj','href'=>'index.php?route=/admin/builds/manual/edit&artifact='.$id,'variant'=>'primary'];
                $actions[]=['label'=>'Usuń','action'=>'index.php?route=/admin/builds/manual/delete','fields'=>['artifact_id'=>$id,'revision'=>$record['revision']],'variant'=>'danger','confirm'=>'Usunąć ręczny build wraz z plikiem?'];
            }
            $rows[]=['cells'=>[$item['project_name'],$item['version_label'],self::CHANNELS[$item['channel']],ucfirst($item['platform']),implode(', ',$record['game_versions'])?:'Nie wskazano',$item['is_published']?'Tak':'Nie'],'actions'=>$actions];
        }
        $this->theme->start_admin_panel('Ręczne buildy','Do 100 najnowszych artefaktów pasujących do filtrów');
        if ($rows===[]) { $this->theme->render_alert('Brak ręcznych buildów pasujących do filtrów.','info'); }
        else { $this->theme->render_admin_action_table(['Projekt','Wersja','Kanał','Platforma','Minecraft','Publikacja'],$rows,$this->security->csrfToken()); }
        $this->theme->end_admin_panel();
    }
}
