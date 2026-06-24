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
    public function version(): string { return '1.3.1'; }
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
        $router->get('/econizer/shop', fn (Request $request) => $this->renderShop($request));
        $router->post('/econizer/shop/buy', fn (Request $request) => $this->buyItem($request));
        $router->get('/econizer/market', fn (Request $request) => $this->renderMarket($request));
        $router->post('/econizer/market/trade', fn (Request $request) => $this->trade($request));
        $router->post('/api/econizer/guilds', fn (Request $request) => $this->syncGuild($request));
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
        $this->startPublic('Econizer', 'Twoje centrum ekonomii Discord: saldo, poziom, historia, sklep i giełda.');
        if ($message !== '') { $this->theme->render_alert($message, $variant); }
        if ($membership === null) {
            $this->theme->render_alert('Twoje konto nie jest jeszcze powiązane z żadnym serwerem Econizer.', 'warning');
            $this->theme->start_card('Serwery Discord', 'Wybierz serwer, którym zarządzasz');
            $this->theme->render_link_list([
                ['label' => 'Pokaż moje serwery Discord', 'href' => 'index.php?route=/econizer/servers', 'meta' => 'Owner, Administrator albo Manage Guild'],
            ]);
            $this->theme->end_card();
            $this->endPublic(); return;
        }
        $guildId = (int) $membership['guild_id'];
        $wallet = $this->econizer->wallet($guildId, $user->id);
        $nextLevel = max(100, (int) $wallet['level'] * 1000);
        $this->theme->start_grid();
        $this->theme->start_column('4'); $this->theme->start_card('Saldo', $membership['currency_name']); $this->theme->render_text(number_format((int) $wallet['balance'], 0, ',', ' ')); $this->theme->end_card(); $this->theme->end_column();
        $this->theme->start_column('4'); $this->theme->start_card('Poziom', 'Level'); $this->theme->render_text((string) $wallet['level']); $this->theme->end_card(); $this->theme->end_column();
        $this->theme->start_column('4'); $this->theme->start_card('Doświadczenie', 'Postęp'); $this->theme->render_text((int) $wallet['experience'] . ' / ' . $nextLevel . ' EXP'); $this->theme->end_card(); $this->theme->end_column();
        $this->theme->end_grid();
        $links = [
            ['label' => 'Moje serwery Discord', 'href' => 'index.php?route=/econizer/servers', 'meta' => 'Instalacja bota i ustawienia zarządzanych serwerów'],
            ['label' => 'Sklep serwerowy', 'href' => 'index.php?route=/econizer/shop&guild_id=' . $guildId, 'meta' => 'Kup rangi, kody i nagrody'],
            ['label' => 'Giełda', 'href' => 'index.php?route=/econizer/market&guild_id=' . $guildId, 'meta' => 'Aktywa i portfel inwestycyjny'],
        ];
        if (in_array($membership['access_role'], ['guild_owner', 'guild_admin'], true)) {
            $links[] = ['label' => 'Zarządzaj serwerem', 'href' => 'index.php?route=/econizer/server&guild_id=' . $guildId, 'meta' => 'Waluta, podatki, VIP i katalog'];
        }
        $this->theme->start_card('Szybkie akcje', (string) $membership['name']); $this->theme->render_link_list($links); $this->theme->end_card();
        $rows = array_map(static fn (array $tx): array => [$tx['created_at'], $tx['transaction_type'], $tx['description'], (int) $tx['amount'], (int) $tx['balance_after']], $this->econizer->transactions($guildId, $user->id));
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
        $this->theme->render_form('index.php?route=/econizer/server/settings', [
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
        $this->theme->start_card('Użytkownicy Discord', 'Bez ręcznego wyboru kont miniPORTAL');
        $this->theme->render_text('Gracze są wiązani z serwerem przez ich Discord User ID. Jeśli użytkownik zalogował się do miniPORTAL przez Discord, pierwsze zdarzenie bota dla tego Discord ID automatycznie przypisze go do właściwego serwera jako gracza. Właściciel lub administrator serwera łączy swoje konto w widoku Moje serwery Discord.');
        $this->theme->end_card();
        $this->theme->end_column();
        $this->theme->end_grid();

        $this->theme->start_grid();
        $limit = (int) $this->econizer->platformSettings()['freemium_shop_limit'];
        $this->theme->start_column('6'); $this->theme->start_card('Dodaj przedmiot', $membership['plan'] === 'freemium' ? 'Limit ' . $limit . ' aktywnych pozycji' : 'Katalog bez limitu');
        $this->theme->render_form('index.php?route=/econizer/server/shop', [
            ['name' => 'guild_id', 'label' => 'Serwer', 'type' => 'hidden', 'value' => (string) $guildId],
            ['name' => 'name', 'label' => 'Nazwa'], ['name' => 'description', 'label' => 'Opis', 'type' => 'textarea'],
            ['name' => 'price', 'label' => 'Cena', 'type' => 'number'], ['name' => 'stock', 'label' => 'Stan (puste = bez limitu)', 'type' => 'number'],
            ['name' => 'delivery_type', 'label' => 'Realizacja', 'type' => 'select', 'options' => ['discord_role' => 'Ranga Discord', 'code' => 'Kod zewnętrzny', 'manual' => 'Ręczna']],
            ['name' => 'delivery_reference', 'label' => 'ID roli / bezpieczna referencja', 'help' => 'Nie wpisuj sekretnego kodu; przechowuj identyfikator w systemie bota.'],
        ], 'Dodaj do sklepu', $this->security->csrfToken()); $this->theme->end_card(); $this->theme->end_column();
        $this->theme->start_column('6'); $this->theme->start_card('Aktywa i notowania', 'Początkowa cena lub kolejne punkty historii');
        $this->theme->render_form('index.php?route=/econizer/server/asset', [
            ['name' => 'guild_id', 'label' => 'Serwer', 'type' => 'hidden', 'value' => (string) $guildId],
            ['name' => 'symbol', 'label' => 'Symbol', 'help' => '2-12 wielkich liter lub cyfr.'], ['name' => 'name', 'label' => 'Nazwa aktywa'],
            ['name' => 'price', 'label' => 'Cena początkowa', 'type' => 'number'],
        ], 'Dodaj aktywo', $this->security->csrfToken());
        $assetOptions = []; foreach ($this->econizer->market($guildId, $user->id) as $asset) { $assetOptions[(string) $asset['id']] = $asset['symbol'] . ' - ' . $asset['name']; }
        if ($assetOptions !== []) {
            $this->theme->render_form('index.php?route=/econizer/server/quote', [
                ['name' => 'guild_id', 'label' => 'Serwer', 'type' => 'hidden', 'value' => (string) $guildId],
                ['name' => 'asset_id', 'label' => 'Aktywo', 'type' => 'select', 'options' => $assetOptions],
                ['name' => 'price', 'label' => 'Nowa cena', 'type' => 'number'],
            ], 'Dodaj notowanie', $this->security->csrfToken());
        }
        $this->theme->end_card(); $this->theme->end_column();
        $this->theme->end_grid();
        $this->theme->start_card('Katalog sklepu', $this->econizer->activeShopItemCount($guildId) . ' aktywnych');
        $this->theme->render_table(['Nazwa', 'Cena', 'Stan', 'Realizacja'], array_map(static fn (array $item): array => [$item['name'], $item['price'], $item['stock'] ?? 'bez limitu', $item['delivery_type']], $this->econizer->shopItems($guildId, false)));
        $this->theme->end_card();
        $this->endPublic();
    }

    private function renderShop(Request $request, string $message = '', string $variant = 'info'): void
    {
        $context = $this->playerContext($request); if ($context === null) { return; }
        [$user, $membership] = $context; $guildId = (int) $membership['guild_id'];
        $this->startPublic('Sklep ' . $membership['name'], 'Nagrody serwerowe rozliczane w walucie ' . $membership['currency_name'] . '.');
        if ($message !== '') { $this->theme->render_alert($message, $variant); }
        if (!$this->econizer->featureEnabled('shop') || (int) $membership['shop_enabled'] !== 1) { $this->theme->render_alert('Sklep jest obecnie wyłączony.', 'warning'); $this->endPublic(); return; }
        $wallet = $this->econizer->wallet($guildId, $user->id); $this->theme->render_alert('Dostępne saldo: ' . $wallet['balance'] . ' ' . $membership['currency_name'], 'info');
        foreach ($this->econizer->shopItems($guildId) as $item) {
            $this->theme->start_card((string) $item['name'], $item['price'] . ' ' . $membership['currency_name']);
            $this->theme->render_text((string) $item['description']);
            $this->theme->render_form('index.php?route=/econizer/shop/buy', [
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
        if (!$this->econizer->featureEnabled('market') || (int) $membership['market_enabled'] !== 1) { $this->theme->render_alert('Giełda jest obecnie wyłączona.', 'warning'); $this->endPublic(); return; }
        $wallet = $this->econizer->wallet($guildId, $user->id); $this->theme->render_alert('Gotówka: ' . $wallet['balance'] . ' ' . $membership['currency_name'], 'info');
        foreach ($this->econizer->market($guildId, $user->id) as $asset) {
            $this->theme->start_card($asset['symbol'] . ' - ' . $asset['name'], 'Cena ' . $asset['current_price']);
            $this->theme->render_admin_fact_grid([
                ['label' => 'Posiadane jednostki', 'value' => (string) $asset['quantity']],
                ['label' => 'Średnia cena', 'value' => (string) $asset['average_price']],
                ['label' => 'Wartość portfela', 'value' => (string) ((int) $asset['quantity'] * (int) $asset['current_price'])],
            ]);
            $quoteData = array_reverse($this->econizer->quotes((int) $asset['id']));
            $this->theme->render_line_chart(array_map(static fn (array $quote): array => ['label' => (string) $quote['quoted_at'], 'value' => (int) $quote['price']], $quoteData), 'Historia ceny ' . $asset['symbol']);
            $quotes = array_map(static fn (array $quote): array => [$quote['quoted_at'], $quote['price']], array_reverse($quoteData));
            $this->theme->render_table(['Notowanie', 'Cena'], $quotes);
            $this->theme->render_form('index.php?route=/econizer/market/trade', [
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
            http_response_code(429); $this->renderManagedServers($request, 'Zbyt wiele prób połączenia z Discord. Spróbuj ponownie później.', 'warning'); return;
        }
        try { $url = $this->discord->discoveryUrl($user->id); }
        catch (Throwable) { $this->audit->record($request, 'econizer_discord_oauth_start', 'not_configured', 'discord', $user->id); $this->renderManagedServers($request, 'Dedykowana aplikacja Discord Econizer nie jest jeszcze kompletna.', 'danger'); return; }
        $this->audit->record($request, 'econizer_discord_oauth_start', 'success', 'discord', $user->id);
        header('Location: ' . $url, true, 302);
    }

    private function completeDiscordDiscovery(Request $request): void
    {
        $user = $this->requireUser();
        if ($user === null) { return; }
        if (!$this->oauthLimiter->allowCallback('econizer_discord')) {
            $this->audit->record($request, 'econizer_discord_oauth_callback', 'rate_limited', 'discord', $user->id);
            http_response_code(429); $this->renderManagedServers($request, 'Przekroczono limit odpowiedzi OAuth Discord.', 'warning'); return;
        }
        if ($request->queryString('error') !== '') {
            $this->audit->record($request, 'econizer_discord_oauth_callback', 'provider_denied', 'discord', $user->id);
            $this->renderManagedServers($request, 'Pobieranie listy serwerów zostało anulowane.', 'warning'); return;
        }
        try { $guilds = $this->discord->complete($request->queryString('state'), $request->queryString('code'), $user->id); }
        catch (Throwable $exception) {
            error_log('Econizer Discord OAuth failed: ' . $exception::class);
            $this->audit->record($request, 'econizer_discord_oauth_callback', 'provider_error', 'discord', $user->id);
            http_response_code(502); $this->renderManagedServers($request, 'Nie udało się bezpiecznie pobrać listy serwerów Discord.', 'danger'); return;
        }
        $this->audit->record($request, 'econizer_discord_oauth_callback', 'success', 'discord', $user->id);
        $this->renderManagedServers($request, 'Pobrano ' . count($guilds) . ' serwerów, którymi możesz zarządzać.', 'success');
    }

    private function renderManagedServers(Request $request, string $message = '', string $variant = 'info'): void
    {
        $user = $this->requireUser();
        if ($user === null) { return; }
        $this->startPublic('Moje serwery Discord', 'Wybierz serwer, na którym jesteś właścicielem albo administratorem.');
        if ($message !== '') { $this->theme->render_alert($message, $variant); }
        if (!$this->config->discordApplicationConfigured()) {
            $this->theme->render_alert('Dedykowana aplikacja Discord Econizer nie jest jeszcze skonfigurowana.', 'warning');
            $this->endPublic(); return;
        }
        $guilds = $this->discord->guilds($user->id);
        if ($guilds === []) {
            $this->theme->start_card('Połącz Discord', 'Lista jest przechowywana tylko tymczasowo w sesji');
            $this->theme->render_text('Pobierz serwery, na których masz Owner, Administrator albo Manage Guild. Token użytkownika Discord nie jest zapisywany.');
            $this->theme->render_link_list([
                ['label' => 'Pobierz moje serwery Discord', 'href' => 'index.php?route=/econizer/discord/connect', 'meta' => 'Authorization Code + PKCE'],
            ]);
            $this->theme->end_card();
            $this->endPublic(); return;
        }
        foreach ($guilds as $guild) {
            $registered = $this->econizer->guildByDiscordId($guild['id']);
            $this->theme->start_card((string) $guild['name'], 'Zweryfikowany dostęp: ' . $guild['access']);
            $this->theme->render_admin_fact_grid([
                ['label' => 'Discord Guild ID', 'value' => $guild['id']],
                ['label' => 'Econizer', 'value' => $registered !== null ? 'Bot zgłosił serwer' : 'Bot niezgłoszony', 'variant' => $registered !== null ? 'success' : 'warning'],
            ]);
            $links = [
                ['label' => 'Szczegóły serwera', 'href' => 'index.php?route=/econizer/discord/server&guild_id=' . rawurlencode($guild['id']), 'meta' => $registered !== null ? 'Połącz konto i przejdź do ustawień' : 'Zaproś bota Econizer'],
            ];
            $this->theme->render_link_list($links);
            $this->theme->end_card();
        }
        $this->theme->start_card('Odświeżenie listy', 'Discord OAuth');
        $this->theme->render_link_list([
            ['label' => 'Odśwież listę z Discord', 'href' => 'index.php?route=/econizer/discord/connect', 'meta' => 'Ponownie pobierz zarządzane serwery'],
        ]);
        $this->theme->end_card();
        $this->endPublic();
    }

    private function renderDiscordGuild(Request $request, string $message = '', string $variant = 'info'): void
    {
        $user = $this->requireUser();
        if ($user === null) { return; }
        $guildId = $request->queryString('guild_id', $request->postString('guild_id'));
        $guild = $this->discord->guild($user->id, $guildId);
        if ($guild === null) { $this->renderManagedServers($request, 'Serwer nie znajduje się w aktualnej, zweryfikowanej liście Discord. Odśwież listę.', 'warning'); return; }
        $registered = $this->econizer->guildByDiscordId($guildId);
        try { $botPresent = $this->discord->botPresent($guildId); } catch (Throwable) { $botPresent = false; }
        $this->startPublic('Serwer Discord: ' . (string) $guild['name'], 'Instalacja bota i ustawienia Econizer dla wybranego serwera.');
        if ($message !== '') { $this->theme->render_alert($message, $variant); }
        $this->theme->start_card((string) $guild['name'], 'Zweryfikowany dostęp: ' . $guild['access']);
        $this->theme->render_admin_fact_grid([
            ['label' => 'Discord Guild ID', 'value' => $guildId],
            ['label' => 'Tenant Econizer', 'value' => $registered !== null ? 'Zgłoszony przez bota' : 'Brak zgłoszenia bota', 'variant' => $registered !== null ? 'success' : 'warning'],
            ['label' => 'Bot na serwerze', 'value' => $botPresent ? 'Połączony' : 'Niepotwierdzony', 'variant' => $botPresent ? 'success' : 'warning'],
            ['label' => 'Plan', 'value' => $registered !== null ? strtoupper((string) $registered['plan']) : 'FREEMIUM po zgłoszeniu'],
        ]);
        if ($registered === null) {
            $this->theme->render_alert('Po zaproszeniu bot wyśle do miniPORTAL informację o serwerze. Dopiero wtedy pojawią się ustawienia Econizer dla tej gildii.', 'info');
            $this->theme->render_link_list([
                ['label' => 'Zaproś Econizer na serwer', 'href' => $this->discord->installationUrl($guildId), 'meta' => 'Discord: bot applications.commands'],
                ['label' => 'Odśwież listę z Discord', 'href' => 'index.php?route=/econizer/discord/connect', 'meta' => 'Sprawdź ponownie po dodaniu bota'],
            ]);
        } else {
            $membership = $this->econizer->membership((int) $registered['id'], $user->id);
            if ($membership !== null && in_array($membership['access_role'], ['guild_owner', 'guild_admin'], true)) {
                $this->theme->render_link_list([
                    ['label' => 'Ustawienia Econizer dla serwera', 'href' => 'index.php?route=/econizer/server&guild_id=' . (int) $registered['id'], 'meta' => 'Waluta, sklep, giełda i członkowie'],
                ]);
            } else {
                $this->theme->render_form('index.php?route=/econizer/discord/link', [
                ['name' => 'guild_id', 'label' => 'Serwer Discord', 'type' => 'hidden', 'value' => $guildId],
                ], 'Połącz konto i otwórz ustawienia', $this->security->csrfToken());
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
        if ($guild === null || $discordUserId === null) { http_response_code(403); $this->renderDiscordGuild($request, 'Nie można połączyć serwera bez świeżej weryfikacji Discord.', 'danger'); return; }
        $registered = $this->econizer->guildByDiscordId($guildId);
        if ($registered === null) { $this->renderDiscordGuild($request, 'Najpierw zaproś bota. Tenant powstaje dopiero po zgłoszeniu serwera przez Econizer.', 'warning'); return; }
        try {
            $this->econizer->addMembership((int) $registered['id'], $user->id, $discordUserId, $guild['owner'] ? 'guild_owner' : 'guild_admin');
        } catch (Throwable) { $this->renderDiscordGuild($request, 'Nie udało się połączyć konta z serwerem Econizer.', 'danger'); return; }
        $this->audit->record($request, 'econizer_discord_link', 'success', 'guild:' . $registered['id'], $user->id);
        $this->renderServer(Request::fromArrays(['guild_id' => (string) $registered['id']], [], ['REQUEST_METHOD' => 'GET']), 'Połączono konto z serwerem Discord.', 'success');
    }

    private function saveServer(Request $request): void
    {
        $context = $this->serverContext($request); if ($context === null || !$this->csrf($request, 'econizer_server_update')) { return; }
        [, $membership] = $context; $daily = $request->postInt('daily_amount', -1) ?? -1; $min = $request->postInt('work_min', -1) ?? -1; $max = $request->postInt('work_max', -1) ?? -1;
        $vip = $request->postInt('vip_daily_amount', -1) ?? -1; $currency = $request->postString('currency_name'); $tax = $this->basisPoints($request->postString('transfer_tax_percent'));
        $roleId = $request->postString('vip_role_id');
        if ($currency === '' || strlen($currency) > 40 || min($daily, $min, $max, $vip) < 0 || $min > $max || $max > 1000000000 || $tax === null || ($roleId !== '' && preg_match('/^[0-9]{6,32}$/', $roleId) !== 1)) {
            $this->renderServer($request, 'Nieprawidłowe ustawienia ekonomii.', 'danger'); return;
        }
        $this->econizer->updateGuild((int) $membership['guild_id'], [
            'currency_name' => $currency, 'daily_amount' => $daily, 'work_min' => $min, 'work_max' => $max,
            'transfer_tax_bps' => $tax, 'vip_role_id' => $roleId === '' ? null : $roleId, 'vip_daily_amount' => $vip,
            'shop_enabled' => $request->postBool('shop_enabled') ? 1 : 0, 'market_enabled' => $request->postBool('market_enabled') ? 1 : 0,
        ]);
        $this->audit->record($request, 'econizer_server_update', 'success', 'guild:' . $membership['guild_id'], $this->auth->user()?->id);
        $this->renderServer($request, 'Ustawienia serwera zostały zapisane.', 'success');
    }

    private function addShopItem(Request $request): void
    {
        $context = $this->serverContext($request); if ($context === null || !$this->csrf($request, 'econizer_shop_create')) { return; }
        [, $membership] = $context; $guildId = (int) $membership['guild_id'];
        $freemiumLimit = (int) $this->econizer->platformSettings()['freemium_shop_limit'];
        if ($membership['plan'] === 'freemium' && $this->econizer->activeShopItemCount($guildId) >= $freemiumLimit) { $this->renderServer($request, 'Plan Freemium pozwala na maksymalnie ' . $freemiumLimit . ' aktywnych pozycji.', 'warning'); return; }
        $name = $request->postString('name'); $description = $request->postString('description'); $price = $request->postInt('price', 0) ?? 0; $stockRaw = $request->postString('stock');
        $stock = $stockRaw === '' ? null : $request->postInt('stock'); $type = $request->postString('delivery_type'); $reference = $request->postString('delivery_reference');
        if ($name === '' || strlen($name) > 120 || strlen($description) > 1000 || $price < 1 || ($stock !== null && $stock < 0) || !in_array($type, ['discord_role', 'code', 'manual'], true) || strlen($reference) > 120) { $this->renderServer($request, 'Nieprawidłowe dane przedmiotu.', 'danger'); return; }
        $id = $this->econizer->addShopItem($guildId, $name, $description, $price, $stock, $type, $reference === '' ? null : $reference);
        $this->audit->record($request, 'econizer_shop_item_create', 'success', 'item:' . $id, $this->auth->user()?->id);
        $this->renderServer($request, 'Przedmiot dodano do sklepu.', 'success');
    }

    private function addAsset(Request $request): void
    {
        $context = $this->serverContext($request); if ($context === null || !$this->csrf($request, 'econizer_asset_create')) { return; }
        [, $membership] = $context; $symbol = strtoupper($request->postString('symbol')); $name = $request->postString('name'); $price = $request->postInt('price', 0) ?? 0;
        if (preg_match('/^[A-Z0-9]{2,12}$/', $symbol) !== 1 || $name === '' || strlen($name) > 80 || $price < 1) { $this->renderServer($request, 'Nieprawidłowe dane aktywa.', 'danger'); return; }
        try { $id = $this->econizer->addAsset((int) $membership['guild_id'], $symbol, $name, $price); }
        catch (\Throwable) { $this->renderServer($request, 'Symbol aktywa musi być unikalny na serwerze.', 'danger'); return; }
        $this->audit->record($request, 'econizer_asset_create', 'success', 'asset:' . $id, $this->auth->user()?->id);
        $this->renderServer($request, 'Aktywo zostało dodane.', 'success');
    }

    private function updateQuote(Request $request): void
    {
        $context = $this->serverContext($request); if ($context === null || !$this->csrf($request, 'econizer_quote_create')) { return; }
        [, $membership] = $context; $price = $request->postInt('price', 0) ?? 0; $assetId = $request->postInt('asset_id', 0) ?? 0;
        if ($price < 1 || $price > 1000000000000 || !$this->econizer->updateAssetPrice((int) $membership['guild_id'], $assetId, $price)) { $this->renderServer($request, 'Nie udało się dodać notowania.', 'danger'); return; }
        $this->audit->record($request, 'econizer_quote_create', 'success', 'asset:' . $assetId, $this->auth->user()?->id);
        $this->renderServer($request, 'Nowe notowanie zostało zapisane.', 'success');
    }

    private function buyItem(Request $request): void
    {
        $context = $this->playerContext($request); if ($context === null || !$this->csrf($request, 'econizer_shop_buy')) { return; }
        [$user, $membership] = $context;
        try { $order = $this->econizer->purchaseItem((int) $membership['guild_id'], $user->id, $request->postInt('item_id', 0) ?? 0); }
        catch (RuntimeException $exception) { $this->renderShop($request, $exception->getMessage(), 'danger'); return; }
        $this->audit->record($request, 'econizer_shop_purchase', 'success', 'order:' . $order, $user->id);
        $this->renderShop($request, 'Zakup przyjęty. Numer zamówienia: ' . $order . '.', 'success');
    }

    private function trade(Request $request): void
    {
        $context = $this->playerContext($request); if ($context === null || !$this->csrf($request, 'econizer_market_trade')) { return; }
        [$user, $membership] = $context; $side = $request->postString('side');
        if (!in_array($side, ['buy', 'sell'], true)) { $this->renderMarket($request, 'Nieprawidłowy kierunek zlecenia.', 'danger'); return; }
        try { $this->econizer->trade((int) $membership['guild_id'], $user->id, $request->postInt('asset_id', 0) ?? 0, $request->postInt('quantity', 0) ?? 0, $side === 'buy'); }
        catch (RuntimeException $exception) { $this->renderMarket($request, $exception->getMessage(), 'danger'); return; }
        $this->audit->record($request, 'econizer_market_trade', 'success', 'guild:' . $membership['guild_id'], $user->id);
        $this->renderMarket($request, 'Zlecenie zostało rozliczone.', 'success');
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
        if (!in_array($context[1]['access_role'], ['guild_owner', 'guild_admin'], true)) { http_response_code(403); $this->theme->render_public_error(403, 'Brak dostępu', 'Zarządzanie wymaga roli właściciela lub administratora serwera.', 'Wróć do Econizer', '/econizer'); return null; }
        return $context;
    }

    /** @return array{User,array<string,mixed>}|null */
    private function playerContext(Request $request): ?array
    {
        $user = $this->requireUser(); if ($user === null) { return null; }
        $guildId = $request->queryInt('guild_id') ?? $request->postInt('guild_id');
        $memberships = $this->econizer->memberships($user->id); $selected = $this->selectedMembership($memberships, $guildId);
        if ($selected === null) { http_response_code(403); $this->theme->render_public_error(403, 'Brak dostępu', 'Nie masz aktywnego powiązania z tym serwerem Econizer.', 'Wróć do Econizer', '/econizer'); return null; }
        $full = $this->econizer->membership((int) $selected['guild_id'], $user->id);
        return $full === null ? null : [$user, $full];
    }

    private function requireUser(): ?User
    {
        $user = $this->auth->user(); if ($user !== null) { return $user; }
        http_response_code(401); $this->theme->render_public_error(401, 'Zaloguj się', 'Econizer łączy portfel z Twoim lokalnym kontem.', 'Przejdź do logowania', '/admin/login'); return null;
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
        $this->audit->record($request, $event, 'invalid_csrf', null, $this->auth->user()?->id); http_response_code(403); $this->theme->render_public_error(403, 'Nieprawidłowy token', 'Odśwież formularz i spróbuj ponownie.', 'Wróć do Econizer', '/econizer'); return false;
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
