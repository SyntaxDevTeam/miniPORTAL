<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Core;

trait TranslationAwareThemeTrait
{
    private TranslatorInterface $translator;

    private string $currentLocale = 'pl';

    /** @var array<string, string> */
    private array $languageLinks = [];

    /** @param array<string, mixed> $config */
    private function initializeTranslation(array $config): void
    {
        $translator = $config['translator'] ?? null;
        $this->translator = $translator instanceof TranslatorInterface
            ? $translator
            : new FileTranslator(dirname(__DIR__) . '/config/i18n', 'pl', 'pl', ['pl', 'en', 'de']);
        $this->currentLocale = $this->translator->locale();

        $links = is_array($config['language_links'] ?? null) ? $config['language_links'] : [];
        foreach ($this->translator->supportedLocales() as $locale) {
            $href = $links[$locale] ?? '/' . $locale;
            if (is_string($href) && preg_match('#^/[A-Za-z0-9/%._~-]*$#', $href) === 1) {
                $this->languageLinks[$locale] = $href;
            }
        }
    }

    /** @param array<string, scalar|null> $parameters */
    private function tr(string $key, array $parameters = [], string $fallback = ''): string
    {
        return $this->translator->translate($key, $parameters, $fallback);
    }

    private function localizedHomePath(): string
    {
        return '/' . $this->currentLocale;
    }

    private function renderLanguageSwitcher(): void
    {
        echo '<li class="nav-item dropdown"><button class="nav-link dropdown-toggle" type="button" ';
        echo 'data-bs-toggle="dropdown" aria-expanded="false" aria-label="';
        echo $this->escape($this->tr('public.language', fallback: 'Język')) . '">';
        echo $this->escape(strtoupper($this->currentLocale)) . '</button><ul class="dropdown-menu dropdown-menu-end">';
        foreach ($this->languageLinks as $locale => $href) {
            echo '<li><a class="dropdown-item' . ($locale === $this->currentLocale ? ' active' : '') . '" href="';
            echo $this->escape($href) . '" hreflang="' . $this->escape($locale) . '" lang="' . $this->escape($locale) . '"';
            echo $locale === $this->currentLocale ? ' aria-current="page"' : '';
            echo '>' . $this->escape($this->tr('language.' . $locale, fallback: strtoupper($locale))) . '</a></li>';
        }
        echo '</ul></li>';
    }

    private function renderAlternateLanguageLinks(string $publicUrl): void
    {
        if ($publicUrl === '') {
            return;
        }
        foreach ($this->languageLinks as $locale => $href) {
            echo '<link rel="alternate" hreflang="' . $this->escape($locale) . '" href="';
            echo $this->escape($publicUrl . ($href === '/' ? '/' : $href)) . '">';
        }
        $defaultHref = $this->languageLinks[$this->translator->defaultLocale()] ?? null;
        if ($defaultHref !== null) {
            echo '<link rel="alternate" hreflang="x-default" href="';
            echo $this->escape($publicUrl . ($defaultHref === '/' ? '/' : $defaultHref)) . '">';
        }
    }
}
