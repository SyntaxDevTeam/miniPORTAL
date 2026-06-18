<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\PluginTranslator;

use SyntaxDevTeam\Cms\Core\AdminMenuRegistry;
use SyntaxDevTeam\Cms\Core\ModuleInterface;
use SyntaxDevTeam\Cms\Core\Request;
use SyntaxDevTeam\Cms\Core\Router;
use SyntaxDevTeam\Cms\Core\Security;
use SyntaxDevTeam\Cms\Core\ThemeInterface;
use SyntaxDevTeam\Cms\Modules\CoreAuth\AdminAccessGate;
use SyntaxDevTeam\Cms\Modules\CoreAuth\AuditLogService;
use SyntaxDevTeam\Cms\Modules\CoreAuth\AuthService;
use SyntaxDevTeam\Cms\Modules\CoreAuth\User;

final class PluginTranslatorModule implements ModuleInterface
{
    public function __construct(
        private readonly ThemeInterface $theme,
        private readonly AdminMenuRegistry $menu,
        private readonly AuthService $auth,
        private readonly AdminAccessGate $access,
        private readonly Security $security,
        private readonly AuditLogService $audit,
        private readonly PluginTranslatorYaml $yaml,
    ) {
    }

    public function id(): string
    {
        return 'plugin_translator';
    }

    public function version(): string
    {
        return '1.0.0';
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
        return ['plugin_translator.use'];
    }

    public function registerAdminMenu(AdminMenuRegistry $menu): void
    {
        $menu->add('System', 'Translator YAML', '/admin/plugin-translator', 'TR', 'plugin_translator.use', 59);
    }

    public function registerRoutes(Router $router): void
    {
        $router->get('/admin/plugin-translator', fn (Request $request) => $this->guard(
            $request,
            'plugin_translator.use',
            fn () => $this->renderUpload()
        ));
        $router->post('/admin/plugin-translator', fn (Request $request) => $this->guard(
            $request,
            'plugin_translator.use',
            fn () => $this->openEditor($request)
        ));
        $router->post('/admin/plugin-translator/export', fn (Request $request) => $this->guard(
            $request,
            'plugin_translator.use',
            fn () => $this->export($request)
        ));
    }

    private function renderUpload(string $message = '', string $variant = 'info', string $source = ''): void
    {
        $this->startPage(
            'Translator YAML',
            'Wgraj albo wklej plik wiadomości YAML i przygotuj nową wersję tłumaczenia.'
        );
        if ($message !== '') {
            $this->theme->render_alert($message, $variant);
        }

        $this->theme->start_admin_panel_grid('balanced');
        $this->theme->start_admin_panel('Źródło tłumaczenia', 'Pliki .yml / .yaml, limit 256 KB');
        $this->theme->render_form(
            'index.php?route=/admin/plugin-translator',
            [[
                'name' => 'yaml_file',
                'label' => 'Plik YAML',
                'type' => 'file',
                'accept' => '.yml,.yaml,text/yaml,text/x-yaml,text/plain',
                'help' => 'Opcjonalnie wybierz plik z wiadomościami pluginu.',
            ], [
                'name' => 'source_yaml',
                'label' => 'Treść YAML',
                'type' => 'textarea',
                'value' => $source,
                'rows' => 16,
                'help' => 'Jeśli wybierzesz plik, jego treść ma pierwszeństwo przed polem tekstowym.',
            ]],
            'Otwórz translator',
            $this->security->csrfToken()
        );
        $this->theme->end_admin_panel();

        $this->theme->start_admin_panel('Oczekiwany format', 'kategoria -> klucz -> treść');
        $this->theme->render_admin_table(
            ['Poziom', 'Przykład', 'Znaczenie'],
            [
                ['Kategoria', 'general', 'Grupa komunikatów pluginu'],
                ['Klucz', 'enabled', 'Stały identyfikator wiadomości'],
                ['Treść', 'Plugin jest włączony.', 'Tekst do przetłumaczenia'],
            ]
        );
        $this->theme->end_admin_panel();
        $this->theme->end_admin_panel_grid();
        $this->endPage();
    }

    private function openEditor(Request $request): void
    {
        $actor = $this->auth->user();
        $source = $request->postString('source_yaml');
        if (!$this->security->validateCsrfToken($request->postString('_token'))) {
            $this->audit->record($request, 'plugin_translation_open', 'invalid_csrf', 'yaml', $actor?->id);
            $this->renderUpload('Token CSRF jest nieprawidłowy lub wygasł.', 'danger', $source);
            return;
        }

        try {
            $source = $this->sourceYaml($request, $source);
            $parsed = $this->yaml->parse($source);
            $items = $this->yaml->flatten($parsed);
            if ($items === []) {
                throw new \InvalidArgumentException('Nie znaleziono żadnych tekstów do tłumaczenia.');
            }
            $this->audit->record($request, 'plugin_translation_open', 'success', 'items:' . count($items), $actor?->id);
            $this->renderEditor($source, $parsed, $items);
        } catch (\Throwable $exception) {
            $this->audit->record($request, 'plugin_translation_open', 'failed', 'yaml', $actor?->id);
            $this->renderUpload($exception->getMessage(), 'danger', $source);
        }
    }

    private function export(Request $request): void
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
            $translated = $this->yaml->translated($parsed, $request->postArray('translations'));
            $output = $this->yaml->dump($translated);
            $this->yaml->parse($output);
            $this->audit->record($request, 'plugin_translation_export', 'success', 'items:' . count($this->yaml->flatten($parsed)), $actor?->id);

            header('Content-Type: application/x-yaml; charset=utf-8');
            header('Content-Disposition: attachment; filename="translation.yml"');
            header('X-Content-Type-Options: nosniff');
            echo $output;
        } catch (\Throwable $exception) {
            $this->audit->record($request, 'plugin_translation_export', 'failed', 'yaml', $actor?->id);
            try {
                $parsed = $this->yaml->parse($source);
                $this->renderEditor($source, $parsed, $this->yaml->flatten($parsed), $exception->getMessage(), 'danger');
            } catch (\Throwable) {
                $this->renderUpload($exception->getMessage(), 'danger', $source);
            }
        }
    }

    /**
     * @param array<string, mixed> $parsed
     * @param list<array{token: string, path: list<string>, label: string, value: string}> $items
     */
    private function renderEditor(
        string $source,
        array $parsed,
        array $items,
        string $message = '',
        string $variant = 'info',
    ): void {
        $this->startPage(
            'Edycja tłumaczenia',
            'Po lewej stronie znajduje się oryginał, po prawej pola nowej wersji YAML.',
            [[
                'label' => 'Wczytaj inny plik',
                'href' => 'index.php?route=/admin/plugin-translator',
                'variant' => 'outline-light',
            ]]
        );
        if ($message !== '') {
            $this->theme->render_alert($message, $variant);
        }

        $this->theme->start_admin_panel_grid('balanced');
        $this->theme->start_admin_panel('Oryginał', count($items) . ' tekstów');
        $this->theme->render_admin_table(
            ['Klucz', 'Treść'],
            array_map(
                static fn (array $item): array => [$item['label'], $item['value']],
                $items
            )
        );
        $this->theme->end_admin_panel();

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

        $this->theme->start_admin_panel('Nowe tłumaczenie', 'Eksport z walidacją YAML');
        $this->theme->render_form(
            'index.php?route=/admin/plugin-translator/export',
            $fields,
            'Pobierz translation.yml',
            $this->security->csrfToken()
        );
        $this->theme->end_admin_panel();
        $this->theme->end_admin_panel_grid();
        $this->endPage();
    }

    private function sourceYaml(Request $request, string $fallback): string
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

        return $content;
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
    private function startPage(string $title, string $lead, ?array $actions = null): void
    {
        $user = $this->auth->user();
        $this->theme->start_admin_page($title, $this->menu->items($user?->permissions ?? []), '/admin/plugin-translator', [
            'name' => $user?->displayName ?? 'Gość',
            'role' => $user?->primaryRole ?? 'Gość',
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

    private function endPage(): void
    {
        $this->theme->end_admin_content();
        $this->theme->end_admin_page();
    }
}
