<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\Econizer;

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

final class EconizerModule implements ModuleInterface, PublicNavigationProviderInterface, AdminSearchProviderInterface, DashboardProviderInterface
{
    private const ECONOMY_TYPES = ['daily', 'work', 'vip_daily', 'transfer_in', 'transfer_out', 'adjustment'];
    private const DELIVERY_TYPES = ['discord_role', 'virtual_item', 'code', 'manual'];

    public function __construct(
        private readonly ThemeInterface $theme,
        private readonly AdminMenuRegistry $menu,
        private readonly EconizerRepository $econizer,
        private readonly AuthService $auth,
        private readonly AdminAccessGate $access,
        private readonly Security $security,
        private readonly AuditLogService $audit,
        private readonly EconizerConfig $config,
        private readonly EconizerDiscordGateway $discord,
        private readonly OAuthAttemptLimiter $oauthLimiter,
    ) {
    }

    public function id(): string { return 'econizer'; }
    public function version(): string { return '1.5.4'; }
    public function dependencies(): array { return ['core_auth']; }
    public function isProtected(): bool { return false; }
    public function requiredPermissions(): array { return ['econizer.view', 'econizer.platform.manage']; }

    public function registerAdminMenu(AdminMenuRegistry $menu): void
    {
        $menu->add('Dedykowane', 'Econizer', '/admin/econizer', 'EC', 'econizer.view', 20);
    }

    public function registerPublicNavigation(PublicNavigationRegistry $navigation): void
    {
        $navigation->add('econizer.dashboard', 'Econizer', '/econizer', 'main', 35);
    }

    public function registerAdminSearch(AdminSearchRegistry $search): void
    {
        $search->add('econizer.platform', 'Econizer: platforma', 'Serwery Discord, plany i globalne funkcje bota.', 'index.php?route=/admin/econizer', ['bot', 'discord', 'ekonomia', 'funkcje', 'freemium'], 'econizer.view', 'Dedykowane', 20);
        $search->add('econizer.server', 'Econizer: mój serwer', 'Waluta, podatki, VIP daily, sklep i giełda serwera.', 'index.php?route=/econizer/servers', ['guild', 'waluta', 'podatki', 'vip', 'sklep'], 'econizer.view', 'Dedykowane', 21);
    }

    public function registerDashboard(DashboardRegistry $dashboard): void
    {
        $dashboard->addMetric('econizer.guilds', 'Serwery Econizer', 'Aktywne serwery Discord obsługiwane przez moduł.', 'EC', function (): array {
            $stats = $this->econizer->stats();
            return ['value' => $stats['guilds'], 'detail' => $stats['players'] . ' przypisanych graczy'];
        }, 'econizer.view', 70);
        $dashboard->addPanel('econizer.commerce', 'Handel Econizer', 'Zamówienia i łączny obrót sklepów.', function (): array {
            $stats = $this->econizer->stats();
            return ['headers' => ['Zamówienia', 'Łączna wartość'], 'rows' => [[$stats['orders'], $stats['volume']]], 'meta' => 'Wszystkie serwery'];
        }, 'econizer.platform.manage', 71, false);
    }

    public function registerRoutes(Router $router): void
    {
        $router->get('/admin/econizer', fn (Request $request) => $this->guard($request, 'econizer.view', fn () => $this->renderPlatform()));
        $router->post('/admin/econizer/feature', fn (Request $request) => $this->guard($request, 'econizer.platform.manage', fn () => $this->saveFeature($request)));
        $router->post('/admin/econizer/settings', fn (Request $request) => $this->guard($request, 'econizer.platform.manage', fn () => $this->savePlatformSettings($request)));
        $router->get('/dashboard', fn (Request $request) => $this->redirectToEconizer());
        $router->get('/econizer', fn (Request $request) => $this->renderDashboard($request));
        $router->get('/econizer/servers', fn (Request $request) => $this->renderManagedServers($request));
        $router->get('/econizer/discord/connect', fn (Request $request) => $this->startDiscordDiscovery($request));
        $router->get('/econizer/discord/callback', fn (Request $request) => $this->completeDiscordDiscovery($request));
        $router->get('/econizer/discord/server', fn (Request $request) => $this->renderDiscordGuild($request));
        $router->post('/econizer/discord/link', fn (Request $request) => $this->linkDiscordGuild($request));
        $router->get('/econizer/server', fn (Request $request) => $this->renderServer($request));
        $router->post('/econizer/server/settings', fn (Request $request) => $this->saveServer($request));
        $router->post('/econizer/server/shop', fn (Request $request) => $this->addShopItem($request));
        $router->post('/econizer/server/asset', fn (Request $request) => $this->addAsset($request));
        $router->post('/econizer/server/quote', fn (Request $request) => $this->updateQuote($request));
        $router->get('/econizer/shop/{discord_guild_id}', fn (Request $request) => $this->renderShop($request));
        $router->get('/econizer/shop', fn (Request $request) => $this->renderShop($request));
        $router->post('/econizer/shop/buy', fn (Request $request) => $this->buyItem($request));
        $router->get('/econizer/market', fn (Request $request) => $this->renderMarket($request));
        $router->post('/econizer/market/trade', fn (Request $request) => $this->trade($request));
        $router->post('/api/econizer/guilds', fn (Request $request) => $this->syncGuild($request));
        $router->get('/api/econizer/shop/orders', fn (Request $request) => $this->shopOrders($request));
        $router->post('/api/econizer/shop/orders/fulfill', fn (Request $request) => $this->fulfillShopOrder($request));
        $router->post('/api/econizer/events', fn (Request $request) => $this->syncEvent($request));
    }

    private function renderPlatform(string $message = '', string $variant = 'info'): void
    {
        $user = $this->auth->user();
        if ($user === null) { return; }
        $this->startAdmin($user);
        if ($message !== '') { $this->theme->render_alert($message, $variant); }
        $features = $this->econizer->features();
        $this->theme->start_admin_metrics();
        $stats = $this->econizer->stats();
        $this->theme->render_admin_metric('Serwery', (string) $stats['guilds'], 'DS', 'aktywne tenanty Discord');
        $this->theme->render_admin_metric('Gracze', (string) $stats['players'], 'GR', 'powiązane konta');
        $this->theme->render_admin_metric('Zamówienia', (string) $stats['orders'], 'SH', 'cała platforma');
        $this->theme->end_admin_metrics();

        $integration = [
            ['label' => 'Plik modułu', 'ok' => $this->config->environmentReadable, 'value' => $this->config->environmentReadable ? 'Odczytany' : 'Brak', 'detail' => 'modules/Econizer/.env lub ECONIZER_ENV_FILE'],
            ['label' => 'Token API', 'ok' => $this->config->apiConfigured(), 'value' => $this->config->apiConfigured() ? 'Skonfigurowany' : 'Brak lub za krótki', 'detail' => 'Nagłówek X-Econizer-Token'],
            ['label' => 'Aplikacja Discord', 'ok' => $this->config->discordApplicationConfigured(), 'value' => $this->config->discordApplicationConfigured() ? 'Skonfigurowana' : 'Niekompletna', 'detail' => 'Client ID, Client Secret, callback oraz nazwa i ikona aplikacji w Discord Developer Portal'],
            ['label' => 'Token bota', 'ok' => $this->config->botTokenConfigured(), 'value' => $this->config->botTokenConfigured() ? 'Skonfigurowany' : 'Brak', 'detail' => 'Weryfikacja obecności bota'],
        ];
        if (count(array_filter($integration, static fn (array $item): bool => $item['ok'])) === count($integration)) {
            $this->theme->render_alert('Integracja Econizer działa poprawnie. Wszystkie wymagane elementy są skonfigurowane.', 'success');
        } else {
            $this->theme->start_admin_panel('Konfiguracja integracji', 'Wartości sekretne nie są wyświetlane');
            $this->theme->render_admin_fact_grid(array_map(static fn (array $item): array => [
                'label' => $item['label'], 'value' => $item['value'], 'detail' => $item['detail'],
                'variant' => $item['ok'] ? 'success' : 'warning',
            ], $integration));
            $this->theme->end_admin_panel();
        }
        if ($this->allows($user, 'econizer.platform.manage')) {
            $this->renderPlatformBotApi();
        }

        $guildRows = array_map(static fn (array $guild): array => [
            'cells' => [(string) $guild['name'], (string) $guild['owner_name'], strtoupper((string) $guild['plan']), (int) $guild['member_count'], (int) $guild['is_active'] === 1 ? 'Aktywny' : 'Wyłączony'],
            'actions' => [['label' => 'Ustawienia', 'href' => 'index.php?route=/econizer/server&guild_id=' . (int) $guild['id'], 'variant' => 'outline-light']],
        ], $this->econizer->guilds());
        $this->theme->start_admin_panel('Serwery Econizer', count($guildRows) . ' aktywowanych tenantów');
        $this->theme->render_alert('Ten widok pokazuje tylko serwery zgłoszone przez bota do /api/econizer/guilds. Zaproszenie bota odbywa się po stronie właściciela lub administratora Discord w widoku /econizer/servers.', 'info');
        $this->theme->render_admin_action_table(['Serwer', 'Właściciel', 'Plan', 'Członkowie', 'Stan'], $guildRows, $this->security->csrfToken());
        $this->theme->end_admin_panel();

        $this->theme->start_admin_panel_grid();
        $this->theme->start_admin_panel_column();
        $this->theme->start_admin_panel('Funkcje bota', 'Globalne przełączniki bez wdrażania nowej wersji modułu');
        $rows = array_map(fn (array $feature): array => [
            'cells' => [$feature['label'], $feature['description'], (int) $feature['is_enabled'] === 1 ? 'Włączona' : 'Wyłączona'],
            'actions' => $this->allows($user, 'econizer.platform.manage') ? [[
                'label' => (int) $feature['is_enabled'] === 1 ? 'Wyłącz' : 'Włącz',
                'action' => 'index.php?route=/admin/econizer/feature',
                'fields' => ['feature_key' => $feature['feature_key'], 'enabled' => (int) $feature['is_enabled'] === 1 ? '0' : '1'],
                'variant' => (int) $feature['is_enabled'] === 1 ? 'outline-danger' : 'primary',
            ]] : [],
        ], $features);
        $this->theme->render_admin_action_table(['Funkcja', 'Zakres', 'Stan'], $rows, $this->security->csrfToken());
        $this->theme->end_admin_panel();
        $this->theme->end_admin_panel_column();

        $this->theme->start_admin_panel_column();
        if ($this->allows($user, 'econizer.platform.manage')) {
            $settings = $this->econizer->platformSettings();
            $this->theme->start_admin_panel('Domyślna ekonomia bota', 'Nowe serwery dziedziczą te wartości');
            $this->theme->render_form('index.php?route=/admin/econizer/settings', [
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
        $memberships = $this->econizer->memberships($user->id);
        $membership = $this->selectedMembership($memberships, $request->queryInt('guild_id'));
        $this->startPublic('Econizer', 'Your Discord economy hub: balance, level, history, shop and market.');
        if ($message !== '') { $this->theme->render_alert($message, $variant); }
        if ($membership === null) {
            $this->theme->render_alert($memberships === [] ? 'Your account is not linked to any Econizer server yet.' : 'Choose the Econizer server you want to open.', $memberships === [] ? 'warning' : 'info');
            $this->theme->start_card('Discord servers', $memberships === [] ? 'Choose a server you manage' : 'Linked servers');
            $links = $memberships === []
                ? [['label' => 'Show my Discord servers', 'href' => 'index.php?route=/econizer/servers', 'meta' => 'Owner, Administrator or Manage Guild']]
                : array_map(static fn (array $item): array => [
                    'label' => (string) $item['name'],
                    'href' => 'index.php?route=/econizer&guild_id=' . (int) $item['guild_id'],
                    'meta' => strtoupper((string) $item['access_role']),
                ], $memberships);
            $this->theme->render_link_list($links);
            $this->theme->end_card();
            $this->endPublic(); return;
        }
        $guildId = (int) $membership['guild_id'];
        $wallet = $this->econizer->wallet($guildId, $user->id);
        $nextLevel = max(100, (int) $wallet['level'] * 1000);
        $this->theme->start_grid();
        $this->theme->start_column('lg-4'); $this->theme->start_card('Balance', $membership['currency_name']); $this->theme->render_text(number_format((int) $wallet['balance'], 0, '.', ' ')); $this->theme->end_card(); $this->theme->end_column();
        $this->theme->start_column('lg-4'); $this->theme->start_card('Level', 'Level'); $this->theme->render_text((string) $wallet['level']); $this->theme->end_card(); $this->theme->end_column();
        $this->theme->start_column('lg-4'); $this->theme->start_card('Experience', 'Progress'); $this->theme->render_text((int) $wallet['experience'] . ' / ' . $nextLevel . ' EXP'); $this->theme->end_card(); $this->theme->end_column();
        $this->theme->end_grid();
        $links = [
            ['label' => 'My Discord servers', 'href' => 'index.php?route=/econizer/servers', 'meta' => 'Bot installation and managed server settings'],
            ['label' => 'Server shop', 'href' => 'index.php?route=/econizer/shop&guild_id=' . $guildId, 'meta' => 'Buy ranks, codes and rewards'],
            ['label' => 'Market', 'href' => 'index.php?route=/econizer/market&guild_id=' . $guildId, 'meta' => 'Assets and investment portfolio'],
        ];
        if (in_array($membership['access_role'], ['guild_owner', 'guild_admin'], true)) {
            $links[] = ['label' => 'Manage server', 'href' => 'index.php?route=/econizer/server&guild_id=' . $guildId, 'meta' => 'Currency, taxes, VIP and catalog'];
        }
        $this->theme->start_card('Quick actions', (string) $membership['name']); $this->theme->render_link_list($links, 'two-column'); $this->theme->end_card();
        $rows = array_map(static fn (array $tx): array => [$tx['created_at'], $tx['transaction_type'], $tx['description'], (int) $tx['amount'], (int) $tx['balance_after']], $this->econizer->transactions($guildId, $user->id));
        $this->theme->start_card('Transaction history', 'Recent operations'); $this->theme->render_table(['Date', 'Type', 'Description', 'Change', 'Balance'], $rows); $this->theme->end_card();
        $this->endPublic();
    }

    private function redirectToEconizer(): void
    {
        header('Location: /econizer', true, 302);
    }

    private function renderServer(Request $request, string $message = '', string $variant = 'info'): void
    {
        $context = $this->serverContext($request);
        if ($context === null) { return; }
        [$user, $membership] = $context; $guildId = (int) $membership['guild_id'];
        $this->startPublic($membership['name'] . ' server settings', 'Configure economy, members, shop and market.');
        if ($message !== '') { $this->theme->render_alert($message, $variant); }
        $tab = $this->serverTab($request);
        $this->theme->render_tabs($this->serverTabs($guildId, $tab));

        if ($tab === 'shop') {
            $this->renderServerShop($guildId, $membership);
            $this->endPublic();
            return;
        }
        if ($tab === 'market') {
            $this->renderServerMarket($guildId, $user, $membership);
            $this->endPublic();
            return;
        }
        $this->renderServerOverview($guildId, $membership);
        $this->endPublic();
    }

    /** @param array<string,mixed> $membership */
    private function renderServerOverview(int $guildId, array $membership): void
    {
        $this->theme->start_grid();
        $this->theme->start_column('6');
        $this->theme->start_card('Economy and automation', strtoupper((string) $membership['plan']));
        $this->theme->render_form('index.php?route=/econizer/server/settings', [
            ['name' => 'guild_id', 'label' => 'Server', 'type' => 'hidden', 'value' => (string) $guildId],
            ['name' => 'tab', 'label' => 'Tab', 'type' => 'hidden', 'value' => 'overview'],
            ['name' => 'currency_name', 'label' => 'Currency name', 'value' => (string) $membership['currency_name']],
            ['name' => 'daily_amount', 'label' => '/daily reward', 'type' => 'number', 'value' => (string) $membership['daily_amount']],
            ['name' => 'work_min', 'label' => 'Minimum /work reward', 'type' => 'number', 'value' => (string) $membership['work_min']],
            ['name' => 'work_max', 'label' => 'Maximum /work reward', 'type' => 'number', 'value' => (string) $membership['work_max']],
            ['name' => 'transfer_tax_percent', 'label' => 'Transfer tax (%)', 'type' => 'number', 'value' => $this->percent((int) $membership['transfer_tax_bps']), 'help' => 'Range 0-25%, stored precisely in basis points.'],
            ['name' => 'vip_role_id', 'label' => 'Discord Role ID for VIP', 'value' => (string) ($membership['vip_role_id'] ?? '')],
            ['name' => 'vip_daily_amount', 'label' => 'VIP daily at midnight', 'type' => 'number', 'value' => (string) $membership['vip_daily_amount']],
            ['name' => 'shop_enabled', 'label' => 'Shop enabled', 'type' => 'checkbox', 'checked' => (int) $membership['shop_enabled'] === 1],
            ['name' => 'market_enabled', 'label' => 'Market enabled', 'type' => 'checkbox', 'checked' => (int) $membership['market_enabled'] === 1],
        ], 'Save settings', $this->security->csrfToken());
        $this->theme->end_card();
        $this->theme->end_column();
        $this->theme->start_column('6');
        $this->theme->start_card('Discord users', 'No manual miniPORTAL account selection');
        $this->theme->render_text('Players are linked to the server by their Discord User ID. If a user signed in to miniPORTAL through Discord, the first bot event for that Discord ID automatically assigns them to the correct server as a player. Server owners and administrators are linked automatically after Discord confirms their managed server list.');
        $discordGuildId = (string) ($membership['discord_guild_id'] ?? '');
        if ($discordGuildId !== '') {
            $this->theme->render_link_list([
                ['label' => 'Player shop link', 'href' => '/econizer/shop/' . rawurlencode($discordGuildId), 'meta' => 'Share this URL with players linked to this Discord server'],
            ]);
        }
        $this->theme->end_card();
        $this->theme->end_column();
        $this->theme->end_grid();
    }

    /** @param array<string,mixed> $membership */
    private function renderServerShop(int $guildId, array $membership): void
    {
        $this->theme->start_grid();
        $limit = (int) $this->econizer->platformSettings()['freemium_shop_limit'];
        $this->theme->start_column('6'); $this->theme->start_card('Add item', $membership['plan'] === 'freemium' ? 'Limit: ' . $limit . ' active items' : 'Unlimited catalog');
        $this->theme->render_form('index.php?route=/econizer/server/shop', [
            ['name' => 'guild_id', 'label' => 'Server', 'type' => 'hidden', 'value' => (string) $guildId],
            ['name' => 'tab', 'label' => 'Tab', 'type' => 'hidden', 'value' => 'shop'],
            ['name' => 'name', 'label' => 'Name'], ['name' => 'description', 'label' => 'Description', 'type' => 'textarea'],
            ['name' => 'price', 'label' => 'Price', 'type' => 'number'], ['name' => 'stock', 'label' => 'Stock (empty = unlimited)', 'type' => 'number'],
            ['name' => 'delivery_type', 'label' => 'Delivery', 'type' => 'select', 'options' => $this->deliveryOptions()],
            ['name' => 'delivery_reference', 'label' => 'Role ID / item key / safe reference', 'help' => 'For Discord role enter Role ID. For virtual item enter the bot-side item key/SKU. miniPORTAL stores the order; the bot fulfills it.'],
        ], 'Add to shop', $this->security->csrfToken()); $this->theme->end_card(); $this->theme->end_column();
        $this->theme->start_column('6');
        $this->theme->start_card('Shop fulfillment', 'Bot pulls pending orders');
        $this->theme->render_text('miniPORTAL creates pending orders and never calls Discord directly. The Econizer bot should poll /api/econizer/shop/orders, grant the Discord role or virtual item, then confirm the order through /api/econizer/shop/orders/fulfill.');
        $this->theme->render_admin_fact_grid([
            ['label' => 'Discord role', 'value' => 'Bot grants role ID'],
            ['label' => 'Virtual item', 'value' => 'Bot reads item key'],
            ['label' => 'Manual/code', 'value' => 'Bot or staff fulfills'],
        ]);
        $this->theme->end_card();
        $this->theme->end_column();
        $this->theme->end_grid();

        $items = $this->econizer->shopItems($guildId, false);
        if ($items === []) {
            $this->theme->start_card('Shop catalog', $this->econizer->activeShopItemCount($guildId) . ' active');
            $this->theme->render_text('No shop items yet.');
            $this->theme->end_card();
            return;
        }
        $this->theme->render_alert('Shop catalog: ' . $this->econizer->activeShopItemCount($guildId) . ' active items.', 'info');
        $this->theme->start_grid();
        foreach ($items as $item) {
            $this->theme->start_column('md-6');
            $this->theme->start_card((string) $item['name'], (int) $item['is_active'] === 1 ? 'Active' : 'Disabled');
            if ((string) $item['description'] !== '') {
                $this->theme->render_text((string) $item['description']);
            }
            $this->theme->render_admin_fact_grid([
                ['label' => 'Price', 'value' => (string) $item['price']],
                ['label' => 'Stock', 'value' => $item['stock'] === null ? 'unlimited' : (string) $item['stock']],
                ['label' => 'Delivery', 'value' => $this->deliveryLabel((string) $item['delivery_type'])],
                ['label' => 'Reference', 'value' => (string) ($item['delivery_reference'] ?? 'none')],
            ]);
            $this->theme->end_card();
            $this->theme->end_column();
        }
        $this->theme->end_grid();
    }

    /** @param array<string,mixed> $membership */
    private function renderServerMarket(int $guildId, User $user, array $membership): void
    {
        $this->theme->start_grid();
        $this->theme->start_column('6'); $this->theme->start_card('Assets and quotes', 'Initial price or new history points');
        $this->theme->render_form('index.php?route=/econizer/server/asset', [
            ['name' => 'guild_id', 'label' => 'Server', 'type' => 'hidden', 'value' => (string) $guildId],
            ['name' => 'tab', 'label' => 'Tab', 'type' => 'hidden', 'value' => 'market'],
            ['name' => 'symbol', 'label' => 'Symbol', 'help' => '2-12 uppercase letters or digits.'], ['name' => 'name', 'label' => 'Asset name'],
            ['name' => 'price', 'label' => 'Initial price', 'type' => 'number'],
        ], 'Add asset', $this->security->csrfToken());
        $assetOptions = []; foreach ($this->econizer->market($guildId, $user->id) as $asset) { $assetOptions[(string) $asset['id']] = $asset['symbol'] . ' - ' . $asset['name']; }
        if ($assetOptions !== []) {
            $this->theme->render_form('index.php?route=/econizer/server/quote', [
                ['name' => 'guild_id', 'label' => 'Server', 'type' => 'hidden', 'value' => (string) $guildId],
                ['name' => 'tab', 'label' => 'Tab', 'type' => 'hidden', 'value' => 'market'],
                ['name' => 'asset_id', 'label' => 'Asset', 'type' => 'select', 'options' => $assetOptions],
                ['name' => 'price', 'label' => 'New price', 'type' => 'number'],
            ], 'Add quote', $this->security->csrfToken());
        }
        $this->theme->end_card(); $this->theme->end_column();
        $this->theme->start_column('6');
        $this->theme->start_card('Current quotes', (int) $membership['market_enabled'] === 1 ? 'Market enabled' : 'Market disabled');
        $assets = $this->econizer->marketAssets($guildId);
        if ($assets === []) {
            $this->theme->render_text('No market assets yet.');
        } else {
            foreach ($assets as $asset) {
                $this->theme->render_admin_fact_grid([
                    ['label' => (string) $asset['symbol'], 'value' => (string) $asset['current_price'], 'detail' => (string) $asset['name']],
                    ['label' => 'Last quote', 'value' => (string) ($asset['last_quote_at'] ?? 'none')],
                    ['label' => 'Status', 'value' => (int) $asset['is_active'] === 1 ? 'Active' : 'Disabled'],
                ]);
            }
        }
        $this->theme->end_card();
        $this->theme->end_column();
        $this->theme->end_grid();
    }

    private function renderPlatformBotApi(): void
    {
        $this->theme->start_admin_panel('Bot delivery contract', 'Pull-based fulfillment');
        $this->theme->render_text('Role grants and virtual item delivery are performed by the Discord bot. miniPORTAL stores purchases as pending orders so the bot owner can process them with the dedicated API token, without exposing Discord bot credentials to per-server settings.');
        $this->theme->render_admin_fact_grid([
            ['label' => 'Fetch pending', 'value' => 'GET /api/econizer/shop/orders?guild_id={discord_guild_id}'],
            ['label' => 'Confirm order', 'value' => 'POST /api/econizer/shop/orders/fulfill'],
            ['label' => 'Sync guild', 'value' => 'POST /api/econizer/guilds'],
            ['label' => 'Sync economy', 'value' => 'POST /api/econizer/events'],
            ['label' => 'Auth', 'value' => 'X-Econizer-Token'],
        ]);
        $this->theme->end_admin_panel();
    }

    private function renderShop(Request $request, string $message = '', string $variant = 'info'): void
    {
        $context = $this->playerContext($request); if ($context === null) { return; }
        [$user, $membership] = $context; $guildId = (int) $membership['guild_id'];
        $this->startPublic($membership['name'] . ' shop', 'Server rewards priced in ' . $membership['currency_name'] . '.');
        if ($message !== '') { $this->theme->render_alert($message, $variant); }
        if (!$this->econizer->featureEnabled('shop') || (int) $membership['shop_enabled'] !== 1) { $this->theme->render_alert('The shop is currently disabled.', 'warning'); $this->endPublic(); return; }
        $wallet = $this->econizer->wallet($guildId, $user->id); $this->theme->render_alert('Available balance: ' . $wallet['balance'] . ' ' . $membership['currency_name'], 'info');
        foreach ($this->econizer->shopItems($guildId) as $item) {
            $this->theme->start_card((string) $item['name'], $item['price'] . ' ' . $membership['currency_name']);
            $this->theme->render_text((string) $item['description']);
            $this->theme->render_form('index.php?route=/econizer/shop/buy', [
                ['name' => 'guild_id', 'label' => 'Server', 'type' => 'hidden', 'value' => (string) $guildId],
                ['name' => 'item_id', 'label' => 'Item', 'type' => 'hidden', 'value' => (string) $item['id']],
            ], 'Buy', $this->security->csrfToken()); $this->theme->end_card();
        }
        $this->endPublic();
    }

    private function renderMarket(Request $request, string $message = '', string $variant = 'info'): void
    {
        $context = $this->playerContext($request); if ($context === null) { return; }
        [$user, $membership] = $context; $guildId = (int) $membership['guild_id'];
        $this->startPublic($membership['name'] . ' market', 'Virtual server assets and your portfolio.');
        if ($message !== '') { $this->theme->render_alert($message, $variant); }
        if (!$this->econizer->featureEnabled('market') || (int) $membership['market_enabled'] !== 1) { $this->theme->render_alert('The market is currently disabled.', 'warning'); $this->endPublic(); return; }
        $wallet = $this->econizer->wallet($guildId, $user->id); $this->theme->render_alert('Cash: ' . $wallet['balance'] . ' ' . $membership['currency_name'], 'info');
        foreach ($this->econizer->market($guildId, $user->id) as $asset) {
            $this->theme->start_card($asset['symbol'] . ' - ' . $asset['name'], 'Price ' . $asset['current_price']);
            $this->theme->render_admin_fact_grid([
                ['label' => 'Units owned', 'value' => (string) $asset['quantity']],
                ['label' => 'Average price', 'value' => (string) $asset['average_price']],
                ['label' => 'Portfolio value', 'value' => (string) ((int) $asset['quantity'] * (int) $asset['current_price'])],
            ]);
            $quoteData = array_reverse($this->econizer->quotes((int) $asset['id']));
            $this->theme->render_line_chart(array_map(static fn (array $quote): array => ['label' => (string) $quote['quoted_at'], 'value' => (int) $quote['price']], $quoteData), $asset['symbol'] . ' price history');
            $quotes = array_map(static fn (array $quote): array => [$quote['quoted_at'], $quote['price']], array_reverse($quoteData));
            $this->theme->render_table(['Quote', 'Price'], $quotes);
            $this->theme->render_form('index.php?route=/econizer/market/trade', [
                ['name' => 'guild_id', 'label' => 'Server', 'type' => 'hidden', 'value' => (string) $guildId],
                ['name' => 'asset_id', 'label' => 'Asset', 'type' => 'hidden', 'value' => (string) $asset['id']],
                ['name' => 'quantity', 'label' => 'Units', 'type' => 'number', 'value' => '1'],
                ['name' => 'side', 'label' => 'Operation', 'type' => 'select', 'options' => ['buy' => 'Buy', 'sell' => 'Sell']],
            ], 'Place order', $this->security->csrfToken()); $this->theme->end_card();
        }
        $this->endPublic();
    }

    private function saveFeature(Request $request): void
    {
        if (!$this->csrf($request, 'econizer_feature')) { return; }
        $ok = $this->econizer->setFeature($request->postString('feature_key'), $request->postBool('enabled'), $this->auth->user()?->id ?? 0);
        $this->audit->record($request, 'econizer_feature_update', $ok ? 'success' : 'not_found', null, $this->auth->user()?->id);
        $this->renderPlatform($ok ? 'Zmieniono stan funkcji.' : 'Nie znaleziono funkcji.', $ok ? 'success' : 'warning');
    }

    private function savePlatformSettings(Request $request): void
    {
        if (!$this->csrf($request, 'econizer_platform_settings')) { return; }
        $daily = $request->postInt('default_daily_amount', -1) ?? -1; $min = $request->postInt('default_work_min', -1) ?? -1;
        $max = $request->postInt('default_work_max', -1) ?? -1; $limit = $request->postInt('freemium_shop_limit', -1) ?? -1;
        if ($request->postString('default_locale') !== 'pl' || min($daily, $min, $max) < 0 || $min > $max || $max > 1000000000 || $limit < 1 || $limit > 100) {
            $this->renderPlatform('Nieprawidłowe ustawienia główne Econizer.', 'danger'); return;
        }
        $this->econizer->updatePlatformSettings(['default_locale' => 'pl', 'default_daily_amount' => $daily, 'default_work_min' => $min, 'default_work_max' => $max, 'freemium_shop_limit' => $limit], $this->auth->user()?->id ?? 0);
        $this->audit->record($request, 'econizer_platform_settings', 'success', null, $this->auth->user()?->id);
        $this->renderPlatform('Ustawienia główne Econizer zostały zapisane.', 'success');
    }

    private function startDiscordDiscovery(Request $request): void
    {
        $user = $this->requireUser();
        if ($user === null) { return; }
        if (!$this->oauthLimiter->allowStart('econizer_discord')) {
            $this->audit->record($request, 'econizer_discord_oauth_start', 'rate_limited', 'discord', $user->id);
            http_response_code(429); $this->renderManagedServers($request, 'Too many Discord connection attempts. Try again later.', 'warning'); return;
        }
        try { $url = $this->discord->discoveryUrl($user->id); }
        catch (Throwable) { $this->audit->record($request, 'econizer_discord_oauth_start', 'not_configured', 'discord', $user->id); $this->renderManagedServers($request, 'The dedicated Econizer Discord application is not complete yet.', 'danger'); return; }
        $this->audit->record($request, 'econizer_discord_oauth_start', 'success', 'discord', $user->id);
        header('Location: ' . $url, true, 302);
    }

    private function completeDiscordDiscovery(Request $request): void
    {
        $user = $this->requireUser();
        if ($user === null) { return; }
        if (!$this->oauthLimiter->allowCallback('econizer_discord')) {
            $this->audit->record($request, 'econizer_discord_oauth_callback', 'rate_limited', 'discord', $user->id);
            http_response_code(429); $this->renderManagedServers($request, 'Discord OAuth callback limit has been exceeded.', 'warning'); return;
        }
        if ($request->queryString('error') !== '') {
            $this->audit->record($request, 'econizer_discord_oauth_callback', 'provider_denied', 'discord', $user->id);
            $this->renderManagedServers($request, 'Fetching the server list was canceled.', 'warning'); return;
        }
        try { $guilds = $this->discord->complete($request->queryString('state'), $request->queryString('code'), $user->id); }
        catch (Throwable $exception) {
            error_log('Econizer Discord OAuth failed: ' . $exception::class);
            $this->audit->record($request, 'econizer_discord_oauth_callback', 'provider_error', 'discord', $user->id);
            http_response_code(502); $this->renderManagedServers($request, 'Could not safely fetch the Discord server list.', 'danger'); return;
        }
        $linked = $this->syncManagedDiscordGuilds($user, $guilds);
        $this->audit->record($request, 'econizer_discord_oauth_callback', 'success', 'discord', $user->id);
        $message = 'Fetched ' . count($guilds) . ' servers you can manage.';
        if ($linked > 0) {
            $message .= ' Econizer access was updated for ' . $linked . ' reported servers.';
        }
        $this->renderManagedServers($request, $message, 'success');
    }

    private function renderManagedServers(Request $request, string $message = '', string $variant = 'info'): void
    {
        $user = $this->requireUser();
        if ($user === null) { return; }
        $this->startPublic('My Discord servers', 'Choose a server where you are an owner or administrator.');
        if ($message !== '') { $this->theme->render_alert($message, $variant); }
        if (!$this->config->discordApplicationConfigured()) {
            $this->theme->render_alert('The dedicated Econizer Discord application is not configured yet.', 'warning');
            $this->endPublic(); return;
        }
        $guilds = $this->discord->guilds($user->id);
        if ($guilds === []) {
            $this->theme->start_card('Connect Discord', 'The list is stored temporarily in the session only');
            $this->theme->render_text('Fetch servers where you have Owner, Administrator or Manage Guild access. The Discord user token is not stored.');
            $this->theme->render_link_list([
                ['label' => 'Fetch my Discord servers', 'href' => 'index.php?route=/econizer/discord/connect', 'meta' => 'Authorization Code + PKCE'],
            ]);
            $this->theme->end_card();
            $this->endPublic(); return;
        }
        $this->syncManagedDiscordGuilds($user, $guilds);
        $this->theme->start_grid();
        foreach ($guilds as $guild) {
            $registered = $this->econizer->guildByDiscordId($guild['id']);
            $membership = $registered !== null ? $this->econizer->membership((int) $registered['id'], $user->id) : null;
            $canManage = $membership !== null && in_array($membership['access_role'], ['guild_owner', 'guild_admin'], true);
            $this->theme->start_column('lg-6');
            $this->theme->start_card((string) $guild['name'], 'Verified access: ' . $guild['access']);
            $this->theme->render_avatar((string) $guild['name'], $this->guildIconUrl($guild), 'md');
            $this->theme->render_admin_fact_grid([
                ['label' => 'Discord Guild ID', 'value' => $guild['id']],
                ['label' => 'Econizer', 'value' => $registered !== null ? 'Bot reported this server' : 'Bot not reported', 'variant' => $registered !== null ? 'success' : 'warning'],
            ]);
            $links = $canManage
                ? [['label' => 'Open Econizer settings', 'href' => 'index.php?route=/econizer/server&guild_id=' . (int) $registered['id'], 'meta' => 'Currency, shop, market and members']]
                : [['label' => 'Server details', 'href' => 'index.php?route=/econizer/discord/server&guild_id=' . rawurlencode($guild['id']), 'meta' => $registered !== null ? 'Check automatic access' : 'Invite the Econizer bot']];
            $this->theme->render_link_list($links);
            $this->theme->end_card();
            $this->theme->end_column();
        }
        $this->theme->start_column('12');
        $this->theme->start_card('Refresh list', 'Discord OAuth');
        $this->theme->render_link_list([
            ['label' => 'Refresh from Discord', 'href' => 'index.php?route=/econizer/discord/connect', 'meta' => 'Fetch managed servers again'],
        ]);
        $this->theme->end_card();
        $this->theme->end_column();
        $this->theme->end_grid();
        $this->endPublic();
    }

    private function renderDiscordGuild(Request $request, string $message = '', string $variant = 'info'): void
    {
        $user = $this->requireUser();
        if ($user === null) { return; }
        $guildId = $request->queryString('guild_id', $request->postString('guild_id'));
        $guild = $this->discord->guild($user->id, $guildId);
        if ($guild === null) { $this->renderManagedServers($request, 'The server is not in the current verified Discord list. Refresh the list.', 'warning'); return; }
        $this->syncManagedDiscordGuilds($user, [$guild]);
        $registered = $this->econizer->guildByDiscordId($guildId);
        try { $botPresent = $this->discord->botPresent($guildId); } catch (Throwable) { $botPresent = false; }
        $this->startPublic('Discord server: ' . (string) $guild['name'], 'Bot installation and Econizer settings for the selected server.');
        if ($message !== '') { $this->theme->render_alert($message, $variant); }
        $this->theme->start_card((string) $guild['name'], 'Verified access: ' . $guild['access']);
        $this->theme->render_avatar((string) $guild['name'], $this->guildIconUrl($guild), 'lg');
        $this->theme->render_admin_fact_grid([
            ['label' => 'Discord Guild ID', 'value' => $guildId],
            ['label' => 'Econizer tenant', 'value' => $registered !== null ? 'Reported by bot' : 'No bot report', 'variant' => $registered !== null ? 'success' : 'warning'],
            ['label' => 'Bot on server', 'value' => $botPresent ? 'Connected' : 'Unconfirmed', 'variant' => $botPresent ? 'success' : 'warning'],
            ['label' => 'Plan', 'value' => $registered !== null ? strtoupper((string) $registered['plan']) : 'FREEMIUM after report'],
        ]);
        if ($registered === null) {
            $this->theme->render_alert('Once you invite the bot, the Econizer settings for that guild will appear.', 'info');
            $this->theme->render_link_list([
                ['label' => 'Invite Econizer to the server', 'href' => $this->discord->installationUrl($guildId), 'meta' => 'Discord: bot applications.commands'],
                ['label' => 'Refresh from Discord', 'href' => 'index.php?route=/econizer/discord/connect', 'meta' => 'Check again after adding the bot'],
            ]);
        } else {
            $membership = $this->econizer->membership((int) $registered['id'], $user->id);
            $canManage = $membership !== null && in_array($membership['access_role'], ['guild_owner', 'guild_admin'], true);
            if (!$botPresent) {
                $this->theme->render_alert('The bot is not confirmed on this Discord server. You can invite Econizer again, then refresh the server list after the bot reports the guild.', 'warning');
                $links = [
                    ['label' => 'Invite Econizer to the server', 'href' => $this->discord->installationUrl($guildId), 'meta' => 'Discord: bot applications.commands'],
                    ['label' => 'Refresh from Discord', 'href' => 'index.php?route=/econizer/discord/connect', 'meta' => 'Check again after adding the bot'],
                ];
                if ($canManage) {
                    $links[] = ['label' => 'Econizer server settings', 'href' => 'index.php?route=/econizer/server&guild_id=' . (int) $registered['id'], 'meta' => 'Currency, shop, market and members'];
                }
                $this->theme->render_link_list($links);
                if (!$canManage) {
                    $this->theme->render_form('index.php?route=/econizer/discord/link', [
                        ['name' => 'guild_id', 'label' => 'Server', 'type' => 'hidden', 'value' => $guildId],
                    ], 'Link local account', $this->security->csrfToken());
                }
            } elseif ($canManage) {
                $this->theme->render_alert('Discord confirmed your server management rights, so Econizer access is already linked to this local account.', 'success');
                $this->theme->render_link_list([
                    ['label' => 'Econizer server settings', 'href' => 'index.php?route=/econizer/server&guild_id=' . (int) $registered['id'], 'meta' => 'Currency, shop, market and members'],
                ]);
            } else {
                $this->theme->render_alert('Discord access was verified, but Econizer could not update the local server membership automatically. Refresh the server list and try again.', 'warning');
                $this->theme->render_form('index.php?route=/econizer/discord/link', [
                    ['name' => 'guild_id', 'label' => 'Server', 'type' => 'hidden', 'value' => $guildId],
                ], 'Link local account', $this->security->csrfToken());
            }
        }
        $this->theme->end_card();
        $this->endPublic();
    }

    private function linkDiscordGuild(Request $request): void
    {
        if (!$this->csrf($request, 'econizer_discord_link')) { return; }
        $user = $this->requireUser();
        if ($user === null) { return; }
        $guildId = $request->postString('guild_id'); $guild = $this->discord->guild($user->id, $guildId); $discordUserId = $this->discord->discordUserId($user->id);
        if ($guild === null || $discordUserId === null) { http_response_code(403); $this->renderDiscordGuild($request, 'The server cannot be linked without a fresh Discord verification.', 'danger'); return; }
        $registered = $this->econizer->guildByDiscordId($guildId);
        if ($registered === null) { $this->renderDiscordGuild($request, 'Invite the bot first. The tenant is created only after Econizer reports the server.', 'warning'); return; }
        try {
            $this->econizer->addMembership((int) $registered['id'], $user->id, $discordUserId, $guild['owner'] ? 'guild_owner' : 'guild_admin');
        } catch (Throwable) { $this->renderDiscordGuild($request, 'Could not link the account with the Econizer server.', 'danger'); return; }
        $this->audit->record($request, 'econizer_discord_link', 'success', 'guild:' . $registered['id'], $user->id);
        $this->renderServer(Request::fromArrays(['guild_id' => (string) $registered['id']], [], ['REQUEST_METHOD' => 'GET']), 'The account has been linked with the Discord server.', 'success');
    }

    private function saveServer(Request $request): void
    {
        $context = $this->serverContext($request); if ($context === null || !$this->csrf($request, 'econizer_server_update')) { return; }
        [, $membership] = $context; $daily = $request->postInt('daily_amount', -1) ?? -1; $min = $request->postInt('work_min', -1) ?? -1; $max = $request->postInt('work_max', -1) ?? -1;
        $vip = $request->postInt('vip_daily_amount', -1) ?? -1; $currency = $request->postString('currency_name'); $tax = $this->basisPoints($request->postString('transfer_tax_percent'));
        $roleId = $request->postString('vip_role_id');
        if ($currency === '' || strlen($currency) > 40 || min($daily, $min, $max, $vip) < 0 || $min > $max || $max > 1000000000 || $tax === null || ($roleId !== '' && preg_match('/^[0-9]{6,32}$/', $roleId) !== 1)) {
            $this->renderServer($request, 'Invalid economy settings.', 'danger'); return;
        }
        $this->econizer->updateGuild((int) $membership['guild_id'], [
            'currency_name' => $currency, 'daily_amount' => $daily, 'work_min' => $min, 'work_max' => $max,
            'transfer_tax_bps' => $tax, 'vip_role_id' => $roleId === '' ? null : $roleId, 'vip_daily_amount' => $vip,
            'shop_enabled' => $request->postBool('shop_enabled') ? 1 : 0, 'market_enabled' => $request->postBool('market_enabled') ? 1 : 0,
        ]);
        $this->audit->record($request, 'econizer_server_update', 'success', 'guild:' . $membership['guild_id'], $this->auth->user()?->id);
        $this->renderServer($request, 'Server settings have been saved.', 'success');
    }

    private function addShopItem(Request $request): void
    {
        $context = $this->serverContext($request); if ($context === null || !$this->csrf($request, 'econizer_shop_create')) { return; }
        [, $membership] = $context; $guildId = (int) $membership['guild_id'];
        $freemiumLimit = (int) $this->econizer->platformSettings()['freemium_shop_limit'];
        if ($membership['plan'] === 'freemium' && $this->econizer->activeShopItemCount($guildId) >= $freemiumLimit) { $this->renderServer($request, 'The Freemium plan allows up to ' . $freemiumLimit . ' active items.', 'warning'); return; }
        $name = $request->postString('name'); $description = $request->postString('description'); $price = $request->postInt('price', 0) ?? 0; $stockRaw = $request->postString('stock');
        $stock = $stockRaw === '' ? null : $request->postInt('stock'); $type = $request->postString('delivery_type'); $reference = $request->postString('delivery_reference');
        if ($name === '' || strlen($name) > 120 || strlen($description) > 1000 || $price < 1 || ($stock !== null && $stock < 0) || !in_array($type, self::DELIVERY_TYPES, true) || strlen($reference) > 120) { $this->renderServer($request, 'Invalid item data.', 'danger'); return; }
        $id = $this->econizer->addShopItem($guildId, $name, $description, $price, $stock, $type, $reference === '' ? null : $reference);
        $this->audit->record($request, 'econizer_shop_item_create', 'success', 'item:' . $id, $this->auth->user()?->id);
        $this->renderServer($request, 'The item has been added to the shop.', 'success');
    }

    private function addAsset(Request $request): void
    {
        $context = $this->serverContext($request); if ($context === null || !$this->csrf($request, 'econizer_asset_create')) { return; }
        [, $membership] = $context; $symbol = strtoupper($request->postString('symbol')); $name = $request->postString('name'); $price = $request->postInt('price', 0) ?? 0;
        if (preg_match('/^[A-Z0-9]{2,12}$/', $symbol) !== 1 || $name === '' || strlen($name) > 80 || $price < 1) { $this->renderServer($request, 'Invalid asset data.', 'danger'); return; }
        try { $id = $this->econizer->addAsset((int) $membership['guild_id'], $symbol, $name, $price); }
        catch (\Throwable) { $this->renderServer($request, 'The asset symbol must be unique on this server.', 'danger'); return; }
        $this->audit->record($request, 'econizer_asset_create', 'success', 'asset:' . $id, $this->auth->user()?->id);
        $this->renderServer($request, 'The asset has been added.', 'success');
    }

    private function updateQuote(Request $request): void
    {
        $context = $this->serverContext($request); if ($context === null || !$this->csrf($request, 'econizer_quote_create')) { return; }
        [, $membership] = $context; $price = $request->postInt('price', 0) ?? 0; $assetId = $request->postInt('asset_id', 0) ?? 0;
        if ($price < 1 || $price > 1000000000000 || !$this->econizer->updateAssetPrice((int) $membership['guild_id'], $assetId, $price)) { $this->renderServer($request, 'Could not add the quote.', 'danger'); return; }
        $this->audit->record($request, 'econizer_quote_create', 'success', 'asset:' . $assetId, $this->auth->user()?->id);
        $this->renderServer($request, 'The new quote has been saved.', 'success');
    }

    private function buyItem(Request $request): void
    {
        $context = $this->playerContext($request); if ($context === null || !$this->csrf($request, 'econizer_shop_buy')) { return; }
        [$user, $membership] = $context;
        try { $order = $this->econizer->purchaseItem((int) $membership['guild_id'], $user->id, $request->postInt('item_id', 0) ?? 0); }
        catch (RuntimeException $exception) { $this->renderShop($request, $exception->getMessage(), 'danger'); return; }
        $this->audit->record($request, 'econizer_shop_purchase', 'success', 'order:' . $order, $user->id);
        $this->renderShop($request, 'Purchase accepted. Order number: ' . $order . '.', 'success');
    }

    private function trade(Request $request): void
    {
        $context = $this->playerContext($request); if ($context === null || !$this->csrf($request, 'econizer_market_trade')) { return; }
        [$user, $membership] = $context; $side = $request->postString('side');
        if (!in_array($side, ['buy', 'sell'], true)) { $this->renderMarket($request, 'Invalid order side.', 'danger'); return; }
        try { $this->econizer->trade((int) $membership['guild_id'], $user->id, $request->postInt('asset_id', 0) ?? 0, $request->postInt('quantity', 0) ?? 0, $side === 'buy'); }
        catch (RuntimeException $exception) { $this->renderMarket($request, $exception->getMessage(), 'danger'); return; }
        $this->audit->record($request, 'econizer_market_trade', 'success', 'guild:' . $membership['guild_id'], $user->id);
        $this->renderMarket($request, 'The order has been settled.', 'success');
    }

    private function syncGuild(Request $request): void
    {
        if (!$this->config->apiConfigured() || !hash_equals($this->config->apiToken, $request->header('X-Econizer-Token'))) { $this->jsonResponse(401, ['ok' => false, 'error' => 'unauthorized']); return; }
        $data = $request->json();
        $guildId = is_array($data) ? (string) ($data['guild_id'] ?? '') : '';
        $name = is_array($data) ? trim((string) ($data['name'] ?? '')) : '';
        $action = is_array($data) ? (string) ($data['action'] ?? 'installed') : 'installed';
        if (preg_match('/^[0-9]{6,32}$/', $guildId) !== 1 || $name === '' || strlen($name) > 120 || !in_array($action, ['installed', 'removed'], true)) {
            $this->jsonResponse(422, ['ok' => false, 'error' => 'invalid_payload']); return;
        }
        $id = $this->econizer->upsertDiscordGuild($guildId, $name, $action === 'installed');
        $this->jsonResponse($id > 0 ? 200 : 500, ['ok' => $id > 0, 'guild_id' => $id, 'active' => $action === 'installed']);
    }

    private function shopOrders(Request $request): void
    {
        if (!$this->config->apiConfigured() || !hash_equals($this->config->apiToken, $request->header('X-Econizer-Token'))) { $this->jsonResponse(401, ['ok' => false, 'error' => 'unauthorized']); return; }
        $guildId = $request->queryString('guild_id');
        if (preg_match('/^[0-9]{6,32}$/', $guildId) !== 1) { $this->jsonResponse(422, ['ok' => false, 'error' => 'invalid_guild']); return; }
        $orders = array_map(static fn (array $order): array => [
            'order_id' => (int) $order['order_id'],
            'guild_id' => (string) $order['discord_guild_id'],
            'discord_user_id' => (string) $order['discord_user_id'],
            'user_id' => (int) $order['user_id'],
            'item_id' => (int) $order['item_id'],
            'name' => (string) $order['name'],
            'description' => (string) $order['description'],
            'delivery_type' => (string) $order['delivery_type'],
            'delivery_reference' => (string) ($order['delivery_reference'] ?? ''),
            'price' => (int) $order['price'],
            'created_at' => (string) $order['created_at'],
        ], $this->econizer->pendingShopOrders($guildId, $request->queryInt('limit', 50) ?? 50));
        $this->jsonResponse(200, ['ok' => true, 'orders' => $orders]);
    }

    private function fulfillShopOrder(Request $request): void
    {
        if (!$this->config->apiConfigured() || !hash_equals($this->config->apiToken, $request->header('X-Econizer-Token'))) { $this->jsonResponse(401, ['ok' => false, 'error' => 'unauthorized']); return; }
        $data = $request->json();
        $guildId = is_array($data) ? (string) ($data['guild_id'] ?? '') : $request->postString('guild_id');
        $orderId = is_array($data) && is_int($data['order_id'] ?? null) ? (int) $data['order_id'] : ($request->postInt('order_id', 0) ?? 0);
        $status = is_array($data) ? (string) ($data['status'] ?? 'fulfilled') : $request->postString('status', 'fulfilled');
        if (preg_match('/^[0-9]{6,32}$/', $guildId) !== 1 || $orderId < 1 || !in_array($status, ['fulfilled', 'cancelled'], true)) { $this->jsonResponse(422, ['ok' => false, 'error' => 'invalid_payload']); return; }
        $ok = $this->econizer->markShopOrder($guildId, $orderId, $status);
        $this->jsonResponse($ok ? 200 : 404, ['ok' => $ok]);
    }

    private function syncEvent(Request $request): void
    {
        if (!$this->config->apiConfigured() || !hash_equals($this->config->apiToken, $request->header('X-Econizer-Token'))) { $this->jsonResponse(401, ['ok' => false, 'error' => 'unauthorized']); return; }
        if (!$this->econizer->featureEnabled('economy')) { $this->jsonResponse(503, ['ok' => false, 'error' => 'economy_disabled']); return; }
        $data = $request->json(); $type = is_array($data) ? (string) ($data['type'] ?? '') : '';
        $guild = is_array($data) ? (string) ($data['guild_id'] ?? '') : ''; $discordUser = is_array($data) ? (string) ($data['user_id'] ?? '') : '';
        $amount = is_array($data) && is_int($data['amount'] ?? null) ? $data['amount'] : null; $experience = is_array($data) && is_int($data['experience'] ?? null) ? $data['experience'] : null;
        $level = is_array($data) && is_int($data['level'] ?? null) ? $data['level'] : null; $reference = is_array($data) ? (string) ($data['event_id'] ?? '') : '';
        $description = is_array($data) ? substr((string) ($data['description'] ?? ''), 0, 255) : '';
        if (!in_array($type, self::ECONOMY_TYPES, true) || preg_match('/^[0-9]{6,32}$/', $guild) !== 1 || preg_match('/^[0-9]{6,32}$/', $discordUser) !== 1 || $amount === null || $experience === null || $experience < 0 || $level === null || $level < 1 || $reference === '' || strlen($reference) > 96) { $this->jsonResponse(422, ['ok' => false, 'error' => 'invalid_payload']); return; }
        $identity = $this->econizer->identity($guild, $discordUser); if ($identity === null) { $this->jsonResponse(404, ['ok' => false, 'error' => 'identity_not_found']); return; }
        $created = $this->econizer->syncEconomy($identity['guild_id'], $identity['user_id'], $type, $amount, $experience, $level, $reference, $description);
        $this->jsonResponse($created ? 201 : 200, ['ok' => true, 'created' => $created]);
    }

    /** @return array{User,array<string,mixed>}|null */
    private function serverContext(Request $request): ?array
    {
        $context = $this->playerContext($request); if ($context === null) { return null; }
        if (!in_array($context[1]['access_role'], ['guild_owner', 'guild_admin'], true)) { http_response_code(403); $this->theme->render_public_error(403, 'Access denied', 'Management requires the server owner or administrator role.', 'Back to Econizer', '/econizer'); return null; }
        return $context;
    }

    /** @return array{User,array<string,mixed>}|null */
    private function playerContext(Request $request): ?array
    {
        $user = $this->requireUser(); if ($user === null) { return null; }
        $guildId = $request->queryInt('guild_id') ?? $request->postInt('guild_id');
        $routeGuildId = $request->routeString('discord_guild_id');
        if ($guildId === null && $routeGuildId !== '') {
            $registered = preg_match('/^[0-9]{6,32}$/', $routeGuildId) === 1 ? $this->econizer->guildByDiscordId($routeGuildId) : null;
            $guildId = $registered !== null ? (int) $registered['id'] : 0;
        }
        $memberships = $this->econizer->memberships($user->id); $selected = $this->selectedMembership($memberships, $guildId);
        if ($selected === null) {
            http_response_code(403);
            $message = $guildId === null ? 'Choose a server-specific Econizer link from your dashboard or use the shop URL shared by the Discord server owner.' : 'You do not have an active link with this Econizer server.';
            $this->theme->render_public_error(403, 'Access denied', $message, 'Back to Econizer', '/econizer');
            return null;
        }
        $full = $this->econizer->membership((int) $selected['guild_id'], $user->id);
        return $full === null ? null : [$user, $full];
    }

    /** @param list<array{id:string,owner:bool}> $guilds */
    private function syncManagedDiscordGuilds(User $user, array $guilds): int
    {
        $discordUserId = $this->discord->discordUserId($user->id);
        if ($discordUserId === null || $guilds === []) {
            return 0;
        }
        try {
            return count($this->econizer->syncManagedGuildMemberships($user->id, $discordUserId, $guilds));
        } catch (Throwable $exception) {
            error_log('Econizer managed guild sync failed: ' . $exception::class);
            return 0;
        }
    }

    private function requireUser(): ?User
    {
        $user = $this->auth->user(); if ($user !== null) { return $user; }
        http_response_code(401); $this->theme->render_public_error(401, 'Sign in', 'Econizer links the wallet with your local account.', 'Go to sign in', '/admin/login'); return null;
    }

    /** @param list<array<string,mixed>> $memberships @return array<string,mixed>|null */
    private function selectedMembership(array $memberships, ?int $guildId): ?array
    {
        if ($guildId === null && count($memberships) === 1) { return $memberships[0]; }
        foreach ($memberships as $membership) { if ((int) $membership['guild_id'] === $guildId) { return $membership; } }
        return null;
    }

    private function startPublic(string $title, string $lead): void
    {
        $this->theme->start_page($title . ' - Econizer', $lead); $this->theme->start_header($title, $lead, 'Econizer / Discord Economy'); $this->theme->end_header(); $this->theme->start_section();
    }
    private function endPublic(): void { $this->theme->end_section(); $this->theme->end_page(); }

    private function startAdmin(User $user): void
    {
        $this->theme->start_admin_page('Econizer', $this->menu->visibleFor($user->permissions), '/admin/econizer', ['name' => $user->displayName, 'role' => ucfirst($user->primaryRole()), 'initials' => $user->initials(), 'logout_action' => 'index.php?route=/admin/logout', 'logout_token' => $this->security->csrfToken()]);
        $this->theme->start_admin_content('Econizer Control Center', 'Wieloserwerowe centrum zarządzania botem ekonomicznym.', [['label' => 'Panel', 'href' => 'index.php?route=/admin'], ['label' => 'Econizer', 'href' => '']]);
    }

    private function guard(Request $request, string $permission, callable $handler): void
    {
        $decision = $this->access->check($permission); if ($decision === AdminAccessGate::ALLOWED) { $handler(); return; }
        $result = $decision === AdminAccessGate::UNAUTHENTICATED ? 'unauthenticated' : 'forbidden';
        $this->audit->record($request, 'econizer_acl', $result, null, $this->auth->user()?->id);
        http_response_code($decision === AdminAccessGate::UNAUTHENTICATED ? 401 : 403);
        $this->theme->render_admin_access_state(http_response_code(), 'Brak dostępu', 'Ta sekcja wymaga uprawnienia Econizer.', 'index.php?route=/admin', 'Wróć do panelu');
    }

    private function csrf(Request $request, string $event): bool
    {
        if ($this->security->validateCsrfToken($request->postString('_token'))) { return true; }
        $this->audit->record($request, $event, 'invalid_csrf', null, $this->auth->user()?->id); http_response_code(403); $this->theme->render_public_error(403, 'Invalid token', 'Refresh the form and try again.', 'Back to Econizer', '/econizer'); return false;
    }

    private function allows(User $user, string $permission): bool { return in_array('*', $user->permissions, true) || in_array($permission, $user->permissions, true); }
    private function basisPoints(string $percent): ?int { if (preg_match('/^(?:[0-9]|1[0-9]|2[0-4])(?:[.,][0-9]{1,2})?$|^25(?:[.,]0{1,2})?$/', $percent) !== 1) { return null; } return (int) round((float) str_replace(',', '.', $percent) * 100); }
    private function percent(int $basisPoints): string { return rtrim(rtrim(number_format($basisPoints / 100, 2, '.', ''), '0'), '.'); }

    /** @return array<string,string> */
    private function deliveryOptions(): array
    {
        return [
            'discord_role' => 'Discord role',
            'virtual_item' => 'Virtual item',
            'code' => 'External code',
            'manual' => 'Manual',
        ];
    }

    private function deliveryLabel(string $type): string
    {
        return $this->deliveryOptions()[$type] ?? $type;
    }

    /** @param array<string,mixed> $guild */
    private function guildIconUrl(array $guild): ?string
    {
        $iconUrl = $guild['icon_url'] ?? null;
        return is_string($iconUrl) && $iconUrl !== '' ? $iconUrl : null;
    }

    private function serverTab(Request $request): string
    {
        $tab = $request->queryString('tab', $request->postString('tab', 'overview'));
        return in_array($tab, ['overview', 'shop', 'market'], true) ? $tab : 'overview';
    }

    /** @return list<array{label:string,href:string,active:bool}> */
    private function serverTabs(int $guildId, string $active): array
    {
        $tabs = ['overview' => 'Overview', 'shop' => 'Shop', 'market' => 'Market'];
        return array_map(
            static fn (string $key, string $label): array => [
                'label' => $label,
                'href' => 'index.php?route=/econizer/server&guild_id=' . $guildId . '&tab=' . $key,
                'active' => $key === $active,
            ],
            array_keys($tabs),
            array_values($tabs)
        );
    }

    /** @param array<string,mixed> $payload */
    private function jsonResponse(int $status, array $payload): void
    {
        http_response_code($status); if (!headers_sent()) { header('Content-Type: application/json; charset=UTF-8'); header('Cache-Control: no-store'); }
        try { echo json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE); } catch (JsonException) { echo '{"ok":false}'; }
    }
}
