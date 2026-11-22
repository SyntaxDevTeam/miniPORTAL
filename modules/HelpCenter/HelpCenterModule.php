<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\HelpCenter;

use InvalidArgumentException;
use SyntaxDevTeam\Cms\Core\{AdminMenuRegistry, AdminSearchProviderInterface, AdminSearchRegistry, ModuleInterface, PublicNavigationProviderInterface, PublicNavigationRegistry, QuestionCollectionThemeInterface, Request, Router, Security, SeoIndex, SeoProviderInterface, TemplateCacheInterface, ThemeInterface};
use SyntaxDevTeam\Cms\Modules\CoreAuth\{AdminAccessGate, AuditLogService, AuthService};

final class HelpCenterModule implements ModuleInterface, PublicNavigationProviderInterface, AdminSearchProviderInterface, SeoProviderInterface
{
    public function __construct(
        private readonly ThemeInterface $theme,
        private readonly AdminMenuRegistry $menu,
        private readonly HelpRepository $repository,
        private readonly AuthService $auth,
        private readonly AdminAccessGate $access,
        private readonly Security $security,
        private readonly AuditLogService $audit,
        private readonly TemplateCacheInterface $cache,
    ) {}

    public function id(): string { return 'help_center'; }
    public function version(): string { return '2.1.0'; }
    public function dependencies(): array { return ['core_auth']; }
    public function isProtected(): bool { return false; }
    public function requiredPermissions(): array { return ['help_center.manage', 'help_center.staff.read']; }

    public function registerAdminMenu(AdminMenuRegistry $menu): void
    {
        $menu->add('Treść', 'Centrum pomocy', '/admin/help-center', '?', 'help_center.manage', 45);
    }

    public function registerPublicNavigation(PublicNavigationRegistry $navigation): void
    {
        $navigation->add('help_center.index', 'Centrum pomocy', '/help', 'main', 75);
    }

    public function registerAdminSearch(AdminSearchRegistry $search): void
    {
        $search->add('help_center.manage', 'Centrum pomocy', 'Pytania, odpowiedzi i kategorie pomocy.',
            '/admin/help-center', ['FAQ', 'pomoc', 'pytania', 'odpowiedzi', 'kategorie'], 'help_center.manage', 'Treść', 45);
    }

    public function registerSeo(SeoIndex $seo): void
    {
        $settings = $this->repository->settings();
        $seo->add('/help', $settings['title'], $settings['intro']);
    }

    public function registerRoutes(Router $router): void
    {
        $router->get('/help', fn() => $this->publicPage());
        $router->get('/faq', static function (): void { header('Location: /help', true, 301); });
        $router->get('/admin/help-center', fn(Request $r) => $this->guard($r, fn() => $this->listing($r)));
        foreach (['category', 'question'] as $type) {
            $router->get('/admin/help-center/' . $type, fn(Request $r) => $this->guard($r, fn() => $this->edit($r, $type)));
            foreach (['save', 'delete', 'toggle'] as $operation) {
                $router->post('/admin/help-center/' . $type . '/' . $operation,
                    fn(Request $r) => $this->guard($r, fn() => $this->mutate($r, $type, $operation)));
            }
        }
        $router->post('/admin/help-center/settings', fn(Request $r) => $this->guard($r, fn() => $this->mutate($r, 'settings', 'save')));
    }

    private function publicPage(): void
    {
        // A personalized FAQ must never be stored by shared caches or reused after logout.
        header('Cache-Control: private, no-store');
        header('Cloudflare-CDN-Cache-Control: no-store');
        header('Vary: Cookie', false);
        $includeStaff = $this->canReadStaff();
        if ($includeStaff) { header('X-Robots-Tag: noindex, nofollow'); }
        $model = $this->repository->publicModel($includeStaff);
        $this->theme->start_page('Centrum pomocy — ' . $model['title'], $model['intro'], !$includeStaff);
        if ($this->theme instanceof QuestionCollectionThemeInterface) {
            $this->theme->render_question_collection($model);
        } else {
            $this->theme->start_header($model['title'], $model['intro']);
            $this->theme->end_header();
            $this->theme->start_section();
            foreach ($model['categories'] as $category) {
                $this->theme->render_section_heading($category['name'], $category['description']);
                foreach ($category['questions'] as $question) {
                    $this->theme->start_card($question['question']);
                    $this->theme->render_rich_content($question['answer'], $question['content_format']);
                    $this->theme->end_card();
                }
            }
            $this->theme->end_section();
        }
        $this->theme->end_page();
    }

    private function canReadStaff(): bool
    {
        return $this->access->check('help_center.staff.read') === AdminAccessGate::ALLOWED;
    }

    private function guard(Request $request, callable $handler): void
    {
        header('Cache-Control: private, no-store');
        $status = $this->access->check('help_center.manage');
        if ($status === 200 && $this->auth->rolePreviewBlocks($request)) { $status = 403; }
        if ($status !== 200) {
            http_response_code($status);
            $this->theme->render_admin_access_state($status, $status === 401 ? 'Wymagane logowanie' : 'Brak uprawnień',
                'Zarządzanie Centrum pomocy wymaga odpowiednich uprawnień.', '/admin', 'Wróć do panelu');
            return;
        }
        $handler();
    }

    private function mutate(Request $request, string $type, string $operation): void
    {
        if (!$this->security->validateCsrfToken($request->postString('_token'))) {
            http_response_code(403);
            $this->audit->record($request, 'help_center_' . $operation, 'invalid_csrf', $type, $this->auth->user()?->id);
            $this->listing($request, 'Token formularza wygasł. Odśwież stronę i spróbuj ponownie.', 'danger');
            return;
        }
        $id = $type === 'settings' ? 1 : $request->postInt('id', 0);
        if ($id === null || $id < 0) {
            http_response_code(422); $this->listing($request, 'Nieprawidłowy identyfikator wpisu.', 'danger'); return;
        }
        $record = $id > 0 ? $this->repository->find($type, $id, $this->canReadStaff()) : null;
        if (($id > 0 && $record === null) || ($operation !== 'save' && $id < 1)) {
            http_response_code(404); $this->listing($request, 'Nie znaleziono wpisu.', 'danger'); return;
        }
        try {
            $data = [];
            if ($operation === 'save') {
                $data = match ($type) { 'category' => HelpInput::category($request), 'question' => HelpInput::question($request), 'settings' => HelpInput::settings($request) };
                // A form opened before the upgrade must not silently make an existing private record public.
                if ($type !== 'settings' && $record !== null && $request->postString('visibility') === '') {
                    unset($data['visibility']);
                }
                if (($data['visibility'] ?? 'public') === 'staff' && !$this->canReadStaff()) {
                    http_response_code(403);
                    $this->audit->record($request, 'help_center_save', 'forbidden', 'staff', $this->auth->user()?->id);
                    $this->listing($request, 'Prywatne FAQ wymaga uprawnienia odczytu administracji serwera.', 'danger');
                    return;
                }
                if ($type === 'question' && $this->repository->find('category', $data['category_id'], $this->canReadStaff()) === null) {
                    throw new InvalidArgumentException('Wybrana kategoria już nie istnieje. Wybierz inną.');
                }
            }
            $id = $this->repository->mutateAccessible($type, $id > 0 ? $id : null, $operation, $data, $this->canReadStaff());
            $this->cache->invalidateTags(['help_center', 'seo', 'navigation']);
            $this->audit->record($request, 'help_center_' . $operation, 'success', $type . ':' . $id, $this->auth->user()?->id);
            header('Location: /admin/help-center?saved=1', true, 303);
        } catch (\OutOfBoundsException $error) {
            http_response_code(404);
            $this->listing($request, 'Nie znaleziono wpisu.', 'danger');
        } catch (InvalidArgumentException $error) {
            http_response_code(422);
            if ($operation === 'save' && $type !== 'settings') {
                $this->form($type, $record ?? [], $error->getMessage(), $request);
            } else { $this->listing($request, $error->getMessage(), 'danger', $type === 'settings'); }
        } catch (\Throwable $error) {
            error_log('[miniPORTAL] help_center_write_failed ' . $error::class);
            $this->audit->record($request, 'help_center_' . $operation, 'failed', $type . ':' . $id, $this->auth->user()?->id);
            http_response_code(500);
            if ($operation === 'save' && $type !== 'settings') {
                $this->form($type, $record ?? [], 'Nie udało się zapisać zmiany. Spróbuj ponownie.', $request);
            } else { $this->listing($request, 'Nie udało się zapisać zmiany. Spróbuj ponownie.', 'danger', $type === 'settings'); }
        }
    }

    private function edit(Request $request, string $type): void
    {
        $id = $request->queryInt('id', 0) ?? 0;
        $record = $id > 0 ? $this->repository->find($type, $id, $this->canReadStaff()) : null;
        if ($id > 0 && $record === null) { http_response_code(404); $this->listing($request, 'Nie znaleziono wpisu.', 'danger'); return; }
        $this->form($type, $record ?? []);
    }

    private function start(string $title): void
    {
        $user = $this->auth->user();
        $this->theme->start_admin_page($title, $this->menu->visibleFor($user?->permissions ?? []), '/admin/help-center', [
            'name' => $user?->displayName ?? '', 'role' => $user?->primaryRole() ?? '', 'initials' => $user?->initials() ?? '',
            'avatar_url' => $user?->avatarUrl ?? '', 'logout_action' => '/admin/logout', 'logout_token' => $this->security->csrfToken(),
        ]);
        $this->theme->start_admin_content($title, 'Dobre odpowiedzi zaczynają się od dobrze uporządkowanej wiedzy.',
            [['label' => 'Panel', 'href' => '/admin'], ['label' => 'Centrum pomocy', 'href' => '/admin/help-center']],
            [['label' => 'Dodaj pytanie', 'href' => '/admin/help-center/question', 'variant' => 'primary'],
                ['label' => 'Dodaj kategorię', 'href' => '/admin/help-center/category'], ['label' => 'Zobacz centrum pomocy', 'href' => '/help']]);
    }

    private function end(): void { $this->theme->end_admin_content(); $this->theme->end_admin_page(); }

    private function listing(Request $request, string $message = '', string $variant = 'success', bool $settingsInput = false): void
    {
        $this->start('Centrum pomocy');
        if ($message !== '') { $this->theme->render_alert($message, $variant); }
        elseif ($request->queryString('saved') === '1') { $this->theme->render_alert('Zmiany zostały zapisane.', 'success'); }
        $categories = $this->repository->categories($this->canReadStaff());
        $questions = $this->repository->questions($this->canReadStaff());
        $this->theme->start_admin_panel('Pytania i odpowiedzi', count($questions) . ' pytań · ' . count($categories) . ' kategorii');
        if ($questions === []) { $this->theme->render_alert('Dodaj pierwsze pytanie i przypisz je do kategorii. Szkice pozostają widoczne tylko w panelu.', 'info'); }
        else {
            $this->theme->render_admin_action_table(['Pytanie', 'Kategoria', 'Stan', 'Dostęp', 'Kolejność', 'Polecane'], array_map(fn(array $q): array => [
                'cells' => [$q['question'], $q['category_name'], !$q['is_published'] ? 'Szkic' : ($q['category_visible'] ? 'Opublikowane' : 'Ukryta kategoria'), ($q['visibility'] === 'staff' || $q['category_visibility'] === 'staff') ? 'Administracja serwera' : 'Publiczne', $q['sort_order'], $q['is_featured'] ? 'Tak' : '—'],
                'actions' => $this->actions('question', $q, (bool)$q['is_published']),
            ], $questions), $this->security->csrfToken());
        }
        $this->theme->end_admin_panel();
        $this->theme->start_admin_panel('Kategorie', 'Kolejność kategorii i pytań jest niezależna. Publicznie pokazujemy tylko kategorie z opublikowanymi odpowiedziami.');
        $this->theme->render_admin_action_table(['Nazwa', 'Opis', 'Pytań', 'Kolejność', 'Widoczność', 'Dostęp'], array_map(fn(array $c): array => [
            'cells' => [$c['icon'] . ' ' . $c['name'], $c['description'], $c['question_count'], $c['sort_order'], $c['is_visible'] ? 'Widoczna' : 'Ukryta', $c['visibility'] === 'staff' ? 'Administracja serwera' : 'Publiczne'],
            'actions' => $this->actions('category', $c, (bool)$c['is_visible']),
        ], $categories), $this->security->csrfToken());
        $this->theme->end_admin_panel();
        $settings = $this->repository->settings();
        if ($settingsInput) { foreach (['title', 'intro', 'contact_url', 'contact_label'] as $key) { $settings[$key] = $request->postString($key); } }
        $this->theme->start_admin_panel('Strona centrum pomocy', 'Nagłówek, opis i droga do dalszego wsparcia.');
        $this->theme->render_form('/admin/help-center/settings', [
            ['name' => 'title', 'label' => 'Nagłówek', 'value' => $settings['title'], 'required' => true, 'maxlength' => 100],
            ['name' => 'intro', 'label' => 'Krótki opis', 'type' => 'textarea', 'value' => $settings['intro'], 'required' => true, 'maxlength' => 700],
            ['name' => 'contact_url', 'label' => 'Adres kontaktu', 'value' => $settings['contact_url'], 'help' => 'Opcjonalnie: lokalna ścieżka, np. /#contact, lub adres HTTPS. Puste pole ukrywa sekcję kontaktu.'],
            ['name' => 'contact_label', 'label' => 'Tekst przycisku kontaktu', 'value' => $settings['contact_label'], 'required' => true, 'maxlength' => 80],
        ], 'Zapisz ustawienia', $this->security->csrfToken());
        $this->theme->end_admin_panel();
        $this->end();
    }

    private function actions(string $type, array $row, bool $visible): array
    {
        $id = (int)$row['id'];
        return [
            ['label' => 'Edytuj', 'href' => '/admin/help-center/' . $type . '?id=' . $id, 'variant' => 'primary'],
            ['label' => $visible ? 'Ukryj' : 'Opublikuj', 'action' => '/admin/help-center/' . $type . '/toggle', 'fields' => ['id' => $id]],
            ['label' => 'Usuń', 'action' => '/admin/help-center/' . $type . '/delete', 'fields' => ['id' => $id], 'variant' => 'danger', 'confirm' => $type === 'category' ? 'Usunąć pustą kategorię? Kategorie z pytaniami są chronione.' : 'Trwale usunąć pytanie i odpowiedź?'],
        ];
    }

    private function form(string $type, array $record, string $error = '', ?Request $submitted = null): void
    {
        $id = (int)($record['id'] ?? 0);
        $values = array_replace(['name' => '', 'description' => '', 'icon' => '✦', 'sort_order' => 100, 'is_visible' => 1,
            'question' => '', 'answer' => '', 'content_format' => 'html', 'keywords' => '', 'category_id' => '', 'is_published' => 0, 'is_featured' => 0, 'visibility' => 'public'], $record);
        if ($submitted !== null) {
            foreach (array_keys($values) as $key) { if ($key !== 'id') { $values[$key] = $submitted->postString($key); } }
            foreach (['is_visible', 'is_published', 'is_featured'] as $key) { $values[$key] = $submitted->postBool($key); }
        }
        $this->start(($id > 0 ? 'Edytuj ' : 'Dodaj ') . ($type === 'category' ? 'kategorię' : 'pytanie'));
        if ($error !== '') { $this->theme->render_alert($error, 'danger'); }
        $fields = [['name' => 'id', 'label' => 'ID', 'type' => 'hidden', 'value' => (string)$id]];
        if ($type === 'category') {
            $fields = [...$fields,
                ['name' => 'name', 'label' => 'Nazwa kategorii', 'value' => $values['name'], 'required' => true, 'maxlength' => 100],
                ['name' => 'description', 'label' => 'Krótki opis', 'value' => $values['description'], 'maxlength' => 240],
                ['name' => 'icon', 'label' => 'Symbol lub emoji', 'value' => $values['icon'], 'help' => 'Na przykład ✦, ♡, 🐴 lub 🏡.'],
                ['name' => 'is_visible', 'label' => 'Kategoria opublikowana', 'type' => 'checkbox', 'checked' => (bool)$values['is_visible'], 'help' => 'Ukrycie kategorii ukrywa również wszystkie jej odpowiedzi.'],
            ];
        } else {
            $options = [];
            foreach ($this->repository->categories($this->canReadStaff()) as $category) { $options[(string)$category['id']] = $category['name'] . ($category['visibility'] === 'staff' ? ' (administracja serwera)' : '') . ($category['is_visible'] ? '' : ' (ukryta)'); }
            if ($options === []) { $this->theme->render_alert('Najpierw dodaj kategorię, aby przypisać do niej pytanie.', 'info'); $this->end(); return; }
            $fields = [...$fields,
                ['name' => 'category_id', 'label' => 'Kategoria', 'type' => 'select', 'value' => (string)$values['category_id'], 'options' => $options, 'required' => true],
                ['name' => 'question', 'label' => 'Pytanie', 'value' => $values['question'], 'required' => true, 'maxlength' => 240],
                ['name' => 'answer', 'label' => 'Odpowiedź', 'type' => 'richtext', 'value' => $values['answer'], 'format_name' => 'content_format', 'format_value' => $values['content_format'], 'help' => 'Używaj akapitów, list, linków i bloków kodu. Wybierz edytor wizualny lub Markdown.'],
                ['name' => 'keywords', 'label' => 'Dodatkowe słowa kluczowe', 'value' => $values['keywords'], 'maxlength' => 500, 'help' => 'Synonimy pomagające wyszukać tę odpowiedź. Nie są pokazywane jako treść.'],
                ['name' => 'is_published', 'label' => 'Opublikuj odpowiedź', 'type' => 'checkbox', 'checked' => (bool)$values['is_published']],
                ['name' => 'is_featured', 'label' => 'Polecane na początek', 'type' => 'checkbox', 'checked' => (bool)$values['is_featured']],
            ];
        }
        $visibilityOptions = ['public' => 'Publiczne'];
        if ($this->canReadStaff()) { $visibilityOptions['staff'] = 'Tylko dla administracji serwera'; }
        $fields[] = ['name' => 'visibility', 'label' => 'Widoczność FAQ', 'type' => 'select', 'value' => $values['visibility'], 'options' => $visibilityOptions,
            'help' => $type === 'category'
                ? 'Prywatna kategoria chroni wszystkie należące do niej odpowiedzi. Samo opublikowanie nie zmienia dostępu.'
                : 'Dostęp wymaga zalogowania i uprawnienia do prywatnego FAQ. Prywatna kategoria zawsze chroni też publicznie oznaczone pytanie.'];
        $fields[] = ['name' => 'sort_order', 'label' => 'Kolejność', 'type' => 'number', 'value' => (string)$values['sort_order'], 'help' => 'Od 0 do 65535. Niższa liczba oznacza wcześniejszą pozycję.'];
        $this->theme->start_admin_panel($type === 'category' ? 'Dane kategorii' : 'Treść odpowiedzi');
        $this->theme->render_form('/admin/help-center/' . $type . '/save', $fields, 'Zapisz zmiany', $this->security->csrfToken());
        $this->theme->end_admin_panel();
        $this->end();
    }
}
