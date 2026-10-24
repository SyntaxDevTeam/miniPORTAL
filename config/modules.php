<?php

declare(strict_types=1);

use SyntaxDevTeam\Cms\Modules\CoreAuth\RegistrationConfig;
use SyntaxDevTeam\Cms\Modules\CoreAuth\UserAdministrationRepository;
use SyntaxDevTeam\Cms\Modules\CoreAuthPolicyExtension\AuthPolicySettingsRepository;
use SyntaxDevTeam\Cms\Modules\CoreAuthPolicyExtension\CoreAuthPolicyExtensionModule;
use SyntaxDevTeam\Cms\Modules\CoreAuthPolicyExtension\CoreAuthPolicyProvider;
use SyntaxDevTeam\Cms\Modules\ItemShop\ItemShopModule;
use SyntaxDevTeam\Cms\Modules\ItemShop\DatabaseItemShopCatalogService;
use SyntaxDevTeam\Cms\Modules\ItemShop\ItemShopStoreDomainService;
use SyntaxDevTeam\Cms\Modules\ItemShop\DatabaseItemShopOrderService;
use SyntaxDevTeam\Cms\Modules\ItemShop\DatabaseItemShopFulfillmentService;
use SyntaxDevTeam\Cms\Modules\ItemShop\ItemShopCartService;
use SyntaxDevTeam\Cms\Modules\ItemShop\ItemShopApiController;
use SyntaxDevTeam\Cms\Modules\ItemShop\ItemShopPanelController;
use SyntaxDevTeam\Cms\Modules\ItemShop\ItemShopSecretVault;
use SyntaxDevTeam\Cms\Modules\ItemShop\ItemShopWebhookService;
use SyntaxDevTeam\Cms\Modules\ItemShop\ItemShopWebhookUrlPolicy;
use SyntaxDevTeam\Cms\Modules\ItemShop\ItemShopWalletCheckoutService;
use SyntaxDevTeam\Cms\Modules\ItemShop\ItemShopTestCheckoutService;
use SyntaxDevTeam\Cms\Modules\ItemShop\ItemShopAgentService;
use SyntaxDevTeam\Cms\Modules\ItemShop\CallableItemShopPaymentCheckout;
use SyntaxDevTeam\Cms\Modules\NodeManager\NodeAgentConfig;
use SyntaxDevTeam\Cms\Modules\NodeManager\UnixSocketNodeAgentClient;
use SyntaxDevTeam\Cms\Modules\Payments\PaymentsModule;
use SyntaxDevTeam\Cms\Modules\Payments\DatabasePaymentStore;
use SyntaxDevTeam\Cms\Modules\Payments\DatabasePaymentProviderAccountStore;
use SyntaxDevTeam\Cms\Modules\Payments\PaymentService;
use SyntaxDevTeam\Cms\Modules\Payments\PaymentProviderAccountService;
use SyntaxDevTeam\Cms\Modules\Payments\PaymentProviderAccountReaderInterface;
use SyntaxDevTeam\Cms\Modules\Payments\PaymentProviderAccountPanelController;
use SyntaxDevTeam\Cms\Modules\Payments\PaymentProviderConnectionRegistry;
use SyntaxDevTeam\Cms\Modules\Payments\PaymentSecretVault;
use SyntaxDevTeam\Cms\Modules\Payments\PaymentProviderFactoryRegistry;
use SyntaxDevTeam\Cms\Modules\Payments\PaymentWebhookController;
use SyntaxDevTeam\Cms\Modules\Payments\PaymentWebhookPayloadVault;
use SyntaxDevTeam\Cms\Modules\Payments\DatabasePaymentWebhookStore;
use SyntaxDevTeam\Cms\Modules\Payments\DatabasePaymentRefundStore;
use SyntaxDevTeam\Cms\Modules\Payments\PaymentRefundService;
use SyntaxDevTeam\Cms\Modules\Payments\HotPayHttpClient;
use SyntaxDevTeam\Cms\Modules\Payments\HotPayProviderFactory;
use SyntaxDevTeam\Cms\Modules\Payments\DatabasePaymentCheckoutStore;
use SyntaxDevTeam\Cms\Modules\Payments\PaymentCheckoutService;
use SyntaxDevTeam\Cms\Modules\Payments\PaymentCheckoutServiceInterface;
use SyntaxDevTeam\Cms\Modules\Payments\PaymentCreateRequest;
use SyntaxDevTeam\Cms\Modules\Payments\PaymentReference;
use SyntaxDevTeam\Cms\Modules\Payments\PaymentCheckoutRequest;
use SyntaxDevTeam\Cms\Modules\Payments\PaymentServiceInterface;
use SyntaxDevTeam\Cms\Modules\Support\SupportModule;
use SyntaxDevTeam\Cms\Modules\SystemMonitor\SystemMonitorDashboardModule;
use SyntaxDevTeam\Cms\Modules\SystemMonitor\SystemMonitorInfrastructureProbe;
use SyntaxDevTeam\Cms\Modules\SystemMonitor\SystemMonitorLogCleaner;
use SyntaxDevTeam\Cms\Modules\SystemMonitor\SystemMonitorModule;
use SyntaxDevTeam\Cms\Modules\Wallet\WalletModule;
use SyntaxDevTeam\Cms\Modules\Wallet\DatabaseWalletStore;
use SyntaxDevTeam\Cms\Modules\Wallet\WalletService;
use SyntaxDevTeam\Cms\Modules\Wallet\WalletApiController;
use SyntaxDevTeam\Cms\Modules\Wallet\WalletPanelController;
use SyntaxDevTeam\Cms\Modules\Wallet\WalletCsvExporter;
use SyntaxDevTeam\Cms\Modules\Wallet\WalletServiceInterface;
use SyntaxDevTeam\Cms\Database\CrudApp;

/**
 * Bazowa lista modułów jest utrzymywana osobno, aby dodatkowe moduły systemowe
 * można było dopinać bez naruszania istniejących fabryk.
 *
 * @var list<array<string, mixed>> $moduleDefinitionsBase
 */
$moduleDefinitionsBase = require __DIR__ . '/modules.base.php';

$moduleDefinitionsBase[] = [
    'directory' => 'CoreAuthPolicyExtension',
    'enabled' => static fn (array $services): bool => $services['database'] !== null,
    'factory' => static function (array $services): CoreAuthPolicyExtensionModule {
        $userAdministration = new UserAdministrationRepository($services['database']);
        $settings = new AuthPolicySettingsRepository($services['database']);
        $policy = new CoreAuthPolicyProvider($settings, $userAdministration);
        $coreRegistrationMode = strtolower(trim((string) (
            $services['auth_config']['registration_mode'] ?? RegistrationConfig::SELF_SERVICE
        )));
        if (!in_array($coreRegistrationMode, [RegistrationConfig::SELF_SERVICE, RegistrationConfig::APPROVAL], true)) {
            $coreRegistrationMode = RegistrationConfig::SELF_SERVICE;
        }

        return new CoreAuthPolicyExtensionModule(
            $services['theme'],
            $services['admin_menu'],
            $services['auth'],
            $services['access'],
            $services['security'],
            $services['audit'],
            $services['providers'],
            $userAdministration,
            $settings,
            $policy,
            $services['template_cache'],
            $coreRegistrationMode,
        );
    },
];

$moduleDefinitionsBase[] = [
    'directory' => 'SystemMonitor',
    'factory' => static function (array $services): SystemMonitorDashboardModule {
        $modulesConfig = is_array($services['config']['modules'] ?? null)
            ? $services['config']['modules']
            : [];
        $agent = class_exists(UnixSocketNodeAgentClient::class)
            ? new UnixSocketNodeAgentClient(NodeAgentConfig::fromModuleConfig($modulesConfig))
            : null;
        $applicationRoot = dirname(__DIR__);

        return new SystemMonitorDashboardModule(
            new SystemMonitorModule(
                $services['theme'],
                $services['admin_menu'],
                $services['auth'],
                $services['access'],
                $services['security'],
                $services['audit'],
            ),
            new SystemMonitorInfrastructureProbe(
                $services['database'],
                $applicationRoot
            ),
            new SystemMonitorLogCleaner($agent, $applicationRoot),
            $services['access'],
            $services['auth'],
            $services['audit'],
            $services['security'],
        );
    },
];

$moduleDefinitionsBase[] = [
    'directory' => 'Wallet',
    'enabled' => static fn (array $services): bool => ($services['database'] ?? null) instanceof CrudApp,
    'factory' => static function (array $services): WalletModule {
        $wallets = new WalletService(
            new DatabaseWalletStore($services['database']),
            $services['event_bus'],
            $services['platform_audit'],
        );

        return new WalletModule(
            $wallets,
            new WalletApiController($wallets, $services['api_tokens'], $services['rate_limiter']),
            new WalletPanelController(
                $wallets,
                $services['theme'],
                $services['admin_menu'],
                $services['security'],
                static function () use ($services): ?array {
                    $user = $services['auth']->user();
                    if ($user === null || !$user->isActive()) return null;
                    return [
                        'id' => $user->id,
                        'name' => $user->displayName,
                        'role' => $user->primaryRole(),
                        'initials' => $user->initials(),
                        'avatar_url' => $user->avatarUrl ?? '',
                        'permissions' => $user->permissions,
                    ];
                },
                static fn (): bool => $services['capabilities']->has('payments.checkout.create'),
                new WalletCsvExporter(),
            ),
        );
    },
];

$moduleDefinitionsBase[] = [
    'directory' => 'Payments',
    'enabled' => static fn (array $services): bool => ($services['database'] ?? null) instanceof CrudApp,
    'factory' => static function (array $services): PaymentsModule {
        $payments = new PaymentService(
            new DatabasePaymentStore($services['database']),
            $services['event_bus'],
            $services['platform_audit'],
        );
        $modulesConfig = is_array($services['config']['modules'] ?? null)
            ? $services['config']['modules']
            : [];
        $secretKey = trim((string) ($modulesConfig['payments_secret_key'] ?? ''));
        if ($secretKey === '') return new PaymentsModule($payments);

        $providerAccounts = new PaymentProviderAccountService(
            new DatabasePaymentProviderAccountStore($services['database']),
            new PaymentSecretVault($secretKey, (string) ($modulesConfig['payments_secret_key_id'] ?? 'primary')),
            $services['platform_audit'],
        );
        $panel = new PaymentProviderAccountPanelController(
            $providerAccounts,
            new PaymentProviderConnectionRegistry(),
            $services['theme'],
            $services['admin_menu'],
            $services['security'],
            static function () use ($services): ?array {
                $user = $services['auth']->user();
                if ($user === null || !$user->isActive()) return null;
                return [
                    'id' => $user->id,
                    'name' => $user->displayName,
                    'role' => $user->primaryRole(),
                    'initials' => $user->initials(),
                    'avatar_url' => $user->avatarUrl ?? '',
                    'permissions' => $user->permissions,
                ];
            },
        );
        $factories = PaymentProviderFactoryRegistry::installed();
        $webhooks = new PaymentWebhookController(
            $providerAccounts,
            $factories,
            new DatabasePaymentWebhookStore($services['database']),
            new PaymentWebhookPayloadVault($secretKey, (string) ($modulesConfig['payments_secret_key_id'] ?? 'primary')),
            $services['rate_limiter'],
            $services['platform_audit'],
        );
        $refunds = new PaymentRefundService(
            new DatabasePaymentRefundStore($services['database']),
            $providerAccounts,
            $factories,
            $services['platform_audit'],
            $services['event_bus'],
        );
        $checkout = new PaymentCheckoutService(
            new DatabasePaymentCheckoutStore($services['database']),
            $providerAccounts,
            $factories,
            $services['platform_audit'],
            $services['event_bus'],
            (string) ($services['config']['app']['public_url'] ?? ''),
        );
        return new PaymentsModule($payments, $panel, $webhooks, $refunds, $checkout, $providerAccounts);
    },
];

$moduleDefinitionsBase[] = [
    'directory' => 'ItemShop',
    'enabled' => static fn (array $services): bool => ($services['database'] ?? null) instanceof CrudApp,
    'factory' => static function (array $services): ItemShopModule {
        $modulesConfig=is_array($services['config']['modules']??null)?$services['config']['modules']:[];
        $secretKey=trim((string)($modulesConfig['itemshop_secret_key']??''));
        $vault = $secretKey!==''?new ItemShopSecretVault($secretKey):null;
        $catalog = new DatabaseItemShopCatalogService($services['database'], $services['platform_audit'], $services['event_bus'], $vault);
        $currentUser = static function () use ($services): ?array {
            $user = $services['auth']->user();
            if ($user === null || !$user->isActive()) return null;
            return ['id'=>$user->id,'name'=>$user->displayName,'role'=>$user->primaryRole(),'initials'=>$user->initials(),'avatar_url'=>$user->avatarUrl??'','permissions'=>$user->permissions];
        };
        $orders = new DatabaseItemShopOrderService($services['database'], $services['platform_audit'], $services['event_bus']);
        $fulfillment = new DatabaseItemShopFulfillmentService($services['database'], $services['platform_audit'], $vault);
        $cart = new ItemShopCartService($services['database']);
        $testCheckout=($modulesConfig['itemshop_test_checkout_enabled']??false)===true?new ItemShopTestCheckoutService($services['database'],$services['platform_audit'],$services['event_bus']):null;
        $webhooks=$vault===null?null:new ItemShopWebhookService($services['database'],$vault,new ItemShopWebhookUrlPolicy(),$services['platform_audit']);
        if($webhooks!==null)foreach(['itemshop.order.created','itemshop.order.status_changed']as$event)$services['event_bus']->subscribe($event,[$webhooks,'enqueue']);
        $walletProvider=$services['capabilities']->hasImplementation('wallet.debit','wallet')?$services['capabilities']->implementation('wallet.debit','wallet'):null;
        $walletCheckout=$walletProvider instanceof WalletServiceInterface?new ItemShopWalletCheckoutService($services['database'],$walletProvider,$services['platform_audit']):null;
        $paymentService=$services['capabilities']->hasImplementation('payments.create','payments')?$services['capabilities']->implementation('payments.create','payments'):null;
        $paymentCheckoutService=$services['capabilities']->hasImplementation('payments.checkout.create','payments')?$services['capabilities']->implementation('payments.checkout.create','payments'):null;
        $paymentAccounts=$services['capabilities']->hasImplementation('payments.provider_accounts.read','payments')?$services['capabilities']->implementation('payments.provider_accounts.read','payments'):null;
        $publicUrl=rtrim((string)($services['config']['app']['public_url']??''),'/');
        $storefrontBaseDomain=(string)($modulesConfig['itemshop_storefront_base_domain']??'syntaxcraft.pl');
        $platformDomain=strtolower((string)(parse_url($publicUrl,PHP_URL_HOST)?:''));
        $domains=new ItemShopStoreDomainService($services['database'],$storefrontBaseDomain,$platformDomain);
        $agent=class_exists(ItemShopAgentService::class)
            ? new ItemShopAgentService($services['database'],$services['platform_audit'],trim((string)($modulesConfig['itemshop_agent_platform_key_id']??'')))
            : null;
        $paymentFactories = $paymentAccounts instanceof PaymentProviderAccountService ? PaymentProviderFactoryRegistry::installed() : null;
        $paymentSettings = $paymentAccounts instanceof PaymentProviderAccountService
            ? new \SyntaxDevTeam\Cms\Modules\Payments\StorePaymentSettingsService($paymentAccounts, $paymentFactories, $publicUrl) : null;
        $paymentContext = new \SyntaxDevTeam\Cms\Modules\ItemShop\ItemShopPaymentStoreContext($services['database']);
        $storePaymentAccounts = static function(string $storeId) use ($paymentContext, $paymentAccounts, $paymentFactories): array {
            $store = $paymentContext->store($storeId);
            return array_values(array_filter($paymentAccounts->accountsForStore($store['tenant'], $storeId),
                static fn($account): bool => $paymentFactories?->has($account->providerId) && match($account->providerId) { 'hotpay', 'simpay', 'paybylink', 'paypal'=>$store['currency']==='PLN', 'stripe'=>in_array($store['currency'],['PLN','EUR','USD','GBP'],true), default=>false }));
        };
        $directPayment=$paymentService instanceof PaymentServiceInterface&&$paymentCheckoutService instanceof PaymentCheckoutServiceInterface&&$paymentAccounts instanceof PaymentProviderAccountReaderInterface&&str_starts_with(strtolower($publicUrl),'https://')?new CallableItemShopPaymentCheckout(
            static function($tenant,string $storeId) use($storePaymentAccounts): array {
                $result=[];
                foreach($storePaymentAccounts($storeId) as $account) $result[$account->id]='Płatność online · '.$account->name;
                return $result;
            },
            static function($tenant,string $userId,string $storeId,array $order,string $accountId) use($paymentService,$paymentCheckoutService,$publicUrl,$paymentContext,$storePaymentAccounts): string {
                if ($tenant->type !== 'account' || $tenant->id !== $userId) throw new RuntimeException('Brak dostępu do checkoutu.');
                $store = $paymentContext->store($storeId);
                $order = $paymentContext->order($storeId, $userId, (string)$order['id']);
                if (!in_array($accountId, array_column($storePaymentAccounts($storeId), 'id'), true)) throw new RuntimeException('Bramka nie jest dostępna w tym sklepie.');
                $sellerTenant = $store['tenant'];
                $returnUrl = $publicUrl.'/shop/'.rawurlencode($store['slug']);
                $payment=$paymentService->create(new PaymentCreateRequest($sellerTenant,(int)$order['total_amount_minor'],(string)$order['currency'],new PaymentReference('itemshop','order',(string)$order['id'],'Zamówienie ItemShop '.substr((string)$order['id'],0,8)),'itemshop-order-'.(string)$order['id'],'user',$userId,(string)$order['correlation_id']))->payment;
                $session=$paymentCheckoutService->checkout(new PaymentCheckoutRequest($sellerTenant,$payment->id,$accountId,$returnUrl.'?payment=return',$returnUrl.'?payment=cancel','itemshop-checkout-'.(string)$order['id'].'-'.$accountId,'user',$userId,['store_id'=>$storeId,'order_id'=>(string)$order['id']],(string)$order['correlation_id']));
                return $session->checkoutUrl;
            }
        ):null;
        $api = new ItemShopApiController($catalog,$orders,$fulfillment,$services['api_tokens'],$services['rate_limiter'],$webhooks);
        return new ItemShopModule($catalog, new ItemShopPanelController($catalog,$services['theme'],$services['admin_menu'],$services['security'],$currentUser,$orders,$fulfillment,$cart,$walletCheckout,$directPayment,$testCheckout,$domains,$publicUrl,$services['rate_limiter'],$agent,
            $paymentSettings === null ? null : $paymentSettings->settings(...),
            $paymentSettings === null ? null : static function($tenant, string $userId, string $storeId, \SyntaxDevTeam\Cms\Core\Request $request) use($paymentSettings): void {
                $paymentSettings->save($tenant,$userId,$storeId,$request->postString('operation'),$request->postString('account_id'),$request->postString('provider_id'),$request->postString('name'),[
                    'service_secret'=>$request->postString('service_secret'), 'notification_password'=>$request->postString('notification_password'),
                    'secret_key'=>$request->postString('secret_key'), 'webhook_secret'=>$request->postString('webhook_secret'),
                    'service_id'=>$request->postString('service_id'), 'api_key'=>$request->postString('api_key'), 'ipn_key'=>$request->postString('ipn_key'),
                    'shop_id'=>$request->postString('shop_id'), 'private_key'=>$request->postString('private_key'),
                    'paypal_email'=>$request->postString('paypal_email'), 'paypal_mode'=>$request->postString('paypal_mode'),
                ]);
            }), $orders, $fulfillment, $cart, $api, $domains);
    },
];

$moduleDefinitionsBase[] = [
    'directory' => 'Support',
    'factory' => static fn (array $services): SupportModule => new SupportModule(),
];

$moduleDefinitionsBase[] = [
    'directory' => 'PlayerProfile',
    'factory' => static fn (array $services): \SyntaxDevTeam\Cms\Modules\PlayerProfile\PlayerProfileModule =>
        (require dirname(__DIR__) . '/modules/PlayerProfile/factory.php')($services),
];

$moduleDefinitionsBase[] = [
    'directory' => 'ServerStaff',
    'enabled' => static fn(array $services): bool => ($services['database'] ?? null) instanceof CrudApp,
    'factory' => static fn(array $services): \SyntaxDevTeam\Cms\Modules\ServerStaff\ServerStaffModule =>
        (require dirname(__DIR__).'/modules/ServerStaff/factory.php')($services),
];

$moduleDefinitionsBase[] = [
    'directory' => 'MoonValeShop',
    'enabled' => static fn (array $services): bool => ($services['database'] ?? null) instanceof CrudApp,
    'factory' => static fn (array $services): \SyntaxDevTeam\Cms\Modules\MoonValeShop\ShopModule =>
        (require dirname(__DIR__) . '/modules/MoonValeShop/factory.php')($services),
];

return $moduleDefinitionsBase;
