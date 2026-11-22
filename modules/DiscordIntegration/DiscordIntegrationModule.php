<?php

declare(strict_types=1);
namespace SyntaxDevTeam\Cms\Modules\DiscordIntegration;
use SyntaxDevTeam\Cms\Core\{ModuleInterface,AdminMenuRegistry,AdminSearchProviderInterface,AdminSearchRegistry,Router,Request,Security,ThemeInterface};
use SyntaxDevTeam\Cms\Modules\CoreAuth\{AuthService,AdminAccessGate,AuditLogService};
use SyntaxDevTeam\Cms\Database\CrudApp;

final class DiscordIntegrationModule implements ModuleInterface, AdminSearchProviderInterface
{
    public function __construct(private readonly ThemeInterface $theme,private readonly AdminMenuRegistry $menu,private readonly AuthService $auth,private readonly AdminAccessGate $access,private readonly Security $security,private readonly AuditLogService $audit,private readonly CrudApp $db,private readonly string $root) {}
    public function id(): string {return 'discord_integration';}
    public function version(): string {return '1.1.0';}
    public function dependencies(): array {return ['core_auth'];}
    public function isProtected(): bool {return false;}
    public function requiredPermissions(): array {return ['discord.manage'];}
    public function registerAdminMenu(AdminMenuRegistry $menu): void {$menu->add('System','Integracja Discord','/admin/discord','DC','discord.manage',65);}
    public function registerAdminSearch(AdminSearchRegistry $search): void {$search->add('discord.settings','Integracja Discord','Bot, serwer i kanały powiadomień modułów.','/admin/discord',['bot','discord','webhook','kanały','powiadomienia'],'discord.manage','System',65);}
    public function registerRoutes(Router $router): void
    {
        $router->get('/admin/discord',fn(Request $r)=>$this->page($r));
        $router->post('/admin/discord',fn(Request $r)=>$this->page($r));
    }
    private function page(Request $request): void
    {
        header('Cache-Control: private, no-store');
        header('Cloudflare-CDN-Cache-Control: no-store');
        header('X-Robots-Tag: noindex, nofollow');
        $user = $this->auth->user();
        if (!$user || $this->access->check('discord.manage') !== 200 || array_intersect($user->roles, ['owner', 'administrator']) === [] || $this->auth->rolePreviewBlocks($request)) {
            http_response_code($user ? 403 : 401);
            $this->theme->render_admin_access_state($user ? 403 : 401, 'Integracja Discord', 'Wymagane konto Ownera lub systemowego Administratora.', '/admin/login', 'Zaloguj się');
            return;
        }

        $store = new DiscordConfig($this->root.'/config/modules/discord-integration.json');
        $modules = DiscordCatalog::modules($this->root, $this->db);
        $labels = ['connection' => 'Połączenie'];
        foreach ($modules as $id => $module) {
            $labels[$id] = $module['name'];
        }
        $labels['queue'] = 'Kolejka';
        $section = $request->queryString('section', 'connection');
        $validSection = isset($labels[$section]);
        if (!$validSection) $section = 'connection';
        $path = self::sectionPath($section);
        $routes = $modules[$section]['routes'] ?? [];
        $message = '';
        $variant = 'info';

        if ($request->method() === 'POST') {
            if (!$this->security->validateCsrfToken($request->postString('_token'))) {
                http_response_code(403);
                $message = 'Token formularza wygasł. Odśwież stronę.';
                $variant = 'danger';
            } elseif (!$validSection || $section === 'queue') {
                http_response_code(422);
                $message = 'Wybierz sekcję ustawień do zapisania.';
                $variant = 'danger';
            } else {
                try {
                    $store->save($this->formInput($request, $routes), $routes);
                    $this->audit->record($request, 'discord_config_save', 'success', 'discord_integration', $user->id);
                    header('Location: '.$path.'&saved=1', true, 303);
                    return;
                } catch (\InvalidArgumentException $e) {
                    http_response_code(422);
                    $message = $e->getMessage();
                    $variant = 'danger';
                } catch (\Throwable) {
                    http_response_code(500);
                    $message = 'Nie udało się zapisać konfiguracji Discord.';
                    $variant = 'danger';
                }
            }
        }

        $data = $store->read();
        $channels = ['' => 'Wybierz kanał'];
        $guilds = [];
        if ($request->method() === 'GET' && $section !== 'queue' && $request->queryString('refresh') === '1') {
            $hub = new DiscordHub($store);
            try {
                if ($section === 'connection') {
                    foreach ($hub->guilds() as $guild) $guilds[] = [$guild['name'], (string) $guild['id']];
                } else {
                    foreach ($hub->channels() as $channel) $channels[$channel['id']] = '#'.$channel['name'].' · '.$channel['id'];
                }
            } catch (\RuntimeException $e) {
                $message = $e->getMessage();
                $variant = 'warning';
            }
        }
        foreach ($data['routes'] as $route) {
            if (DiscordConfig::id($route['channel'] ?? '')) $channels[$route['channel']] ??= 'Kanał · '.$route['channel'];
        }

        $this->theme->start_admin_page('Integracja Discord', $this->menu->visibleFor($user->permissions), '/admin/discord', ['name' => $user->displayName, 'role' => $user->primaryRole(), 'initials' => $user->initials(), 'logout_action' => '/admin/logout', 'logout_token' => $this->security->csrfToken()]);
        $this->theme->start_admin_content('Integracja Discord', 'Ustawienia połączenia i powiadomień modułów.');
        $tabs = [];
        foreach ($labels as $id => $label) {
            $tabs[] = ['label' => $label, 'href' => self::sectionPath($id), 'active' => $id === $section];
        }
        $this->theme->render_tabs($tabs);
        if ($message !== '') $this->theme->render_alert($message, $variant);
        elseif ($request->queryString('saved') === '1') $this->theme->render_alert('Zapisano konfigurację.', 'success');

        $this->theme->start_admin_panel($labels[$section]);
        if ($section === 'queue') {
            $this->renderQueue();
        } else {
            $fields = [['name' => 'revision', 'type' => 'hidden', 'value' => (string) $data['revision']]];
            if ($section === 'connection') {
                $this->theme->render_text('Możesz użyć bota DiscordSRV. Bot potrzebuje dostępu do wybranych kanałów, wysyłania wiadomości i osadzania linków.');
                if ($guilds !== []) $this->theme->render_admin_table(['Serwer Discord', 'ID'], $guilds);
                $this->theme->render_button('Pobierz nazwy serwerów', $path.'&refresh=1', 'secondary');
                $fields[] = ['name' => 'token', 'type' => 'password', 'label' => 'Token bota', 'value' => '', 'autocomplete' => 'new-password', 'help' => ($data['token'] !== '' ? 'Token zapisany. ' : '').'Puste pole zachowuje token.'];
                $fields[] = ['name' => 'clear_token', 'type' => 'checkbox', 'label' => 'Usuń token bota'];
                $fields[] = ['name' => 'guild', 'label' => 'ID serwera Discord', 'value' => $data['guild'], 'help' => 'Włącz tryb programisty Discord i wybierz „Kopiuj ID serwera”.'];
            } else {
                $this->theme->render_button('Pobierz nazwy kanałów', $path.'&refresh=1', 'secondary');
                $fields = array_merge($fields, $this->routeFields($routes, $data['routes'], $channels));
            }
            $this->theme->render_form($path, $fields, 'Zapisz ustawienia', $this->security->csrfToken());
        }
        $this->theme->end_admin_panel();
        $this->theme->end_admin_content();
        $this->theme->end_admin_page();
    }

    private static function sectionPath(string $section): string
    {
        return '/admin/discord?section='.rawurlencode($section);
    }

    private function formInput(Request $request, array $routes): array
    {
        $input = ['revision' => $request->postInt('revision', -1)];
        if ($routes === []) {
            return $input + [
                'token' => $request->postString('token'),
                'clear_token' => $request->postBool('clear_token'),
                'guild' => $request->postString('guild'),
            ];
        }
        foreach ($routes as $key => $label) {
            $prefix = str_replace('.', '_', $key);
            $input['routes'][$key] = [
                'mode' => $request->postString($prefix.'_mode'),
                'channel' => $request->postString($prefix.'_channel'),
                'webhook' => $request->postString($prefix.'_webhook'),
                'clear' => $request->postBool($prefix.'_clear'),
                'roles' => $request->postString($prefix.'_roles'),
                'users' => $request->postString($prefix.'_users'),
            ];
        }
        return $input;
    }

    private function routeFields(array $catalog, array $routes, array $channels): array
    {
        $fields = [];
        $help = 'Dla wspólnego bota lub webhooka. W trybie programisty Discord skopiuj ID. Wpisz maksymalnie 10 ID, oddzielając je spacjami lub przecinkami.';
        foreach ($catalog as $key => $label) {
            $route = $routes[$key] ?? [];
            $prefix = str_replace('.', '_', $key);
            $fields[] = ['name' => $prefix.'_mode', 'label' => $label, 'type' => 'select', 'value' => $route['mode'] ?? 'legacy', 'options' => ['legacy' => 'Dotychczasowa konfiguracja modułu', 'disabled' => 'Wyłączone centralnie', 'bot' => 'Wysyłaj przez wspólnego bota', 'webhook' => 'Wysyłaj przez webhook zarządzany tutaj']];
            $fields[] = ['name' => $prefix.'_channel', 'label' => $label.' — kanał', 'type' => 'select', 'value' => $route['channel'] ?? '', 'options' => $channels, 'help' => 'Dla trybu bot. Pobierz nazwy po zapisaniu połączenia.'];
            $fields[] = ['name' => $prefix.'_webhook', 'label' => $label.' — webhook', 'type' => 'password', 'value' => '', 'autocomplete' => 'new-password', 'help' => !empty($route['webhook']) ? 'Webhook zapisany. Puste pole zachowuje sekret.' : 'Dla trybu webhook.'];
            $fields[] = ['name' => $prefix.'_clear', 'type' => 'checkbox', 'label' => $label.' — usuń zapisany webhook'];
            $fields[] = ['name' => $prefix.'_roles', 'label' => $label.' — ID ról do pingowania', 'value' => implode(', ', $route['roles'] ?? []), 'help' => $help.' Discord musi zezwalać nadawcy na wzmiankę danej roli.'];
            $fields[] = ['name' => $prefix.'_users', 'label' => $label.' — ID użytkowników do pingowania', 'value' => implode(', ', $route['users'] ?? []), 'help' => $help];
        }
        return $fields;
    }

    private function renderQueue(): void
    {
        $rows = $this->db->query('SELECT id,route_key,status,attempts,last_status,created_at FROM discord_integration_outbox ORDER BY id DESC LIMIT 30')->fetchAll(\PDO::FETCH_NUM);
        $this->theme->render_admin_table(['ID', 'Zdarzenie', 'Stan', 'Próby', 'HTTP', 'Data UTC'], $rows);
        $this->theme->render_text('Kolejka pokazuje powiadomienia Rekrutacji. Pozostałe moduły zachowują własną historię wysyłki.');
    }
}
