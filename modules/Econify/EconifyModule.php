<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\Econify;

use JsonException;
use RuntimeException;
use Throwable;
use SyntaxDevTeam\Cms\Core\AdminMenuRegistry;
use SyntaxDevTeam\Cms\Core\AdminSearchProviderInterface;
use SyntaxDevTeam\Cms\Core\AdminSearchRegistry;
use SyntaxDevTeam\Cms\Core\DashboardProviderInterface;
use SyntaxDevTeam\Cms\Core\DashboardRegistry;
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
use SyntaxDevTeam\Cms\Modules\CoreAuth\OAuthAttemptLimiter;
use SyntaxDevTeam\Cms\Modules\CoreAuth\User;

final class EconifyModule implements ModuleInterface, PublicNavigationProviderInterface, AdminSearchProviderInterface, DashboardProviderInterface
{
    private const ECONOMY_TYPES = ['daily', 'work', 'vip_daily', 'transfer_in', 'transfer_out', 'adjustment'];

    public function __construct(
        private readonly ThemeInterface $theme,
        private readonly AdminMenuRegistry $menu,
        private readonly EconifyRepository $econify,
        private readonly AuthService $auth,
        private readonly AdminAccessGate $access,
        private readonly Security $security,
        private readonly AuditLogService $audit,
        private readonly EconifyConfig $config,
        private readonly EconifyDiscordGateway $discord,
        private readonly OAuthAttemptLimiter $oauthLimiter,
    ) {
    }

    public function id(): string { return 'econify'; }
    public function version(): string { return '1.1.0'; }
    public function dependencies(): array { return ['core_auth']; }
    public function isProtected(): bool { return false; }
    public function requiredPermissions(): array { return ['econify.view', 'econify.platform.manage']; }

    public function registerAdminMenu(AdminMenuRegistry $menu): void
    {
        $menu->add('Dedykowane', 'Econify', '/admin/econify', 'EC', 'econify.view', 20);
    }

    public function registerPublicNavigation(PublicNavigationRegistry $navigation): void
    {
        $navigation->add('econify.dashboard', 'Econify', '/econify', 'main', 35);
    }

    public function registerAdminSearch(AdminSearchRegistry $search): void
    {
        $search->add('econify.platform', 'Econify: platforma', 'Serwery Discord, plany i globalne funkcje bota.', 'index.php?route=/admin/econify', ['bot', 'discord', 'ekonomia', 'funkcje', 'freemium'], 'econify.view', 'Dedykowane', 20);
        $search->add('econify.server', 'Econify: mój serwer', 'Waluta, podatki, VIP daily, sklep i giełda serwera.', 'index.php?route=/econify/server', ['guild', 'waluta', 'podatki', 'vip', 'sklep'], 'econify.view', 'Dedykowane', 21);
    }

    public function registerDashboard(DashboardRegistry $dashboard): void
    {
        $dashboard->addMetric('econify.guilds', 'Serwery Econify', 'Aktywne serwery Discord obsługiwane przez moduł.', 'EC', function (): array {
            $stats = $this->econify->stats();
            return ['value' => $stats['guilds'], 'detail' => $stats['players'] . ' przypisanych graczy'];
        }, 'econify.view', 70);
        $dashboard->addPanel('econify.commerce', 'Handel Econify', 'Zamówienia i łączny obrót sklepów.', function (): array {
            $stats = $this->econify->stats();
            return ['headers' => ['Zamówienia', 'Łączna wartość'], 'rows' => [[$stats['orders'], $stats['volume']]], 'meta' => 'Wszystkie serwery'];
        }, 'econify.platform.manage', 71, false);
    }

    public function registerRoutes(Router $router): void
    {
        $router->get('/admin/econify', fn (Request $request) => $this->guard($request, 'econify.view', fn () => $this->renderPlatform()));
        $router->post('/admin/econify/feature', fn (Request $request) => $this->guard($request, 'econify.platform.manage', fn () => $this->saveFeature($request)));
        $router->post('/admin/econify/settings', fn (Request $request) => $this->guard($request, 'econify.platform.manage', fn () => $this->savePlatformSettings($request)));
        $router->get('/admin/econify/discord/connect', fn (Request $request) => $this->guard($request, 'econify.view', fn () => $this->startDiscordDiscovery($request)));
        $router->get('/admin/econify/discord/callback', fn (Request $request) => $this->guard($request, 'econify.view', fn () => $this->completeDiscordDiscovery($request)));
        $router->get('/econify/discord/callback', fn (Request $request) => $this->guard($request, 'econify.view', fn () => $this->completeDiscordDiscovery($request)));
        $router->get('/admin/econify/discord/server', fn (Request $request) => $this->guard($request, 'econify.view', fn () => $this->renderDiscordGuild($request)));
        $router->post('/admin/econify/discord/activate', fn (Request $request) => $this->guard($request, 'econify.view', fn () => $this->activateDiscordGuild($request)));
        $router->get('/econify', fn (Request $request) => $this->renderDashboard($request));
        $router->get('/econify/server', fn (Request $request) => $this->renderServer($request));
        $router->post('/econify/server/settings', fn (Request $request) => $this->saveServer($request));
        $router->post('/econify/server/member', fn (Request $request) => $this->addMember($request));
        $router->post('/econify/server/shop', fn (Request $request) => $this->addShopItem($request));
        $router->post('/econify/server/asset', fn (Request $request) => $this->addAsset($request));
        $router->post('/econify/server/quote', fn (Request $request) => $this->updateQuote($request));
        $router->get('/econify/shop', fn (Request $request) => $this->renderShop($request));
        $router->post('/econify/shop/buy', fn (Request $request) => $this->buyItem($request));
        $router->get('/econify/market', fn (Request $request) => $this->renderMarket($request));
        $router->post('/econify/market/trade', fn (Request $request) => $this->trade($request));
        $router->post('/api/econify/events', fn (Request $request) => $this->syncEvent($request));
    }

    private function renderPlatform(string $message = '', string $variant = 'info'): void
    {
        $user = $this->auth->user();
        if ($user === null) { return; }
        $this->startAdmin($user);
        if ($message !== '') { $this->theme->render_alert($message, $variant); }
        $features = $this->econify->features();
        $this->theme->start_admin_metrics();
        $stats = $this->econify->stats();
        $this->theme->render_admin_metric('Serwery', (string) $stats['guilds'], 'DS', 'aktywne tenanty Discord');
        $this->theme->render_admin_metric('Gracze', (string) $stats['players'], 'GR', 'powiązane konta');
        $this->theme->render_admin_metric('Zamówienia', (string) $stats['orders'], 'SH', 'cała platforma');
        $this->theme->end_admin_metrics();

        $integration = [
            ['label' => 'Plik modułu', 'ok' => $this->config->environmentReadable, 'value' => $this->config->environmentReadable ? 'Odczytany' : 'Brak', 'detail' => 'modules/Econify/.env lub ECONIFY_ENV_FILE'],
            ['label' => 'Token API', 'ok' => $this->config->apiConfigured(), 'value' => $this->config->apiConfigured() ? 'Skonfigurowany' : 'Brak lub za krótki', 'detail' => 'Nagłówek X-Econify-Token'],
            ['label' => 'Aplikacja Discord', 'ok' => $this->config->discordApplicationConfigured(), 'value' => $this->config->discordApplicationConfigured() ? 'Skonfigurowana' : 'Niekompletna', 'detail' => 'Client ID, Client Secret i callback'],
            ['label' => 'Token bota', 'ok' => $this->config->botTokenConfigured(), 'value' => $this->config->botTokenConfigured() ? 'Skonfigurowany' : 'Brak', 'detail' => 'Weryfikacja obecności bota'],
        ];
        if (count(array_filter($integration, static fn (array $item): bool => $item['ok'])) === count($integration)) {
            $this->theme->render_alert('Integracja Econify działa poprawnie. Wszystkie wymagane elementy są skonfigurowane.', 'success');
        } else {
            $this->theme->start_admin_panel('Konfiguracja integracji', 'Wartości sekretne nie są wyświetlane');
            $this->theme->render_admin_fact_grid(array_map(static fn (array $item): array => [
                'label' => $item['label'], 'value' => $item['value'], 'detail' => $item['detail'],
                'variant' => $item['ok'] ? 'success' : 'warning',
            ], $integration));
            $this->theme->end_admin_panel();
        }

        $this->theme->start_admin_panel_grid();
        $this->theme->start_admin_panel_column();
        $guildRows = array_map(static fn (array $guild): array => [
            'cells' => [(string) $guild['name'], (string) $guild['owner_name'], strtoupper((string) $guild['plan']), (int) $guild['member_count'], (int) $guild['is_active'] === 1 ? 'Aktywny' : 'Wyłączony'],
            'actions' => [['label' => 'Ustawienia', 'href' => 'index.php?route=/econify/server&guild_id=' . (int) $guild['id'], 'variant' => 'outline-light']],
        ], $this->econify->guilds());
        $this->theme->start_admin_panel('Serwery Econify', count($guildRows) . ' aktywowanych tenantów');
        $this->theme->render_admin_action_table(['Serwer', 'Właściciel', 'Plan', 'Członkowie', 'Stan'], $guildRows, $this->security->csrfToken());
        $this->theme->end_admin_panel();
        $this->theme->end_admin_panel_column();

        $this->theme->start_admin_panel_column();
        $discordGuilds = $this->discord->guilds($user->id);
        $this->theme->start_admin_panel('Twoje serwery Discord', 'Tylko serwery z uprawnieniem Owner, Administrator lub Zarządzanie serwerem');
        if (!$this->config->discordApplicationConfigured()) {
            $this->theme->render_alert('Uzupełnij dedykowane Client ID, Client Secret i callback Econify, aby pobrać listę serwerów.', 'warning');
        } elseif ($discordGuilds === []) {
            $this->theme->render_alert('Połącz Discord, aby jednorazowo pobrać serwery, którymi możesz zarządzać. Token użytkownika nie jest zapisywany.', 'info');
            $this->theme->render_admin_panel_actions([['label' => 'Pobierz moje serwery Discord', 'href' => 'index.php?route=/admin/econify/discord/connect', 'variant' => 'primary']]);
        } else {
            $this->theme->render_admin_panel_actions([['label' => 'Odśwież listę z Discord', 'href' => 'index.php?route=/admin/econify/discord/connect', 'variant' => 'outline-light']]);
            foreach ($discordGuilds as $guild) {
                $registered = $this->econify->guildByDiscordId($guild['id']);
                $this->theme->start_admin_panel((string) $guild['name'], $guild['access'] . ($registered !== null ? ' / Econify aktywne' : ' / Bot nieaktywny'));
                $this->theme->render_admin_fact_grid([
                    ['label' => 'Discord Guild ID', 'value' => $guild['id']],
                    ['label' => 'Status Econify', 'value' => $registered !== null ? 'Aktywny' : 'Nieaktywowany', 'variant' => $registered !== null ? 'success' : 'warning'],
                ]);
                $this->theme->render_admin_panel_actions([['label' => 'Otwórz serwer', 'href' => 'index.php?route=/admin/econify/discord/server&guild_id=' . rawurlencode($guild['id']), 'variant' => 'primary']]);
                $this->theme->end_admin_panel();
            }
        }
        $this->theme->end_admin_panel();
        $this->theme->end_admin_panel_column();
        $this->theme->end_admin_panel_grid();

        $this->theme->start_admin_panel_grid();
        $this->theme->start_admin_panel_column();
        $this->theme->start_admin_panel('Funkcje bota', 'Globalne przełączniki bez wdrażania nowej wersji modułu');
        $rows = array_map(fn (array $feature): array => [
            'cells' => [$feature['label'], $feature['description'], (int) $feature['is_enabled'] === 1 ? 'Włączona' : 'Wyłączona'],
            'actions' => $this->allows($user, 'econify.platform.manage') ? [[
                'label' => (int) $feature['is_enabled'] === 1 ? 'Wyłącz' : 'Włącz',
                'action' => 'index.php?route=/admin/econify/feature',
                'fields' => ['feature_key' => $feature['feature_key'], 'enabled' => (int) $feature['is_enabled'] === 1 ? '0' : '1'],
                'variant' => (int) $feature['is_enabled'] === 1 ? 'outline-danger' : 'primary',
            ]] : [],
        ], $features);
        $this->theme->render_admin_action_table(['Funkcja', 'Zakres', 'Stan'], $rows, $this->security->csrfToken());
        $this->theme->end_admin_panel();
        $this->theme->end_admin_panel_column();

        $this->theme->start_admin_panel_column();
        if ($this->allows($user, 'econify.platform.manage')) {
            $settings = $this->econify->platformSettings();
            $this->theme->start_admin_panel('Domyślna ekonomia bota', 'Nowe serwery dziedziczą te wartości');
            $this->theme->render_form('index.php?route=/admin/econify/settings', [
                ['name' => 'default_locale', 'label' => 'Język bota', 'type' => 'select', 'value' => (string) $settings['default_locale'], 'options' => ['pl' => 'Polski']],
                ['name' => 'default_daily_amount', 'label' => 'Domyślne /daily', 'type' => 'number', 'value' => (string) $settings['default_daily_amount']],
                ['name' => 'default_work_min', 'label' => 'Domyślne /work minimum', 'type' => 'number', 'value' => (string) $settings['default_work_min']],
                ['name' => 'default_work_max', 'label' => 'Domyślne /work maksimum', 'type' => 'number', 'value' => (string) $settings['default_work_max']],
                ['name' => 'freemium_shop_limit', 'label' => 'Limit sklepu Freemium', 'type' => 'number', 'value' => (string) $settings['freemium_shop_limit']],
            ], 'Zapisz ustawienia główne', $this->security->csrfToken());
            $this->theme->end_admin_panel();
        }
        $this->theme->end_admin_panel_column();
        $this->theme->end_admin_panel_grid();

        $this->theme->end_admin_content();
        $this->theme->end_admin_page();
    }

    private function renderDashboard(Request $request, string $message = '', string $variant = 'info'): void
    {
        $user = $this->requireUser();
        if ($user === null) { return; }
        $memberships = $this->econify->memberships($user->id);
        $membership = $this->selectedMembership($memberships, $request->queryInt('guild_id'));
        $this->startPublic('Econify', 'Twoje centrum ekonomii Discord: saldo, poziom, historia, sklep i giełda.');
        if ($message !== '') { $this->theme->render_alert($message, $variant); }
        if ($membership === null) {
            $this->theme->render_alert('Twoje konto nie jest jeszcze powiązane z żadnym serwerem Econify.', 'warning');
            $this->endPublic(); return;
        }
        $guildId = (int) $membership['guild_id'];
        $wallet = $this->econify->wallet($guildId, $user->id);
        $nextLevel = max(100, (int) $wallet['level'] * 1000);
        $this->theme->start_grid();
        $this->theme->start_column('4'); $this->theme->start_card('Saldo', $membership['currency_name']); $this->theme->render_text(number_format((int) $wallet['balance'], 0, ',', ' ')); $this->theme->end_card(); $this->theme->end_column();
        $this->theme->start_column('4'); $this->theme->start_card('Poziom', 'Level'); $this->theme->render_text((string) $wallet['level']); $this->theme->end_card(); $this->theme->end_column();
        $this->theme->start_column('4'); $this->theme->start_card('Doświadczenie', 'Postęp'); $this->theme->render_text((int) $wallet['experience'] . ' / ' . $nextLevel . ' EXP'); $this->theme->end_card(); $this->theme->end_column();
        $this->theme->end_grid();
        $links = [
            ['label' => 'Sklep serwerowy', 'href' => 'index.php?route=/econify/shop&guild_id=' . $guildId, 'meta' => 'Kup rangi, kody i nagrody'],
            ['label' => 'Giełda', 'href' => 'index.php?route=/econify/market&guild_id=' . $guildId, 'meta' => 'Aktywa i portfel inwestycyjny'],
        ];
        if (in_array($membership['access_role'], ['guild_owner', 'guild_admin'], true)) {
            $links[] = ['label' => 'Zarządzaj serwerem', 'href' => 'index.php?route=/econify/server&guild_id=' . $guildId, 'meta' => 'Waluta, podatki, VIP i katalog'];
        }
        $this->theme->start_card('Szybkie akcje', (string) $membership['name']); $this->theme->render_link_list($links); $this->theme->end_card();
        $rows = array_map(static fn (array $tx): array => [$tx['created_at'], $tx['transaction_type'], $tx['description'], (int) $tx['amount'], (int) $tx['balance_after']], $this->econify->transactions($guildId, $user->id));
        $this->theme->start_card('Historia transakcji', 'Ostatnie operacje'); $this->theme->render_table(['Data', 'Typ', 'Opis', 'Zmiana', 'Saldo'], $rows); $this->theme->end_card();
        $this->endPublic();
    }

    private function renderServer(Request $request, string $message = '', string $variant = 'info'): void
    {
        $context = $this->serverContext($request);
        if ($context === null) { return; }
        [$user, $membership] = $context; $guildId = (int) $membership['guild_id'];
        $this->startPublic('Ustawienia serwera ' . $membership['name'], 'Konfiguracja ekonomii, członków, sklepu i rynku.');
        if ($message !== '') { $this->theme->render_alert($message, $variant); }
        $this->theme->start_grid();
        $this->theme->start_column('6');
        $this->theme->start_card('Ekonomia i automatyzacje', strtoupper((string) $membership['plan']));
        $this->theme->render_form('index.php?route=/econify/server/settings', [
            ['name' => 'guild_id', 'label' => 'Serwer', 'type' => 'hidden', 'value' => (string) $guildId],
            ['name' => 'currency_name', 'label' => 'Nazwa waluty', 'value' => (string) $membership['currency_name']],
            ['name' => 'daily_amount', 'label' => 'Nagroda /daily', 'type' => 'number', 'value' => (string) $membership['daily_amount']],
            ['name' => 'work_min', 'label' => 'Minimalna nagroda /work', 'type' => 'number', 'value' => (string) $membership['work_min']],
            ['name' => 'work_max', 'label' => 'Maksymalna nagroda /work', 'type' => 'number', 'value' => (string) $membership['work_max']],
            ['name' => 'transfer_tax_percent', 'label' => 'Podatek od przelewu (%)', 'type' => 'number', 'value' => $this->percent((int) $membership['transfer_tax_bps']), 'help' => 'Zakres 0-25%, zapisywany precyzyjnie w punktach bazowych.'],
            ['name' => 'vip_role_id', 'label' => 'Discord Role ID dla VIP', 'value' => (string) ($membership['vip_role_id'] ?? '')],
            ['name' => 'vip_daily_amount', 'label' => 'VIP daily o północy', 'type' => 'number', 'value' => (string) $membership['vip_daily_amount']],
            ['name' => 'shop_enabled', 'label' => 'Sklep włączony', 'type' => 'checkbox', 'checked' => (int) $membership['shop_enabled'] === 1],
            ['name' => 'market_enabled', 'label' => 'Giełda włączona', 'type' => 'checkbox', 'checked' => (int) $membership['market_enabled'] === 1],
        ], 'Zapisz ustawienia', $this->security->csrfToken());
        $this->theme->end_card();
        $this->theme->end_column();
        $this->theme->start_column('6');
        $options = []; foreach ($this->econify->users() as $account) { $options[(string) $account['id']] = $account['label']; }
        $this->theme->start_card('Powiąż użytkownika', 'Dostęp ograniczony do tego serwera');
        $this->theme->render_form('index.php?route=/econify/server/member', [
            ['name' => 'guild_id', 'label' => 'Serwer', 'type' => 'hidden', 'value' => (string) $guildId],
            ['name' => 'user_id', 'label' => 'Konto miniPORTAL', 'type' => 'select', 'options' => $options],
            ['name' => 'discord_user_id', 'label' => 'Discord User ID'],
            ['name' => 'access_role', 'label' => 'Poziom dostępu', 'type' => 'select', 'options' => ['player' => 'Gracz', 'guild_admin' => 'Administrator serwera', 'guild_owner' => 'Właściciel serwera']],
        ], 'Powiąż konto', $this->security->csrfToken());
        $this->theme->end_card();
        $this->theme->end_column();
        $this->theme->end_grid();

        $this->theme->start_grid();
        $limit = (int) $this->econify->platformSettings()['freemium_shop_limit'];
        $this->theme->start_column('6'); $this->theme->start_card('Dodaj przedmiot', $membership['plan'] === 'freemium' ? 'Limit ' . $limit . ' aktywnych pozycji' : 'Katalog bez limitu');
        $this->theme->render_form('index.php?route=/econify/server/shop', [
            ['name' => 'guild_id', 'label' => 'Serwer', 'type' => 'hidden', 'value' => (string) $guildId],
            ['name' => 'name', 'label' => 'Nazwa'], ['name' => 'description', 'label' => 'Opis', 'type' => 'textarea'],
            ['name' => 'price', 'label' => 'Cena', 'type' => 'number'], ['name' => 'stock', 'label' => 'Stan (puste = bez limitu)', 'type' => 'number'],
            ['name' => 'delivery_type', 'label' => 'Realizacja', 'type' => 'select', 'options' => ['discord_role' => 'Ranga Discord', 'code' => 'Kod zewnętrzny', 'manual' => 'Ręczna']],
            ['name' => 'delivery_reference', 'label' => 'ID roli / bezpieczna referencja', 'help' => 'Nie wpisuj sekretnego kodu; przechowuj identyfikator w systemie bota.'],
        ], 'Dodaj do sklepu', $this->security->csrfToken()); $this->theme->end_card(); $this->theme->end_column();
        $this->theme->start_column('6'); $this->theme->start_card('Aktywa i notowania', 'Początkowa cena lub kolejne punkty historii');
        $this->theme->render_form('index.php?route=/econify/server/asset', [
            ['name' => 'guild_id', 'label' => 'Serwer', 'type' => 'hidden', 'value' => (string) $guildId],
            ['name' => 'symbol', 'label' => 'Symbol', 'help' => '2-12 wielkich liter lub cyfr.'], ['name' => 'name', 'label' => 'Nazwa aktywa'],
            ['name' => 'price', 'label' => 'Cena początkowa', 'type' => 'number'],
        ], 'Dodaj aktywo', $this->security->csrfToken());
        $assetOptions = []; foreach ($this->econify->market($guildId, $user->id) as $asset) { $assetOptions[(string) $asset['id']] = $asset['symbol'] . ' - ' . $asset['name']; }
        if ($assetOptions !== []) {
            $this->theme->render_form('index.php?route=/econify/server/quote', [
                ['name' => 'guild_id', 'label' => 'Serwer', 'type' => 'hidden', 'value' => (string) $guildId],
                ['name' => 'asset_id', 'label' => 'Aktywo', 'type' => 'select', 'options' => $assetOptions],
                ['name' => 'price', 'label' => 'Nowa cena', 'type' => 'number'],
            ], 'Dodaj notowanie', $this->security->csrfToken());
        }
        $this->theme->end_card(); $this->theme->end_column();
        $this->theme->end_grid();
        $this->theme->start_card('Katalog sklepu', $this->econify->activeShopItemCount($guildId) . ' aktywnych');
        $this->theme->render_table(['Nazwa', 'Cena', 'Stan', 'Realizacja'], array_map(static fn (array $item): array => [$item['name'], $item['price'], $item['stock'] ?? 'bez limitu', $item['delivery_type']], $this->econify->shopItems($guildId, false)));
        $this->theme->end_card();
        $this->endPublic();
    }

    private function renderShop(Request $request, string $message = '', string $variant = 'info'): void
    {
        $context = $this->playerContext($request); if ($context === null) { return; }
        [$user, $membership] = $context; $guildId = (int) $membership['guild_id'];
        $this->startPublic('Sklep ' . $membership['name'], 'Nagrody serwerowe rozliczane w walucie ' . $membership['currency_name'] . '.');
        if ($message !== '') { $this->theme->render_alert($message, $variant); }
        if (!$this->econify->featureEnabled('shop') || (int) $membership['shop_enabled'] !== 1) { $this->theme->render_alert('Sklep jest obecnie wyłączony.', 'warning'); $this->endPublic(); return; }
        $wallet = $this->econify->wallet($guildId, $user->id); $this->theme->render_alert('Dostępne saldo: ' . $wallet['balance'] . ' ' . $membership['currency_name'], 'info');
        foreach ($this->econify->shopItems($guildId) as $item) {
            $this->theme->start_card((string) $item['name'], $item['price'] . ' ' . $membership['currency_name']);
            $this->theme->render_text((string) $item['description']);
            $this->theme->render_form('index.php?route=/econify/shop/buy', [
                ['name' => 'guild_id', 'label' => 'Serwer', 'type' => 'hidden', 'value' => (string) $guildId],
                ['name' => 'item_id', 'label' => 'Przedmiot', 'type' => 'hidden', 'value' => (string) $item['id']],
            ], 'Kup', $this->security->csrfToken()); $this->theme->end_card();
        }
        $this->endPublic();
    }

    private function renderMarket(Request $request, string $message = '', string $variant = 'info'): void
    {
        $context = $this->playerContext($request); if ($context === null) { return; }
        [$user, $membership] = $context; $guildId = (int) $membership['guild_id'];
        $this->startPublic('Giełda ' . $membership['name'], 'Wirtualne aktywa serwera i Twój portfel.');
        if ($message !== '') { $this->theme->render_alert($message, $variant); }
        if (!$this->econify->featureEnabled('market') || (int) $membership['market_enabled'] !== 1) { $this->theme->render_alert('Giełda jest obecnie wyłączona.', 'warning'); $this->endPublic(); return; }
        $wallet = $this->econify->wallet($guildId, $user->id); $this->theme->render_alert('Gotówka: ' . $wallet['balance'] . ' ' . $membership['currency_name'], 'info');
        foreach ($this->econify->market($guildId, $user->id) as $asset) {
            $this->theme->start_card($asset['symbol'] . ' - ' . $asset['name'], 'Cena ' . $asset['current_price']);
            $this->theme->render_admin_fact_grid([
                ['label' => 'Posiadane jednostki', 'value' => (string) $asset['quantity']],
                ['label' => 'Średnia cena', 'value' => (string) $asset['average_price']],
                ['label' => 'Wartość portfela', 'value' => (string) ((int) $asset['quantity'] * (int) $asset['current_price'])],
            ]);
            $quoteData = array_reverse($this->econify->quotes((int) $asset['id']));
            $this->theme->render_line_chart(array_map(static fn (array $quote): array => ['label' => (string) $quote['quoted_at'], 'value' => (int) $quote['price']], $quoteData), 'Historia ceny ' . $asset['symbol']);
            $quotes = array_map(static fn (array $quote): array => [$quote['quoted_at'], $quote['price']], array_reverse($quoteData));
            $this->theme->render_table(['Notowanie', 'Cena'], $quotes);
            $this->theme->render_form('index.php?route=/econify/market/trade', [
                ['name' => 'guild_id', 'label' => 'Serwer', 'type' => 'hidden', 'value' => (string) $guildId],
                ['name' => 'asset_id', 'label' => 'Aktywo', 'type' => 'hidden', 'value' => (string) $asset['id']],
                ['name' => 'quantity', 'label' => 'Liczba jednostek', 'type' => 'number', 'value' => '1'],
                ['name' => 'side', 'label' => 'Operacja', 'type' => 'select', 'options' => ['buy' => 'Kup', 'sell' => 'Sprzedaj']],
            ], 'Złóż zlecenie', $this->security->csrfToken()); $this->theme->end_card();
        }
        $this->endPublic();
    }

    private function saveFeature(Request $request): void
    {
        if (!$this->csrf($request, 'econify_feature')) { return; }
        $ok = $this->econify->setFeature($request->postString('feature_key'), $request->postBool('enabled'), $this->auth->user()?->id ?? 0);
        $this->audit->record($request, 'econify_feature_update', $ok ? 'success' : 'not_found', null, $this->auth->user()?->id);
        $this->renderPlatform($ok ? 'Zmieniono stan funkcji.' : 'Nie znaleziono funkcji.', $ok ? 'success' : 'warning');
    }

    private function savePlatformSettings(Request $request): void
    {
        if (!$this->csrf($request, 'econify_platform_settings')) { return; }
        $daily = $request->postInt('default_daily_amount', -1) ?? -1; $min = $request->postInt('default_work_min', -1) ?? -1;
        $max = $request->postInt('default_work_max', -1) ?? -1; $limit = $request->postInt('freemium_shop_limit', -1) ?? -1;
        if ($request->postString('default_locale') !== 'pl' || min($daily, $min, $max) < 0 || $min > $max || $max > 1000000000 || $limit < 1 || $limit > 100) {
            $this->renderPlatform('Nieprawidłowe ustawienia główne Econify.', 'danger'); return;
        }
        $this->econify->updatePlatformSettings(['default_locale' => 'pl', 'default_daily_amount' => $daily, 'default_work_min' => $min, 'default_work_max' => $max, 'freemium_shop_limit' => $limit], $this->auth->user()?->id ?? 0);
        $this->audit->record($request, 'econify_platform_settings', 'success', null, $this->auth->user()?->id);
        $this->renderPlatform('Ustawienia główne Econify zostały zapisane.', 'success');
    }

    private function startDiscordDiscovery(Request $request): void
    {
        $user = $this->auth->user();
        if ($user === null) { return; }
        if (!$this->oauthLimiter->allowStart('econify_discord')) {
            $this->audit->record($request, 'econify_discord_oauth_start', 'rate_limited', 'discord', $user->id);
            http_response_code(429); $this->renderPlatform('Zbyt wiele prób połączenia z Discord. Spróbuj ponownie później.', 'warning'); return;
        }
        try { $url = $this->discord->discoveryUrl($user->id); }
        catch (Throwable) { $this->audit->record($request, 'econify_discord_oauth_start', 'not_configured', 'discord', $user->id); $this->renderPlatform('Dedykowana aplikacja Discord Econify nie jest jeszcze kompletna.', 'danger'); return; }
        $this->audit->record($request, 'econify_discord_oauth_start', 'success', 'discord', $user->id);
        header('Location: ' . $url, true, 302);
    }

    private function completeDiscordDiscovery(Request $request): void
    {
        $user = $this->auth->user();
        if ($user === null) { return; }
        if (!$this->oauthLimiter->allowCallback('econify_discord')) {
            $this->audit->record($request, 'econify_discord_oauth_callback', 'rate_limited', 'discord', $user->id);
            http_response_code(429); $this->renderPlatform('Przekroczono limit odpowiedzi OAuth Discord.', 'warning'); return;
        }
        if ($request->queryString('error') !== '') {
            $this->audit->record($request, 'econify_discord_oauth_callback', 'provider_denied', 'discord', $user->id);
            $this->renderPlatform('Pobieranie listy serwerów zostało anulowane.', 'warning'); return;
        }
        try { $guilds = $this->discord->complete($request->queryString('state'), $request->queryString('code'), $user->id); }
        catch (Throwable $exception) {
            error_log('Econify Discord OAuth failed: ' . $exception::class);
            $this->audit->record($request, 'econify_discord_oauth_callback', 'provider_error', 'discord', $user->id);
            http_response_code(502); $this->renderPlatform('Nie udało się bezpiecznie pobrać listy serwerów Discord.', 'danger'); return;
        }
        $this->audit->record($request, 'econify_discord_oauth_callback', 'success', 'discord', $user->id);
        $this->renderPlatform('Pobrano ' . count($guilds) . ' serwerów, którymi możesz zarządzać.', 'success');
    }

    private function renderDiscordGuild(Request $request, string $message = '', string $variant = 'info'): void
    {
        $user = $this->auth->user();
        if ($user === null) { return; }
        $guildId = $request->queryString('guild_id', $request->postString('guild_id'));
        $guild = $this->discord->guild($user->id, $guildId);
        if ($guild === null) { $this->renderPlatform('Serwer nie znajduje się w aktualnej, zweryfikowanej liście Discord. Odśwież listę.', 'warning'); return; }
        $registered = $this->econify->guildByDiscordId($guildId);
        try { $botPresent = $this->discord->botPresent($guildId); } catch (Throwable) { $botPresent = false; }
        $this->startAdmin($user);
        if ($message !== '') { $this->theme->render_alert($message, $variant); }
        $this->theme->start_admin_panel((string) $guild['name'], 'Zweryfikowany dostęp: ' . $guild['access']);
        $this->theme->render_admin_fact_grid([
            ['label' => 'Discord Guild ID', 'value' => $guildId],
            ['label' => 'Tenant Econify', 'value' => $registered !== null ? 'Aktywny' : 'Nieutworzony', 'variant' => $registered !== null ? 'success' : 'warning'],
            ['label' => 'Bot na serwerze', 'value' => $botPresent ? 'Połączony' : 'Niepotwierdzony', 'variant' => $botPresent ? 'success' : 'warning'],
            ['label' => 'Plan', 'value' => $registered !== null ? strtoupper((string) $registered['plan']) : 'FREEMIUM po aktywacji'],
        ]);
        $this->theme->render_admin_panel_actions([
            ['label' => $botPresent ? 'Ponów autoryzację bota' : 'Zaproś bota Econify', 'href' => $this->discord->installationUrl($guildId), 'variant' => 'primary'],
            ['label' => 'Odśwież listę Discord', 'href' => 'index.php?route=/admin/econify/discord/connect', 'variant' => 'outline-light'],
        ]);
        if ($registered === null) {
            $this->theme->render_form('index.php?route=/admin/econify/discord/activate', [
                ['name' => 'guild_id', 'label' => 'Serwer Discord', 'type' => 'hidden', 'value' => $guildId],
            ], 'Aktywuj plan Freemium', $this->security->csrfToken());
        } else {
            $this->theme->render_admin_panel_actions([['label' => 'Ustawienia ekonomii serwera', 'href' => 'index.php?route=/econify/server&guild_id=' . (int) $registered['id'], 'variant' => 'outline-light']]);
        }
        $this->theme->end_admin_panel();
        $this->theme->end_admin_content(); $this->theme->end_admin_page();
    }

    private function activateDiscordGuild(Request $request): void
    {
        if (!$this->csrf($request, 'econify_discord_activate')) { return; }
        $user = $this->auth->user();
        if ($user === null) { return; }
        $guildId = $request->postString('guild_id'); $guild = $this->discord->guild($user->id, $guildId); $discordUserId = $this->discord->discordUserId($user->id);
        if ($guild === null || $discordUserId === null) { http_response_code(403); $this->renderPlatform('Nie można aktywować serwera bez świeżej weryfikacji Discord.', 'danger'); return; }
        $registered = $this->econify->guildByDiscordId($guildId);
        try {
            $id = $registered !== null ? (int) $registered['id'] : $this->econify->createGuild($guildId, (string) $guild['name'], $user->id, 'freemium');
            $this->econify->addMembership($id, $user->id, $discordUserId, $guild['owner'] ? 'guild_owner' : 'guild_admin');
        } catch (Throwable) { $this->renderDiscordGuild($request, 'Nie udało się aktywować serwera Econify.', 'danger'); return; }
        $this->audit->record($request, 'econify_discord_activate', 'success', 'guild:' . $id, $user->id);
        $this->renderDiscordGuild($request, 'Serwer został aktywowany w planie Freemium.', 'success');
    }

    private function saveServer(Request $request): void
    {
        $context = $this->serverContext($request); if ($context === null || !$this->csrf($request, 'econify_server_update')) { return; }
        [, $membership] = $context; $daily = $request->postInt('daily_amount', -1) ?? -1; $min = $request->postInt('work_min', -1) ?? -1; $max = $request->postInt('work_max', -1) ?? -1;
        $vip = $request->postInt('vip_daily_amount', -1) ?? -1; $currency = $request->postString('currency_name'); $tax = $this->basisPoints($request->postString('transfer_tax_percent'));
        $roleId = $request->postString('vip_role_id');
        if ($currency === '' || strlen($currency) > 40 || min($daily, $min, $max, $vip) < 0 || $min > $max || $max > 1000000000 || $tax === null || ($roleId !== '' && preg_match('/^[0-9]{6,32}$/', $roleId) !== 1)) {
            $this->renderServer($request, 'Nieprawidłowe ustawienia ekonomii.', 'danger'); return;
        }
        $this->econify->updateGuild((int) $membership['guild_id'], [
            'currency_name' => $currency, 'daily_amount' => $daily, 'work_min' => $min, 'work_max' => $max,
            'transfer_tax_bps' => $tax, 'vip_role_id' => $roleId === '' ? null : $roleId, 'vip_daily_amount' => $vip,
            'shop_enabled' => $request->postBool('shop_enabled') ? 1 : 0, 'market_enabled' => $request->postBool('market_enabled') ? 1 : 0,
        ]);
        $this->audit->record($request, 'econify_server_update', 'success', 'guild:' . $membership['guild_id'], $this->auth->user()?->id);
        $this->renderServer($request, 'Ustawienia serwera zostały zapisane.', 'success');
    }

    private function addMember(Request $request): void
    {
        $context = $this->serverContext($request); if ($context === null || !$this->csrf($request, 'econify_member_upsert')) { return; }
        [, $membership] = $context; $userId = $request->postInt('user_id', 0) ?? 0; $discord = $request->postString('discord_user_id'); $role = $request->postString('access_role');
        if ($userId < 1 || preg_match('/^[0-9]{6,32}$/', $discord) !== 1 || !in_array($role, ['guild_owner', 'guild_admin', 'player'], true)) { $this->renderServer($request, 'Nieprawidłowe powiązanie użytkownika.', 'danger'); return; }
        try { $this->econify->addMembership((int) $membership['guild_id'], $userId, $discord, $role); }
        catch (\Throwable) { $this->renderServer($request, 'Konto Discord jest już powiązane albo dane są nieprawidłowe.', 'danger'); return; }
        $this->audit->record($request, 'econify_member_upsert', 'success', 'guild:' . $membership['guild_id'], $this->auth->user()?->id);
        $this->renderServer($request, 'Powiązanie konta zostało zapisane.', 'success');
    }

    private function addShopItem(Request $request): void
    {
        $context = $this->serverContext($request); if ($context === null || !$this->csrf($request, 'econify_shop_create')) { return; }
        [, $membership] = $context; $guildId = (int) $membership['guild_id'];
        $freemiumLimit = (int) $this->econify->platformSettings()['freemium_shop_limit'];
        if ($membership['plan'] === 'freemium' && $this->econify->activeShopItemCount($guildId) >= $freemiumLimit) { $this->renderServer($request, 'Plan Freemium pozwala na maksymalnie ' . $freemiumLimit . ' aktywnych pozycji.', 'warning'); return; }
        $name = $request->postString('name'); $description = $request->postString('description'); $price = $request->postInt('price', 0) ?? 0; $stockRaw = $request->postString('stock');
        $stock = $stockRaw === '' ? null : $request->postInt('stock'); $type = $request->postString('delivery_type'); $reference = $request->postString('delivery_reference');
        if ($name === '' || strlen($name) > 120 || strlen($description) > 1000 || $price < 1 || ($stock !== null && $stock < 0) || !in_array($type, ['discord_role', 'code', 'manual'], true) || strlen($reference) > 120) { $this->renderServer($request, 'Nieprawidłowe dane przedmiotu.', 'danger'); return; }
        $id = $this->econify->addShopItem($guildId, $name, $description, $price, $stock, $type, $reference === '' ? null : $reference);
        $this->audit->record($request, 'econify_shop_item_create', 'success', 'item:' . $id, $this->auth->user()?->id);
        $this->renderServer($request, 'Przedmiot dodano do sklepu.', 'success');
    }

    private function addAsset(Request $request): void
    {
        $context = $this->serverContext($request); if ($context === null || !$this->csrf($request, 'econify_asset_create')) { return; }
        [, $membership] = $context; $symbol = strtoupper($request->postString('symbol')); $name = $request->postString('name'); $price = $request->postInt('price', 0) ?? 0;
        if (preg_match('/^[A-Z0-9]{2,12}$/', $symbol) !== 1 || $name === '' || strlen($name) > 80 || $price < 1) { $this->renderServer($request, 'Nieprawidłowe dane aktywa.', 'danger'); return; }
        try { $id = $this->econify->addAsset((int) $membership['guild_id'], $symbol, $name, $price); }
        catch (\Throwable) { $this->renderServer($request, 'Symbol aktywa musi być unikalny na serwerze.', 'danger'); return; }
        $this->audit->record($request, 'econify_asset_create', 'success', 'asset:' . $id, $this->auth->user()?->id);
        $this->renderServer($request, 'Aktywo zostało dodane.', 'success');
    }

    private function updateQuote(Request $request): void
    {
        $context = $this->serverContext($request); if ($context === null || !$this->csrf($request, 'econify_quote_create')) { return; }
        [, $membership] = $context; $price = $request->postInt('price', 0) ?? 0; $assetId = $request->postInt('asset_id', 0) ?? 0;
        if ($price < 1 || $price > 1000000000000 || !$this->econify->updateAssetPrice((int) $membership['guild_id'], $assetId, $price)) { $this->renderServer($request, 'Nie udało się dodać notowania.', 'danger'); return; }
        $this->audit->record($request, 'econify_quote_create', 'success', 'asset:' . $assetId, $this->auth->user()?->id);
        $this->renderServer($request, 'Nowe notowanie zostało zapisane.', 'success');
    }

    private function buyItem(Request $request): void
    {
        $context = $this->playerContext($request); if ($context === null || !$this->csrf($request, 'econify_shop_buy')) { return; }
        [$user, $membership] = $context;
        try { $order = $this->econify->purchaseItem((int) $membership['guild_id'], $user->id, $request->postInt('item_id', 0) ?? 0); }
        catch (RuntimeException $exception) { $this->renderShop($request, $exception->getMessage(), 'danger'); return; }
        $this->audit->record($request, 'econify_shop_purchase', 'success', 'order:' . $order, $user->id);
        $this->renderShop($request, 'Zakup przyjęty. Numer zamówienia: ' . $order . '.', 'success');
    }

    private function trade(Request $request): void
    {
        $context = $this->playerContext($request); if ($context === null || !$this->csrf($request, 'econify_market_trade')) { return; }
        [$user, $membership] = $context; $side = $request->postString('side');
        if (!in_array($side, ['buy', 'sell'], true)) { $this->renderMarket($request, 'Nieprawidłowy kierunek zlecenia.', 'danger'); return; }
        try { $this->econify->trade((int) $membership['guild_id'], $user->id, $request->postInt('asset_id', 0) ?? 0, $request->postInt('quantity', 0) ?? 0, $side === 'buy'); }
        catch (RuntimeException $exception) { $this->renderMarket($request, $exception->getMessage(), 'danger'); return; }
        $this->audit->record($request, 'econify_market_trade', 'success', 'guild:' . $membership['guild_id'], $user->id);
        $this->renderMarket($request, 'Zlecenie zostało rozliczone.', 'success');
    }

    private function syncEvent(Request $request): void
    {
        if (!$this->config->apiConfigured() || !hash_equals($this->config->apiToken, $request->header('X-Econify-Token'))) { $this->jsonResponse(401, ['ok' => false, 'error' => 'unauthorized']); return; }
        if (!$this->econify->featureEnabled('economy')) { $this->jsonResponse(503, ['ok' => false, 'error' => 'economy_disabled']); return; }
        $data = $request->json(); $type = is_array($data) ? (string) ($data['type'] ?? '') : '';
        $guild = is_array($data) ? (string) ($data['guild_id'] ?? '') : ''; $discordUser = is_array($data) ? (string) ($data['user_id'] ?? '') : '';
        $amount = is_array($data) && is_int($data['amount'] ?? null) ? $data['amount'] : null; $experience = is_array($data) && is_int($data['experience'] ?? null) ? $data['experience'] : null;
        $level = is_array($data) && is_int($data['level'] ?? null) ? $data['level'] : null; $reference = is_array($data) ? (string) ($data['event_id'] ?? '') : '';
        $description = is_array($data) ? substr((string) ($data['description'] ?? ''), 0, 255) : '';
        if (!in_array($type, self::ECONOMY_TYPES, true) || preg_match('/^[0-9]{6,32}$/', $guild) !== 1 || preg_match('/^[0-9]{6,32}$/', $discordUser) !== 1 || $amount === null || $experience === null || $experience < 0 || $level === null || $level < 1 || $reference === '' || strlen($reference) > 96) { $this->jsonResponse(422, ['ok' => false, 'error' => 'invalid_payload']); return; }
        $identity = $this->econify->identity($guild, $discordUser); if ($identity === null) { $this->jsonResponse(404, ['ok' => false, 'error' => 'identity_not_found']); return; }
        $created = $this->econify->syncEconomy($identity['guild_id'], $identity['user_id'], $type, $amount, $experience, $level, $reference, $description);
        $this->jsonResponse($created ? 201 : 200, ['ok' => true, 'created' => $created]);
    }

    /** @return array{User,array<string,mixed>}|null */
    private function serverContext(Request $request): ?array
    {
        $context = $this->playerContext($request); if ($context === null) { return null; }
        if (!in_array($context[1]['access_role'], ['guild_owner', 'guild_admin'], true)) { http_response_code(403); $this->theme->render_public_error(403, 'Brak dostępu', 'Zarządzanie wymaga roli właściciela lub administratora serwera.', 'Wróć do Econify', '/econify'); return null; }
        return $context;
    }

    /** @return array{User,array<string,mixed>}|null */
    private function playerContext(Request $request): ?array
    {
        $user = $this->requireUser(); if ($user === null) { return null; }
        $guildId = $request->queryInt('guild_id') ?? $request->postInt('guild_id');
        $memberships = $this->econify->memberships($user->id); $selected = $this->selectedMembership($memberships, $guildId);
        if ($selected === null) { http_response_code(403); $this->theme->render_public_error(403, 'Brak dostępu', 'Nie masz aktywnego powiązania z tym serwerem Econify.', 'Wróć do Econify', '/econify'); return null; }
        $full = $this->econify->membership((int) $selected['guild_id'], $user->id);
        return $full === null ? null : [$user, $full];
    }

    private function requireUser(): ?User
    {
        $user = $this->auth->user(); if ($user !== null) { return $user; }
        http_response_code(401); $this->theme->render_public_error(401, 'Zaloguj się', 'Econify łączy portfel z Twoim lokalnym kontem.', 'Przejdź do logowania', '/admin/login'); return null;
    }

    /** @param list<array<string,mixed>> $memberships @return array<string,mixed>|null */
    private function selectedMembership(array $memberships, ?int $guildId): ?array
    {
        if ($guildId === null && count($memberships) === 1) { return $memberships[0]; }
        foreach ($memberships as $membership) { if ((int) $membership['guild_id'] === $guildId) { return $membership; } }
        return $memberships[0] ?? null;
    }

    private function startPublic(string $title, string $lead): void
    {
        $this->theme->start_page($title . ' - Econify', $lead); $this->theme->start_header($title, $lead, 'Econify / Discord Economy'); $this->theme->end_header(); $this->theme->start_section();
    }
    private function endPublic(): void { $this->theme->end_section(); $this->theme->end_page(); }

    private function startAdmin(User $user): void
    {
        $this->theme->start_admin_page('Econify', $this->menu->visibleFor($user->permissions), '/admin/econify', ['name' => $user->displayName, 'role' => ucfirst($user->primaryRole()), 'initials' => $user->initials(), 'logout_action' => 'index.php?route=/admin/logout', 'logout_token' => $this->security->csrfToken()]);
        $this->theme->start_admin_content('Econify Control Center', 'Wieloserwerowe centrum zarządzania botem ekonomicznym.', [['label' => 'Panel', 'href' => 'index.php?route=/admin'], ['label' => 'Econify', 'href' => '']]);
    }

    private function guard(Request $request, string $permission, callable $handler): void
    {
        $decision = $this->access->check($permission); if ($decision === AdminAccessGate::ALLOWED) { $handler(); return; }
        $result = $decision === AdminAccessGate::UNAUTHENTICATED ? 'unauthenticated' : 'forbidden';
        $this->audit->record($request, 'econify_acl', $result, null, $this->auth->user()?->id);
        http_response_code($decision === AdminAccessGate::UNAUTHENTICATED ? 401 : 403);
        $this->theme->render_admin_access_state(http_response_code(), 'Brak dostępu', 'Ta sekcja wymaga uprawnienia Econify.', 'index.php?route=/admin', 'Wróć do panelu');
    }

    private function csrf(Request $request, string $event): bool
    {
        if ($this->security->validateCsrfToken($request->postString('_token'))) { return true; }
        $this->audit->record($request, $event, 'invalid_csrf', null, $this->auth->user()?->id); http_response_code(403); $this->theme->render_public_error(403, 'Nieprawidłowy token', 'Odśwież formularz i spróbuj ponownie.', 'Wróć do Econify', '/econify'); return false;
    }

    private function allows(User $user, string $permission): bool { return in_array('*', $user->permissions, true) || in_array($permission, $user->permissions, true); }
    private function basisPoints(string $percent): ?int { if (preg_match('/^(?:[0-9]|1[0-9]|2[0-4])(?:[.,][0-9]{1,2})?$|^25(?:[.,]0{1,2})?$/', $percent) !== 1) { return null; } return (int) round((float) str_replace(',', '.', $percent) * 100); }
    private function percent(int $basisPoints): string { return rtrim(rtrim(number_format($basisPoints / 100, 2, '.', ''), '0'), '.'); }

    /** @param array<string,mixed> $payload */
    private function jsonResponse(int $status, array $payload): void
    {
        http_response_code($status); if (!headers_sent()) { header('Content-Type: application/json; charset=UTF-8'); header('Cache-Control: no-store'); }
        try { echo json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE); } catch (JsonException) { echo '{"ok":false}'; }
    }
}
