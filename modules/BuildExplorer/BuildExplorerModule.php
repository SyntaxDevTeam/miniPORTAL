<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\BuildExplorer;

use SyntaxDevTeam\Cms\Core\AdminMenuRegistry;
use SyntaxDevTeam\Cms\Core\ModuleInterface;
use SyntaxDevTeam\Cms\Core\PublicNavigationProviderInterface;
use SyntaxDevTeam\Cms\Core\PublicNavigationRegistry;
use SyntaxDevTeam\Cms\Core\Request;
use SyntaxDevTeam\Cms\Core\Router;
use SyntaxDevTeam\Cms\Core\Security;
use SyntaxDevTeam\Cms\Core\ThemeInterface;
use SyntaxDevTeam\Cms\Modules\CoreAuth\AuditLogService;
use SyntaxDevTeam\Cms\Modules\CoreAuth\AuthService;
use SyntaxDevTeam\Cms\Modules\CoreAuth\User;

final class BuildExplorerModule implements ModuleInterface, PublicNavigationProviderInterface
{
    private const CHANNELS = [
        'release' => 'Release',
        'snapshot' => 'Snapshot',
        'dev' => 'Dev',
        'wip' => 'WIP',
    ];

    public function __construct(
        private readonly ThemeInterface $theme,
        private readonly AdminMenuRegistry $menu,
        private readonly BuildRepository $builds,
        private readonly AuthService $auth,
        private readonly Security $security,
        private readonly AuditLogService $audit,
        private readonly BuildArtifactStorage $storage,
    ) {}

    public function id(): string { return 'build_explorer'; }
    public function version(): string { return '1.1.1'; }
    public function dependencies(): array { return ['core_auth', 'projects']; }
    public function isProtected(): bool { return false; }
    public function requiredPermissions(): array { return ['builds.view', 'builds.manage']; }

    public function registerAdminMenu(AdminMenuRegistry $menu): void
    {
        $menu->add('Treść', 'Build Explorer', '/admin/builds', 'BL', 'builds.view', 38);
    }

    public function registerPublicNavigation(PublicNavigationRegistry $navigation): void
    {
        $navigation->add('build_explorer.index', 'Pliki do pobrania', '/builds', 'main', 56);
    }

    public function registerRoutes(Router $router): void
    {
        $router->get('/builds', fn () => $this->renderPublic());
        $router->get('/builds/download', fn (Request $request) => $this->download($request));
        $router->get('/builds/project', fn (Request $request) => $this->renderPublic($request->queryString('slug')));
        $slugs = [];
        foreach ($this->builds->all(true) as $build) { $slugs[$build->projectSlug] = true; }
        foreach (array_keys($slugs) as $slug) {
            $router->get('/builds/project/' . $slug, fn () => $this->renderPublic($slug));
        }
        $router->get('/admin/builds', fn (Request $request) => $this->guard($request, 'builds.view', fn () => $this->renderAdmin()));
        $router->get('/admin/builds/create', fn (Request $request) => $this->guard($request, 'builds.manage', fn () => $this->renderForm()));
        $router->post('/admin/builds/create', fn (Request $request) => $this->guard($request, 'builds.manage', fn () => $this->save($request)));
        $router->get('/admin/builds/edit', fn (Request $request) => $this->guard($request, 'builds.manage', fn () => $this->renderEdit($request)));
        $router->post('/admin/builds/edit', fn (Request $request) => $this->guard($request, 'builds.manage', fn () => $this->save($request, $this->builds->find($request->postInt('id', 0) ?? 0))));
        $router->post('/admin/builds/delete', fn (Request $request) => $this->guard($request, 'builds.manage', fn () => $this->delete($request)));
    }

    private function renderPublic(?string $projectSlug = null): void
    {
        $builds = $this->builds->all(true, $projectSlug !== '' ? $projectSlug : null);
        if ($projectSlug !== null && $projectSlug !== '' && $builds === []) {
            $this->theme->render_public_error(404, 'Nie znaleziono buildów', 'Ten projekt nie ma publicznych plików do pobrania.', 'Wróć do plików', '/builds');
            return;
        }
        $title = $projectSlug !== null && $builds !== [] ? 'Buildy: ' . $builds[0]->projectName : 'Pliki do pobrania';
        $this->theme->start_page($title . ' - SyntaxDevTeam', 'Wersje projektów SyntaxDevTeam do pobrania.');
        $this->theme->start_header($title, 'Release, Snapshot, Dev i WIP w jednym katalogu.', 'SyntaxDevTeam / Build Explorer');
        $this->theme->end_header();
        $this->theme->start_section();
        if ($builds === []) {
            $this->theme->render_alert('Nie opublikowano jeszcze żadnych plików.', 'info');
        } else {
            $this->theme->start_grid();
            foreach ($builds as $build) {
                $this->theme->start_column('lg-6');
                $this->theme->start_card($build->projectName . ' ' . $build->versionLabel, self::CHANNELS[$build->channel]);
                $this->theme->render_table(['Pole', 'Wartość'], [
                    ['Plik', $build->filename],
                    ['Rozmiar', $this->fileSize($build->fileSizeBytes)],
                    ['SHA-256', $build->checksumSha256 !== '' ? $build->checksumSha256 : 'Nie podano'],
                    ['Opublikowano', $build->publishedAt ?? 'Brak daty'],
                ]);
                if ($build->changelog !== '') { $this->theme->render_text($build->changelog); }
                $downloadHref = $build->storageKey !== ''
                    ? 'index.php?route=/builds/download&id=' . $build->id
                    : $build->downloadUrl;
                $this->theme->render_button('Pobierz plik', $downloadHref, 'primary');
                $this->theme->render_button('Projekt', '/projects/' . rawurlencode($build->projectSlug), 'outline-light');
                $this->theme->end_card();
                $this->theme->end_column();
            }
            $this->theme->end_grid();
        }
        $this->theme->end_section();
        $this->theme->end_page();
    }

    private function renderAdmin(string $message = '', string $variant = 'info'): void
    {
        $this->startPage('Build Explorer', 'Metadane plików i bezpieczne zewnętrzne adresy pobierania.', [[
            'label' => 'Dodaj build', 'href' => 'index.php?route=/admin/builds/create', 'variant' => 'primary',
        ], ['label' => 'Publiczna lista', 'href' => '/builds', 'variant' => 'outline-light']]);
        if ($message !== '') { $this->theme->render_alert($message, $variant); }
        $builds = $this->builds->all();
        $this->theme->start_admin_panel('Buildy projektów', count($builds) . ' plików');
        if ($builds === []) {
            $this->theme->render_alert('Nie dodano jeszcze żadnych buildów.', 'info');
        } else {
            $this->theme->render_admin_action_table(['Projekt', 'Wersja', 'Kanał', 'Plik', 'Publikacja'], array_map(
                static fn (ProjectBuild $build): array => [
                    'cells' => [$build->projectName, $build->versionLabel, self::CHANNELS[$build->channel], $build->filename, $build->published ? 'Publiczny' : 'Ukryty'],
                    'actions' => [[
                        'label' => 'Edytuj', 'href' => 'index.php?route=/admin/builds/edit&id=' . $build->id, 'variant' => 'primary',
                    ], [
                        'label' => 'Usuń', 'action' => 'index.php?route=/admin/builds/delete', 'fields' => ['id' => $build->id], 'variant' => 'danger', 'confirm' => 'Usunąć build z katalogu?',
                    ]],
                ], $builds
            ), $this->security->csrfToken());
        }
        $this->theme->end_admin_panel();
        $this->endPage();
    }

    private function renderEdit(Request $request): void
    {
        $build = $this->builds->find($request->queryInt('id', 0) ?? 0);
        $build instanceof ProjectBuild ? $this->renderForm($build) : $this->renderAdmin('Nie znaleziono buildu.', 'danger');
    }

    private function renderForm(?ProjectBuild $build = null, string $message = '', string $variant = 'info'): void
    {
        $projects = $this->builds->projectOptions();
        $this->startPage($build === null ? 'Dodaj build' : 'Edytuj build', 'Plik JAR zostanie zapisany poza publicznym katalogiem WWW.', [[
            'label' => 'Wróć do buildów', 'href' => 'index.php?route=/admin/builds', 'variant' => 'outline-light',
        ]]);
        if ($message !== '') { $this->theme->render_alert($message, $variant); }
        if ($projects === []) {
            $this->theme->render_alert('Najpierw dodaj projekt w module Projekty.', 'warning'); $this->endPage(); return;
        }
        if ($build !== null) {
            $this->theme->render_admin_fact_grid([
                ['label' => 'Aktualny plik', 'value' => $build->filename, 'detail' => $build->storageKey !== '' ? 'Plik lokalny' : 'Link zewnętrzny'],
                ['label' => 'Rozmiar', 'value' => $this->fileSize($build->fileSizeBytes), 'detail' => 'Obliczony po uploadzie'],
                ['label' => 'SHA-256', 'value' => $build->checksumSha256 ?: 'Brak', 'detail' => 'Obliczony po uploadzie'],
            ]);
        }
        $fields = $build !== null ? [['name' => 'id', 'label' => 'ID', 'type' => 'hidden', 'value' => (string) $build->id]] : [];
        $fields = [...$fields,
            ['name' => 'project_id', 'label' => 'Projekt', 'type' => 'select', 'value' => (string) ($build?->projectId ?? array_key_first($projects)), 'options' => $this->stringOptions($projects)],
            ['name' => 'server_type', 'label' => 'Serwer / platforma', 'value' => $build?->serverType ?? '', 'help' => 'Np. Paper, Spigot, Folia.'],
            ['name' => 'version_label', 'label' => 'Wersja', 'value' => $build?->versionLabel ?? ''],
            ['name' => 'channel', 'label' => 'Kanał', 'type' => 'select', 'value' => $build?->channel ?? 'release', 'options' => self::CHANNELS],
            ['name' => 'build_number', 'label' => 'Numer buildu', 'value' => $build?->buildNumber ?? '', 'help' => 'Np. 1 albo 14c0e44.'],
            ['name' => 'filename', 'label' => 'Nazwa pliku', 'value' => $build?->filename ?? '', 'help' => 'Puste pole: <projekt>-<serwer>-<wersja>-<typ>-<build>.jar. Możesz wpisać własną nazwę .jar.'],
            ['name' => 'artifact', 'label' => $build === null ? 'Plik JAR' : 'Nowy plik JAR (opcjonalnie)', 'type' => 'file', 'accept' => '.jar,application/java-archive'],
            ['name' => 'changelog', 'label' => 'Opis zmian', 'type' => 'textarea', 'rows' => 7, 'value' => $build?->changelog ?? ''],
            ['name' => 'is_published', 'label' => 'Widoczny publicznie', 'type' => 'checkbox', 'checked' => $build?->published ?? false],
        ];
        $this->theme->start_admin_panel('Dane buildu', 'Build Explorer');
        $this->theme->render_form('index.php?route=' . ($build === null ? '/admin/builds/create' : '/admin/builds/edit'), $fields, $build === null ? 'Dodaj build' : 'Zapisz build', $this->security->csrfToken());
        $this->theme->end_admin_panel();
        $this->endPage();
    }

    private function save(Request $request, ?ProjectBuild $build = null): void
    {
        $actor = $this->auth->user();
        if ($build === null && $request->postInt('id', 0)) { $this->renderAdmin('Nie znaleziono buildu.', 'danger'); return; }
        if (!$this->security->validateCsrfToken($request->postString('_token'))) {
            $this->audit->record($request, 'build_save', 'invalid_csrf', 'builds', $actor?->id);
            $this->renderForm($build, 'Token CSRF jest nieprawidłowy lub wygasł.', 'danger'); return;
        }
        $projectId = $request->postInt('project_id', 0) ?? 0;
        $projects = $this->builds->projectOptions();
        $serverType = $this->bounded($request->postString('server_type'), 80);
        $version = $this->bounded($request->postString('version_label'), 120);
        $channel = $request->postString('channel');
        $buildNumber = $this->bounded($request->postString('build_number'), 80);
        $filename = trim($request->postString('filename'));
        $changelog = $this->bounded($request->postString('changelog'), 10000);
        $published = $request->postBool('is_published');
        if (!isset($projects[$projectId]) || $serverType === '' || $version === '' || $buildNumber === '' || !isset(self::CHANNELS[$channel])) {
            $this->renderForm($build, 'Uzupełnij projekt, serwer, wersję, kanał i numer buildu.', 'warning'); return;
        }
        if ($filename === '') {
            try {
                $filename = BuildArtifactStorage::filename($projects[$projectId], $serverType, $version, $channel, $buildNumber);
            } catch (\Throwable $exception) {
                $this->renderForm($build, $exception->getMessage(), 'warning'); return;
            }
        }
        if (basename($filename) !== $filename || preg_match('/^[A-Za-z0-9._-]{1,251}\.jar$/i', $filename) !== 1) {
            $this->renderForm($build, 'Nazwa musi być bezpiecznym plikiem .jar bez ścieżki.', 'warning'); return;
        }
        $file = $request->file('artifact');
        $hasUpload = $file !== null && $file['error'] !== UPLOAD_ERR_NO_FILE;
        if ($build === null && !$hasUpload) {
            $this->renderForm(null, 'Wybierz plik JAR do zapisania.', 'warning'); return;
        }
        $stored = null;
        if ($hasUpload) {
            try { $stored = $this->storage->store($file); }
            catch (\Throwable $exception) { $this->renderForm($build, $exception->getMessage(), 'danger'); return; }
        }
        $data = [
            'project_id' => $projectId, 'server_type' => $serverType, 'version_label' => $version,
            'channel' => $channel, 'build_number' => $buildNumber, 'filename' => $filename,
            'storage_key' => $stored['storage_key'] ?? $build?->storageKey,
            'download_url' => $stored !== null ? null : ($build?->downloadUrl ?: null),
            'checksum_sha256' => $stored['checksum'] ?? ($build?->checksumSha256 ?: null),
            'file_size_bytes' => $stored['size'] ?? $build?->fileSizeBytes,
            'changelog' => $changelog, 'is_published' => $published ? 1 : 0,
            'published_at' => $published ? ($build?->publishedAt ?? date('Y-m-d H:i:s')) : null,
        ];
        try {
            if ($build === null) {
                $id = $this->builds->create($data); $event = 'build_create';
                if ($id <= 0) { throw new \RuntimeException('Baza nie zwróciła identyfikatora buildu.'); }
            } else {
                $id = $build->id;
                if (!$this->builds->update($id, $data)) { throw new \RuntimeException('Nie udało się zaktualizować buildu.'); }
                $event = 'build_update';
            }
        } catch (\Throwable $exception) {
            if ($stored !== null) { $this->storage->delete($stored['storage_key']); }
            $this->audit->record($request, 'build_save', 'failed', 'builds', $actor?->id);
            $this->renderForm($build, 'Nie udało się zapisać buildu: ' . $exception->getMessage(), 'danger');
            return;
        }
        $this->audit->record($request, $event, 'success', 'build:' . $id, $actor?->id);
        if ($stored !== null) { $this->storage->delete($build?->storageKey); }
        $this->renderAdmin('Build został zapisany.', 'success');
    }

    private function delete(Request $request): void
    {
        $actor = $this->auth->user();
        if (!$this->security->validateCsrfToken($request->postString('_token'))) {
            $this->audit->record($request, 'build_delete', 'invalid_csrf', 'builds', $actor?->id);
            $this->renderAdmin('Token CSRF jest nieprawidłowy lub wygasł.', 'danger'); return;
        }
        $id = $request->postInt('id', 0) ?? 0; $build = $this->builds->find($id); $ok = $this->builds->delete($id);
        if ($ok) { $this->storage->delete($build?->storageKey); }
        $this->audit->record($request, 'build_delete', $ok ? 'success' : 'failed', 'build:' . $id, $actor?->id);
        $this->renderAdmin($ok ? 'Build został usunięty.' : 'Nie udało się usunąć buildu.', $ok ? 'success' : 'danger');
    }

    private function download(Request $request): void
    {
        $build = $this->builds->findPublic($request->queryInt('id', 0) ?? 0);
        $path = $build instanceof ProjectBuild ? $this->storage->path($build->storageKey) : null;
        if (!$build instanceof ProjectBuild || $path === null) {
            http_response_code(404);
            $this->theme->render_public_error(404, 'Plik niedostępny', 'Nie znaleziono opublikowanego pliku JAR.', 'Wróć do plików', '/builds'); return;
        }
        header('Content-Type: application/java-archive');
        header('Content-Disposition: attachment; filename="' . $build->filename . '"');
        header('Content-Length: ' . (string) filesize($path));
        header('X-Content-Type-Options: nosniff');
        readfile($path);
    }

    private function guard(Request $request, string $permission, callable $handler): void
    {
        $user = $this->auth->user();
        if (!$user instanceof User) { http_response_code(401); $this->theme->render_admin_access_state(401, 'Wymagane logowanie', 'Build Explorer wymaga aktywnej sesji.', 'index.php?route=/admin/login', 'Przejdź do logowania'); return; }
        if (!in_array('*', $user->permissions, true) && !in_array($permission, $user->permissions, true)) {
            $this->audit->record($request, 'admin_access', 'denied', $permission, $user->id);
            http_response_code(403); $this->theme->render_admin_access_state(403, 'Brak uprawnień', 'Twoje konto nie ma uprawnienia: ' . $permission, 'index.php?route=/admin', 'Wróć do panelu'); return;
        }
        $handler();
    }

    private function startPage(string $title, string $lead, ?array $actions = null): void
    {
        $user = $this->auth->user();
        $this->theme->start_admin_page($title, $this->menu->visibleFor($user?->permissions ?? []), '/admin/builds', [
            'name' => $user?->displayName ?? 'Gość', 'role' => $user?->primaryRole() ?? 'Gość', 'initials' => $user?->initials() ?? 'G',
            'avatar_url' => $user?->avatarUrl ?? '', 'logout_action' => 'index.php?route=/admin/logout', 'logout_token' => $this->security->csrfToken(),
        ]);
        $this->theme->start_admin_content($title, $lead, [['label' => 'Panel', 'href' => 'index.php?route=/admin'], ['label' => 'Build Explorer', 'href' => 'index.php?route=/admin/builds']], $actions);
    }
    private function endPage(): void { $this->theme->end_admin_content(); $this->theme->end_admin_page(); }
    private function bounded(string $value, int $max): string { return function_exists('mb_substr') ? mb_substr(trim($value), 0, $max) : substr(trim($value), 0, $max); }
    /** @param array<int, string> $options @return array<string, string> */
    private function stringOptions(array $options): array { $result = []; foreach ($options as $id => $label) { $result[(string) $id] = $label; } return $result; }
    private function fileSize(?int $bytes): string
    {
        if ($bytes === null) { return 'Nie podano'; }
        if ($bytes >= 1048576) { return number_format($bytes / 1048576, 2, ',', ' ') . ' MB'; }
        if ($bytes >= 1024) { return number_format($bytes / 1024, 1, ',', ' ') . ' KB'; }
        return $bytes . ' B';
    }
}
