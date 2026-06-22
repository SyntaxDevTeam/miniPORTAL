<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Core;

use SyntaxDevTeam\Cms\Database\CrudApp;
use Throwable;

final class Bootstrap
{
    private ?CrudApp $database = null;

    private string $databaseStatus = 'Nie skonfigurowano';

    private TemplateCacheInterface $templateCache;

    private TranslatorInterface $translator;

    private LocaleContext $localeContext;

    private function __construct(
        private array $config,
        private readonly ThemeInterface $theme,
        private readonly Request $request,
        private readonly Security $security,
        private readonly array $availableThemes,
    ) {
    }

    public static function boot(array $config): self
    {
        $timezone = (string) ($config['app']['timezone'] ?? 'UTC');
        date_default_timezone_set($timezone);

        $rawRequest = Request::fromGlobals();
        $i18nConfig = is_array($config['i18n'] ?? null) ? $config['i18n'] : [];
        $supportedLocales = array_values(array_filter(
            is_array($i18nConfig['supported_locales'] ?? null) ? $i18nConfig['supported_locales'] : ['pl', 'en', 'de'],
            static fn (mixed $locale): bool => is_string($locale) && preg_match('/^[a-z]{2}$/', $locale) === 1
        ));
        $defaultLocale = (string) ($i18nConfig['default_locale'] ?? 'pl');
        if ($supportedLocales === [] || !in_array($defaultLocale, $supportedLocales, true)) {
            $supportedLocales = ['pl', 'en', 'de'];
            $defaultLocale = 'pl';
        }
        $localeContext = (new LocaleResolver($supportedLocales, $defaultLocale))->resolve($rawRequest);
        $request = $rawRequest->withPath($localeContext->routePath);
        $translator = new FileTranslator(
            (string) ($i18nConfig['catalog_directory'] ?? dirname(__DIR__) . '/config/i18n'),
            $localeContext->locale,
            $localeContext->defaultLocale,
            $localeContext->supportedLocales,
        );
        $security = new Security($config['session'] ?? []);
        $security->boot($request);

        $themeEngine = new ThemeEngine(dirname(__DIR__) . '/templates');
        $availableThemes = $themeEngine->available();
        $configuredTheme = (string) ($config['app']['theme'] ?? 'default');
        [$database, $databaseStatus] = self::connectDatabase($config);
        $config['app'] = array_replace(
            $config['app'] ?? [],
            self::themeSettings($database)
        );
        $localeNames = ['pl' => 'pl_PL', 'en' => 'en_GB', 'de' => 'de_DE'];
        $config['app']['public_path'] = $localeContext->publicPath;
        $config['app']['public_locale'] = $localeNames[$localeContext->locale] ?? 'pl_PL';
        $config['app']['current_locale'] = $localeContext->locale;
        $config['app']['language_links'] = $localeContext->languageLinks();
        $config['app']['translator'] = $translator;
        $themeName = (string) ($config['app']['theme'] ?? 'default');
        if (!isset($availableThemes[$themeName])) {
            $themeName = isset($availableThemes[$configuredTheme]) ? $configuredTheme : 'default';
            $config['app']['theme'] = $themeName;
        }
        $application = new self(
            $config,
            $themeEngine->load($themeName, $config['app'] ?? []),
            $request,
            $security,
            $availableThemes,
        );
        $application->database = $database;
        $application->databaseStatus = $databaseStatus;
        $application->translator = $translator;
        $application->localeContext = $localeContext;
        $cacheConfig = is_array($config['cache'] ?? null) ? $config['cache'] : [];
        $application->templateCache = new FileTemplateCache(
            dirname(__DIR__) . '/cache/templates',
            ($cacheConfig['enabled'] ?? true) === true,
            (int) ($cacheConfig['ttl'] ?? 300),
        );

        return $application;
    }

    public function theme(): ThemeInterface
    {
        return $this->theme;
    }

    public function database(): ?CrudApp
    {
        return $this->database;
    }

    public function request(): Request
    {
        return $this->request;
    }

    public function security(): Security
    {
        return $this->security;
    }

    public function templateCache(): TemplateCacheInterface
    {
        return $this->templateCache;
    }

    public function translator(): TranslatorInterface
    {
        return $this->translator;
    }

    public function locale(): LocaleContext
    {
        return $this->localeContext;
    }

    public function config(): array
    {
        return $this->config;
    }

    /**
     * @return array<string, string>
     */
    public function availableThemes(): array
    {
        return $this->availableThemes;
    }

    public function diagnostics(): array
    {
        return [
            ['Konfiguracja', 'config/config.php', 'Gotowa'],
            ['Warstwa CRUD', CrudApp::class, $this->databaseStatus],
            ['Autoloader', Autoloader::class, 'Gotowy'],
            ['Silnik startowy', self::class, 'Gotowy'],
            ['ThemeEngine', ThemeEngine::class, 'Gotowy'],
            ['Cache szablonów', FileTemplateCache::class, $this->templateCache->stats()['enabled'] ? 'Gotowy' : 'Wyłączony'],
            ['Security', Security::class, 'Gotowy'],
            ['Request', Request::class, 'Gotowy'],
            ['i18n', FileTranslator::class, strtoupper($this->translator->locale())],
            ['Router', Router::class, 'Gotowy'],
            ['ModuleInterface', ModuleInterface::class, 'Gotowy'],
            ['Menu panelu', AdminMenuRegistry::class, 'Gotowy'],
            ['Szablon', $this->theme::class, 'Gotowy'],
            ['Stylebook', 'templates/default/stylebook.html', 'Gotowy'],
        ];
    }

    /**
     * @return array{0: ?CrudApp, 1: string}
     */
    private static function connectDatabase(array $config): array
    {
        $databaseConfig = $config['database'] ?? [];

        if (($databaseConfig['enabled'] ?? false) !== true) {
            return [null, 'Nie skonfigurowano'];
        }

        unset($databaseConfig['enabled']);

        try {
            return [CrudApp::getInstance($databaseConfig), 'Połączono przez CrudApp'];
        } catch (Throwable $exception) {
            $status = ($config['app']['debug'] ?? false)
                ? 'Błąd: ' . $exception->getMessage()
                : 'Błąd połączenia';

            return [null, $status];
        }
    }

    /**
     * @return array<string, string>
     */
    private static function themeSettings(?CrudApp $database): array
    {
        if ($database === null) {
            return [];
        }

        try {
            $rows = $database->read(
                'system_settings',
                ['setting_key', 'setting_value'],
                ['setting_key' => [
                    'theme',
                    'public_url',
                    'public_name',
                    'public_default_title',
                    'public_eyebrow',
                    'public_meta_description',
                    'public_meta_keywords',
                    'public_meta_author',
                    'public_meta_robots',
                    'public_locale',
                    'public_social_image_url',
                    'public_social_image_alt',
                    'public_twitter_site',
                    'public_theme_color',
                    'public_google_site_verification',
                    'public_bing_site_verification',
                    'public_footer_text',
                    'public_favicon_path',
                    'public_favicon_version',
                ]]
            ) ?? [];
        } catch (Throwable) {
            return [];
        }

        $settings = [];
        foreach ($rows as $row) {
            $key = (string) ($row['setting_key'] ?? '');
            if (in_array($key, [
                'theme',
                'public_url',
                'public_name',
                'public_default_title',
                'public_eyebrow',
                'public_meta_description',
                'public_meta_keywords',
                'public_meta_author',
                'public_meta_robots',
                'public_locale',
                'public_social_image_url',
                'public_social_image_alt',
                'public_twitter_site',
                'public_theme_color',
                'public_google_site_verification',
                'public_bing_site_verification',
                'public_footer_text',
                'public_favicon_path',
                'public_favicon_version',
            ], true)) {
                $settings[$key] = (string) ($row['setting_value'] ?? '');
            }
        }

        return $settings;
    }
}
