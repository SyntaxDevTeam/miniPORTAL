<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\PluginTranslator;

use SyntaxDevTeam\Cms\Core\AdminMenuRegistry;
use SyntaxDevTeam\Cms\Core\ModuleInterface;
use SyntaxDevTeam\Cms\Core\PublicNavigationProviderInterface;
use SyntaxDevTeam\Cms\Core\PublicNavigationRegistry;
use SyntaxDevTeam\Cms\Core\Request;
use SyntaxDevTeam\Cms\Core\Router;
use SyntaxDevTeam\Cms\Core\Security;
use SyntaxDevTeam\Cms\Core\ThemeInterface;
use SyntaxDevTeam\Cms\Modules\CoreAuth\AdminAccessGate;
use SyntaxDevTeam\Cms\Modules\CoreAuth\AuditLogService;
use SyntaxDevTeam\Cms\Modules\CoreAuth\AuthService;
use SyntaxDevTeam\Cms\Modules\CoreAuth\User;

final class PluginTranslatorModule implements ModuleInterface, PublicNavigationProviderInterface
{
    public function __construct(
        private readonly ThemeInterface $theme,
        private readonly AdminMenuRegistry $menu,
        private readonly AuthService $auth,
        private readonly AdminAccessGate $access,
        private readonly Security $security,
        private readonly AuditLogService $audit,
        private readonly PluginTranslatorYaml $yaml,
        private readonly MinecraftFormatPreview $formatPreview,
        private readonly ?PluginTranslationRepository $translations,
    ) {
    }

    public function id(): string
    {
        return 'plugin_translator';
    }

    public function version(): string
    {
        return '1.3.3';
    }

    public function dependencies(): array
    {
        return ['core_auth', 'core_pages'];
    }

    public function isProtected(): bool
    {
        return false;
    }

    public function requiredPermissions(): array
    {
        return ['plugin_translator.use', 'plugin_translator.review'];
    }

    public function registerAdminMenu(AdminMenuRegistry $menu): void
    {
        $menu->add('System', 'Translator YAML', '/admin/plugin-translator', 'TR', 'plugin_translator.review', 59);
    }

    public function registerPublicNavigation(PublicNavigationRegistry $navigation): void
    {
        $navigation->add('plugin_translator.index', 'Tłumaczenia', '/translations', 'none', 70);
    }

    public function registerRoutes(Router $router): void
    {
        $router->get('/translations', fn (Request $request) => $this->renderPublicStart());
        $router->get('/translations/project', fn (Request $request) => $this->renderPublicProject($request));
        $router->get('/translations/upload-ready', fn (Request $request) => $this->renderReadyUpload());
        $router->post('/translations/upload-ready', fn (Request $request) => $this->submitReadyUpload($request));
        $router->get('/translations/download', fn (Request $request) => $this->downloadApprovedSubmission($request));
        $router->get('/translations/mine', fn (Request $request) => $this->renderUserSubmissions());
        $router->get('/translations/edit', fn (Request $request) => $this->editUserSubmission($request));
        $router->get('/translations/resume', fn (Request $request) => $this->resumePublicEditor());
        $router->post('/translations/open', fn (Request $request) => $this->openPublicEditor($request));
        $router->post('/translations/submit', fn (Request $request) => $this->submitPublicTranslation($request));

        $router->get('/admin/plugin-translator', fn (Request $request) => $this->guard(
            $request,
            'plugin_translator.review',
            fn () => $this->renderAdminQueue()
        ));
        $router->get('/admin/plugin-translator/plugins', fn (Request $request) => $this->guard(
            $request,
            'plugin_translator.review',
            fn () => $this->renderAdminProjects()
        ));
        $router->post('/admin/plugin-translator/plugins', fn (Request $request) => $this->guard(
            $request,
            'plugin_translator.review',
            fn () => $this->createAdminProject($request)
        ));
        $router->get('/admin/plugin-translator/plugins/edit', fn (Request $request) => $this->guard(
            $request,
            'plugin_translator.review',
            fn () => $this->renderAdminProjectEdit($request)
        ));
        $router->post('/admin/plugin-translator/plugins/edit', fn (Request $request) => $this->guard(
            $request,
            'plugin_translator.review',
            fn () => $this->updateAdminProject($request)
        ));
        $router->post('/admin/plugin-translator/plugins/status', fn (Request $request) => $this->guard(
            $request,
            'plugin_translator.review',
            fn () => $this->changeAdminProjectStatus($request)
        ));
        $router->post('/admin/plugin-translator/plugins/delete', fn (Request $request) => $this->guard(
            $request,
            'plugin_translator.review',
            fn () => $this->deleteAdminProject($request)
        ));
        $router->get('/admin/plugin-translator/view', fn (Request $request) => $this->guard(
            $request,
            'plugin_translator.review',
            fn () => $this->renderAdminSubmission($request)
        ));
        $router->post('/admin/plugin-translator/review', fn (Request $request) => $this->guard(
            $request,
            'plugin_translator.review',
            fn () => $this->reviewSubmission($request)
        ));
        $router->post('/admin/plugin-translator/delete', fn (Request $request) => $this->guard(
            $request,
            'plugin_translator.review',
            fn () => $this->deleteSubmission($request)
        ));
        $router->get('/admin/plugin-translator/download', fn (Request $request) => $this->guard(
            $request,
            'plugin_translator.review',
            fn () => $this->downloadSubmission($request)
        ));
        $router->get('/admin/plugin-translator/tool', fn (Request $request) => $this->guard(
            $request,
            'plugin_translator.use',
            fn () => $this->renderUpload()
        ));
        $router->post('/admin/plugin-translator/tool', fn (Request $request) => $this->guard(
            $request,
            'plugin_translator.use',
            fn () => $this->openAdminToolEditor($request)
        ));
        $router->post('/admin/plugin-translator/export', fn (Request $request) => $this->guard(
            $request,
            'plugin_translator.use',
            fn () => $this->exportTool($request)
        ));
    }

    private function renderPublicStart(string $message = '', string $variant = 'info'): void
    {
        $this->theme->start_page('Tłumaczenia pluginów - SyntaxDevTeam', 'Pomóż przygotować tłumaczenie pliku YAML dla pluginów SyntaxDevTeam.');
        $this->theme->start_header('Tłumaczenia pluginów', 'Wgraj plik wiadomości YAML, uzupełnij tłumaczenie i wyślij je do sprawdzenia.', 'SyntaxDevTeam / Lokalizacja');
        $this->theme->end_header();
        $this->theme->start_section();
        if ($message !== '') {
            $this->theme->render_alert($message, $variant);
        }
        if ($this->translations === null) {
            $this->theme->render_alert('Translator wymaga aktywnego połączenia z bazą danych.', 'danger');
            $this->theme->end_section();
            $this->theme->end_page();
            return;
        }

        $projects = $this->translations->projects();
        $this->theme->start_grid();
        $this->theme->start_column('lg-7');
        $this->theme->start_card('Rozpocznij tłumaczenie', 'YAML');
        if ($projects === []) {
            $this->theme->render_alert('Tłumaczenie będzie dostępne po dodaniu pierwszego pluginu przez administratora.', 'info');
        } else {
            $this->theme->render_form(
            'index.php?route=/translations/open',
            [[
                'name' => 'project_id',
                'label' => 'Kategoria tłumaczeń',
                'type' => 'select',
                'options' => $this->projectOptions($projects),
            ], [
                'name' => 'plugin_version',
                'label' => 'Wersja projektu',
                'type' => 'text',
                'help' => 'Opcjonalnie, np. 2.4.1.',
            ], [
                'name' => 'yaml_file',
                'label' => 'Plik YAML',
                'type' => 'file',
                'accept' => '.yml,.yaml,text/yaml,text/x-yaml,text/plain',
                'help' => 'Przeciągnij i upuść plik na pole albo wybierz go z dysku.',
            ], [
                'name' => 'target_language',
                'label' => 'Język docelowy',
                'type' => 'select',
                'value' => 'EN',
                'options' => $this->targetLanguages(),
            ]],
            'Otwórz edytor',
            $this->security->csrfToken()
            );
        }
        if ($this->auth->user() instanceof User) {
            $this->theme->render_button('Moje tłumaczenia', 'index.php?route=/translations/mine', 'outline-light');
            $this->theme->render_button('Wyślij gotowy plik', 'index.php?route=/translations/upload-ready', 'outline-light');
        } else {
            $this->theme->render_button('Zaloguj się, aby wrócić do szkiców', 'index.php?route=/admin/login', 'outline-light');
        }
        $this->theme->end_card();
        $this->theme->end_column();

        $this->theme->start_column('lg-5');
        $this->theme->start_card('Katalog tłumaczeń', count($projects) . ' kategorii');
        if ($projects === []) {
            $this->theme->render_alert('Administrator nie dodał jeszcze kategorii tłumaczeń.', 'info');
        } else {
            $this->theme->render_table(
                ['Kategoria', 'Zaakceptowane pliki'],
                array_map(static fn (PluginTranslationProject $project): array => [
                    $project->name,
                    (string) $project->approvedFiles,
                ], $projects)
            );
            foreach ($projects as $project) {
                $this->theme->render_button(
                    'Otwórz ' . $project->name,
                    'index.php?route=/translations/project&id=' . $project->id,
                    'outline-light'
                );
            }
        }
        $this->theme->end_card();
        $this->theme->end_column();
        $this->theme->end_grid();

        $this->theme->start_card('Ostatnio zatwierdzone', 'Review');
        $approved = $this->translations->recentApproved();
        if ($approved === []) {
            $this->theme->render_alert('Nie ma jeszcze zatwierdzonych tłumaczeń.', 'info');
        } else {
            $this->theme->render_table(
                ['Kategoria', 'Język', 'Wersja', 'Data'],
                array_map(
                    static fn (PluginTranslationSubmission $submission): array => [
                        $submission->projectName,
                        $submission->targetLanguage,
                        $submission->pluginVersion !== '' ? $submission->pluginVersion : '—',
                        $submission->reviewedAt ?? $submission->updatedAt,
                    ],
                    $approved
                )
            );
        }
        $this->theme->end_card();
        $this->theme->end_section();
        $this->theme->end_page();
    }

    private function renderPublicProject(Request $request): void
    {
        if ($this->translations === null) {
            $this->renderPublicStart('Translator wymaga aktywnego połączenia z bazą danych.', 'danger');
            return;
        }
        $project = $this->translations->project($request->queryInt('id', 0) ?? 0);
        if (!$project instanceof PluginTranslationProject) {
            $this->renderPublicStart('Nie znaleziono pluginu w katalogu tłumaczeń.', 'danger');
            return;
        }
        $files = $this->translations->approvedForProject($project->id);
        $this->theme->start_page($project->name . ' - tłumaczenia', 'Zaakceptowane pliki językowe pluginu ' . $project->name . '.');
        $this->theme->start_header($project->name, 'Zaakceptowane pliki tłumaczeń YAML.', 'SyntaxDevTeam / Lokalizacja');
        $this->theme->end_header();
        $this->theme->start_section();
        $this->theme->start_card('Pliki językowe', count($files) . ' plików');
        if ($files === []) {
            $this->theme->render_alert('Ten plugin nie ma jeszcze zaakceptowanych tłumaczeń.', 'info');
        } else {
            $this->renderApprovedFilesTable($files);
        }
        $this->theme->render_button('Wróć do katalogu', 'index.php?route=/translations', 'outline-light');
        if ($project->pageSlug !== '') {
            $this->theme->render_button('Strona projektu', '/p/' . $project->pageSlug, 'primary');
        }
        $this->theme->end_card();
        $this->theme->end_section();
        $this->theme->end_page();
    }

    private function renderReadyUpload(string $message = '', string $variant = 'info'): void
    {
        $user = $this->auth->user();
        if (!$user instanceof User) {
            $this->renderLoginRequired('', 'gotowe-tlumaczenie.yml', 'EN', [], 'Zaloguj się, aby przesłać gotowy plik do akceptacji.', 'index.php?route=/translations/upload-ready');
            return;
        }
        if ($this->translations === null) {
            $this->renderPublicStart('Translator wymaga aktywnego połączenia z bazą danych.', 'danger');
            return;
        }
        $projects = $this->translations->projects();
        $this->theme->start_page('Wyślij gotowe tłumaczenie - SyntaxDevTeam', 'Prześlij gotowy plik YAML do weryfikacji administratora.');
        $this->theme->start_header('Wyślij gotowy plik', 'Gotowe tłumaczenie trafi do kolejki administracyjnej i pojawi się w katalogu po akceptacji.', 'SyntaxDevTeam / Lokalizacja');
        $this->theme->end_header();
        $this->theme->start_section();
        if ($message !== '') {
            $this->theme->render_alert($message, $variant);
        }
        if ($projects === []) {
            $this->theme->render_alert('Brak aktywnych kategorii. Administrator musi najpierw utworzyć kategorię tłumaczeń.', 'warning');
        } else {
            $this->theme->start_card('Gotowy plik YAML', 'Do akceptacji');
            $this->theme->render_form('index.php?route=/translations/upload-ready', [[
                'name' => 'project_id',
                'label' => 'Kategoria tłumaczeń',
                'type' => 'select',
                'options' => $this->projectOptions($projects),
            ], [
                'name' => 'plugin_version',
                'label' => 'Wersja projektu',
                'type' => 'text',
                'help' => 'Opcjonalnie, np. 2.4.1.',
            ], [
                'name' => 'target_language',
                'label' => 'Język pliku',
                'type' => 'select',
                'value' => 'PL',
                'options' => $this->targetLanguages(),
            ], [
                'name' => 'title',
                'label' => 'Nazwa zgłoszenia',
                'type' => 'text',
                'value' => 'Gotowe tłumaczenie',
            ], [
                'name' => 'yaml_file',
                'label' => 'Gotowy plik YAML',
                'type' => 'file',
                'accept' => '.yml,.yaml,text/yaml,text/x-yaml,text/plain',
            ]], 'Wyślij do akceptacji', $this->security->csrfToken());
            $this->theme->end_card();
        }
        $this->theme->end_section();
        $this->theme->end_page();
    }

    private function submitReadyUpload(Request $request): void
    {
        $user = $this->auth->user();
        if (!$user instanceof User) {
            $this->renderReadyUpload('Sesja wygasła. Zaloguj się ponownie.', 'warning');
            return;
        }
        if (!$this->security->validateCsrfToken($request->postString('_token'))) {
            $this->renderReadyUpload('Token formularza jest nieprawidłowy lub wygasł.', 'danger');
            return;
        }
        if ($this->translations === null) {
            $this->renderPublicStart('Translator wymaga aktywnego połączenia z bazą danych.', 'danger');
            return;
        }

        try {
            $projectId = $request->postInt('project_id', 0) ?? 0;
            $project = $this->translations->project($projectId);
            if (!$project instanceof PluginTranslationProject) {
                throw new \InvalidArgumentException('Wybierz aktywną kategorię tłumaczeń.');
            }
            $filename = 'translation.yml';
            $source = $this->sourceYaml($request, '', $filename);
            $parsed = $this->yaml->parse($source);
            $items = $this->yaml->flatten($parsed);
            if ($items === []) {
                throw new \InvalidArgumentException('Gotowy plik nie zawiera żadnych linijek tekstu.');
            }
            $id = $this->translations->create(
                $project->id,
                $user->id,
                $user->displayName,
                $user->email,
                $this->bounded($request->postString('title', 'Gotowe tłumaczenie'), 180),
                $this->bounded($filename, 190),
                $this->bounded($request->postString('plugin_version'), 40),
                'completed_upload',
                $this->normalizeLanguage($request->postString('target_language', 'PL')),
                $source,
                [],
                $source,
                count($items),
                count($items),
                'ready_for_review'
            );
            $this->audit->record($request, 'plugin_translation_upload_ready', 'success', 'submission:' . $id, $user->id);
            $this->renderUserSubmissions('Gotowy plik został wysłany do akceptacji administratora.', 'success');
        } catch (\Throwable $exception) {
            $this->audit->record($request, 'plugin_translation_upload_ready', 'failed', 'yaml', $user->id);
            $this->renderReadyUpload($exception->getMessage(), 'danger');
        }
    }

    private function downloadApprovedSubmission(Request $request): void
    {
        $submission = $this->translations?->find($request->queryInt('id', 0) ?? 0);
        $project = $submission instanceof PluginTranslationSubmission
            ? $this->translations?->project($submission->projectId)
            : null;
        if (!$submission instanceof PluginTranslationSubmission
            || !$project instanceof PluginTranslationProject
            || $submission->status !== 'approved') {
            $this->renderPublicStart('Nie znaleziono zaakceptowanego pliku tłumaczenia.', 'danger');
            return;
        }

        header('Content-Type: application/x-yaml; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $this->downloadFilename($submission) . '"');
        header('X-Content-Type-Options: nosniff');
        echo $submission->outputYaml;
    }

    private function renderUserSubmissions(string $message = '', string $variant = 'info'): void
    {
        $user = $this->auth->user();
        if (!$user instanceof User) {
            $_SESSION['_miniportal_after_login'] = 'index.php?route=/translations/mine';
            $this->theme->start_page('Moje tłumaczenia - SyntaxDevTeam', 'Zaloguj się, aby zobaczyć swoje tłumaczenia.');
            $this->theme->start_header('Moje tłumaczenia', 'Zaloguj się, aby wrócić do zapisanych szkiców i zgłoszeń.', 'SyntaxDevTeam / Lokalizacja');
            $this->theme->end_header();
            $this->theme->start_section();
            $this->theme->render_alert('Zaloguj się, aby zobaczyć swoje wersje robocze tłumaczeń.', 'warning');
            $this->theme->render_button('Przejdź do logowania', 'index.php?route=/admin/login', 'primary');
            $this->theme->end_section();
            $this->theme->end_page();
            return;
        }
        if ($this->translations === null) {
            $this->renderPublicStart('Translator wymaga aktywnego połączenia z bazą danych.', 'danger');
            return;
        }

        $submissions = $this->translations->forUser($user->id);
        $this->theme->start_page('Moje tłumaczenia - SyntaxDevTeam', 'Lista zapisanych szkiców i zgłoszeń tłumaczeń.');
        $this->theme->start_header('Moje tłumaczenia', 'Wróć do szkicu, popraw odrzucone zgłoszenie albo sprawdź status pracy.', 'SyntaxDevTeam / Lokalizacja');
        $this->theme->end_header();
        $this->theme->start_section();
        if ($message !== '') {
            $this->theme->render_alert($message, $variant);
        }
        $this->theme->start_card('Zapisane prace', count($submissions) . ' zgłoszeń');
        if ($submissions === []) {
            $this->theme->render_alert('Nie masz jeszcze zapisanych tłumaczeń.', 'info');
            $this->theme->render_button('Rozpocznij tłumaczenie', 'index.php?route=/translations', 'primary');
        } else {
            $this->renderUserSubmissionsTable($submissions);
        }
        $this->theme->end_card();
        $this->theme->end_section();
        $this->theme->end_page();
    }

    private function editUserSubmission(Request $request): void
    {
        $user = $this->auth->user();
        if (!$user instanceof User) {
            $_SESSION['_miniportal_after_login'] = 'index.php?route=/translations/edit&id=' . (int) ($request->queryInt('id', 0) ?? 0);
            $this->renderUserSubmissions();
            return;
        }
        if ($this->translations === null) {
            $this->renderPublicStart('Translator wymaga aktywnego połączenia z bazą danych.', 'danger');
            return;
        }

        $submission = $this->translations->findForUser($request->queryInt('id', 0) ?? 0, $user->id);
        if (!$submission instanceof PluginTranslationSubmission) {
            $this->renderUserSubmissions('Nie znaleziono Twojej pracy tłumaczeniowej.', 'danger');
            return;
        }
        if ($submission->submissionKind !== 'editor') {
            $this->renderUserSubmissions('Gotowy plik można zastąpić przez ponowny upload.', 'warning');
            return;
        }
        if (!in_array($submission->status, ['draft', 'ready_for_review', 'rejected'], true)) {
            $this->renderUserSubmissions('Zatwierdzonego tłumaczenia nie można już edytować.', 'warning');
            return;
        }

        try {
            $items = $this->yaml->flatten($this->yaml->parse($submission->sourceYaml));
            $translations = json_decode($submission->translationsJson, true, 512, JSON_THROW_ON_ERROR);
            $this->renderPublicEditor(
                $submission->sourceYaml,
                $submission->sourceFilename,
                $submission->targetLanguage,
                $submission->projectId,
                $submission->pluginVersion,
                $items,
                is_array($translations) ? $this->normalizedTranslations($translations) : [],
                'Wczytano zapisaną pracę. Możesz kontynuować edycję.',
                'success',
                false,
                $submission
            );
        } catch (\Throwable $exception) {
            $this->renderUserSubmissions($exception->getMessage(), 'danger');
        }
    }

    private function openPublicEditor(Request $request): void
    {
        $source = $request->postString('source_yaml');
        if (!$this->security->validateCsrfToken($request->postString('_token'))) {
            $this->renderPublicStart('Token formularza jest nieprawidłowy lub wygasł.', 'danger');
            return;
        }

        try {
            $user = $this->auth->user();
            $filename = 'messages.yml';
            $source = $this->sourceYaml($request, $source, $filename);
            $targetLanguage = $this->normalizeLanguage($request->postString('target_language', 'EN'));
            $projectId = $request->postInt('project_id', 0) ?? 0;
            if (!$this->translations?->project($projectId) instanceof PluginTranslationProject) {
                throw new \InvalidArgumentException('Wybierz aktywną kategorię tłumaczeń.');
            }
            $pluginVersion = $this->bounded($request->postString('plugin_version'), 40);
            $parsed = $this->yaml->parse($source);
            $items = $this->yaml->flatten($parsed);
            if ($items === []) {
                throw new \InvalidArgumentException('Nie znaleziono żadnych linijek tekstu do tłumaczenia.');
            }
            if (!$user instanceof User) {
                $this->storeResume([
                    'source_yaml' => $source,
                    'source_filename' => $filename,
                    'target_language' => $targetLanguage,
                    'project_id' => $projectId,
                    'plugin_version' => $pluginVersion,
                    'translations' => [],
                ]);
                $this->renderLoginRequired($source, $filename, $targetLanguage, [], 'Zaloguj się, aby rozpocząć wprowadzanie tłumaczeń. Plik został zachowany w tej sesji.');
                return;
            }
            $this->renderPublicEditor($source, $filename, $targetLanguage, $projectId, $pluginVersion, $items);
        } catch (\Throwable $exception) {
            $this->renderPublicStart($exception->getMessage(), 'danger');
        }
    }

    private function resumePublicEditor(): void
    {
        $resume = $this->resumeData();
        if ($resume === null) {
            $this->renderPublicStart('Nie znaleziono zachowanej pracy tłumaczenia.', 'warning');
            return;
        }
        if (!$this->auth->user() instanceof User) {
            $this->renderLoginRequired(
                $resume['source_yaml'],
                $resume['source_filename'],
                $resume['target_language'],
                $resume['translations'],
                'Zaloguj się, aby kontynuować tłumaczenie.'
            );
            return;
        }

        try {
            $items = $this->yaml->flatten($this->yaml->parse($resume['source_yaml']));
            unset($_SESSION['_plugin_translation_resume']);
            $this->renderPublicEditor(
                $resume['source_yaml'],
                $resume['source_filename'],
                $resume['target_language'],
                $resume['project_id'],
                $resume['plugin_version'],
                $items,
                $resume['translations'],
                'Możesz kontynuować zachowane tłumaczenie.',
                'success'
            );
        } catch (\Throwable $exception) {
            $this->renderPublicStart($exception->getMessage(), 'danger');
        }
    }

    private function submitPublicTranslation(Request $request): void
    {
        $source = $request->postString('source_yaml');
        if (!$this->security->validateCsrfToken($request->postString('_token'))) {
            $this->renderPublicStart('Token formularza jest nieprawidłowy lub wygasł.', 'danger');
            return;
        }
        if ($this->translations === null) {
            $this->renderPublicStart('Translator wymaga aktywnego połączenia z bazą danych.', 'danger');
            return;
        }

        try {
            $parsed = $this->yaml->parse($source);
            $items = $this->yaml->flatten($parsed);
            $translations = $this->normalizedTranslations($request->postArray('translations'));
            $targetLanguage = $this->normalizeLanguage($request->postString('target_language', 'EN'));
            $projectId = $request->postInt('project_id', 0) ?? 0;
            $pluginVersion = $this->bounded($request->postString('plugin_version'), 40);
            $filename = $request->postString('source_filename', 'messages.yml');
            $user = $this->auth->user();
            if (!$user instanceof User) {
                $this->storeResume([
                    'source_yaml' => $source,
                    'source_filename' => $filename,
                    'target_language' => $targetLanguage,
                    'project_id' => $projectId,
                    'plugin_version' => $pluginVersion,
                    'translations' => $translations,
                    'title' => $request->postString('title'),
                    'author_name' => $request->postString('author_name'),
                    'author_email' => $request->postString('author_email'),
                    'status' => $request->postString('status', 'draft'),
                ]);
                $this->renderLoginRequired($source, $filename, $targetLanguage, $translations, 'Zaloguj się, aby zapisać tłumaczenie. Wpisane pola zostały zachowane.');
                return;
            }
            if (!$this->translations->project($projectId) instanceof PluginTranslationProject) {
                throw new \InvalidArgumentException('Wybierz aktywną kategorię tłumaczeń.');
            }
            if ($request->postString('_action') === 'preview') {
                $this->renderPublicEditor($source, $filename, $targetLanguage, $projectId, $pluginVersion, $items, $translations, 'Sprawdzono formatowanie. Podgląd HTML znajduje się pod formularzem.', 'success', true, $this->postedSubmission($request, $user));
                return;
            }
            $translated = $this->yaml->translated($parsed, $translations);
            $output = $this->yaml->dump($translated);
            $this->yaml->parse($output);

            $status = $request->postString('status') === 'ready_for_review' ? 'ready_for_review' : 'draft';
            $translatedCount = $this->yaml->translatedCount($items, $translations);
            if ($status === 'ready_for_review' && $translatedCount < count($items)) {
                $this->renderPublicEditor($source, $filename, $targetLanguage, $projectId, $pluginVersion, $items, $translations, 'Tłumaczenie można oznaczyć jako gotowe dopiero po uzupełnieniu wszystkich linijek tekstu.', 'danger', false, $this->postedSubmission($request, $user));
                return;
            }

            $submissionId = $request->postInt('submission_id', 0) ?? 0;
            if ($submissionId > 0 && $user instanceof User) {
                $existing = $this->translations->findForUser($submissionId, $user->id);
                if (!$existing instanceof PluginTranslationSubmission || !in_array($existing->status, ['draft', 'ready_for_review', 'rejected'], true)) {
                    $this->renderPublicEditor($source, $filename, $targetLanguage, $projectId, $pluginVersion, $items, $translations, 'Nie możesz edytować tej pracy tłumaczeniowej.', 'danger');
                    return;
                }
                $updated = $this->translations->updateUserSubmission(
                    $submissionId,
                    $user->id,
                    $projectId,
                    $this->bounded($request->postString('author_name', $user->displayName), 160),
                    $this->bounded($request->postString('author_email', $user->email), 190),
                    $this->bounded($request->postString('title', 'Tłumaczenie pluginu'), 180),
                    $pluginVersion,
                    $targetLanguage,
                    $translations,
                    $output,
                    count($items),
                    $translatedCount,
                    $status
                );
                if (!$updated) {
                    $this->renderPublicEditor($source, $filename, $targetLanguage, $projectId, $pluginVersion, $items, $translations, 'Nie udało się zaktualizować tej pracy.', 'danger', false, $this->postedSubmission($request, $user));
                    return;
                }
                $id = $submissionId;
            } else {
                $id = $this->translations->create(
                    $projectId,
                    $user?->id,
                    $this->bounded($request->postString('author_name', $user?->displayName ?? 'Anonim'), 160),
                    $this->bounded($request->postString('author_email', $user?->email ?? ''), 190),
                    $this->bounded($request->postString('title', 'Tłumaczenie pluginu'), 180),
                    $this->bounded($filename, 190),
                    $pluginVersion,
                    'editor',
                    $targetLanguage,
                    $source,
                    $translations,
                    $output,
                    count($items),
                    $translatedCount,
                    $status
                );
            }
            $this->audit->record($request, 'plugin_translation_submit', 'success', 'submission:' . $id . ':' . $status, $user?->id);
            $message = $status === 'ready_for_review'
                ? 'Tłumaczenie zapisane i oznaczone jako gotowe do sprawdzenia.'
                : 'Wersja robocza tłumaczenia została zapisana.';
            $this->renderUserSubmissions($message, 'success');
        } catch (\Throwable $exception) {
            $this->renderPublicStart($exception->getMessage(), 'danger');
        }
    }

    /**
     * @param list<array{token: string, path: list<string>, label: string, value: string}> $items
     * @param array<string, string> $translations
     */
    private function renderPublicEditor(
        string $source,
        string $filename,
        string $targetLanguage,
        int $projectId,
        string $pluginVersion,
        array $items,
        array $translations = [],
        string $message = '',
        string $variant = 'info',
        bool $showPreview = false,
        ?PluginTranslationSubmission $submission = null,
    ): void {
        $user = $this->auth->user();
        $this->theme->start_page('Edycja tłumaczenia - SyntaxDevTeam', 'Uzupełnij wartości tłumaczenia YAML.');
        $this->theme->start_header('Edycja tłumaczenia', 'Uzupełnij pola i zapisz szkic albo wyślij gotową pracę do sprawdzenia.', 'SyntaxDevTeam / Lokalizacja');
        $this->theme->end_header();
        $this->theme->start_section();
        if ($message !== '') {
            $this->theme->render_alert($message, $variant);
        }

        $this->renderTranslationForm($source, $filename, $targetLanguage, $projectId, $pluginVersion, $items, $translations, $user, $showPreview, $submission);
        $this->theme->end_section();
        $this->theme->end_page();
    }

    /**
     * @param list<array{token: string, path: list<string>, label: string, value: string}> $items
     * @param array<string, string> $translations
     */
    private function renderTranslationForm(
        string $source,
        string $filename,
        string $targetLanguage,
        int $projectId,
        string $pluginVersion,
        array $items,
        array $translations,
        ?User $user,
        bool $showPreview,
        ?PluginTranslationSubmission $submission = null,
    ): void {
        echo '<form class="showcase-card translation-workspace" action="index.php?route=/translations/submit" method="post">';
        $this->theme->csrf_field($this->security->csrfToken());
        $this->hidden('source_yaml', $source);
        $this->hidden('source_filename', $filename);
        $this->hidden('target_language', $targetLanguage);
        $this->hidden('project_id', (string) $projectId);
        $this->hidden('plugin_version', $pluginVersion);
        if ($submission instanceof PluginTranslationSubmission) {
            $this->hidden('submission_id', (string) $submission->id);
        }

        echo '<div class="translation-meta-grid">';
        $this->input('title', 'Nazwa tłumaczenia', $submission?->title ?? 'Tłumaczenie ' . $filename);
        $this->input('author_name', 'Autor', $submission?->authorName ?? $user?->displayName ?? '');
        $this->input('author_email', 'E-mail kontaktowy', $submission?->authorEmail ?? $user?->email ?? '', 'email');
        echo '<label class="translation-field"><span>Status zapisu</span><select class="form-select" name="status">';
        $selectedStatus = $submission?->status === 'ready_for_review' ? 'ready_for_review' : 'draft';
        echo '<option value="draft"' . ($selectedStatus === 'draft' ? ' selected' : '') . '>Kopia robocza</option>';
        echo '<option value="ready_for_review"' . ($selectedStatus === 'ready_for_review' ? ' selected' : '') . '>Gotowe do sprawdzenia</option>';
        echo '</select></label>';
        echo '</div>';

        echo '<div class="translation-editor" aria-label="Edytor tłumaczeń">';
        echo '<div class="translation-editor-head"><span>Oryginał</span><span>Twoje tłumaczenie</span></div>';
        foreach ($items as $item) {
            $value = $translations[$item['token']] ?? '';
            $textarea = strlen($item['value']) > 90 || strlen($value) > 90;
            echo '<div class="translation-row">';
            echo '<div class="translation-original"><small>' . $this->escape($item['label']) . '</small>';
            echo '<p>' . $this->escape($item['value']) . '</p></div>';
            echo '<label class="translation-input"><span class="visually-hidden">' . $this->escape($item['label']) . '</span>';
            if ($textarea) {
                echo '<textarea class="form-control" name="translations[' . $this->escape($item['token']) . ']" rows="3">';
                echo $this->escape($value) . '</textarea>';
            } else {
                echo '<input class="form-control" name="translations[' . $this->escape($item['token']) . ']" value="' . $this->escape($value) . '">';
            }
            echo '</label></div>';
        }
        echo '</div>';

        echo '<div class="translation-actions">';
        echo '<button class="btn btn-primary" type="submit" name="_action" value="save">Zapisz tłumaczenie</button>';
        echo '<button class="btn btn-outline-light" type="submit" name="_action" value="preview">Sprawdź formatowanie</button>';
        echo '</div>';
        if ($showPreview) {
            $this->renderFormattingPreview($items, $translations);
        }
        echo '</form>';
    }

    /**
     * @param list<PluginTranslationSubmission> $submissions
     */
    private function renderUserSubmissionsTable(array $submissions): void
    {
        echo '<div class="table-responsive"><table class="table table-hover align-middle admin-data-table">';
        echo '<thead><tr>';
        foreach (['Kategoria', 'Nazwa', 'Język', 'Wersja', 'Rodzaj', 'Status', 'Postęp', 'Aktualizacja', 'Akcja'] as $header) {
            echo '<th scope="col">' . $this->escape($header) . '</th>';
        }
        echo '</tr></thead><tbody>';
        foreach ($submissions as $submission) {
            echo '<tr>';
            echo '<td>' . $this->escape($submission->projectName) . '</td>';
            echo '<td>' . $this->escape($submission->title) . '</td>';
            echo '<td>' . $this->escape($submission->targetLanguage) . '</td>';
            echo '<td>' . $this->escape($submission->pluginVersion !== '' ? $submission->pluginVersion : '—') . '</td>';
            echo '<td>' . $this->escape($this->submissionKindLabel($submission->submissionKind)) . '</td>';
            echo '<td>' . $this->escape($this->statusLabel($submission->status)) . '</td>';
            echo '<td>' . $this->escape($submission->progressPercent . '% (' . $submission->translatedItems . '/' . $submission->totalItems . ')') . '</td>';
            echo '<td>' . $this->escape($submission->updatedAt) . '</td>';
            echo '<td>';
            if ($submission->submissionKind === 'completed_upload' && in_array($submission->status, ['ready_for_review', 'rejected'], true)) {
                echo '<a class="btn btn-sm btn-outline-light" href="index.php?route=/translations/upload-ready">Wyślij ponownie</a>';
            } elseif (in_array($submission->status, ['draft', 'ready_for_review', 'rejected'], true)) {
                echo '<a class="btn btn-sm btn-primary" href="index.php?route=/translations/edit&amp;id=' . $submission->id . '">Kontynuuj</a>';
            } else {
                echo '<span class="text-secondary">Zatwierdzone</span>';
            }
            echo '</td></tr>';
        }
        echo '</tbody></table></div>';
    }

    /**
     * @param list<array{token: string, path: list<string>, label: string, value: string}> $items
     * @param array<string, string> $translations
     */
    private function renderFormattingPreview(array $items, array $translations): void
    {
        echo '<section class="translation-preview" aria-label="Podgląd formatowania Minecraft">';
        echo '<h2 class="h4">Podgląd formatowania</h2>';
        foreach ($items as $item) {
            $value = $translations[$item['token']] ?? '';
            if (trim($value) === '') {
                continue;
            }
            $preview = $this->formatPreview->preview($value);
            echo '<div class="translation-preview-row"><small>' . $this->escape($item['label']) . '</small>';
            if ($preview['issues'] !== []) {
                echo '<ul class="translation-preview-issues">';
                foreach ($preview['issues'] as $issue) {
                    echo '<li>' . $this->escape($issue) . '</li>';
                }
                echo '</ul>';
            }
            if ($preview['variables'] !== []) {
                echo '<div class="translation-preview-vars">Zmienne: ' . $this->escape(implode(', ', $preview['variables'])) . '</div>';
            }
            echo '<p>';
            foreach ($preview['segments'] as $segment) {
                $styles = [];
                if ($segment['color'] !== '') {
                    $styles[] = 'color: ' . $segment['color'];
                }
                if ($segment['bold']) {
                    $styles[] = 'font-weight: 800';
                }
                if ($segment['italic']) {
                    $styles[] = 'font-style: italic';
                }
                $decorations = [];
                if ($segment['underline']) {
                    $decorations[] = 'underline';
                }
                if ($segment['strikethrough']) {
                    $decorations[] = 'line-through';
                }
                if ($decorations !== []) {
                    $styles[] = 'text-decoration: ' . implode(' ', $decorations);
                }
                echo '<span style="' . $this->escape(implode('; ', $styles)) . '">';
                echo $this->escape($segment['text']) . '</span>';
            }
            echo '</p></div>';
        }
        echo '</section>';
    }

    private function postedSubmission(Request $request, ?User $user): ?PluginTranslationSubmission
    {
        $id = $request->postInt('submission_id', 0) ?? 0;
        if ($id <= 0 || !$user instanceof User || $this->translations === null) {
            return null;
        }

        return $this->translations->findForUser($id, $user->id);
    }

    private function renderLoginRequired(
        string $source,
        string $filename,
        string $targetLanguage,
        array $translations,
        string $message,
        string $afterLogin = 'index.php?route=/translations/resume',
    ): void {
        $_SESSION['_miniportal_after_login'] = $afterLogin;
        $this->theme->start_page('Logowanie do tłumaczenia - SyntaxDevTeam', 'Zaloguj się, aby kontynuować tłumaczenie.');
        $this->theme->start_header('Zaloguj się do tłumaczenia', $message, 'SyntaxDevTeam / Lokalizacja');
        $this->theme->end_header();
        $this->theme->start_section();
        $this->theme->render_alert($message, 'warning');
        $this->theme->start_card('Praca zachowana', 'Sesja');
        $this->theme->render_text('Plik: ' . $filename . ', język docelowy: ' . $targetLanguage . ', przetłumaczone pola: ' . count(array_filter($translations, static fn (string $value): bool => trim($value) !== '')) . '.');
        $this->theme->render_button('Przejdź do logowania', 'index.php?route=/admin/login', 'primary');
        $this->theme->end_card();
        $this->theme->end_section();
        $this->theme->end_page();
    }

    private function renderAdminProjects(string $message = '', string $variant = 'info'): void
    {
        $this->startAdminPage('Kategorie tłumaczeń', 'Kategorie grupujące zaakceptowane i oczekujące pliki językowe pluginów, botów i innych projektów.', [[
            'label' => 'Wróć do kolejki',
            'href' => 'index.php?route=/admin/plugin-translator',
            'variant' => 'outline-light',
        ]]);
        if ($message !== '') {
            $this->theme->render_alert($message, $variant);
        }
        if ($this->translations === null) {
            $this->theme->render_alert('Translator wymaga aktywnego połączenia z bazą danych.', 'danger');
            $this->endAdminPage();
            return;
        }

        $projects = $this->translations->projects(true);
        $this->theme->start_admin_panel_grid('balanced');
        $this->theme->start_admin_panel('Kategorie tłumaczeń', count($projects) . ' pozycji');
        if ($projects === []) {
            $this->theme->render_alert('Katalog kategorii jest pusty.', 'info');
        } else {
            $this->theme->render_admin_action_table(
                ['Nazwa', 'Slug', 'Powiązana strona', 'Status', 'Zaakceptowane pliki'],
                array_map(fn (PluginTranslationProject $project): array => [
                    'cells' => [$project->name, $project->slug, $project->pageTitle !== '' ? $project->pageTitle : 'Brak', $project->status === 'active' ? 'Aktywny' : 'Ukryty', $project->approvedFiles],
                    'actions' => $this->adminProjectActions($project),
                ], $projects),
                $this->security->csrfToken()
            );
        }
        $this->theme->end_admin_panel();

        $this->theme->start_admin_panel('Dodaj kategorię', 'Nowa grupa plików językowych');
        $this->theme->render_form('index.php?route=/admin/plugin-translator/plugins', [[
            'name' => 'name',
            'label' => 'Nazwa kategorii',
            'type' => 'text',
        ], [
            'name' => 'slug',
            'label' => 'Slug',
            'type' => 'text',
            'help' => 'Małe litery, cyfry i myślniki.',
        ], [
            'name' => 'page_id',
            'label' => 'Powiązana strona',
            'type' => 'select',
            'options' => $this->translations->publishedPageOptions(),
        ]], 'Dodaj kategorię', $this->security->csrfToken());
        $this->theme->end_admin_panel();
        $this->theme->end_admin_panel_grid();
        $this->endAdminPage();
    }

    private function renderAdminProjectEdit(Request $request, string $message = '', string $variant = 'info'): void
    {
        $project = $this->translations?->project($request->queryInt('id', 0) ?? 0, true);
        if (!$project instanceof PluginTranslationProject || $project->slug === 'nieprzypisane') {
            $this->renderAdminProjects('Nie znaleziono edytowalnej kategorii.', 'danger');
            return;
        }
        $this->startAdminPage('Edytuj kategorię', 'Zmień nazwę, slug albo powiązaną stronę katalogu tłumaczeń.', [[
            'label' => 'Wróć do kategorii',
            'href' => 'index.php?route=/admin/plugin-translator/plugins',
            'variant' => 'outline-light',
        ]]);
        if ($message !== '') {
            $this->theme->render_alert($message, $variant);
        }
        $this->theme->start_admin_panel('Dane kategorii', $project->name);
        $this->theme->render_form('index.php?route=/admin/plugin-translator/plugins/edit', [[
            'name' => 'id',
            'label' => 'ID',
            'type' => 'hidden',
            'value' => (string) $project->id,
        ], [
            'name' => 'name',
            'label' => 'Nazwa kategorii',
            'type' => 'text',
            'value' => $project->name,
        ], [
            'name' => 'slug',
            'label' => 'Slug',
            'type' => 'text',
            'value' => $project->slug,
        ], [
            'name' => 'page_id',
            'label' => 'Powiązana strona',
            'type' => 'select',
            'value' => $project->pageId !== null ? (string) $project->pageId : '',
            'options' => $this->translations?->publishedPageOptions() ?? [],
        ]], 'Zapisz kategorię', $this->security->csrfToken());
        $this->theme->end_admin_panel();
        $this->endAdminPage();
    }

    private function updateAdminProject(Request $request): void
    {
        $actor = $this->auth->user();
        if (!$actor instanceof User || !$this->security->validateCsrfToken($request->postString('_token'))) {
            $this->renderAdminProjects('Token formularza jest nieprawidłowy lub wygasł.', 'danger');
            return;
        }
        if ($this->translations === null) {
            $this->renderAdminProjects('Translator wymaga aktywnego połączenia z bazą danych.', 'danger');
            return;
        }
        $id = $request->postInt('id', 0) ?? 0;
        try {
            $name = $this->bounded($request->postString('name'), 160);
            $slug = $this->normalizeSlug($request->postString('slug', $name));
            $pageId = $request->postInt('page_id', 0) ?? 0;
            if ($name === '' || $slug === '') {
                throw new \InvalidArgumentException('Nazwa i slug kategorii są wymagane.');
            }
            if ($pageId > 0 && !$this->translations->publishedPageExists($pageId)) {
                throw new \InvalidArgumentException('Wybrana strona nie istnieje albo nie jest opublikowana.');
            }
            if (!$this->translations->updateProject($id, $name, $slug, $pageId > 0 ? $pageId : null)) {
                throw new \RuntimeException('Nie udało się zaktualizować kategorii.');
            }
            $this->audit->record($request, 'plugin_translation_project_update', 'success', 'project:' . $id, $actor->id);
            $this->renderAdminProjects('Kategoria została zaktualizowana.', 'success');
        } catch (\Throwable $exception) {
            $this->audit->record($request, 'plugin_translation_project_update', 'failed', 'project:' . $id, $actor->id);
            $this->renderAdminProjects($exception->getMessage(), 'danger');
        }
    }

    private function createAdminProject(Request $request): void
    {
        $actor = $this->auth->user();
        if (!$actor instanceof User || !$this->security->validateCsrfToken($request->postString('_token'))) {
            $this->renderAdminProjects('Token formularza jest nieprawidłowy lub wygasł.', 'danger');
            return;
        }
        if ($this->translations === null) {
            $this->renderAdminProjects('Translator wymaga aktywnego połączenia z bazą danych.', 'danger');
            return;
        }
        try {
            $name = $this->bounded($request->postString('name'), 160);
            $slug = $this->normalizeSlug($request->postString('slug', $name));
            $pageId = $request->postInt('page_id', 0) ?? 0;
            if ($name === '' || $slug === '') {
                throw new \InvalidArgumentException('Nazwa i slug kategorii są wymagane.');
            }
            if ($pageId > 0 && !$this->translations->publishedPageExists($pageId)) {
                throw new \InvalidArgumentException('Wybrana strona nie istnieje albo nie jest opublikowana.');
            }
            $id = $this->translations->createProject($name, $slug, $pageId > 0 ? $pageId : null, $actor->id);
            $this->audit->record($request, 'plugin_translation_project_create', 'success', 'project:' . $id, $actor->id);
            $this->renderAdminProjects('Kategoria została dodana.', 'success');
        } catch (\Throwable $exception) {
            $this->audit->record($request, 'plugin_translation_project_create', 'failed', 'project', $actor->id);
            $this->renderAdminProjects($exception->getMessage(), 'danger');
        }
    }

    private function changeAdminProjectStatus(Request $request): void
    {
        $actor = $this->auth->user();
        if (!$actor instanceof User || !$this->security->validateCsrfToken($request->postString('_token'))) {
            $this->renderAdminProjects('Token formularza jest nieprawidłowy lub wygasł.', 'danger');
            return;
        }
        $id = $request->postInt('id', 0) ?? 0;
        $status = $request->postString('status');
        if ($this->translations === null || !$this->translations->setProjectStatus($id, $status)) {
            $this->renderAdminProjects('Nie udało się zmienić widoczności pluginu.', 'danger');
            return;
        }
        $this->audit->record($request, 'plugin_translation_project_status', $status, 'project:' . $id, $actor->id);
        $this->renderAdminProjects('Widoczność pluginu została zmieniona.', 'success');
    }

    private function deleteAdminProject(Request $request): void
    {
        $actor = $this->auth->user();
        if (!$actor instanceof User || !$this->security->validateCsrfToken($request->postString('_token'))) {
            $this->renderAdminProjects('Token formularza jest nieprawidłowy lub wygasł.', 'danger');
            return;
        }
        $id = $request->postInt('id', 0) ?? 0;
        if ($this->translations === null || !$this->translations->deleteProject($id)) {
            $this->renderAdminProjects('Nie udało się usunąć kategorii.', 'danger');
            return;
        }
        $this->audit->record($request, 'plugin_translation_project_delete', 'success', 'project:' . $id, $actor->id);
        $this->renderAdminProjects('Kategoria została usunięta, a jej zgłoszenia przeniesiono do Nieprzypisane.', 'success');
    }

    private function renderAdminQueue(string $message = '', string $variant = 'info'): void
    {
        $this->startAdminPage(
            'Translator YAML',
            'Podgląd prac użytkowników, statusów ukończenia i kolejki zatwierdzania.',
            [[
                'label' => 'Kategorie',
                'href' => 'index.php?route=/admin/plugin-translator/plugins',
                'variant' => 'outline-light',
            ], [
                'label' => 'Edytor pliku YAML',
                'href' => 'index.php?route=/admin/plugin-translator/tool',
                'variant' => 'outline-light',
            ], [
                'label' => 'Publiczny formularz',
                'href' => 'index.php?route=/translations',
                'variant' => 'primary',
            ]]
        );
        if ($message !== '') {
            $this->theme->render_alert($message, $variant);
        }
        if ($this->translations === null) {
            $this->theme->render_alert('Translator wymaga aktywnego połączenia z bazą danych.', 'danger');
            $this->endAdminPage();
            return;
        }

        $submissions = $this->translations->all();
        $this->theme->start_admin_panel('Prace tłumaczeniowe', count($submissions) . ' zgłoszeń');
        if ($submissions === []) {
            $this->theme->render_alert('Nie ma jeszcze zapisanych prac tłumaczeniowych.', 'info');
        } else {
            $this->theme->render_admin_action_table(
                ['ID', 'Kategoria', 'Nazwa', 'Język', 'Wersja', 'Rodzaj', 'Autor', 'Status', 'Postęp', 'Aktualizacja'],
                array_map(
                    fn (PluginTranslationSubmission $submission): array => [
                        'cells' => [
                            $submission->id,
                            $submission->projectName,
                            $submission->title,
                            $submission->targetLanguage,
                            $submission->pluginVersion !== '' ? $submission->pluginVersion : '—',
                            $this->submissionKindLabel($submission->submissionKind),
                            $submission->authorName,
                            $this->statusLabel($submission->status),
                            $submission->progressPercent . '% (' . $submission->translatedItems . '/' . $submission->totalItems . ')',
                            $submission->updatedAt,
                        ],
                        'actions' => $this->adminSubmissionActions($submission),
                    ],
                    $submissions
                ),
                $this->security->csrfToken()
            );
        }
        $this->theme->end_admin_panel();
        $this->endAdminPage();
    }

    private function renderAdminSubmission(Request $request, string $message = '', string $variant = 'info'): void
    {
        $submission = $this->translations?->find($request->queryInt('id', 0) ?? 0);
        if (!$submission instanceof PluginTranslationSubmission) {
            $this->renderAdminQueue('Nie znaleziono zgłoszenia tłumaczenia.', 'danger');
            return;
        }

        $this->startAdminPage(
            'Podgląd tłumaczenia',
            'Porównanie oryginału i wygenerowanego YAML przed decyzją administracyjną.',
            [[
                'label' => 'Wróć do kolejki',
                'href' => 'index.php?route=/admin/plugin-translator',
                'variant' => 'outline-light',
            ], [
                'label' => 'Pobierz YAML',
                'href' => 'index.php?route=/admin/plugin-translator/download&id=' . $submission->id,
                'variant' => 'primary',
            ]]
        );
        if ($message !== '') {
            $this->theme->render_alert($message, $variant);
        }

        $sourceItems = $this->yaml->flatten($this->yaml->parse($submission->sourceYaml));
        $translationItems = $this->yaml->flatten($this->yaml->parse($submission->outputYaml));
        $translatedByLabel = [];
        foreach ($translationItems as $item) {
            $translatedByLabel[$item['label']] = $item['value'];
        }

        $this->theme->start_admin_metrics();
        $this->theme->render_admin_metric('Status', $this->statusLabel($submission->status), 'ST', $submission->title);
        $this->theme->render_admin_metric('Kategoria', $submission->projectName, 'KT', $submission->pluginVersion !== '' ? $submission->pluginVersion : 'Wersja niepodana');
        $this->theme->render_admin_metric('Język', $submission->targetLanguage, 'LG', $this->submissionKindLabel($submission->submissionKind));
        $this->theme->render_admin_metric('Postęp', $submission->progressPercent . '%', 'PR', $submission->translatedItems . '/' . $submission->totalItems);
        $this->theme->render_admin_metric('Autor', $submission->authorName, 'AU', $submission->authorEmail);
        $this->theme->end_admin_metrics();

        $this->theme->start_admin_panel_grid('balanced');
        $this->theme->start_admin_panel('Różnice', $submission->sourceFilename);
        $this->theme->render_admin_table(
            ['Klucz', 'Oryginał', 'Tłumaczenie'],
            array_map(
                static fn (array $item): array => [
                    $item['label'],
                    $item['value'],
                    $translatedByLabel[$item['label']] ?? '',
                ],
                $sourceItems
            )
        );
        $this->theme->end_admin_panel();

        $this->theme->start_admin_panel('Decyzja', 'Gotowe tłumaczenia można zatwierdzić albo odrzucić');
        $this->theme->render_form(
            'index.php?route=/admin/plugin-translator/review',
            [[
                'name' => 'id',
                'label' => 'ID',
                'type' => 'hidden',
                'value' => (string) $submission->id,
            ], [
                'name' => 'status',
                'label' => 'Decyzja',
                'type' => 'select',
                'value' => 'approved',
                'options' => [
                    'approved' => 'Zatwierdź',
                    'rejected' => 'Odrzuć',
                ],
            ], [
                'name' => 'note',
                'label' => 'Notatka',
                'type' => 'textarea',
                'value' => $submission->reviewNote,
                'rows' => 4,
            ]],
            'Zapisz decyzję',
            $this->security->csrfToken()
        );
        $this->theme->end_admin_panel();
        $this->theme->end_admin_panel_grid();
        $this->endAdminPage();
    }

    private function reviewSubmission(Request $request): void
    {
        $actor = $this->auth->user();
        if (!$this->security->validateCsrfToken($request->postString('_token'))) {
            $this->audit->record($request, 'plugin_translation_review', 'invalid_csrf', 'submission', $actor?->id);
            $this->renderAdminQueue('Token CSRF jest nieprawidłowy lub wygasł.', 'danger');
            return;
        }
        $id = $request->postInt('id', 0) ?? 0;
        $status = $request->postString('status');
        if ($actor === null || $this->translations === null || !$this->translations->review($id, $actor->id, $status, $this->bounded($request->postString('note'), 500))) {
            $this->audit->record($request, 'plugin_translation_review', 'failed', 'submission:' . $id, $actor?->id);
            $this->renderAdminQueue('Nie udało się zapisać decyzji.', 'danger');
            return;
        }

        $this->audit->record($request, 'plugin_translation_review', $status, 'submission:' . $id, $actor->id);
        $this->renderAdminQueue('Decyzja została zapisana.', 'success');
    }

    private function deleteSubmission(Request $request): void
    {
        $actor = $this->auth->user();
        if (!$actor instanceof User || !$this->security->validateCsrfToken($request->postString('_token'))) {
            $this->renderAdminQueue('Token formularza jest nieprawidłowy lub wygasł.', 'danger');
            return;
        }
        $id = $request->postInt('id', 0) ?? 0;
        if ($this->translations === null || !$this->translations->deleteSubmission($id)) {
            $this->renderAdminQueue('Nie udało się usunąć zgłoszenia.', 'danger');
            return;
        }
        $this->audit->record($request, 'plugin_translation_delete', 'success', 'submission:' . $id, $actor->id);
        $this->renderAdminQueue('Zgłoszenie zostało usunięte.', 'success');
    }

    private function downloadSubmission(Request $request): void
    {
        $submission = $this->translations?->find($request->queryInt('id', 0) ?? 0);
        if (!$submission instanceof PluginTranslationSubmission) {
            $this->renderAdminQueue('Nie znaleziono zgłoszenia tłumaczenia.', 'danger');
            return;
        }

        header('Content-Type: application/x-yaml; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $this->downloadFilename($submission) . '"');
        header('X-Content-Type-Options: nosniff');
        echo $submission->outputYaml;
    }

    private function renderUpload(string $message = '', string $variant = 'info', string $source = ''): void
    {
        $this->startAdminPage(
            'Edytor pliku YAML',
            'Wgraj plik YAML, zmień jego wartości i pobierz poprawną składniowo wersję pod oryginalną nazwą.',
            [[
                'label' => 'Kolejka prac',
                'href' => 'index.php?route=/admin/plugin-translator',
                'variant' => 'outline-light',
            ]]
        );
        if ($message !== '') {
            $this->theme->render_alert($message, $variant);
        }

        $this->theme->start_admin_panel('Plik do edycji', 'Pliki .yml / .yaml, limit 256 KB');
        $this->theme->render_form(
            'index.php?route=/admin/plugin-translator/tool',
            [[
                'name' => 'yaml_file',
                'label' => 'Plik YAML',
                'type' => 'file',
                'accept' => '.yml,.yaml,text/yaml,text/x-yaml,text/plain',
            ], [
                'name' => 'source_yaml',
                'label' => 'Treść YAML',
                'type' => 'textarea',
                'value' => $source,
                'rows' => 16,
            ]],
            'Otwórz translator',
            $this->security->csrfToken()
        );
        $this->theme->end_admin_panel();
        $this->endAdminPage();
    }

    private function openAdminToolEditor(Request $request): void
    {
        $actor = $this->auth->user();
        $source = $request->postString('source_yaml');
        if (!$this->security->validateCsrfToken($request->postString('_token'))) {
            $this->audit->record($request, 'plugin_translation_open', 'invalid_csrf', 'yaml', $actor?->id);
            $this->renderUpload('Token CSRF jest nieprawidłowy lub wygasł.', 'danger', $source);
            return;
        }

        try {
            $filename = 'messages.yml';
            $source = $this->sourceYaml($request, $source, $filename);
            $items = $this->yaml->flatten($this->yaml->parse($source));
            if ($items === []) {
                throw new \InvalidArgumentException('Nie znaleziono żadnych tekstów do tłumaczenia.');
            }
            $this->audit->record($request, 'plugin_translation_open', 'success', 'items:' . count($items), $actor?->id);
            $this->renderToolEditor($source, $filename, $items);
        } catch (\Throwable $exception) {
            $this->audit->record($request, 'plugin_translation_open', 'failed', 'yaml', $actor?->id);
            $this->renderUpload($exception->getMessage(), 'danger', $source);
        }
    }

    private function exportTool(Request $request): void
    {
        $actor = $this->auth->user();
        $source = $request->postString('source_yaml');
        if (!$this->security->validateCsrfToken($request->postString('_token'))) {
            $this->audit->record($request, 'plugin_translation_export', 'invalid_csrf', 'yaml', $actor?->id);
            $this->renderUpload('Token CSRF jest nieprawidłowy lub wygasł.', 'danger', $source);
            return;
        }

        try {
            $parsed = $this->yaml->parse($source);
            $output = $this->yaml->dump($this->yaml->translated($parsed, $request->postArray('translations')));
            $this->yaml->parse($output);
            $filename = $this->safeYamlFilename($request->postString('source_filename', 'messages.yml'));
            $this->audit->record($request, 'plugin_translation_export', 'success', 'items:' . count($this->yaml->flatten($parsed)), $actor?->id);
            header('Content-Type: application/x-yaml; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('X-Content-Type-Options: nosniff');
            echo $output;
        } catch (\Throwable $exception) {
            $this->audit->record($request, 'plugin_translation_export', 'failed', 'yaml', $actor?->id);
            $this->renderUpload($exception->getMessage(), 'danger', $source);
        }
    }

    /**
     * @param list<array{token: string, path: list<string>, label: string, value: string}> $items
     */
    private function renderToolEditor(string $source, string $filename, array $items): void
    {
        $filename = $this->safeYamlFilename($filename);
        $this->startAdminPage(
            'Edytor pliku YAML',
            'Edytujesz ' . $filename . '. Pobrany plik zachowa tę nazwę.',
            [[
                'label' => 'Wczytaj inny plik',
                'href' => 'index.php?route=/admin/plugin-translator/tool',
                'variant' => 'outline-light',
            ]]
        );

        $fields = [[
            'name' => 'source_yaml',
            'label' => 'Źródłowy YAML',
            'type' => 'hidden',
            'value' => $source,
        ], [
            'name' => 'source_filename',
            'label' => 'Nazwa pliku',
            'type' => 'hidden',
            'value' => $filename,
        ]];
        foreach ($items as $item) {
            $fields[] = [
                'name' => 'translations[' . $item['token'] . ']',
                'label' => $item['label'],
                'type' => strlen($item['value']) > 90 ? 'textarea' : 'text',
                'value' => $item['value'],
                'rows' => 4,
                'help' => 'Oryginał: ' . $item['value'],
            ];
        }

        $this->theme->start_admin_panel_grid('balanced');
        $this->theme->start_admin_panel('Oryginał', count($items) . ' tekstów');
        $this->theme->render_admin_table(['Klucz', 'Treść'], array_map(static fn (array $item): array => [$item['label'], $item['value']], $items));
        $this->theme->end_admin_panel();
        $this->theme->start_admin_panel('Nowe tłumaczenie', 'Eksport z walidacją YAML');
        $this->theme->render_form('index.php?route=/admin/plugin-translator/export', $fields, 'Pobierz ' . $filename, $this->security->csrfToken());
        $this->theme->end_admin_panel();
        $this->theme->end_admin_panel_grid();
        $this->endAdminPage();
    }

    private function sourceYaml(Request $request, string $fallback, string &$filename): string
    {
        $file = $request->file('yaml_file');
        if ($file === null || $file['error'] === UPLOAD_ERR_NO_FILE) {
            return $fallback;
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('Nie udało się odebrać pliku YAML.');
        }
        $name = strtolower($file['name']);
        if (!str_ends_with($name, '.yml') && !str_ends_with($name, '.yaml')) {
            throw new \RuntimeException('Translator przyjmuje wyłącznie pliki .yml albo .yaml.');
        }
        if ($file['size'] > 262144) {
            throw new \RuntimeException('Plik YAML jest za duży. Limit translatora to 256 KB.');
        }
        $content = file_get_contents($file['tmp_name']);
        if ($content === false) {
            throw new \RuntimeException('Nie można odczytać pliku YAML.');
        }
        $filename = $file['name'];

        return $content;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function storeResume(array $data): void
    {
        $_SESSION['_plugin_translation_resume'] = [
            'source_yaml' => (string) ($data['source_yaml'] ?? ''),
            'source_filename' => (string) ($data['source_filename'] ?? 'messages.yml'),
            'target_language' => $this->normalizeLanguage((string) ($data['target_language'] ?? 'EN')),
            'project_id' => (int) ($data['project_id'] ?? 0),
            'plugin_version' => $this->bounded((string) ($data['plugin_version'] ?? ''), 40),
            'translations' => is_array($data['translations'] ?? null) ? $this->normalizedTranslations($data['translations']) : [],
            'title' => (string) ($data['title'] ?? ''),
            'author_name' => (string) ($data['author_name'] ?? ''),
            'author_email' => (string) ($data['author_email'] ?? ''),
            'status' => (string) ($data['status'] ?? 'draft'),
        ];
        $_SESSION['_miniportal_after_login'] = 'index.php?route=/translations/resume';
    }

    /**
     * @return array{source_yaml: string, source_filename: string, target_language: string, project_id: int, plugin_version: string, translations: array<string, string>, title: string, author_name: string, author_email: string, status: string}|null
     */
    private function resumeData(): ?array
    {
        $resume = $_SESSION['_plugin_translation_resume'] ?? null;
        if (!is_array($resume) || ($resume['source_yaml'] ?? '') === '') {
            return null;
        }

        return [
            'source_yaml' => (string) $resume['source_yaml'],
            'source_filename' => (string) ($resume['source_filename'] ?? 'messages.yml'),
            'target_language' => $this->normalizeLanguage((string) ($resume['target_language'] ?? 'EN')),
            'project_id' => (int) ($resume['project_id'] ?? 0),
            'plugin_version' => $this->bounded((string) ($resume['plugin_version'] ?? ''), 40),
            'translations' => is_array($resume['translations'] ?? null) ? $this->normalizedTranslations($resume['translations']) : [],
            'title' => (string) ($resume['title'] ?? ''),
            'author_name' => (string) ($resume['author_name'] ?? ''),
            'author_email' => (string) ($resume['author_email'] ?? ''),
            'status' => (string) ($resume['status'] ?? 'draft'),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function normalizedTranslations(array $values): array
    {
        $translations = [];
        foreach ($values as $key => $value) {
            if (is_scalar($value)) {
                $translations[(string) $key] = (string) $value;
            }
        }

        return $translations;
    }

    /**
     * @return array<string, string>
     */
    private function targetLanguages(): array
    {
        return [
            'AA' => 'Afar (AA)', 'AB' => 'Abchaski (AB)', 'AE' => 'Awestyjski (AE)', 'AF' => 'Afrikaans (AF)',
            'AK' => 'Akan (AK)', 'AM' => 'Amharski (AM)', 'AN' => 'Aragoński (AN)', 'AR' => 'Arabski (AR)',
            'AS' => 'Asamski (AS)', 'AV' => 'Awarski (AV)', 'AY' => 'Ajmara (AY)', 'AZ' => 'Azerbejdżański (AZ)',
            'BA' => 'Baszkirski (BA)', 'BE' => 'Białoruski (BE)', 'BG' => 'Bułgarski (BG)', 'BH' => 'Bihari (BH)',
            'BI' => 'Bislama (BI)', 'BM' => 'Bambara (BM)', 'BN' => 'Bengalski (BN)', 'BO' => 'Tybetański (BO)',
            'BR' => 'Bretoński (BR)', 'BS' => 'Bośniacki (BS)', 'CA' => 'Kataloński (CA)', 'CE' => 'Czeczeński (CE)',
            'CH' => 'Chamorro (CH)', 'CO' => 'Korsykański (CO)', 'CR' => 'Kri (CR)', 'CS' => 'Czeski (CS)',
            'CU' => 'Cerkiewnosłowiański (CU)', 'CV' => 'Czuwaski (CV)', 'CY' => 'Walijski (CY)', 'DA' => 'Duński (DA)',
            'DE' => 'Niemiecki (DE)', 'DV' => 'Divehi (DV)', 'DZ' => 'Dzongkha (DZ)', 'EE' => 'Ewe (EE)',
            'EL' => 'Grecki (EL)', 'EN' => 'Angielski (EN)', 'EO' => 'Esperanto (EO)', 'ES' => 'Hiszpański (ES)',
            'ET' => 'Estoński (ET)', 'EU' => 'Baskijski (EU)', 'FA' => 'Perski (FA)', 'FF' => 'Fula (FF)',
            'FI' => 'Fiński (FI)', 'FJ' => 'Fidżyjski (FJ)', 'FO' => 'Farerski (FO)', 'FR' => 'Francuski (FR)',
            'FY' => 'Fryzyjski (FY)', 'GA' => 'Irlandzki (GA)', 'GD' => 'Szkocki gaelicki (GD)', 'GL' => 'Galicyjski (GL)',
            'GN' => 'Guarani (GN)', 'GU' => 'Gudżarati (GU)', 'GV' => 'Manx (GV)', 'HA' => 'Hausa (HA)',
            'HE' => 'Hebrajski (HE)', 'HI' => 'Hindi (HI)', 'HO' => 'Hiri motu (HO)', 'HR' => 'Chorwacki (HR)',
            'HT' => 'Haitański (HT)', 'HU' => 'Węgierski (HU)', 'HY' => 'Ormiański (HY)', 'HZ' => 'Herero (HZ)',
            'IA' => 'Interlingua (IA)', 'ID' => 'Indonezyjski (ID)', 'IE' => 'Interlingue (IE)', 'IG' => 'Igbo (IG)',
            'II' => 'Nuosu (II)', 'IK' => 'Inupiak (IK)', 'IO' => 'Ido (IO)', 'IS' => 'Islandzki (IS)',
            'IT' => 'Włoski (IT)', 'IU' => 'Inuktitut (IU)', 'JA' => 'Japoński (JA)', 'JV' => 'Jawajski (JV)',
            'KA' => 'Gruziński (KA)', 'KG' => 'Kongo (KG)', 'KI' => 'Kikuju (KI)', 'KJ' => 'Kwanyama (KJ)',
            'KK' => 'Kazachski (KK)', 'KL' => 'Grenlandzki (KL)', 'KM' => 'Khmerski (KM)', 'KN' => 'Kannada (KN)',
            'KO' => 'Koreański (KO)', 'KR' => 'Kanuri (KR)', 'KS' => 'Kaszmirski (KS)', 'KU' => 'Kurdyjski (KU)',
            'KV' => 'Komi (KV)', 'KW' => 'Kornijski (KW)', 'KY' => 'Kirgiski (KY)', 'LA' => 'Łaciński (LA)',
            'LB' => 'Luksemburski (LB)', 'LG' => 'Ganda (LG)', 'LI' => 'Limburski (LI)', 'LN' => 'Lingala (LN)',
            'LO' => 'Laotański (LO)', 'LT' => 'Litewski (LT)', 'LU' => 'Luba-katanga (LU)', 'LV' => 'Łotewski (LV)',
            'MG' => 'Malgaski (MG)', 'MH' => 'Marszalski (MH)', 'MI' => 'Maoryski (MI)', 'MK' => 'Macedoński (MK)',
            'ML' => 'Malajalam (ML)', 'MN' => 'Mongolski (MN)', 'MR' => 'Marathi (MR)', 'MS' => 'Malajski (MS)',
            'MT' => 'Maltański (MT)', 'MY' => 'Birmański (MY)', 'NA' => 'Nauru (NA)', 'NB' => 'Norweski bokmål (NB)',
            'ND' => 'Ndebele północny (ND)', 'NE' => 'Nepalski (NE)', 'NG' => 'Ndonga (NG)', 'NL' => 'Niderlandzki (NL)',
            'NN' => 'Norweski nynorsk (NN)', 'NO' => 'Norweski (NO)', 'NR' => 'Ndebele południowy (NR)', 'NV' => 'Nawaho (NV)',
            'NY' => 'Nyanja (NY)', 'OC' => 'Oksytański (OC)', 'OJ' => 'Odżibwe (OJ)', 'OM' => 'Oromo (OM)',
            'OR' => 'Orija (OR)', 'OS' => 'Osetyjski (OS)', 'PA' => 'Pendżabski (PA)', 'PI' => 'Pali (PI)',
            'PL' => 'Polski (PL)', 'PS' => 'Paszto (PS)', 'PT' => 'Portugalski (PT)', 'QU' => 'Keczua (QU)',
            'RM' => 'Retoromański (RM)', 'RN' => 'Rundi (RN)', 'RO' => 'Rumuński (RO)', 'RU' => 'Rosyjski (RU)',
            'RW' => 'Kinyarwanda (RW)', 'SA' => 'Sanskryt (SA)', 'SC' => 'Sardyński (SC)', 'SD' => 'Sindhi (SD)',
            'SE' => 'Północnosaamski (SE)', 'SG' => 'Sango (SG)', 'SI' => 'Syngaleski (SI)', 'SK' => 'Słowacki (SK)',
            'SL' => 'Słoweński (SL)', 'SM' => 'Samoański (SM)', 'SN' => 'Shona (SN)', 'SO' => 'Somalijski (SO)',
            'SQ' => 'Albański (SQ)', 'SR' => 'Serbski (SR)', 'SS' => 'Swati (SS)', 'ST' => 'Sotho południowy (ST)',
            'SU' => 'Sundajski (SU)', 'SV' => 'Szwedzki (SV)', 'SW' => 'Suahili (SW)', 'TA' => 'Tamilski (TA)',
            'TE' => 'Telugu (TE)', 'TG' => 'Tadżycki (TG)', 'TH' => 'Tajski (TH)', 'TI' => 'Tigrinia (TI)',
            'TK' => 'Turkmeński (TK)', 'TL' => 'Tagalski (TL)', 'TN' => 'Tswana (TN)', 'TO' => 'Tonga (TO)',
            'TR' => 'Turecki (TR)', 'TS' => 'Tsonga (TS)', 'TT' => 'Tatarski (TT)', 'TW' => 'Twi (TW)',
            'TY' => 'Tahitański (TY)', 'UG' => 'Ujgurski (UG)', 'UK' => 'Ukraiński (UK)', 'UR' => 'Urdu (UR)',
            'UZ' => 'Uzbecki (UZ)', 'VE' => 'Venda (VE)', 'VI' => 'Wietnamski (VI)', 'VO' => 'Volapük (VO)',
            'WA' => 'Waloński (WA)', 'WO' => 'Wolof (WO)', 'XH' => 'Xhosa (XH)', 'YI' => 'Jidysz (YI)',
            'YO' => 'Joruba (YO)', 'ZA' => 'Zhuang (ZA)', 'ZH' => 'Chiński (ZH)', 'ZU' => 'Zulu (ZU)',
        ];
    }

    private function normalizeLanguage(string $language): string
    {
        $language = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $language) ?? 'EN', 0, 2));

        return array_key_exists($language, $this->targetLanguages()) ? $language : 'EN';
    }

    /**
     * @param list<PluginTranslationProject> $projects
     * @return array<string, string>
     */
    private function projectOptions(array $projects): array
    {
        $options = [];
        foreach ($projects as $project) {
            $options[(string) $project->id] = $project->name;
        }

        return $options;
    }

    /**
     * @param list<PluginTranslationSubmission> $files
     */
    private function renderApprovedFilesTable(array $files): void
    {
        echo '<div class="table-responsive"><table class="table table-hover align-middle admin-data-table">';
        echo '<thead><tr><th>Język</th><th>Wersja</th><th>Plik</th><th>Autor</th><th>Akcja</th></tr></thead><tbody>';
        foreach ($files as $file) {
            echo '<tr><td>' . $this->escape($file->targetLanguage) . '</td>';
            echo '<td>' . $this->escape($file->pluginVersion !== '' ? $file->pluginVersion : '—') . '</td>';
            echo '<td>' . $this->escape($file->sourceFilename) . '</td>';
            echo '<td>' . $this->escape($file->authorName) . '</td>';
            echo '<td><a class="btn btn-sm btn-primary" href="index.php?route=/translations/download&amp;id=' . $file->id . '">Pobierz YAML</a></td></tr>';
        }
        echo '</tbody></table></div>';
    }

    private function downloadFilename(PluginTranslationSubmission $submission): string
    {
        return 'messages_' . strtolower($submission->targetLanguage) . '.yml';
    }

    private function safeYamlFilename(string $filename): string
    {
        $filename = basename(str_replace('\\', '/', trim($filename)));
        $filename = preg_replace('/[\x00-\x1F\x7F"\']/', '-', $filename) ?? '';
        if ($filename === '' || (!str_ends_with(strtolower($filename), '.yml') && !str_ends_with(strtolower($filename), '.yaml'))) {
            return 'messages.yml';
        }

        return substr($filename, 0, 190);
    }

    private function normalizeSlug(string $value): string
    {
        $value = strtr(strtolower(trim($value)), [
            'ą' => 'a', 'ć' => 'c', 'ę' => 'e', 'ł' => 'l', 'ń' => 'n',
            'ó' => 'o', 'ś' => 's', 'ź' => 'z', 'ż' => 'z',
        ]);
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';

        return trim(substr($value, 0, 160), '-');
    }

    private function submissionKindLabel(string $kind): string
    {
        return $kind === 'completed_upload' ? 'Gotowy plik' : 'Edytor';
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function adminProjectActions(PluginTranslationProject $project): array
    {
        if ($project->slug === 'nieprzypisane') {
            return [];
        }

        return [[
            'label' => 'Edytuj',
            'href' => 'index.php?route=/admin/plugin-translator/plugins/edit&id=' . $project->id,
            'variant' => 'primary',
        ], [
            'label' => $project->status === 'active' ? 'Ukryj' : 'Pokaż',
            'action' => 'index.php?route=/admin/plugin-translator/plugins/status',
            'variant' => 'outline-light',
            'fields' => ['id' => $project->id, 'status' => $project->status === 'active' ? 'hidden' : 'active'],
        ], [
            'label' => 'Usuń',
            'action' => 'index.php?route=/admin/plugin-translator/plugins/delete',
            'variant' => 'danger',
            'fields' => ['id' => $project->id],
            'confirm' => 'Usunąć kategorię? Przypisane zgłoszenia zostaną przeniesione do Nieprzypisane.',
        ]];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function adminSubmissionActions(PluginTranslationSubmission $submission): array
    {
        $actions = [[
            'label' => 'Podgląd',
            'href' => 'index.php?route=/admin/plugin-translator/view&id=' . $submission->id,
            'variant' => $submission->status === 'ready_for_review' ? 'primary' : 'outline-light',
        ], [
            'label' => 'Pobierz',
            'href' => 'index.php?route=/admin/plugin-translator/download&id=' . $submission->id,
            'variant' => 'outline-light',
        ]];
        if ($submission->status !== 'approved') {
            $actions[] = [
                'label' => 'Zatwierdź',
                'action' => 'index.php?route=/admin/plugin-translator/review',
                'variant' => 'success',
                'fields' => ['id' => $submission->id, 'status' => 'approved', 'note' => ''],
            ];
        }
        if ($submission->status !== 'rejected') {
            $actions[] = [
                'label' => 'Odrzuć',
                'action' => 'index.php?route=/admin/plugin-translator/review',
                'variant' => 'warning',
                'fields' => ['id' => $submission->id, 'status' => 'rejected', 'note' => ''],
                'confirm' => 'Odrzucić to zgłoszenie?',
            ];
        }
        $actions[] = [
            'label' => 'Usuń',
            'action' => 'index.php?route=/admin/plugin-translator/delete',
            'variant' => 'danger',
            'fields' => ['id' => $submission->id],
            'confirm' => 'Trwale usunąć zgłoszenie tłumaczenia?',
        ];

        return $actions;
    }

    private function hidden(string $name, string $value): void
    {
        echo '<input type="hidden" name="' . $this->escape($name) . '" value="' . $this->escape($value) . '">';
    }

    private function input(string $name, string $label, string $value, string $type = 'text'): void
    {
        $type = in_array($type, ['text', 'email'], true) ? $type : 'text';
        echo '<label class="translation-field"><span>' . $this->escape($label) . '</span>';
        echo '<input class="form-control" type="' . $type . '" name="' . $this->escape($name) . '" value="' . $this->escape($value) . '">';
        echo '</label>';
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function statusLabel(string $status): string
    {
        return [
            'draft' => 'Wersja robocza',
            'ready_for_review' => 'Gotowe do zatwierdzenia',
            'approved' => 'Zatwierdzone',
            'rejected' => 'Odrzucone',
        ][$status] ?? $status;
    }

    private function bounded(string $value, int $max): string
    {
        if (function_exists('mb_substr')) {
            return mb_substr(trim($value), 0, $max);
        }

        return substr(trim($value), 0, $max);
    }

    /**
     * @param callable(): void $handler
     */
    private function guard(Request $request, string $permission, callable $handler): void
    {
        $user = $this->auth->user();
        if (!$user instanceof User) {
            $this->theme->render_admin_access_state(401, 'Wymagane logowanie', 'Zaloguj się, aby używać translatora YAML.', 'index.php?route=/admin/login', 'Przejdź do logowania');
            return;
        }

        if (!$this->hasPermission($user->permissions, $permission)) {
            $this->audit->record($request, 'admin_access', 'denied', $permission, $user->id);
            $this->theme->render_admin_access_state(403, 'Brak uprawnień', 'Twoje konto nie ma uprawnienia: ' . $permission, 'index.php?route=/admin', 'Wróć do panelu');
            return;
        }

        $handler();
    }

    /**
     * @param list<string> $permissions
     */
    private function hasPermission(array $permissions, string $permission): bool
    {
        return in_array('*', $permissions, true) || in_array($permission, $permissions, true);
    }

    /**
     * @param array{label: string, href: string, variant?: string}|list<array{label: string, href: string, variant?: string}>|null $actions
     */
    private function startAdminPage(string $title, string $lead, ?array $actions = null): void
    {
        $user = $this->auth->user();
        $this->theme->start_admin_page($title, $this->menu->visibleFor($user?->permissions ?? []), '/admin/plugin-translator', [
            'name' => $user?->displayName ?? 'Gość',
            'role' => $user?->primaryRole() ?? 'Gość',
            'initials' => $user?->initials() ?? 'G',
            'avatar_url' => $user?->avatarUrl ?? '',
            'logout_action' => 'index.php?route=/admin/logout',
            'logout_token' => $this->security->csrfToken(),
            'profile_links' => [
                ['label' => 'Pokaż profil', 'href' => 'index.php?route=/admin/profile'],
                ['label' => 'Edytuj dane', 'href' => 'index.php?route=/admin/profile/edit'],
                ['label' => 'Połączone konta', 'href' => 'index.php?route=/admin/profile/identities'],
                ['label' => 'Ustawienia avatara', 'href' => 'index.php?route=/admin/profile/avatar'],
                ['label' => 'Bezpieczeństwo', 'href' => 'index.php?route=/admin/profile/security'],
            ],
        ]);
        $this->theme->start_admin_content(
            $title,
            $lead,
            [['label' => 'Panel', 'href' => 'index.php?route=/admin'], ['label' => 'Translator YAML', 'href' => 'index.php?route=/admin/plugin-translator']],
            $actions
        );
    }

    private function endAdminPage(): void
    {
        $this->theme->end_admin_content();
        $this->theme->end_admin_page();
    }
}
