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
        private readonly ?PluginTranslationRepository $translations,
    ) {
    }

    public function id(): string
    {
        return 'plugin_translator';
    }

    public function version(): string
    {
        return '1.1.0';
    }

    public function dependencies(): array
    {
        return ['core_auth'];
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
        $router->post('/translations/open', fn (Request $request) => $this->openPublicEditor($request));
        $router->post('/translations/submit', fn (Request $request) => $this->submitPublicTranslation($request));

        $router->get('/admin/plugin-translator', fn (Request $request) => $this->guard(
            $request,
            'plugin_translator.review',
            fn () => $this->renderAdminQueue()
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

    private function renderPublicStart(string $message = '', string $variant = 'info', string $source = ''): void
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

        $this->theme->start_grid();
        $this->theme->start_column('lg-7');
        $this->theme->start_card('Rozpocznij tłumaczenie', 'YAML');
        $this->theme->render_form(
            'index.php?route=/translations/open',
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
                'rows' => 14,
            ]],
            'Otwórz edytor',
            $this->security->csrfToken()
        );
        $this->theme->end_card();
        $this->theme->end_column();

        $this->theme->start_column('lg-5');
        $this->theme->start_card('Ostatnio zatwierdzone', 'Review');
        $approved = $this->translations->recentApproved();
        if ($approved === []) {
            $this->theme->render_alert('Nie ma jeszcze zatwierdzonych tłumaczeń.', 'info');
        } else {
            $this->theme->render_table(
                ['Nazwa', 'Postęp', 'Data'],
                array_map(
                    static fn (PluginTranslationSubmission $submission): array => [
                        $submission->title,
                        $submission->progressPercent . '%',
                        $submission->reviewedAt ?? $submission->updatedAt,
                    ],
                    $approved
                )
            );
        }
        $this->theme->end_card();
        $this->theme->end_column();
        $this->theme->end_grid();
        $this->theme->end_section();
        $this->theme->end_page();
    }

    private function openPublicEditor(Request $request): void
    {
        $source = $request->postString('source_yaml');
        if (!$this->security->validateCsrfToken($request->postString('_token'))) {
            $this->renderPublicStart('Token formularza jest nieprawidłowy lub wygasł.', 'danger', $source);
            return;
        }

        try {
            $filename = 'messages.yml';
            $source = $this->sourceYaml($request, $source, $filename);
            $parsed = $this->yaml->parse($source);
            $items = $this->yaml->flatten($parsed);
            if ($items === []) {
                throw new \InvalidArgumentException('Nie znaleziono żadnych tekstów do tłumaczenia.');
            }
            $this->renderPublicEditor($source, $filename, $items);
        } catch (\Throwable $exception) {
            $this->renderPublicStart($exception->getMessage(), 'danger', $source);
        }
    }

    private function submitPublicTranslation(Request $request): void
    {
        $source = $request->postString('source_yaml');
        if (!$this->security->validateCsrfToken($request->postString('_token'))) {
            $this->renderPublicStart('Token formularza jest nieprawidłowy lub wygasł.', 'danger', $source);
            return;
        }
        if ($this->translations === null) {
            $this->renderPublicStart('Translator wymaga aktywnego połączenia z bazą danych.', 'danger', $source);
            return;
        }

        try {
            $parsed = $this->yaml->parse($source);
            $items = $this->yaml->flatten($parsed);
            $translations = $this->normalizedTranslations($request->postArray('translations'));
            $translated = $this->yaml->translated($parsed, $translations);
            $output = $this->yaml->dump($translated);
            $this->yaml->parse($output);

            $status = $request->postString('status') === 'ready_for_review' ? 'ready_for_review' : 'draft';
            $translatedCount = $this->yaml->translatedCount($items, $translations);
            if ($status === 'ready_for_review' && $translatedCount < count($items)) {
                $this->renderPublicEditor($source, $request->postString('source_filename', 'messages.yml'), $items, $translations, 'Tłumaczenie można oznaczyć jako gotowe dopiero po uzupełnieniu wszystkich pozycji.', 'danger');
                return;
            }

            $user = $this->auth->user();
            $id = $this->translations->create(
                $user?->id,
                $this->bounded($request->postString('author_name', $user?->displayName ?? 'Anonim'), 160),
                $this->bounded($request->postString('author_email', $user?->email ?? ''), 190),
                $this->bounded($request->postString('title', 'Tłumaczenie pluginu'), 180),
                $this->bounded($request->postString('source_filename', 'messages.yml'), 190),
                $source,
                $translations,
                $output,
                count($items),
                $translatedCount,
                $status
            );
            $this->audit->record($request, 'plugin_translation_submit', 'success', 'submission:' . $id . ':' . $status, $user?->id);
            $message = $status === 'ready_for_review'
                ? 'Tłumaczenie zapisane i oznaczone jako gotowe do sprawdzenia.'
                : 'Wersja robocza tłumaczenia została zapisana.';
            $this->renderPublicStart($message, 'success');
        } catch (\Throwable $exception) {
            $this->renderPublicStart($exception->getMessage(), 'danger', $source);
        }
    }

    /**
     * @param list<array{token: string, path: list<string>, label: string, value: string}> $items
     * @param array<string, string> $translations
     */
    private function renderPublicEditor(
        string $source,
        string $filename,
        array $items,
        array $translations = [],
        string $message = '',
        string $variant = 'info',
    ): void {
        $user = $this->auth->user();
        $this->theme->start_page('Edycja tłumaczenia - SyntaxDevTeam', 'Uzupełnij wartości tłumaczenia YAML.');
        $this->theme->start_header('Edycja tłumaczenia', 'Uzupełnij pola i zapisz szkic albo wyślij gotową pracę do sprawdzenia.', 'SyntaxDevTeam / Lokalizacja');
        $this->theme->end_header();
        $this->theme->start_section();
        if ($message !== '') {
            $this->theme->render_alert($message, $variant);
        }

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
        ], [
            'name' => 'title',
            'label' => 'Nazwa tłumaczenia',
            'type' => 'text',
            'value' => 'Tłumaczenie ' . $filename,
        ], [
            'name' => 'author_name',
            'label' => 'Autor',
            'type' => 'text',
            'value' => $user?->displayName ?? '',
        ], [
            'name' => 'author_email',
            'label' => 'E-mail kontaktowy',
            'type' => 'email',
            'value' => $user?->email ?? '',
        ], [
            'name' => 'status',
            'label' => 'Status zapisu',
            'type' => 'select',
            'value' => 'ready_for_review',
            'options' => [
                'ready_for_review' => 'Gotowe do sprawdzenia',
                'draft' => 'Zapisz jako wersję roboczą',
            ],
        ]];
        foreach ($items as $item) {
            $fields[] = [
                'name' => 'translations[' . $item['token'] . ']',
                'label' => $item['label'],
                'type' => strlen($item['value']) > 90 ? 'textarea' : 'text',
                'value' => $translations[$item['token']] ?? '',
                'rows' => 4,
                'help' => 'Oryginał: ' . $item['value'],
            ];
        }

        $this->theme->start_grid();
        $this->theme->start_column('lg-5');
        $this->theme->start_card('Oryginał', count($items) . ' tekstów');
        $this->theme->render_table(['Klucz', 'Treść'], array_map(static fn (array $item): array => [$item['label'], $item['value']], $items));
        $this->theme->end_card();
        $this->theme->end_column();
        $this->theme->start_column('lg-7');
        $this->theme->start_card('Twoje tłumaczenie', 'Zgłoszenie');
        $this->theme->render_form('index.php?route=/translations/submit', $fields, 'Zapisz tłumaczenie', $this->security->csrfToken());
        $this->theme->end_card();
        $this->theme->end_column();
        $this->theme->end_grid();
        $this->theme->end_section();
        $this->theme->end_page();
    }

    private function renderAdminQueue(string $message = '', string $variant = 'info'): void
    {
        $this->startAdminPage(
            'Translator YAML',
            'Podgląd prac użytkowników, statusów ukończenia i kolejki zatwierdzania.',
            [[
                'label' => 'Narzędzie eksportu',
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
                ['ID', 'Nazwa', 'Autor', 'Status', 'Postęp', 'Aktualizacja'],
                array_map(
                    fn (PluginTranslationSubmission $submission): array => [
                        'cells' => [
                            $submission->id,
                            $submission->title,
                            $submission->authorName,
                            $this->statusLabel($submission->status),
                            $submission->progressPercent . '% (' . $submission->translatedItems . '/' . $submission->totalItems . ')',
                            $submission->updatedAt,
                        ],
                        'actions' => [[
                            'label' => 'Podgląd',
                            'href' => 'index.php?route=/admin/plugin-translator/view&id=' . $submission->id,
                            'variant' => $submission->status === 'ready_for_review' ? 'primary' : 'outline-light',
                        ], [
                            'label' => 'Pobierz',
                            'href' => 'index.php?route=/admin/plugin-translator/download&id=' . $submission->id,
                            'variant' => 'outline-light',
                        ]],
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

    private function downloadSubmission(Request $request): void
    {
        $submission = $this->translations?->find($request->queryInt('id', 0) ?? 0);
        if (!$submission instanceof PluginTranslationSubmission) {
            $this->renderAdminQueue('Nie znaleziono zgłoszenia tłumaczenia.', 'danger');
            return;
        }

        header('Content-Type: application/x-yaml; charset=utf-8');
        header('Content-Disposition: attachment; filename="translation-' . $submission->id . '.yml"');
        header('X-Content-Type-Options: nosniff');
        echo $submission->outputYaml;
    }

    private function renderUpload(string $message = '', string $variant = 'info', string $source = ''): void
    {
        $this->startAdminPage(
            'Narzędzie eksportu YAML',
            'Administracyjny tryb jednorazowego wgrania i pobrania pliku YAML.',
            [[
                'label' => 'Kolejka prac',
                'href' => 'index.php?route=/admin/plugin-translator',
                'variant' => 'outline-light',
            ]]
        );
        if ($message !== '') {
            $this->theme->render_alert($message, $variant);
        }

        $this->theme->start_admin_panel('Źródło tłumaczenia', 'Pliki .yml / .yaml, limit 256 KB');
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
            $this->renderToolEditor($source, $items);
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
            $this->audit->record($request, 'plugin_translation_export', 'success', 'items:' . count($this->yaml->flatten($parsed)), $actor?->id);
            header('Content-Type: application/x-yaml; charset=utf-8');
            header('Content-Disposition: attachment; filename="translation.yml"');
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
    private function renderToolEditor(string $source, array $items): void
    {
        $this->startAdminPage(
            'Edycja tłumaczenia',
            'Jednorazowy eksport nowej wersji YAML.',
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
        $this->theme->render_form('index.php?route=/admin/plugin-translator/export', $fields, 'Pobierz translation.yml', $this->security->csrfToken());
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
        $this->theme->start_admin_page($title, $this->menu->items($user?->permissions ?? []), '/admin/plugin-translator', [
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
