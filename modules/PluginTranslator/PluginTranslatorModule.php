<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\PluginTranslator;

use SyntaxDevTeam\Cms\Core\AdminMenuRegistry;
use SyntaxDevTeam\Cms\Core\DownloadResponse;
use SyntaxDevTeam\Cms\Core\ModuleInterface;
use SyntaxDevTeam\Cms\Core\PublicNavigationProviderInterface;
use SyntaxDevTeam\Cms\Core\PublicNavigationRegistry;
use SyntaxDevTeam\Cms\Core\Request;
use SyntaxDevTeam\Cms\Core\Router;
use SyntaxDevTeam\Cms\Core\Security;
use SyntaxDevTeam\Cms\Core\SeoIndex;
use SyntaxDevTeam\Cms\Core\SeoProviderInterface;
use SyntaxDevTeam\Cms\Core\ThemeInterface;
use SyntaxDevTeam\Cms\Modules\CoreAuth\AdminAccessGate;
use SyntaxDevTeam\Cms\Modules\CoreAuth\AuditLogService;
use SyntaxDevTeam\Cms\Modules\CoreAuth\AuthService;
use SyntaxDevTeam\Cms\Modules\CoreAuth\User;

final class PluginTranslatorModule implements ModuleInterface, PublicNavigationProviderInterface, SeoProviderInterface
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
        return '1.4.9';
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
        $menu->add('Narzędzia', 'Translator YAML', '/admin/plugin-translator', 'TR', 'plugin_translator.review', 10);
    }

    public function registerPublicNavigation(PublicNavigationRegistry $navigation): void
    {
        $navigation->add('plugin_translator.index', 'Translations', '/translations', 'none', 70);
    }

    public function registerRoutes(Router $router): void
    {
        $router->get('/translations', fn (Request $request) => $this->renderPublicStart(
            activeTab: $this->normalizePublicTab($request->queryString('tab'))
        ));
        $router->get('/translations/assets/{asset}', fn (Request $request) => $this->servePublicAsset($request));
        $router->get('/translations/project', fn (Request $request) => $this->renderPublicProject($request));
        $router->get('/translations/upload-ready', fn (Request $request) => $this->renderReadyUpload());
        $router->post('/translations/upload-ready', fn (Request $request) => $this->submitReadyUpload($request));
        $router->get('/translations/download', fn (Request $request) => $this->downloadApprovedSubmission($request));
        $router->get('/translations/suggest', fn (Request $request) => $this->suggestApprovedSubmission($request));
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

    public function registerSeo(SeoIndex $seo): void
    {
        $projects = $this->translations?->projects() ?? [];
        $lastModified = null;
        foreach ($projects as $project) {
            if ($lastModified === null || $project->updatedAt > $lastModified) {
                $lastModified = $project->updatedAt;
            }
        }
        $seo->add(
            '/translations',
            'YAML translations - SyntaxDevTeam',
            'Translate YAML message files and browse approved SyntaxDevTeam language files.',
            $lastModified,
            0.6,
            'weekly',
            'CollectionPage'
        );
        foreach ($projects as $project) {
            if ($project->slug === 'nieprzypisane') {
                continue;
            }
            $seo->add(
                '/translations/project?slug=' . rawurlencode($project->slug),
                $project->name . ' translations',
                'Approved language files for ' . $project->name . '.',
                $project->updatedAt,
                0.5,
                'weekly',
                'CollectionPage'
            );
        }
    }

    private function renderPublicStart(string $message = '', string $variant = 'info', string $activeTab = 'start'): void
    {
        $this->theme->start_page('YAML translations - SyntaxDevTeam', 'Translate YAML message files for any plugin, with optional SyntaxDevTeam catalog support.');
        $this->theme->start_header('YAML translations', 'Open a YAML message file, translate it safely and decide what to do with the result when the work is ready.', 'SyntaxDevTeam / Localization');
        $this->theme->end_header();
        $this->theme->start_section();
        if ($message !== '') {
            $this->theme->render_alert($message, $variant);
        }
        if ($this->translations === null) {
            $this->theme->render_alert('The translator requires an active database connection.', 'danger');
            $this->theme->end_section();
            $this->theme->end_page();
            return;
        }

        $projects = $this->publicProjects($this->translations->projects());
        $activeTab = $this->normalizePublicTab($activeTab);
        echo '<div class="translation-hub">';
        $this->renderPublicIntro();
        $this->renderPublicTabs($activeTab);
        if ($activeTab === 'mine') {
            $this->renderUserSubmissionsContent();
        } elseif ($activeTab === 'upload') {
            $this->renderReadyUploadContent($projects);
        } else {
            $this->renderStartTranslationContent($projects);
        }
        echo '</div>';

        $latestFiles = array_slice($this->publicFiles($this->translations->recentApproved(12)), 0, 6);
        $this->renderLatestDownloads($latestFiles);

        $this->theme->start_card('Available projects', count($projects) . ' categories');
        if ($projects === []) {
            $this->theme->render_alert('No translation categories have been added yet.', 'info');
        } else {
            $this->renderProjectCatalog($projects);
        }
        $this->theme->end_card();
        $this->theme->end_section();
        $this->theme->end_page();
    }

    private function renderPublicIntro(): void
    {
        $this->theme->start_card('Welcome', 'YAML workflow');
        $this->theme->render_text('This tool helps you translate message files for any plugin or bot. Upload a YAML file, open the editor and work line by line without changing the original key structure.');
        $this->theme->render_text('You can keep translations as drafts, submit SyntaxDevTeam plugin translations for review, or download finished work when you only need a local file.');
        $this->theme->end_card();
    }

    /** @param list<PluginTranslationProject> $projects */
    private function renderStartTranslationContent(array $projects): void
    {
        unset($projects);

        $this->theme->start_card('Start a translation', 'Open editor');
        $this->theme->render_form('index.php?route=/translations/open', [
            [
                'name' => 'yaml_file', 'label' => 'YAML file', 'type' => 'file',
                'accept' => '.yml,.yaml,text/yaml,text/x-yaml,text/plain',
                'help' => 'Choose a .yml or .yaml message file. The editor will keep the original key structure.',
            ], [
                'name' => 'target_language', 'label' => 'Target language', 'type' => 'hidden',
                'value' => 'EN',
            ],
        ], 'Open editor', $this->security->csrfToken());
        $this->theme->end_card();
    }

    private function renderPublicTabs(string $activeTab): void
    {
        $tabs = [
            'start' => ['Start translation', ''],
            'mine' => ['My drafts', 'mine'],
            'upload' => ['Submit ready file', 'upload'],
        ];
        echo '<nav class="translation-tabs" aria-label="Translation tools">';
        foreach ($tabs as $tab => [$label, $query]) {
            $href = 'index.php?route=/translations' . ($query !== '' ? '&amp;tab=' . $query : '');
            echo '<a class="translation-tab' . ($tab === $activeTab ? ' is-active' : '') . '" href="' . $href . '"';
            echo $tab === $activeTab ? ' aria-current="page"' : '';
            echo '>' . $this->escape($label) . '</a>';
        }
        echo '</nav>';
    }

    /**
     * @param list<PluginTranslationSubmission> $files
     */
    private function renderLatestDownloads(array $files): void
    {
        $files = $this->publicFiles($files);
        $this->theme->start_card('Latest downloads', $files === [] ? 'No approved files yet' : count($files) . ' ready files');
        if ($files === []) {
            $this->theme->render_text('Approved translations will appear here as soon as the team reviews them.');
            $this->theme->end_card();
            return;
        }

        echo '<div class="table-responsive"><table class="table table-hover align-middle admin-data-table">';
        echo '<thead><tr><th scope="col">Project</th><th scope="col">Language</th><th scope="col">Version</th><th scope="col">Updated</th><th scope="col">Actions</th></tr></thead><tbody>';
        foreach ($files as $file) {
            echo '<tr>';
            echo '<td><a class="translation-catalog-link" href="index.php?route=/translations/project&amp;id=' . $file->projectId . '">' . $this->escape($file->projectName) . '</a></td>';
            echo '<td>' . $this->languageBadge($file->targetLanguage) . '</td>';
            echo '<td>' . $this->escape($file->pluginVersion !== '' ? $file->pluginVersion : 'Any version') . '</td>';
            echo '<td>' . $this->escape($this->publicDate($file->reviewedAt ?? $file->updatedAt)) . '</td>';
            echo '<td><div class="translation-table-actions">';
            echo '<a class="btn btn-sm btn-primary" href="index.php?route=/translations/download&amp;id=' . $file->id . '">Download YAML</a>';
            echo '<a class="btn btn-sm btn-outline-light" href="index.php?route=/translations/suggest&amp;id=' . $file->id . '">Suggest correction</a>';
            echo '</div></td>';
            echo '</tr>';
        }
        echo '</tbody></table></div>';
        $this->theme->end_card();
    }

    /** @param list<PluginTranslationProject> $projects */
    private function renderProjectCatalog(array $projects): void
    {
        $projects = $this->publicProjects($projects);
        echo '<div class="table-responsive"><table class="table table-hover align-middle admin-data-table">';
        echo '<thead><tr><th scope="col">Project</th><th scope="col">Approved files</th><th scope="col">Project page</th><th scope="col">Action</th></tr></thead><tbody>';
        foreach ($projects as $project) {
            echo '<tr>';
            echo '<td><a class="translation-catalog-link" href="index.php?route=/translations/project&amp;id=' . $project->id . '">' . $this->escape($project->name) . '</a></td>';
            echo '<td>' . $this->escape($project->approvedFiles . ' ready ' . ($project->approvedFiles === 1 ? 'file' : 'files')) . '</td>';
            echo '<td>';
            if ($project->pageSlug !== '') {
                echo '<a class="btn btn-sm btn-outline-light" href="/p/' . $this->escape($project->pageSlug) . '">Project page</a>';
            } else {
                echo '<span class="text-secondary">Catalog only</span>';
            }
            echo '</td>';
            echo '<td><a class="btn btn-sm btn-primary" href="index.php?route=/translations/project&amp;id=' . $project->id . '">Open translations</a></td>';
            echo '</tr>';
        }
        echo '</tbody></table></div>';
    }

    private function renderPublicProject(Request $request): void
    {
        if ($this->translations === null) {
            $this->renderPublicStart('The translator requires an active database connection.', 'danger');
            return;
        }
        $project = $this->translations->project($request->queryInt('id', 0) ?? 0);
        if (!$project instanceof PluginTranslationProject || $project->slug === 'nieprzypisane') {
            $this->renderPublicStart('Plugin not found in the translation catalog.', 'danger');
            return;
        }
        $files = $this->publicFiles($this->translations->approvedForProject($project->id));
        $languageFilter = $this->normalizeOptionalLanguage($request->queryString('language'));
        $visibleFiles = $languageFilter === ''
            ? $files
            : array_values(array_filter(
                $files,
                static fn (PluginTranslationSubmission $file): bool => $file->targetLanguage === $languageFilter
            ));
        $this->theme->start_page($project->name . ' - translations', 'Approved language files for ' . $project->name . '.');
        $this->theme->start_header($project->name, 'Approved YAML translation files.', 'SyntaxDevTeam / Localization');
        $this->theme->end_header();
        $this->theme->start_section();
        $this->theme->start_card('Language files', count($visibleFiles) . ' files');
        if ($files === []) {
            $this->theme->render_alert('This plugin does not have approved translations yet.', 'info');
        } elseif ($visibleFiles === []) {
            $this->renderLanguageFilter($project, $files, $languageFilter);
            $this->theme->render_alert('No approved files match this language filter.', 'info');
        } else {
            $this->renderLanguageFilter($project, $files, $languageFilter);
            $this->renderApprovedFilesTable($visibleFiles);
        }
        $this->theme->render_button('Back to catalog', 'index.php?route=/translations', 'outline-light');
        if ($project->pageSlug !== '') {
            $this->theme->render_button('Project page', '/p/' . $project->pageSlug, 'primary');
        }
        $this->theme->end_card();
        $this->theme->end_section();
        $this->theme->end_page();
    }

    private function renderReadyUpload(string $message = '', string $variant = 'info'): void
    {
        $this->renderPublicStart($message, $variant, 'upload');
    }

    /** @param list<PluginTranslationProject> $projects */
    private function renderReadyUploadContent(array $projects): void
    {
        $user = $this->auth->user();
        $this->theme->start_card('Submit a ready file', 'For review');
        if (!$user instanceof User) {
            $_SESSION['_miniportal_after_login'] = 'index.php?route=/translations&tab=upload';
            $this->theme->render_alert('Sign in to submit a ready file for review.', 'warning');
            $this->theme->render_button('Go to sign in', 'index.php?route=/admin/login', 'primary');
            $this->theme->end_card();
            return;
        }
        $this->theme->render_form('index.php?route=/translations/upload-ready', [[
                'name' => 'syntaxdevteam_plugin',
                'label' => 'Plugin SyntaxDevTeam',
                'type' => 'checkbox',
                'help' => 'Optionally assign the ready file to the SyntaxDevTeam plugin catalog.',
            ], [
                'name' => 'project_id',
                'label' => 'Translation category',
                'type' => 'select',
                'options' => $this->projectOptions($projects),
            ], [
                'name' => 'plugin_version',
                'label' => 'Project version',
                'type' => 'text',
                'help' => 'Optional, for example 2.4.1.',
            ], [
                'name' => 'target_language',
                'label' => 'File language',
                'type' => 'select',
                'value' => 'PL',
                'options' => $this->targetLanguages(),
            ], [
                'name' => 'title',
                'label' => 'Submission name',
                'type' => 'text',
                'value' => 'Ready translation',
            ], [
                'name' => 'yaml_file',
                'label' => 'Ready YAML file',
                'type' => 'file',
                'accept' => '.yml,.yaml,text/yaml,text/x-yaml,text/plain',
            ]], 'Submit for review', $this->security->csrfToken());
        $this->theme->end_card();
    }

    private function submitReadyUpload(Request $request): void
    {
        $user = $this->auth->user();
        if (!$user instanceof User) {
            $this->renderReadyUpload('Your session expired. Please sign in again.', 'warning');
            return;
        }
        if (!$this->security->validateCsrfToken($request->postString('_token'))) {
            $this->renderReadyUpload('The form token is invalid or expired.', 'danger');
            return;
        }
        if ($this->translations === null) {
            $this->renderPublicStart('The translator requires an active database connection.', 'danger');
            return;
        }

        try {
            $projectId = $this->submissionProjectId($request);
            $filename = 'translation.yml';
            $source = $this->sourceYaml($request, '', $filename);
            $parsed = $this->yaml->parse($source);
            $items = $this->yaml->flatten($parsed);
            if ($items === []) {
                throw new \InvalidArgumentException('The ready file does not contain any text lines.');
            }
            $id = $this->translations->create(
                $projectId,
                $user->id,
                $user->displayName,
                $user->email,
                $this->bounded($request->postString('title', 'Ready translation'), 180),
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
            $this->renderUserSubmissions('The ready file has been submitted for review.', 'success');
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
            || $project->slug === 'nieprzypisane'
            || $submission->status !== 'approved') {
            $this->renderPublicStart('Approved translation file not found.', 'danger');
            return;
        }

        DownloadResponse::sendString($submission->outputYaml, $this->downloadFilename($submission), 'application/x-yaml; charset=utf-8');
    }

    private function servePublicAsset(Request $request): void
    {
        $asset = $request->routeString('asset');
        $files = [
            'autosave.js' => ['assets/autosave.js', 'application/javascript; charset=UTF-8'],
        ];
        if (!isset($files[$asset])) {
            http_response_code(404);
            return;
        }

        [$file, $contentType] = $files[$asset];
        $path = __DIR__ . '/' . $file;
        if (!is_file($path)) {
            http_response_code(404);
            return;
        }

        header('Content-Type: ' . $contentType);
        header('Cache-Control: public, max-age=86400');
        readfile($path);
    }

    private function suggestApprovedSubmission(Request $request): void
    {
        $submission = $this->translations?->find($request->queryInt('id', 0) ?? 0);
        $project = $submission instanceof PluginTranslationSubmission
            ? $this->translations?->project($submission->projectId)
            : null;
        if (!$submission instanceof PluginTranslationSubmission
            || !$project instanceof PluginTranslationProject
            || $project->slug === 'nieprzypisane'
            || $submission->status !== 'approved') {
            $this->renderPublicStart('Approved translation for correction was not found.', 'danger');
            return;
        }

        if (!$this->auth->user() instanceof User) {
            $this->renderLoginRequired(
                $submission->outputYaml,
                $this->downloadFilename($submission),
                $submission->targetLanguage,
                [],
                'Sign in to suggest a correction for this translation.',
                'index.php?route=/translations/suggest&id=' . $submission->id
            );
            return;
        }

        try {
            $source = $submission->submissionKind === 'editor'
                ? $submission->sourceYaml
                : $submission->outputYaml;
            $items = $this->yaml->flatten($this->yaml->parse($source));
            if ($submission->submissionKind === 'editor') {
                $stored = json_decode($submission->translationsJson, true, 512, JSON_THROW_ON_ERROR);
                $translations = is_array($stored) ? $this->normalizedTranslations($stored) : [];
            } else {
                $translations = [];
                foreach ($items as $item) {
                    $translations[$item['token']] = $item['value'];
                }
            }
            $this->renderPublicEditor(
                $source,
                $submission->sourceFilename,
                $submission->targetLanguage,
                $submission->projectId,
                $submission->pluginVersion,
                $items,
                $translations,
                'A new correction proposal has been created. The approved file remains unchanged.',
                'info',
                false,
                $submission,
                true
            );
        } catch (\Throwable $exception) {
            $this->renderPublicStart($exception->getMessage(), 'danger');
        }
    }

    private function renderUserSubmissions(string $message = '', string $variant = 'info'): void
    {
        $this->renderPublicStart($message, $variant, 'mine');
    }

    private function renderUserSubmissionsContent(): void
    {
        $user = $this->auth->user();
        $this->theme->start_card('My drafts', 'Saved work');
        if (!$user instanceof User) {
            $_SESSION['_miniportal_after_login'] = 'index.php?route=/translations&tab=mine';
            $this->theme->render_alert('Sign in to view your translation drafts.', 'warning');
            $this->theme->render_button('Go to sign in', 'index.php?route=/admin/login', 'primary');
            $this->theme->end_card();
            return;
        }
        $submissions = $this->translations?->forUser($user->id) ?? [];
        if ($submissions === []) {
            $this->theme->render_alert('You do not have saved translations yet.', 'info');
        } else {
            $this->renderUserSubmissionsTable($submissions);
        }
        $this->theme->end_card();
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
            $this->renderPublicStart('The translator requires an active database connection.', 'danger');
            return;
        }

        $submission = $this->translations->findForUser($request->queryInt('id', 0) ?? 0, $user->id);
        if (!$submission instanceof PluginTranslationSubmission) {
            $this->renderUserSubmissions('Your translation work was not found.', 'danger');
            return;
        }
        if ($submission->submissionKind !== 'editor') {
            $this->renderUserSubmissions('A ready file can be replaced by submitting a new upload.', 'warning');
            return;
        }
        if (!in_array($submission->status, ['draft', 'ready_for_review', 'rejected'], true)) {
            $this->renderUserSubmissions('An approved translation cannot be edited anymore.', 'warning');
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
                'Saved work has been loaded. You can continue editing.',
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
            $this->renderPublicStart('The form token is invalid or expired.', 'danger');
            return;
        }

        try {
            $user = $this->auth->user();
            $filename = 'messages.yml';
            $source = $this->sourceYaml($request, $source, $filename);
            $targetLanguage = $this->normalizeLanguage($request->postString('target_language', 'EN'));
            $projectId = $this->submissionProjectId($request);
            $pluginVersion = $this->bounded($request->postString('plugin_version'), 40);
            $parsed = $this->yaml->parse($source);
            $items = $this->yaml->flatten($parsed);
            if ($items === []) {
                throw new \InvalidArgumentException('No text lines were found for translation.');
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
                $this->renderLoginRequired($source, $filename, $targetLanguage, [], 'Sign in to start translating. The file has been kept in this session.');
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
            $this->renderPublicStart('Saved translation work was not found.', 'warning');
            return;
        }
        if (!$this->auth->user() instanceof User) {
            $this->renderLoginRequired(
                $resume['source_yaml'],
                $resume['source_filename'],
                $resume['target_language'],
                $resume['translations'],
                'Sign in to continue translating.'
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
                'You can continue the saved translation.',
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
            $this->renderPublicStart('The form token is invalid or expired.', 'danger');
            return;
        }
        if ($this->translations === null) {
            $this->renderPublicStart('The translator requires an active database connection.', 'danger');
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
            $action = $request->postString('_action', 'decide');
            if ($action === 'save') {
                $action = 'save_draft';
            }
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
                $this->renderLoginRequired($source, $filename, $targetLanguage, $translations, 'Sign in to save the translation. Your entered fields have been kept.');
                return;
            }
            $projectId = $this->existingOrFallbackProjectId($projectId);
            if ($action === 'preview') {
                $this->renderPublicEditor($source, $filename, $targetLanguage, $projectId, $pluginVersion, $items, $translations, 'Formatting checked. The HTML preview is below the form.', 'success', true, $this->postedSubmission($request, $user));
                return;
            }
            $translated = $this->yaml->translated($parsed, $translations);
            $output = $this->yaml->dump($translated);
            $this->yaml->parse($output);

            $translatedCount = $this->yaml->translatedCount($items, $translations);
            if ($action === 'decide') {
                $this->renderTranslationDecision($source, $filename, $targetLanguage, $projectId, $pluginVersion, $items, $translations, $translatedCount, $this->postedSubmission($request, $user));
                return;
            }
            if ($action === 'discard') {
                unset($_SESSION['_plugin_translation_resume']);
                $this->renderPublicStart('The temporary translation result has been discarded.', 'success');
                return;
            }
            if ($action === 'download') {
                $this->audit->record($request, 'plugin_translation_download_local', 'success', 'items:' . $translatedCount, $user->id);
                DownloadResponse::sendString($output, $this->safeYamlFilename($filename), 'application/x-yaml; charset=utf-8');
                return;
            }

            $status = $action === 'submit_review' ? 'ready_for_review' : 'draft';
            $syntaxDevTeamPlugin = $request->postBool('syntaxdevteam_plugin');
            if ($status === 'ready_for_review' && !$syntaxDevTeamPlugin) {
                $this->renderTranslationDecision($source, $filename, $targetLanguage, $projectId, $pluginVersion, $items, $translations, $translatedCount, $this->postedSubmission($request, $user), 'Only SyntaxDevTeam plugin translations can be submitted for review.', 'danger');
                return;
            }
            if ($status === 'ready_for_review' && $translatedCount < count($items)) {
                $this->renderTranslationDecision($source, $filename, $targetLanguage, $projectId, $pluginVersion, $items, $translations, $translatedCount, $this->postedSubmission($request, $user), 'A translation can be submitted for review only after all text lines are completed.', 'danger');
                return;
            }
            if ($syntaxDevTeamPlugin) {
                $projectId = $this->submissionProjectId($request);
            } else {
                $projectId = $this->translations->fallbackProjectId();
            }
            $pluginVersion = $this->bounded($request->postString('plugin_version', $pluginVersion), 40);

            $submissionId = $request->postInt('submission_id', 0) ?? 0;
            if ($submissionId > 0 && $user instanceof User) {
                $existing = $this->translations->findForUser($submissionId, $user->id);
                if (!$existing instanceof PluginTranslationSubmission || !in_array($existing->status, ['draft', 'ready_for_review', 'rejected'], true)) {
                    $this->renderPublicEditor($source, $filename, $targetLanguage, $projectId, $pluginVersion, $items, $translations, 'You cannot edit this translation work.', 'danger');
                    return;
                }
                $updated = $this->translations->updateUserSubmission(
                    $submissionId,
                    $user->id,
                    $projectId,
                    $this->bounded($request->postString('author_name', $user->displayName), 160),
                    $this->bounded($request->postString('author_email', $user->email), 190),
                    $this->bounded($request->postString('title', 'Plugin translation'), 180),
                    $pluginVersion,
                    $targetLanguage,
                    $translations,
                    $output,
                    count($items),
                    $translatedCount,
                    $status
                );
                if (!$updated) {
                    $this->renderPublicEditor($source, $filename, $targetLanguage, $projectId, $pluginVersion, $items, $translations, 'This translation work could not be updated.', 'danger', false, $this->postedSubmission($request, $user));
                    return;
                }
                $id = $submissionId;
            } else {
                $id = $this->translations->create(
                    $projectId,
                    $user?->id,
                    $this->bounded($request->postString('author_name', $user?->displayName ?? 'Anonim'), 160),
                    $this->bounded($request->postString('author_email', $user?->email ?? ''), 190),
                    $this->bounded($request->postString('title', 'Plugin translation'), 180),
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
                ? 'The translation has been submitted for SyntaxDevTeam review.'
                : 'The translation draft has been saved.';
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
        bool $cloneSubmission = false,
    ): void {
        $user = $this->auth->user();
        $this->theme->start_page('Edit translation - SyntaxDevTeam', 'Fill in the YAML translation values.');
        $this->theme->start_header('Edit translation', 'Fill in the fields and save a draft or submit ready work for review.', 'SyntaxDevTeam / Localization');
        $this->theme->end_header();
        $this->theme->start_section();
        if ($message !== '') {
            $this->theme->render_alert($message, $variant);
        }

        $this->renderTranslationForm($source, $filename, $targetLanguage, $projectId, $pluginVersion, $items, $translations, $user, $showPreview, $submission, $cloneSubmission);
        $this->theme->end_section();
        $this->renderAutosaveScript();
        $this->theme->end_page();
    }

    /**
     * @param list<array{token: string, path: list<string>, label: string, value: string}> $items
     * @param array<string, string> $translations
     */
    private function renderTranslationDecision(
        string $source,
        string $filename,
        string $targetLanguage,
        int $projectId,
        string $pluginVersion,
        array $items,
        array $translations,
        int $translatedCount,
        ?PluginTranslationSubmission $submission = null,
        string $message = '',
        string $variant = 'info',
    ): void {
        $user = $this->auth->user();
        $projects = $this->translations?->projects() ?? [];
        $projects = $this->publicProjects($projects);
        $selectedProject = $this->translations?->project($projectId);
        $syntaxDevTeamSelected = $selectedProject instanceof PluginTranslationProject
            && $selectedProject->slug !== 'nieprzypisane';
        $complete = $translatedCount >= count($items);

        $this->theme->start_page('Finish translation - SyntaxDevTeam', 'Choose what to do with the translated YAML file.');
        $this->theme->start_header('Finish translation', 'Download the file, keep it as a draft or submit it for SyntaxDevTeam review.', 'SyntaxDevTeam / Localization');
        $this->theme->end_header();
        $this->theme->start_section();
        if ($message !== '') {
            $this->theme->render_alert($message, $variant);
        }

        $this->theme->start_card('Translation summary', $translatedCount . '/' . count($items) . ' lines');
        $this->theme->render_text('File: ' . $this->safeYamlFilename($filename) . ', target language: ' . $targetLanguage . '.');
        $this->theme->render_text($complete ? 'All lines are translated. The file can be submitted for review.' : 'You can download or save this work now. Review submission becomes available after every line is translated.');
        $this->theme->end_card();

        echo '<form class="showcase-card translation-decision" action="index.php?route=/translations/submit" method="post" data-translation-decision data-complete="' . ($complete ? '1' : '0') . '">';
        $this->theme->csrf_field($this->security->csrfToken());
        $this->hidden('source_yaml', $source);
        $this->hidden('source_filename', $filename);
        $this->hidden('autosave_key', $this->autosaveKey($source, $filename, $submission));
        if ($submission instanceof PluginTranslationSubmission) {
            $this->hidden('submission_id', (string) $submission->id);
        }
        $this->hiddenTranslations($translations);

        echo '<div class="translation-meta-grid">';
        $this->input('title', 'Translation name', $submission?->title ?? 'Translation ' . $this->safeYamlFilename($filename));
        $this->input('author_name', 'Author', $submission?->authorName ?? $user?->displayName ?? '');
        $this->input('author_email', 'Contact e-mail', $submission?->authorEmail ?? $user?->email ?? '', 'email');
        echo '<label class="translation-field"><span>Target language</span><select class="form-select" name="target_language">';
        foreach ($this->targetLanguages() as $code => $label) {
            echo '<option value="' . $this->escape($code) . '"' . ($code === $targetLanguage ? ' selected' : '') . '>' . $this->escape($label) . '</option>';
        }
        echo '</select></label>';
        echo '</div>';

        echo '<div class="alert alert-secondary" data-decision-note>';
        echo $this->escape($syntaxDevTeamSelected ? 'SyntaxDevTeam review is available for complete translations assigned to an active category.' : 'For third-party plugins you can download the file now or keep a private draft.');
        echo '</div>';

        echo '<fieldset class="translation-decision-group">';
        echo '<legend>Project ownership</legend>';
        echo '<label class="form-check">';
        echo '<input class="form-check-input" type="checkbox" name="syntaxdevteam_plugin" value="1" data-decision-owner' . ($syntaxDevTeamSelected ? ' checked' : '') . '>';
        echo '<span class="form-check-label">This file is for a SyntaxDevTeam plugin</span>';
        echo '</label>';
        echo '<div data-syntaxdevteam-fields>';
        echo '<label class="translation-field"><span>SyntaxDevTeam category</span><select class="form-select" name="project_id">';
        foreach ($this->projectOptions($projects) as $value => $label) {
            echo '<option value="' . $this->escape($value) . '"' . ((string) $projectId === $value ? ' selected' : '') . '>' . $this->escape($label) . '</option>';
        }
        echo '</select></label>';
        $this->input('plugin_version', 'Project version', $submission?->pluginVersion ?? $pluginVersion);
        echo '</div>';
        echo '</fieldset>';

        echo '<div class="translation-actions">';
        echo '<button class="btn btn-primary" type="submit" name="_action" value="download">Download YAML</button>';
        echo '<button class="btn btn-outline-light" type="submit" name="_action" value="save_draft">Save draft</button>';
        echo '<button class="btn btn-outline-light" type="submit" name="_action" value="submit_review" data-review-action>Submit for review</button>';
        echo '<button class="btn btn-outline-light" type="submit" name="_action" value="discard">Discard</button>';
        echo '<button class="btn btn-outline-light" type="submit" name="_action" value="preview">Back to editor</button>';
        echo '</div>';
        echo '</form>';

        $this->theme->end_section();
        $this->renderAutosaveScript();
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
        bool $cloneSubmission = false,
    ): void {
        $autosaveKey = $this->autosaveKey($source, $filename, $submission);
        echo '<form class="showcase-card translation-workspace" action="index.php?route=/translations/submit" method="post" data-translation-autosave data-autosave-key="' . $this->escape($autosaveKey) . '">';
        $this->theme->csrf_field($this->security->csrfToken());
        $this->hidden('source_yaml', $source);
        $this->hidden('source_filename', $filename);
        $this->hidden('target_language', $targetLanguage);
        $this->hidden('project_id', (string) $projectId);
        $this->hidden('plugin_version', $pluginVersion);
        $this->hidden('autosave_key', $autosaveKey);
        if ($submission instanceof PluginTranslationSubmission && !$cloneSubmission) {
            $this->hidden('submission_id', (string) $submission->id);
        }

        echo '<div class="alert alert-secondary translation-autosave-status" data-autosave-status hidden>Autosave is ready.</div>';
        echo '<div class="translation-editor" aria-label="Translation editor">';
        echo '<div class="translation-editor-head"><span>Original</span><span>Your translation</span></div>';
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
        echo '<button class="btn btn-primary" type="submit" name="_action" value="decide">Continue</button>';
        echo '<button class="btn btn-outline-light" type="submit" name="_action" value="preview">Check formatting</button>';
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
        foreach (['Category', 'Name', 'Language', 'Version', 'Kind', 'Status', 'Progress', 'Updated', 'Action'] as $header) {
            echo '<th scope="col">' . $this->escape($header) . '</th>';
        }
        echo '</tr></thead><tbody>';
        foreach ($submissions as $submission) {
            echo '<tr>';
            echo '<td>' . $this->escape($this->publicProjectName($submission->projectName)) . '</td>';
            echo '<td>' . $this->escape($submission->title) . '</td>';
            echo '<td>' . $this->escape($submission->targetLanguage) . '</td>';
            echo '<td>' . $this->escape($submission->pluginVersion !== '' ? $submission->pluginVersion : '—') . '</td>';
            echo '<td>' . $this->escape($this->submissionKindLabel($submission->submissionKind)) . '</td>';
            echo '<td>' . $this->escape($this->publicStatusLabel($submission->status)) . '</td>';
            echo '<td>' . $this->escape($submission->progressPercent . '% (' . $submission->translatedItems . '/' . $submission->totalItems . ')') . '</td>';
            echo '<td>' . $this->escape($submission->updatedAt) . '</td>';
            echo '<td>';
            if ($submission->submissionKind === 'completed_upload' && in_array($submission->status, ['ready_for_review', 'rejected'], true)) {
                echo '<a class="btn btn-sm btn-outline-light" href="index.php?route=/translations&amp;tab=upload">Submit again</a>';
            } elseif (in_array($submission->status, ['draft', 'ready_for_review', 'rejected'], true)) {
                echo '<a class="btn btn-sm btn-primary" href="index.php?route=/translations/edit&amp;id=' . $submission->id . '">Continue</a>';
            } else {
                echo '<span class="text-secondary">Approved</span>';
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
        echo '<section class="translation-preview" aria-label="Minecraft formatting preview">';
        echo '<h2 class="h4">Formatting preview</h2>';
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
                echo '<div class="translation-preview-vars">Variables: ' . $this->escape(implode(', ', $preview['variables'])) . '</div>';
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
        $this->theme->start_page('Translation sign-in - SyntaxDevTeam', 'Sign in to continue translating.');
        $this->theme->start_header('Sign in to translate', $message, 'SyntaxDevTeam / Localization');
        $this->theme->end_header();
        $this->theme->start_section();
        $this->theme->render_alert($message, 'warning');
        $this->theme->start_card('Work preserved', 'Session');
        $this->theme->render_text('File: ' . $filename . ', target language: ' . $targetLanguage . ', translated fields: ' . count(array_filter($translations, static fn (string $value): bool => trim($value) !== '')) . '.');
        $this->theme->render_button('Go to sign in', 'index.php?route=/admin/login', 'primary');
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

        DownloadResponse::sendString($submission->outputYaml, $this->downloadFilename($submission), 'application/x-yaml; charset=utf-8');
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
            DownloadResponse::sendString($output, $filename, 'application/x-yaml; charset=utf-8');
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
            throw new \RuntimeException('The YAML file upload could not be received.');
        }
        $name = strtolower($file['name']);
        if (!str_ends_with($name, '.yml') && !str_ends_with($name, '.yaml')) {
            throw new \RuntimeException('The translator accepts only .yml or .yaml files.');
        }
        if ($file['size'] > 262144) {
            throw new \RuntimeException('The YAML file is too large. The translator limit is 256 KB.');
        }
        $content = file_get_contents($file['tmp_name']);
        if ($content === false) {
            throw new \RuntimeException('The YAML file could not be read.');
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
            'AA' => 'Afar (AA)', 'AB' => 'Abkhazian (AB)', 'AE' => 'Avestan (AE)', 'AF' => 'Afrikaans (AF)',
            'AK' => 'Akan (AK)', 'AM' => 'Amharic (AM)', 'AN' => 'Aragonese (AN)', 'AR' => 'Arabic (AR)',
            'AS' => 'Assamese (AS)', 'AV' => 'Avaric (AV)', 'AY' => 'Aymara (AY)', 'AZ' => 'Azerbaijani (AZ)',
            'BA' => 'Bashkir (BA)', 'BE' => 'Belarusian (BE)', 'BG' => 'Bulgarian (BG)', 'BH' => 'Bihari (BH)',
            'BI' => 'Bislama (BI)', 'BM' => 'Bambara (BM)', 'BN' => 'Bengali (BN)', 'BO' => 'Tibetan (BO)',
            'BR' => 'Breton (BR)', 'BS' => 'Bosnian (BS)', 'CA' => 'Catalan (CA)', 'CE' => 'Chechen (CE)',
            'CH' => 'Chamorro (CH)', 'CO' => 'Corsican (CO)', 'CR' => 'Cree (CR)', 'CS' => 'Czech (CS)',
            'CU' => 'Church Slavic (CU)', 'CV' => 'Chuvash (CV)', 'CY' => 'Welsh (CY)', 'DA' => 'Danish (DA)',
            'DE' => 'German (DE)', 'DV' => 'Divehi (DV)', 'DZ' => 'Dzongkha (DZ)', 'EE' => 'Ewe (EE)',
            'EL' => 'Greek (EL)', 'EN' => 'English (EN)', 'EO' => 'Esperanto (EO)', 'ES' => 'Spanish (ES)',
            'ET' => 'Estonian (ET)', 'EU' => 'Basque (EU)', 'FA' => 'Persian (FA)', 'FF' => 'Fulah (FF)',
            'FI' => 'Finnish (FI)', 'FJ' => 'Fijian (FJ)', 'FO' => 'Faroese (FO)', 'FR' => 'French (FR)',
            'FY' => 'Western Frisian (FY)', 'GA' => 'Irish (GA)', 'GD' => 'Scottish Gaelic (GD)', 'GL' => 'Galician (GL)',
            'GN' => 'Guarani (GN)', 'GU' => 'Gujarati (GU)', 'GV' => 'Manx (GV)', 'HA' => 'Hausa (HA)',
            'HE' => 'Hebrew (HE)', 'HI' => 'Hindi (HI)', 'HO' => 'Hiri Motu (HO)', 'HR' => 'Croatian (HR)',
            'HT' => 'Haitian Creole (HT)', 'HU' => 'Hungarian (HU)', 'HY' => 'Armenian (HY)', 'HZ' => 'Herero (HZ)',
            'IA' => 'Interlingua (IA)', 'ID' => 'Indonesian (ID)', 'IE' => 'Interlingue (IE)', 'IG' => 'Igbo (IG)',
            'II' => 'Nuosu (II)', 'IK' => 'Inupiaq (IK)', 'IO' => 'Ido (IO)', 'IS' => 'Icelandic (IS)',
            'IT' => 'Italian (IT)', 'IU' => 'Inuktitut (IU)', 'JA' => 'Japanese (JA)', 'JV' => 'Javanese (JV)',
            'KA' => 'Georgian (KA)', 'KG' => 'Kongo (KG)', 'KI' => 'Kikuyu (KI)', 'KJ' => 'Kwanyama (KJ)',
            'KK' => 'Kazakh (KK)', 'KL' => 'Greenlandic (KL)', 'KM' => 'Khmer (KM)', 'KN' => 'Kannada (KN)',
            'KO' => 'Korean (KO)', 'KR' => 'Kanuri (KR)', 'KS' => 'Kashmiri (KS)', 'KU' => 'Kurdish (KU)',
            'KV' => 'Komi (KV)', 'KW' => 'Cornish (KW)', 'KY' => 'Kyrgyz (KY)', 'LA' => 'Latin (LA)',
            'LB' => 'Luxembourgish (LB)', 'LG' => 'Ganda (LG)', 'LI' => 'Limburgish (LI)', 'LN' => 'Lingala (LN)',
            'LO' => 'Lao (LO)', 'LT' => 'Lithuanian (LT)', 'LU' => 'Luba-Katanga (LU)', 'LV' => 'Latvian (LV)',
            'MG' => 'Malagasy (MG)', 'MH' => 'Marshallese (MH)', 'MI' => 'Maori (MI)', 'MK' => 'Macedonian (MK)',
            'ML' => 'Malayalam (ML)', 'MN' => 'Mongolian (MN)', 'MR' => 'Marathi (MR)', 'MS' => 'Malay (MS)',
            'MT' => 'Maltese (MT)', 'MY' => 'Burmese (MY)', 'NA' => 'Nauru (NA)', 'NB' => 'Norwegian Bokmal (NB)',
            'ND' => 'Northern Ndebele (ND)', 'NE' => 'Nepali (NE)', 'NG' => 'Ndonga (NG)', 'NL' => 'Dutch (NL)',
            'NN' => 'Norwegian Nynorsk (NN)', 'NO' => 'Norwegian (NO)', 'NR' => 'Southern Ndebele (NR)', 'NV' => 'Navajo (NV)',
            'NY' => 'Nyanja (NY)', 'OC' => 'Occitan (OC)', 'OJ' => 'Ojibwe (OJ)', 'OM' => 'Oromo (OM)',
            'OR' => 'Odia (OR)', 'OS' => 'Ossetian (OS)', 'PA' => 'Punjabi (PA)', 'PI' => 'Pali (PI)',
            'PL' => 'Polish (PL)', 'PS' => 'Pashto (PS)', 'PT' => 'Portuguese (PT)', 'QU' => 'Quechua (QU)',
            'RM' => 'Romansh (RM)', 'RN' => 'Rundi (RN)', 'RO' => 'Romanian (RO)', 'RU' => 'Russian (RU)',
            'RW' => 'Kinyarwanda (RW)', 'SA' => 'Sanskrit (SA)', 'SC' => 'Sardinian (SC)', 'SD' => 'Sindhi (SD)',
            'SE' => 'Northern Sami (SE)', 'SG' => 'Sango (SG)', 'SI' => 'Sinhala (SI)', 'SK' => 'Slovak (SK)',
            'SL' => 'Slovenian (SL)', 'SM' => 'Samoan (SM)', 'SN' => 'Shona (SN)', 'SO' => 'Somali (SO)',
            'SQ' => 'Albanian (SQ)', 'SR' => 'Serbian (SR)', 'SS' => 'Swati (SS)', 'ST' => 'Southern Sotho (ST)',
            'SU' => 'Sundanese (SU)', 'SV' => 'Swedish (SV)', 'SW' => 'Swahili (SW)', 'TA' => 'Tamil (TA)',
            'TE' => 'Telugu (TE)', 'TG' => 'Tajik (TG)', 'TH' => 'Thai (TH)', 'TI' => 'Tigrinya (TI)',
            'TK' => 'Turkmen (TK)', 'TL' => 'Tagalog (TL)', 'TN' => 'Tswana (TN)', 'TO' => 'Tongan (TO)',
            'TR' => 'Turkish (TR)', 'TS' => 'Tsonga (TS)', 'TT' => 'Tatar (TT)', 'TW' => 'Twi (TW)',
            'TY' => 'Tahitian (TY)', 'UG' => 'Uyghur (UG)', 'UK' => 'Ukrainian (UK)', 'UR' => 'Urdu (UR)',
            'UZ' => 'Uzbek (UZ)', 'VE' => 'Venda (VE)', 'VI' => 'Vietnamese (VI)', 'VO' => 'Volapuk (VO)',
            'WA' => 'Walloon (WA)', 'WO' => 'Wolof (WO)', 'XH' => 'Xhosa (XH)', 'YI' => 'Yiddish (YI)',
            'YO' => 'Yoruba (YO)', 'ZA' => 'Zhuang (ZA)', 'ZH' => 'Chinese (ZH)', 'ZU' => 'Zulu (ZU)',
        ];
    }

    private function normalizeLanguage(string $language): string
    {
        $language = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $language) ?? 'EN', 0, 2));

        return array_key_exists($language, $this->targetLanguages()) ? $language : 'EN';
    }

    private function normalizeOptionalLanguage(string $language): string
    {
        $language = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $language) ?? '', 0, 2));

        return array_key_exists($language, $this->targetLanguages()) ? $language : '';
    }

    private function normalizePublicTab(string $tab): string
    {
        return in_array($tab, ['mine', 'upload'], true) ? $tab : 'start';
    }

    /**
     * @param list<PluginTranslationProject> $projects
     * @return array<string, string>
     */
    private function projectOptions(array $projects): array
    {
        $options = ['' => 'Any YAML file'];
        foreach ($this->publicProjects($projects) as $project) {
            $options[(string) $project->id] = $project->name;
        }

        return $options;
    }

    private function submissionProjectId(Request $request): int
    {
        if (!$this->translations instanceof PluginTranslationRepository) {
            throw new \InvalidArgumentException('The translator requires an active database connection.');
        }

        $projectId = $request->postInt('project_id', 0) ?? 0;
        if (!$request->postBool('syntaxdevteam_plugin')) {
            return $this->translations->fallbackProjectId();
        }
        $project = $this->translations->project($projectId);
        if ($project instanceof PluginTranslationProject && $project->slug !== 'nieprzypisane') {
            return $projectId;
        }

        throw new \InvalidArgumentException('Choose an active SyntaxDevTeam plugin category or leave the SyntaxDevTeam plugin option unchecked.');
    }

    private function existingOrFallbackProjectId(int $projectId): int
    {
        if (!$this->translations instanceof PluginTranslationRepository) {
            throw new \InvalidArgumentException('The translator requires an active database connection.');
        }
        if ($this->translations->project($projectId, true) instanceof PluginTranslationProject) {
            return $projectId;
        }

        return $this->translations->fallbackProjectId();
    }

    /**
     * @param list<PluginTranslationProject> $projects
     * @return list<PluginTranslationProject>
     */
    private function publicProjects(array $projects): array
    {
        return array_values(array_filter(
            $projects,
            static fn (PluginTranslationProject $project): bool => $project->slug !== 'nieprzypisane'
        ));
    }

    /**
     * @param list<PluginTranslationSubmission> $files
     * @return list<PluginTranslationSubmission>
     */
    private function publicFiles(array $files): array
    {
        return array_values(array_filter(
            $files,
            static fn (PluginTranslationSubmission $file): bool => $file->projectSlug !== 'nieprzypisane'
        ));
    }

    /**
     * @param list<PluginTranslationSubmission> $files
     */
    private function renderApprovedFilesTable(array $files): void
    {
        echo '<div class="table-responsive"><table class="table table-hover align-middle admin-data-table">';
        echo '<thead><tr><th>Language</th><th>Version</th><th>File</th><th>Author</th><th>Updated</th><th>Action</th></tr></thead><tbody>';
        foreach ($files as $file) {
            echo '<tr><td>' . $this->languageBadge($file->targetLanguage) . '</td>';
            echo '<td>' . $this->escape($file->pluginVersion !== '' ? $file->pluginVersion : '—') . '</td>';
            echo '<td>' . $this->escape($file->sourceFilename) . '</td>';
            echo '<td>' . $this->escape($file->authorName) . '</td>';
            echo '<td>' . $this->escape($this->publicDate($file->reviewedAt ?? $file->updatedAt)) . '</td>';
            echo '<td><div class="translation-table-actions">';
            echo '<a class="btn btn-sm btn-primary" href="index.php?route=/translations/download&amp;id=' . $file->id . '">Download YAML</a>';
            echo '<a class="btn btn-sm btn-outline-light" href="index.php?route=/translations/suggest&amp;id=' . $file->id . '">Suggest correction</a>';
            echo '</div></td></tr>';
        }
        echo '</tbody></table></div>';
    }

    /**
     * @param list<PluginTranslationSubmission> $files
     */
    private function renderLanguageFilter(PluginTranslationProject $project, array $files, string $activeLanguage): void
    {
        $counts = [];
        foreach ($files as $file) {
            $language = $this->normalizeLanguage($file->targetLanguage);
            $counts[$language] = ($counts[$language] ?? 0) + 1;
        }
        ksort($counts);

        if ($counts === []) {
            return;
        }

        echo '<div class="translation-table-actions mb-3" aria-label="Language filter">';
        echo '<a class="btn btn-sm ' . ($activeLanguage === '' ? 'btn-primary' : 'btn-outline-light') . '" href="' . $this->publicProjectHref($project) . '">All languages <span class="badge text-bg-secondary">' . count($files) . '</span></a>';
        foreach ($counts as $language => $count) {
            echo '<a class="btn btn-sm ' . ($activeLanguage === $language ? 'btn-primary' : 'btn-outline-light') . '" href="' . $this->publicProjectHref($project, $language) . '">';
            echo $this->languageBadge($language) . ' <span class="badge text-bg-secondary">' . $count . '</span>';
            echo '</a>';
        }
        echo '</div>';
    }

    private function publicProjectHref(PluginTranslationProject $project, string $language = ''): string
    {
        $href = 'index.php?route=/translations/project&amp;id=' . $project->id;
        if ($language !== '') {
            $href .= '&amp;language=' . rawurlencode($language);
        }

        return $href;
    }

    private function downloadFilename(PluginTranslationSubmission $submission): string
    {
        return 'messages_' . strtolower($submission->targetLanguage) . '.yml';
    }

    private function languageBadge(string $language): string
    {
        $language = $this->normalizeLanguage($language);
        $label = $this->targetLanguages()[$language] ?? $language;
        $flag = $this->languageFlag($language);
        $marker = $flag !== ''
            ? '<span class="translation-language-flag" aria-hidden="true">' . $flag . '</span>'
            : '<span class="badge text-bg-secondary">' . $this->escape($language) . '</span>';

        return $marker . ' <span>' . $this->escape($label) . '</span>';
    }

    private function languageFlag(string $language): string
    {
        $countries = [
            'AF' => 'ZA', 'AK' => 'GH', 'AM' => 'ET', 'AR' => 'SA', 'AS' => 'IN', 'AY' => 'BO', 'AZ' => 'AZ',
            'BA' => 'RU', 'BE' => 'BY', 'BG' => 'BG', 'BM' => 'ML', 'BN' => 'BD', 'BO' => 'CN', 'BR' => 'FR',
            'BS' => 'BA', 'CA' => 'ES', 'CE' => 'RU', 'CS' => 'CZ', 'CY' => 'GB', 'DA' => 'DK', 'DE' => 'DE',
            'DV' => 'MV', 'DZ' => 'BT', 'EE' => 'GH', 'EL' => 'GR', 'EN' => 'GB', 'ES' => 'ES', 'ET' => 'EE',
            'EU' => 'ES', 'FA' => 'IR', 'FI' => 'FI', 'FJ' => 'FJ', 'FO' => 'FO', 'FR' => 'FR', 'FY' => 'NL',
            'GA' => 'IE', 'GD' => 'GB', 'GL' => 'ES', 'GN' => 'PY', 'GU' => 'IN', 'GV' => 'IM', 'HA' => 'NG',
            'HE' => 'IL', 'HI' => 'IN', 'HR' => 'HR', 'HT' => 'HT', 'HU' => 'HU', 'HY' => 'AM', 'ID' => 'ID',
            'IG' => 'NG', 'IS' => 'IS', 'IT' => 'IT', 'JA' => 'JP', 'JV' => 'ID', 'KA' => 'GE', 'KK' => 'KZ',
            'KL' => 'GL', 'KM' => 'KH', 'KN' => 'IN', 'KO' => 'KR', 'KU' => 'IQ', 'KY' => 'KG', 'LB' => 'LU',
            'LG' => 'UG', 'LN' => 'CD', 'LO' => 'LA', 'LT' => 'LT', 'LU' => 'CD', 'LV' => 'LV', 'MG' => 'MG',
            'MH' => 'MH', 'MI' => 'NZ', 'MK' => 'MK', 'ML' => 'IN', 'MN' => 'MN', 'MR' => 'IN', 'MS' => 'MY',
            'MT' => 'MT', 'MY' => 'MM', 'NA' => 'NR', 'NB' => 'NO', 'ND' => 'ZW', 'NE' => 'NP', 'NL' => 'NL',
            'NN' => 'NO', 'NO' => 'NO', 'NR' => 'ZA', 'NY' => 'MW', 'OM' => 'ET', 'OR' => 'IN', 'PA' => 'IN',
            'PL' => 'PL', 'PS' => 'AF', 'PT' => 'PT', 'QU' => 'PE', 'RM' => 'CH', 'RN' => 'BI', 'RO' => 'RO',
            'RU' => 'RU', 'RW' => 'RW', 'SC' => 'IT', 'SD' => 'PK', 'SE' => 'NO', 'SG' => 'CF', 'SI' => 'LK',
            'SK' => 'SK', 'SL' => 'SI', 'SM' => 'WS', 'SN' => 'ZW', 'SO' => 'SO', 'SQ' => 'AL', 'SR' => 'RS',
            'SS' => 'SZ', 'ST' => 'LS', 'SV' => 'SE', 'SW' => 'TZ', 'TA' => 'IN', 'TE' => 'IN', 'TG' => 'TJ',
            'TH' => 'TH', 'TI' => 'ER', 'TK' => 'TM', 'TL' => 'PH', 'TN' => 'BW', 'TO' => 'TO', 'TR' => 'TR',
            'TS' => 'ZA', 'TT' => 'RU', 'UK' => 'UA', 'UR' => 'PK', 'UZ' => 'UZ', 'VE' => 'ZA', 'VI' => 'VN',
            'XH' => 'ZA', 'YI' => 'IL', 'YO' => 'NG', 'ZA' => 'CN', 'ZH' => 'CN', 'ZU' => 'ZA',
        ];
        $country = $countries[$language] ?? '';
        if (!preg_match('/^[A-Z]{2}$/', $country)) {
            return '';
        }

        return sprintf('&#x%X;&#x%X;', 0x1F1E6 + ord($country[0]) - 65, 0x1F1E6 + ord($country[1]) - 65);
    }

    private function publicDate(string $value): string
    {
        try {
            return (new \DateTimeImmutable($value))->format('Y-m-d');
        } catch (\Throwable) {
            return $value;
        }
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

    private function autosaveKey(string $source, string $filename, ?PluginTranslationSubmission $submission = null): string
    {
        $user = $this->auth->user();
        $owner = $user instanceof User ? 'user:' . $user->id : 'guest';
        $work = $submission instanceof PluginTranslationSubmission
            ? 'submission:' . $submission->id
            : 'source:' . hash('sha256', $this->safeYamlFilename($filename) . "\0" . $source);

        return substr(hash('sha256', $owner . "\0" . $work), 0, 48);
    }

    private function renderAutosaveScript(): void
    {
        echo '<script src="index.php?route=/translations/assets/autosave.js&amp;v=' . $this->escape($this->version()) . '"></script>';
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
        return $kind === 'completed_upload' ? 'Ready file' : 'Editor';
    }

    private function publicProjectName(string $name): string
    {
        return $name === 'Nieprzypisane' ? 'Unassigned YAML file' : $name;
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

    /**
     * @param array<string, string> $translations
     */
    private function hiddenTranslations(array $translations): void
    {
        foreach ($translations as $token => $value) {
            $this->hidden('translations[' . $token . ']', $value);
        }
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

    private function publicStatusLabel(string $status): string
    {
        return [
            'draft' => 'Draft',
            'ready_for_review' => 'Ready for review',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
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
