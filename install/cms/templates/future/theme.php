<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Templates\FutureTheme;

use SyntaxDevTeam\Cms\Core\ContentRenderer;
use SyntaxDevTeam\Cms\Core\RichTextSanitizer;
use SyntaxDevTeam\Cms\Core\ThemeInterface;

final class Theme implements ThemeInterface
{
    private readonly string $publicName;

    private readonly string $publicDefaultTitle;

    private readonly string $publicEyebrow;

    private readonly string $publicMetaDescription;

    private readonly string $publicMetaKeywords;

    private readonly string $publicMetaAuthor;

    private readonly string $publicMetaRobots;

    private readonly string $publicLocale;

    private readonly string $publicLanguage;

    private readonly string $publicSocialImageUrl;

    private readonly string $publicSocialImageAlt;

    private readonly string $publicTwitterSite;

    private readonly string $publicThemeColor;

    private readonly string $publicGoogleSiteVerification;

    private readonly string $publicBingSiteVerification;

    private readonly string $publicFooterText;

    private readonly string $publicFaviconPath;

    private readonly string $publicFaviconVersion;

    private readonly string $publicUrl;

    private readonly string $publicPath;

    private array $publicNavigation = [];

    private bool $publicAuthenticated = false;

    private array $adminSearchItems = [];

    public function __construct(array $config = [])
    {
        $publicUrl = rtrim(trim((string) ($config['public_url'] ?? '')), '/');
        $this->publicUrl = filter_var($publicUrl, FILTER_VALIDATE_URL) !== false
            && str_starts_with($publicUrl, 'https://')
            && in_array((string) parse_url($publicUrl, PHP_URL_PATH), ['', '/'], true) ? $publicUrl : '';
        $this->publicName = trim((string) ($config['public_name'] ?? 'SyntaxDevTeam')) ?: 'SyntaxDevTeam';
        $this->publicDefaultTitle = trim((string) (
            $config['public_default_title'] ?? 'SyntaxDevTeam - software dla serwerów, społeczności i urządzeń'
        )) ?: $this->publicName;
        $this->publicEyebrow = trim((string) ($config['public_eyebrow'] ?? 'Software dla społeczności'));
        $this->publicMetaDescription = trim((string) (
            $config['public_meta_description']
            ?? 'SyntaxDevTeam tworzy pluginy Minecraft, boty Discord, aplikacje Android i narzędzia backendowe.'
        ));
        $this->publicMetaKeywords = trim((string) ($config['public_meta_keywords'] ?? ''));
        $this->publicMetaAuthor = trim((string) ($config['public_meta_author'] ?? $this->publicName));
        $robots = trim((string) ($config['public_meta_robots'] ?? 'index, follow, max-image-preview:large'));
        $this->publicMetaRobots = in_array($robots, [
            'index, follow',
            'index, follow, max-image-preview:large',
            'noindex, nofollow',
        ], true) ? $robots : 'index, follow, max-image-preview:large';
        $locale = trim((string) ($config['public_locale'] ?? 'pl_PL'));
        $this->publicLocale = preg_match('/^[a-z]{2}_[A-Z]{2}$/', $locale) === 1 ? $locale : 'pl_PL';
        $this->publicLanguage = str_replace('_', '-', $this->publicLocale);
        $socialImageUrl = trim((string) ($config['public_social_image_url'] ?? ''));
        $this->publicSocialImageUrl = $this->isSafePublicAssetUrl($socialImageUrl) ? $socialImageUrl : '';
        $this->publicSocialImageAlt = trim((string) ($config['public_social_image_alt'] ?? 'Logo SyntaxDevTeam'));
        $twitterSite = ltrim(trim((string) ($config['public_twitter_site'] ?? '')), '@');
        $this->publicTwitterSite = preg_match('/^[A-Za-z0-9_]{1,15}$/', $twitterSite) === 1 ? $twitterSite : '';
        $themeColor = strtolower(trim((string) ($config['public_theme_color'] ?? '#080c12')));
        $this->publicThemeColor = preg_match('/^#[0-9a-f]{6}$/', $themeColor) === 1 ? $themeColor : '#080c12';
        $this->publicGoogleSiteVerification = $this->verificationToken(
            (string) ($config['public_google_site_verification'] ?? '')
        );
        $this->publicBingSiteVerification = $this->verificationToken(
            (string) ($config['public_bing_site_verification'] ?? '')
        );
        $path = '/' . ltrim(trim((string) ($config['public_path'] ?? '/')), '/');
        $this->publicPath = preg_match('#^/[A-Za-z0-9/%._~-]*$#', $path) === 1 ? rtrim($path, '/') ?: '/' : '/';
        $this->publicFooterText = trim((string) (
            $config['public_footer_text']
            ?? 'Projektowane modułowo. Rozwijane świadomie.'
        )) ?: 'Projektowane modułowo. Rozwijane świadomie.';
        $faviconPath = rtrim(trim((string) ($config['public_favicon_path'] ?? '')), '/');
        $this->publicFaviconPath = preg_match('#^/[A-Za-z0-9/_-]+$#', $faviconPath) === 1
            ? $faviconPath
            : '';
        $faviconVersion = trim((string) ($config['public_favicon_version'] ?? ''));
        $this->publicFaviconVersion = ctype_digit($faviconVersion) ? $faviconVersion : '';
    }

    public function set_public_navigation(array $items, bool $authenticated): void
    {
        $this->publicNavigation = $items;
        $this->publicAuthenticated = $authenticated;
    }

    public function set_admin_search_items(array $items): void
    {
        $this->adminSearchItems = $items;
    }

    public function render_homepage(array $sections, array $pages, bool $authenticated): void
    {
        $pageTitle = $this->publicDefaultTitle;
        echo '<!doctype html><html lang="' . $this->escape($this->publicLanguage) . '" data-bs-theme="dark"><head>';
        echo '<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
        echo '<meta name="description" content="' . $this->escape($this->publicMetaDescription) . '">';
        if ($this->publicMetaKeywords !== '') {
            echo '<meta name="keywords" content="' . $this->escape($this->publicMetaKeywords) . '">';
        }
        echo '<title>' . $this->escape($pageTitle) . '</title>';
        $this->renderBrandHead($pageTitle, $this->publicMetaDescription);
        echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" ';
        echo 'integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">';
        echo '<link rel="stylesheet" href="' . $this->asset('css/stylebook.css') . '">';
        echo '<link rel="stylesheet" href="' . $this->asset('css/homepage.css') . '"></head><body>';
        echo '<div class="site-grid" aria-hidden="true"></div><a class="visually-hidden-focusable skip-link" href="#content">Przejdź do treści</a>';
        $this->renderPublicNavbar($pages, $authenticated, $sections, true);
        echo '<main id="content" tabindex="-1">';
        $heroRendered = false;
        foreach ($sections as $section) {
            $this->renderHomepageWidgetArea($section['widgets_before'] ?? [], $authenticated);
            if ($section['type'] === 'hero' && !$heroRendered) {
                $this->renderHomepageHero($section, $authenticated);
                $heroRendered = true;
            } else {
                $this->renderHomepageSection($section);
            }
            $this->renderHomepageWidgetArea($section['widgets_after'] ?? [], $authenticated);
        }

        echo '</main>';
        $this->renderPublicFooter($pages);
        echo '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" ';
        echo 'integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>';
        echo '<script src="' . $this->asset('js/site.js') . '"></script></body></html>';
    }

    private function renderPublicNavbar(array $pages, bool $authenticated, array $sections = [], bool $fixed = false): void
    {
        echo '<nav class="navbar navbar-expand-lg border-bottom' . ($fixed ? ' fixed-top' : '') . '" data-site-nav aria-label="Główna nawigacja"><div class="container">';
        echo '<a class="navbar-brand fw-bold" href="/">' . $this->brandLogo('site-brand-logo');
        echo $this->escape($this->publicName) . '</a>';
        echo '<button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Przełącz nawigację"><span class="navbar-toggler-icon"></span></button>';
        echo '<div class="collapse navbar-collapse" id="mainNav"><ul class="navbar-nav ms-auto align-items-lg-center">';
        if (!$fixed) {
            echo '<li class="nav-item"><a class="nav-link" href="/"';
            echo $this->publicPath === '/' ? ' aria-current="page"' : '';
            echo '>Home</a></li>';
        }
        $contactSection = null;
        foreach ($sections as $section) {
            if ($section['type'] === 'hero') {
                continue;
            }
            if ((string) ($section['key'] ?? '') === 'contact' || (string) ($section['layout'] ?? '') === 'contact') {
                $contactSection = $section;
                continue;
            }
            echo '<li class="nav-item"><a class="nav-link" href="#' . $this->escape($section['key']) . '">';
            echo $this->escape($this->navigationLabel($section)) . '</a></li>';
        }
        foreach ($pages as $page) {
            if ($page['navigation_area'] !== 'main') {
                continue;
            }
            $href = $this->navigationHref($page);
            echo '<li class="nav-item"><a class="nav-link" href="' . $this->escape($href) . '"';
            echo $this->isCurrentPublicHref($href) ? ' aria-current="page"' : '';
            echo '>';
            echo $this->escape($page['navigation_label'] !== '' ? $page['navigation_label'] : $page['title']);
            echo '</a></li>';
        }
        if ($pages !== [] && array_filter($pages, static fn (array $page): bool => $page['navigation_area'] === 'main') === []) {
            if ($authenticated) {
                echo '<li class="nav-item"><a class="nav-link" href="index.php?route=/pages">Podstrony</a></li>';
            }
        }
        if ($contactSection !== null) {
            echo '<li class="nav-item"><a class="nav-link" href="#' . $this->escape($contactSection['key']) . '">';
            echo $this->escape($this->navigationLabel($contactSection)) . '</a></li>';
        } else {
            echo '<li class="nav-item"><a class="nav-link" href="/#contact">Kontakt</a></li>';
        }
        echo '<li class="nav-item ms-lg-2"><a class="btn btn-sm btn-outline-light" href="';
        echo $authenticated ? '/admin' : '/admin/login';
        echo '">' . ($authenticated ? 'Otwórz panel' : 'Zaloguj się') . '</a></li></ul></div></div></nav>';
    }

    private function renderPublicFooter(array $pages): void
    {
        echo '<footer class="border-top py-4"><div class="container d-flex flex-column flex-md-row justify-content-between gap-2 text-secondary small">';
        echo '<span>&copy; ' . date('Y') . ' Powered by ';
        echo '<a class="text-secondary" href="https://syntaxdevteam.pl/p/miniportal">miniPORTAL</a> by ';
        echo '<a class="text-secondary" href="https://syntaxdevteam.pl">SyntaxDevTeam</a></span>';
        echo '<div class="d-flex flex-wrap align-items-center gap-3">';
        $footerPages = array_values(array_filter(
            $pages,
            static fn (array $page): bool => $page['navigation_area'] === 'footer'
        ));
        if ($footerPages !== []) {
            echo '<nav class="footer-navigation d-flex flex-wrap gap-3" aria-label="Nawigacja w stopce">';
        }
        foreach ($footerPages as $page) {
            echo '<a class="text-secondary" href="' . $this->escape($this->navigationHref($page)) . '">';
            echo $this->escape($page['navigation_label'] !== '' ? $page['navigation_label'] : $page['title']);
            echo '</a>';
        }
        echo $footerPages !== [] ? '</nav>' : '';
        if (strcasecmp(trim($this->publicFooterText), 'Powered by miniPORTAL by SyntaxDevTeam') !== 0) {
            echo '<span>' . $this->escape($this->publicFooterText) . '</span>';
        }
        echo '</div></div></footer>';
    }

    public function start_page(string $title, string $description = '', bool $indexable = true): void
    {
        $pageTitle = $title;
        $metaDescription = $description !== '' ? $description : $this->publicMetaDescription;

        echo '<!doctype html><html lang="' . $this->escape($this->publicLanguage) . '" data-bs-theme="dark"><head>';
        echo '<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
        echo '<meta name="description" content="' . $this->escape($metaDescription) . '">';
        if ($this->publicMetaKeywords !== '') {
            echo '<meta name="keywords" content="' . $this->escape($this->publicMetaKeywords) . '">';
        }
        echo '<title>' . $this->escape($pageTitle) . '</title>';
        $this->renderBrandHead($pageTitle, $metaDescription, $indexable);
        echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" ';
        echo 'integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">';
        echo '<link rel="stylesheet" href="' . $this->asset('css/stylebook.css') . '">';
        echo '</head><body><a class="visually-hidden-focusable skip-link" href="#content">Przejdź do treści</a>';
        $this->renderPublicNavbar($this->publicNavigation, $this->publicAuthenticated);
        echo '<main id="content" tabindex="-1">';
    }

    public function end_page(): void
    {
        echo '</main>';
        $this->renderPublicFooter($this->publicNavigation);
        echo '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" ';
        echo 'integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>';
        echo '<script src="' . $this->asset('js/site.js') . '"></script></body></html>';
    }

    public function start_header(string $title, string $lead = '', string $eyebrow = ''): void
    {
        echo '<header class="stylebook-hero border-bottom"><div class="container py-5">';
        $eyebrow = trim($eyebrow) !== '' ? trim($eyebrow) : $this->publicEyebrow;
        if ($eyebrow !== '') {
            echo '<p class="eyebrow mb-2">' . $this->escape($eyebrow) . '</p>';
        }
        echo '<h1 class="display-4 fw-bold">' . $this->escape($title) . '</h1>';

        if ($lead !== '') {
            echo '<p class="lead text-secondary col-lg-8 mb-0">' . $this->escape($lead) . '</p>';
        }
    }

    public function end_header(): void
    {
        echo '</div></header>';
    }

    public function start_section(): void
    {
        echo '<section class="container py-5">';
    }

    public function end_section(): void
    {
        echo '</section>';
    }

    public function start_grid(): void
    {
        echo '<div class="row g-4 mb-5">';
    }

    public function end_grid(): void
    {
        echo '</div>';
    }

    public function start_column(string $size = '12'): void
    {
        $allowedSizes = ['12', 'lg-4', 'lg-5', 'lg-6', 'lg-7', 'md-6', 'md-8'];
        $size = in_array($size, $allowedSizes, true) ? $size : '12';
        echo '<div class="col-' . $size . '">';
    }

    public function end_column(): void
    {
        echo '</div>';
    }

    public function start_card(string $title = '', string $label = ''): void
    {
        echo '<article class="showcase-card h-100">';

        if ($label !== '') {
            echo '<p class="showcase-label">' . $this->escape($label) . '</p>';
        }

        if ($title !== '') {
            echo '<h2 class="h4">' . $this->escape($title) . '</h2>';
        }
    }

    public function end_card(): void
    {
        echo '</article>';
    }

    public function render_text(string $text): void
    {
        echo '<p class="text-secondary">' . $this->escape($text) . '</p>';
    }

    public function render_button(string $label, string $href, string $variant = 'primary'): void
    {
        $variant = $this->buttonVariant($variant);

        echo '<a class="btn btn-' . $variant . '" href="' . $this->escape($href) . '">';
        echo $this->escape($label) . '</a>';
    }

    public function render_breadcrumb(array $items): void
    {
        if ($items === []) { return; }
        echo '<nav class="public-breadcrumb" aria-label="Ścieżka nawigacji"><ol>';
        foreach ($items as $index => $item) {
            $label = (string) ($item['label'] ?? '');
            if ($label === '') { continue; }
            $href = $this->safeHref((string) ($item['href'] ?? ''));
            $isLast = $index === array_key_last($items) || $href === '';
            echo '<li>';
            if ($isLast) {
                echo '<span aria-current="page">' . $this->escape($label) . '</span>';
            } else {
                echo '<a href="' . $this->escape($href) . '">' . $this->escape($label) . '</a>';
            }
            echo '</li>';
        }
        echo '</ol></nav>';
    }

    public function render_link_list(array $links): void
    {
        echo '<nav class="public-link-list" aria-label="Powiązane zasoby">';
        foreach ($links as $link) {
            $href = $this->safeHref((string) ($link['href'] ?? ''));
            if ($href === '') {
                continue;
            }
            echo '<a class="public-link-item" href="' . $this->escape($href) . '">';
            echo '<span>' . $this->escape((string) ($link['label'] ?? 'Link')) . '</span>';
            if (($link['meta'] ?? '') !== '') {
                echo '<small>' . $this->escape((string) $link['meta']) . '</small>';
            }
            echo '<strong aria-hidden="true">&gt;</strong></a>';
        }
        echo '</nav>';
    }

    public function render_avatar(string $name, ?string $avatarUrl = null, string $size = 'md'): void
    {
        $size = in_array($size, ['sm', 'md', 'lg'], true) ? $size : 'md';
        $avatarUrl = $avatarUrl !== null ? $this->safeHref($avatarUrl) : '';
        echo '<div class="public-avatar public-avatar-' . $this->escape($size) . '" aria-hidden="true">';
        if ($avatarUrl !== '' && preg_match('~^https?://~i', $avatarUrl) === 1) {
            echo '<img src="' . $this->escape($avatarUrl) . '" alt="" loading="lazy">';
        } else {
            echo '<span>' . $this->escape($this->initials($name)) . '</span>';
        }
        echo '</div>';
    }

    public function render_content_navigation(array $items): void
    {
        if ($items === []) {
            return;
        }

        echo '<nav class="content-navigation" aria-label="Nawigacja treści">';
        foreach ($items as $item) {
            $label = (string) ($item['label'] ?? '');
            $title = (string) ($item['title'] ?? '');
            $description = (string) ($item['description'] ?? '');
            $href = (string) ($item['href'] ?? '');
            $direction = (string) ($item['direction'] ?? '');
            $directionClass = in_array($direction, ['previous', 'next'], true)
                ? ' content-nav-' . $direction
                : '';
            $disabled = ($item['disabled'] ?? false) === true || $href === '';
            $class = 'content-nav-item' . $directionClass . ($disabled ? ' is-disabled' : '');
            $tag = $disabled ? 'span' : 'a';
            echo '<' . $tag . ' class="' . $class . '"';
            echo $disabled ? ' aria-disabled="true"' : ' href="' . $this->escape($href) . '"';
            echo '><span class="content-nav-label">' . $this->escape($label) . '</span>';
            echo '<strong class="content-nav-title">' . $this->escape($title) . '</strong>';
            if ($description !== '') {
                echo '<small class="content-nav-description">' . $this->escape($description) . '</small>';
            }
            echo '</' . $tag . '>';
        }
        echo '</nav>';
    }

    public function render_alert(string $message, string $variant = 'info'): void
    {
        $allowedVariants = ['success', 'danger', 'warning', 'info'];
        $variant = in_array($variant, $allowedVariants, true) ? $variant : 'info';

        echo '<div class="alert alert-' . $variant . '" role="status">' . $this->escape($message) . '</div>';
    }

    public function render_table(array $headers, array $rows): void
    {
        echo '<div class="table-responsive showcase-card p-0"><table class="table table-hover align-middle mb-0"><thead><tr>';

        foreach ($headers as $header) {
            echo '<th scope="col">' . $this->escape($header) . '</th>';
        }

        echo '</tr></thead><tbody>';

        foreach ($rows as $row) {
            echo '<tr>';
            foreach ($row as $cell) {
                echo '<td>' . $this->escape((string) $cell) . '</td>';
            }
            echo '</tr>';
        }

        echo '</tbody></table></div>';
    }

    public function render_detail_card(
        string $title,
        string $label,
        array $facts,
        array $headers = [],
        array $rows = [],
        array $actions = [],
    ): void {
        echo '<article class="showcase-card public-detail-card">';
        if ($label !== '') {
            echo '<p class="showcase-label">' . $this->escape($label) . '</p>';
        }
        if ($title !== '') {
            echo '<h2 class="h4">' . $this->escape($title) . '</h2>';
        }
        if ($facts !== []) {
            echo '<dl class="public-detail-list">';
            foreach ($facts as $fact) {
                echo '<div class="public-detail-row"><dt>' . $this->escape((string) ($fact['label'] ?? '')) . '</dt>';
                echo '<dd>' . $this->escape((string) ($fact['value'] ?? '')) . '</dd></div>';
            }
            echo '</dl>';
        }
        if ($headers !== [] && $rows !== []) {
            echo '<div class="table-responsive public-detail-table-wrap"><table class="table table-hover align-middle public-detail-table"><thead><tr>';
            foreach ($headers as $header) {
                echo '<th scope="col">' . $this->escape($header) . '</th>';
            }
            echo '</tr></thead><tbody>';
            foreach ($rows as $row) {
                echo '<tr>';
                foreach ($row as $cell) {
                    echo '<td>' . $this->escape((string) $cell) . '</td>';
                }
                echo '</tr>';
            }
            echo '</tbody></table></div>';
        }
        if ($actions !== []) {
            echo '<div class="public-detail-actions">';
            foreach ($actions as $action) {
                $href = $this->safeHref((string) ($action['href'] ?? ''));
                if ($href === '') { continue; }
                $variant = $this->buttonVariant((string) ($action['variant'] ?? 'primary'));
                echo '<a class="btn btn-' . $variant . '" href="' . $this->escape($href) . '">';
                echo $this->escape((string) ($action['label'] ?? 'Otwórz')) . '</a>';
            }
            echo '</div>';
        }
        echo '</article>';
    }

    public function render_line_chart(array $points, string $label): void
    {
        $points = array_values(array_filter($points, static fn (mixed $point): bool => is_array($point) && isset($point['label']) && is_numeric($point['value'] ?? null)));
        if ($points === []) { $this->render_alert('Brak danych do narysowania wykresu.', 'info'); return; }
        $values = array_map(static fn (array $point): float => (float) $point['value'], $points);
        $min = min($values); $max = max($values); $range = max(1.0, $max - $min); $count = count($points);
        $coordinates = [];
        foreach ($values as $index => $value) { $coordinates[] = round(24 + ($count === 1 ? 296 : $index * 592 / ($count - 1)), 2) . ',' . round(196 - (($value - $min) / $range) * 172, 2); }
        echo '<figure class="line-chart" role="group" aria-label="' . $this->escape($label) . '"><svg viewBox="0 0 640 220" role="img" aria-labelledby="line-chart-title-' . substr(hash('sha256', $label), 0, 10) . '">';
        echo '<title id="line-chart-title-' . substr(hash('sha256', $label), 0, 10) . '">' . $this->escape($label) . '</title><line x1="24" y1="196" x2="616" y2="196" class="line-chart-axis"/><polyline points="' . implode(' ', $coordinates) . '" class="line-chart-series"/></svg>';
        echo '<figcaption><span>' . $this->escape((string) $points[0]['label']) . '</span><strong>' . $this->escape($label) . ' (' . $this->escape((string) $min) . '-' . $this->escape((string) $max) . ')</strong><span>' . $this->escape((string) $points[array_key_last($points)]['label']) . '</span></figcaption></figure>';
    }

    public function render_action_table(array $headers, array $rows): void
    {
        echo '<div class="table-responsive showcase-card p-0"><table class="table table-hover align-middle mb-0"><thead><tr>';
        foreach ($headers as $header) { echo '<th scope="col">' . $this->escape($header) . '</th>'; }
        echo '<th scope="col">Akcje</th></tr></thead><tbody>';
        foreach ($rows as $row) {
            echo '<tr>';
            foreach ($row['cells'] as $cell) { echo '<td>' . $this->escape((string) $cell) . '</td>'; }
            echo '<td><div class="d-flex flex-wrap gap-2">';
            foreach ($row['actions'] as $action) {
                $variant = $this->buttonVariant((string) ($action['variant'] ?? 'outline-light'));
                echo '<a class="btn btn-sm btn-' . $variant . '" href="' . $this->escape($this->safeHref($action['href'])) . '">';
                echo $this->escape($action['label']) . '</a>';
            }
            echo '</div></td></tr>';
        }
        echo '</tbody></table></div>';
    }

    public function render_form(
        string $action,
        array $fields,
        string $submitLabel,
        string $csrfToken = '',
        string $method = 'post',
    ): void {
        $method = strtolower($method) === 'get' ? 'get' : 'post';
        $hasFile = array_any($fields, static fn (array $field): bool => ($field['type'] ?? '') === 'file');
        $formClass = $fields === [] ? 'showcase-card form-action-only' : 'showcase-card';
        echo '<form class="' . $formClass . '" action="' . $this->escape($action) . '" method="' . $method . '"';
        echo $hasFile ? ' enctype="multipart/form-data"' : '';
        echo '>';

        if ($csrfToken !== '') {
            $this->csrf_field($csrfToken);
        }

        foreach ($fields as $field) {
            $name = $this->escape($field['name']);
            $label = $this->escape($field['label']);
            $type = $field['type'] ?? 'text';
            $rawValue = (string) ($field['value'] ?? '');
            $value = $this->escape($rawValue);
            $helpId = $name . '-help';
            $controlAttributes = ($field['required'] ?? false) ? ' required' : '';
            if (isset($field['maxlength'])) {
                $controlAttributes .= ' maxlength="' . max(1, min(10000, (int) $field['maxlength'])) . '"';
            }
            foreach (['autocomplete', 'inputmode', 'placeholder'] as $attribute) {
                if (isset($field[$attribute]) && trim((string) $field[$attribute]) !== '') {
                    $controlAttributes .= ' ' . $attribute . '="' . $this->escape((string) $field[$attribute]) . '"';
                }
            }
            if (($field['help'] ?? '') !== '') {
                $controlAttributes .= ' aria-describedby="' . $helpId . '"';
            }

            if ($type === 'hidden') {
                echo '<input type="hidden" name="' . $name . '" value="' . $value . '">';
                continue;
            }

            if ($type === 'checkbox') {
                $checked = ($field['checked'] ?? false) ? ' checked' : '';
                echo '<div class="form-check mb-3">';
                echo '<input class="form-check-input" id="' . $name . '" name="' . $name . '" type="checkbox" value="1"' . $checked . '>';
                echo '<label class="form-check-label" for="' . $name . '">' . $label . '</label></div>';
                continue;
            }

            echo '<div class="mb-3">';
            if (in_array($type, ['richtext', 'checkbox_groups'], true)) {
                echo '<span class="form-label" id="' . $name . '-label">' . $label . '</span>';
            } else {
                echo '<label class="form-label" for="' . $name . '">' . $label . '</label>';
            }

            if ($type === 'richtext') {
                $formatName = $this->escape((string) ($field['format_name'] ?? $field['name'] . '_format'));
                $format = (new ContentRenderer())->normalizeFormat((string) ($field['format_value'] ?? 'html'));
                $safeValue = (new RichTextSanitizer())->sanitize($format === ContentRenderer::HTML ? $rawValue : '');
                echo '<div class="richtext-editor" data-richtext data-richtext-format="' . $format . '">';
                echo '<div class="richtext-format-row"><div class="richtext-mode-switch" role="group" aria-label="Format źródłowy">';
                echo '<button class="editor-mode' . ($format === ContentRenderer::HTML ? ' is-active' : '') . '" type="button" ';
                echo 'data-richtext-mode="html">Edytor wizualny</button>';
                echo '<button class="editor-mode' . ($format === ContentRenderer::MARKDOWN ? ' is-active' : '') . '" type="button" ';
                echo 'data-richtext-mode="markdown">Markdown</button></div>';
                echo '<label class="richtext-format-label">Format zapisu<select class="form-select form-select-sm" ';
                echo 'name="' . $formatName . '" data-richtext-format-input>';
                echo '<option value="html"' . ($format === ContentRenderer::HTML ? ' selected' : '') . '>HTML</option>';
                echo '<option value="markdown"' . ($format === ContentRenderer::MARKDOWN ? ' selected' : '') . '>Markdown</option>';
                echo '</select></label></div>';
                echo '<div class="editor-toolbar" data-richtext-toolbar role="toolbar" aria-label="Formatowanie treści"';
                echo $format === ContentRenderer::MARKDOWN ? ' hidden' : '';
                echo '>';
                foreach ([
                    ['bold', 'B', 'Pogrubienie'],
                    ['italic', 'I', 'Kursywa'],
                    ['underline', 'U', 'Podkreślenie'],
                    ['insertUnorderedList', 'UL', 'Lista punktowana'],
                    ['insertOrderedList', 'OL', 'Lista numerowana'],
                ] as [$command, $caption, $ariaLabel]) {
                    echo '<button class="editor-tool" type="button" data-richtext-command="' . $command . '" aria-label="';
                    echo $ariaLabel . '">' . $caption . '</button>';
                }
                foreach (['p' => 'Akapit', 'h2' => 'Nagłówek 2', 'h3' => 'Nagłówek 3', 'blockquote' => 'Cytat'] as $tag => $caption) {
                    echo '<button class="editor-tool" type="button" data-richtext-block="' . $tag . '">';
                    echo $caption . '</button>';
                }
                echo '</div><div class="richtext-surface form-control" id="' . $name . '-editor" contenteditable="true" ';
                echo 'data-richtext-surface aria-labelledby="' . $name . '-label"';
                echo $format === ContentRenderer::MARKDOWN ? ' hidden' : '';
                echo '>' . $safeValue . '</div>';
                echo '<textarea class="richtext-markdown form-control" data-richtext-markdown rows="18" ';
                echo 'aria-labelledby="' . $name . '-label"' . ($format === ContentRenderer::HTML ? ' hidden' : '') . '>';
                echo $format === ContentRenderer::MARKDOWN ? $value : '';
                echo '</textarea>';
                echo '<textarea class="visually-hidden" id="' . $name . '" name="' . $name . '" data-richtext-input>';
                echo $value . '</textarea>';
                echo '<p class="form-text mb-0 mt-2" data-richtext-hint>';
                echo $format === ContentRenderer::MARKDOWN
                    ? 'Markdown w stylu GitHub: tabele, listy zadań, kod, linki i obrazy.'
                    : 'Tryb wizualny zapisuje kontrolowany HTML.';
                echo '</p></div>';
            } elseif ($type === 'textarea') {
                $rows = max(2, min(20, (int) ($field['rows'] ?? 5)));
                echo '<textarea class="form-control" id="' . $name . '" name="' . $name . '" rows="' . $rows . '"';
                echo $controlAttributes . '>';
                echo $value . '</textarea>';
            } elseif ($type === 'select') {
                echo '<select class="form-select" id="' . $name . '" name="' . $name . '"' . $controlAttributes . '>';
                foreach ($field['options'] ?? [] as $optionValue => $optionLabel) {
                    $selected = (string) $optionValue === ($field['value'] ?? '') ? ' selected' : '';
                    echo '<option value="' . $this->escape((string) $optionValue) . '"' . $selected . '>';
                    echo $this->escape($optionLabel) . '</option>';
                }
                echo '</select>';
            } elseif ($type === 'multiselect') {
                $selectedValues = array_map('strval', $field['values'] ?? []);
                $size = max(3, min(12, count($field['options'] ?? [])));
                echo '<select class="form-select" id="' . $name . '" name="' . $name . '[]" multiple size="' . $size . '">';
                foreach ($field['options'] ?? [] as $optionValue => $optionLabel) {
                    $selected = in_array((string) $optionValue, $selectedValues, true) ? ' selected' : '';
                    echo '<option value="' . $this->escape((string) $optionValue) . '"' . $selected . '>';
                    echo $this->escape($optionLabel) . '</option>';
                }
                echo '</select>';
            } elseif ($type === 'checkbox_groups') {
                $selectedValues = array_map('strval', $field['values'] ?? []);
                echo '<div class="permission-groups" aria-labelledby="' . $name . '-label">';
                foreach ($field['groups'] ?? [] as $groupLabel => $options) {
                    $groupId = $name . '-' . substr(hash('sha256', (string) $groupLabel), 0, 10);
                    echo '<fieldset class="permission-group" data-checkbox-group>';
                    echo '<legend class="permission-group-header">';
                    echo '<span>' . $this->escape((string) $groupLabel) . '</span>';
                    echo '<span class="permission-group-tools">';
                    echo '<span class="permission-group-count" data-checkbox-group-count></span>';
                    echo '<button class="btn btn-sm btn-outline-light" type="button" data-checkbox-group-set="all">Zaznacz wszystkie</button>';
                    echo '<button class="btn btn-sm btn-outline-secondary" type="button" data-checkbox-group-set="none">Wyczyść</button>';
                    echo '</span></legend><div class="permission-group-options">';
                    foreach ($options as $optionValue => $optionLabel) {
                        $optionId = $groupId . '-' . substr(hash('sha256', (string) $optionValue), 0, 10);
                        $checked = in_array((string) $optionValue, $selectedValues, true) ? ' checked' : '';
                        echo '<label class="permission-option" for="' . $optionId . '">';
                        echo '<input class="form-check-input" id="' . $optionId . '" name="' . $name;
                        echo '[]" type="checkbox" value="' . $this->escape((string) $optionValue) . '"' . $checked . '>';
                        echo '<span><strong>' . $this->escape((string) $optionLabel) . '</strong>';
                        echo '<small>' . $this->escape((string) $optionValue) . '</small></span></label>';
                    }
                    echo '</div></fieldset>';
                }
                echo '</div>';
            } elseif ($type === 'file') {
                $accept = isset($field['accept']) ? ' accept="' . $this->escape((string) $field['accept']) . '"' : '';
                echo '<div class="file-dropzone">';
                echo '<input class="form-control" id="' . $name . '" name="' . $name . '" type="file"' . $accept . $controlAttributes . '>';
                echo '<span>Przeciągnij i upuść plik albo wybierz go z dysku.</span></div>';
            } else {
                $allowedTypes = ['text', 'email', 'password', 'number', 'url', 'date', 'color'];
                $type = in_array($type, $allowedTypes, true) ? $type : 'text';
                $class = $type === 'color' ? 'form-control form-control-color' : 'form-control';
                echo '<input class="' . $class . '" id="' . $name . '" name="' . $name . '" type="' . $type;
                echo '" value="' . $value . '"' . $controlAttributes . '>';
            }

            if (($field['help'] ?? '') !== '') {
                echo '<div class="form-text" id="' . $helpId . '">' . $this->escape((string) $field['help']) . '</div>';
            }
            echo '</div>';
        }

        echo '<button class="btn btn-primary" type="submit">' . $this->escape($submitLabel) . '</button></form>';
    }

    public function csrf_field(string $token): void
    {
        echo '<input type="hidden" name="_token" value="' . $this->escape($token) . '">';
    }

    public function start_admin_page(string $title, array $menuItems, string $activePath, array $user): void
    {
        echo '<!doctype html><html lang="pl" data-bs-theme="dark"><head>';
        echo '<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
        echo '<meta name="description" content="Panel administracyjny miniPORTAL">';
        $this->renderBrandHead('', '', false);
        echo '<title>' . $this->escape($title) . ' - miniPORTAL Admin</title>';
        echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" ';
        echo 'integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">';
        echo '<link rel="stylesheet" href="' . $this->asset('css/stylebook.css') . '">';
        echo '<link rel="stylesheet" href="' . $this->asset('css/admin.css') . '">';
        echo '</head><body class="admin-stylebook"><div class="site-grid" aria-hidden="true"></div>';
        echo '<a class="visually-hidden-focusable skip-link" href="#admin-main">Przejdź do treści panelu</a>';
        echo '<div class="offcanvas offcanvas-start text-bg-dark" tabindex="-1" id="adminMobileSidebar" ';
        echo 'aria-labelledby="adminMobileSidebarLabel"><div class="offcanvas-header border-bottom">';
        echo '<h2 class="offcanvas-title h5" id="adminMobileSidebarLabel">miniPORTAL Admin</h2>';
        echo '<button class="btn-close btn-close-white" type="button" data-bs-dismiss="offcanvas" aria-label="Zamknij"></button>';
        echo '</div><div class="offcanvas-body p-0">';
        $this->renderAdminMenu($menuItems, $activePath, true);
        echo '</div></div>';
        echo '<div class="admin-preview rounded-0 border-0"><div class="admin-shell">';
        echo '<aside class="admin-sidebar" aria-label="Nawigacja panelu"><div class="admin-sidebar-header">';
        echo '<a class="admin-brand text-decoration-none" href="index.php?route=/admin">';
        echo $this->brandLogo('admin-brand-logo', 'admin-logo.png') . '<span>miniPORTAL</span></a></div>';
        $this->renderAdminMenu($menuItems, $activePath);
        echo '</aside>';
        echo '<div class="admin-workspace"><header class="admin-topbar">';
        echo '<button class="admin-icon-button d-lg-none" type="button" data-admin-sidebar-toggle aria-label="Otwórz nawigację panelu">MN</button>';
        echo '<div class="admin-search" data-admin-search><span class="admin-search-mark" aria-hidden="true">SZ</span>';
        echo '<label class="visually-hidden" for="admin-global-search">Szukaj w panelu</label>';
        echo '<input class="form-control" id="admin-global-search" type="search" placeholder="Szukaj w panelu..." ';
        echo 'autocomplete="off" aria-controls="admin-search-results" aria-expanded="false" data-admin-search-input>';
        echo '<div class="admin-search-results" id="admin-search-results" role="listbox" hidden data-admin-search-results>';
        foreach ($this->adminSearchItems as $item) {
            $terms = strtolower($item['label'] . ' ' . $item['description'] . ' ' . $item['section'] . ' ' . $item['keywords']);
            echo '<a class="admin-search-result" role="option" href="' . $this->escape($this->safeHref($item['href'])) . '" ';
            echo 'data-admin-search-item data-search="' . $this->escape($terms) . '" hidden>';
            echo '<strong>' . $this->escape($item['label']) . '</strong><span>';
            echo $this->escape($item['section'] . ' / ' . $item['description']) . '</span></a>';
        }
        echo '<p class="admin-search-empty" data-admin-search-empty hidden>Brak pasujących funkcji.</p></div></div>';
        echo '<div class="ms-auto d-flex align-items-center gap-2">';
        echo '<a class="admin-icon-button text-decoration-none" href="index.php" aria-label="Wróć do strony głównej">HM</a>';
        echo '<div class="dropdown admin-user-menu">';
        echo '<button class="admin-user-menu-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">';
        $this->renderAdminAvatar($user);
        echo '<span class="admin-user-copy d-none d-sm-block"><strong>' . $this->escape($user['name']) . '</strong>';
        echo '<span>' . $this->escape($user['role']) . '</span></span></button>';
        echo '<div class="dropdown-menu dropdown-menu-end admin-user-dropdown">';
        foreach ($this->adminProfileLinks($user) as $link) {
            echo '<a class="dropdown-item" href="' . $this->escape($this->safeHref($link['href'])) . '">';
            echo $this->escape($link['label']) . '</a>';
        }
        if (($user['logout_action'] ?? '') !== '' && ($user['logout_token'] ?? '') !== '') {
            echo '<div class="dropdown-divider"></div><form action="' . $this->escape($user['logout_action']) . '" method="post">';
            $this->csrf_field($user['logout_token']);
            echo '<button class="dropdown-item" type="submit">Wyloguj</button></form>';
        }
        echo '</div></div>';
        echo '</div></header><main id="admin-main" class="admin-content">';
    }

    public function end_admin_page(): void
    {
        echo '</main></div></div></div>';
        echo '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" ';
        echo 'integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>';
        echo '<script src="' . $this->asset('js/admin.js') . '"></script></body></html>';
    }

    public function start_admin_content(
        string $title,
        string $lead = '',
        array $breadcrumbs = [],
        ?array $action = null,
    ): void {
        if ($breadcrumbs !== []) {
            echo '<nav aria-label="Breadcrumb"><ol class="breadcrumb admin-breadcrumb">';
            foreach ($breadcrumbs as $index => $breadcrumb) {
                $isLast = $index === array_key_last($breadcrumbs);
                if ($isLast || $breadcrumb['href'] === '') {
                    echo '<li class="breadcrumb-item active" aria-current="page">' . $this->escape($breadcrumb['label']) . '</li>';
                } else {
                    echo '<li class="breadcrumb-item"><a href="' . $this->escape($breadcrumb['href']) . '">';
                    echo $this->escape($breadcrumb['label']) . '</a></li>';
                }
            }
            echo '</ol></nav>';
        }

        echo '<div class="admin-page-heading"><div><h1>' . $this->escape($title) . '</h1>';
        if ($lead !== '') {
            echo '<p class="text-secondary mb-0">' . $this->escape($lead) . '</p>';
        }
        echo '</div></div>';
        $this->renderAdminModuleActions($action);
    }

    public function end_admin_content(): void
    {
    }

    public function start_admin_metrics(): void
    {
        echo '<div class="metric-grid mb-4">';
    }

    public function render_admin_metric(string $label, string $value, string $symbol, string $detail = ''): void
    {
        echo '<article class="metric-card" data-symbol="' . $this->escape(substr($symbol, 0, 3)) . '">';
        echo '<span class="metric-label">' . $this->escape($label) . '</span>';
        echo '<strong class="metric-value">' . $this->escape($value) . '</strong>';
        if ($detail !== '') {
            echo '<span class="metric-change">' . $this->escape($detail) . '</span>';
        }
        echo '</article>';
    }

    public function end_admin_metrics(): void
    {
        echo '</div>';
    }

    public function start_admin_panel_grid(string $variant = 'balanced'): void
    {
        $variant = preg_replace('/[^a-z0-9_-]/i', '', $variant) ?: 'balanced';
        echo '<div class="admin-panel-grid admin-panel-grid-' . $this->escape($variant) . '">';
    }

    public function end_admin_panel_grid(): void
    {
        echo '</div>';
    }

    public function start_admin_panel_column(): void
    {
        echo '<div class="admin-panel-column">';
    }

    public function end_admin_panel_column(): void
    {
        echo '</div>';
    }

    public function start_admin_panel(string $title, string $meta = ''): void
    {
        echo '<section class="admin-panel"><div class="admin-panel-header"><h2 class="h5 mb-0">';
        echo $this->escape($title) . '</h2>';
        if ($meta !== '') {
            echo '<span class="status-badge status-badge-published">' . $this->escape($meta) . '</span>';
        }
        echo '</div><div class="admin-panel-body">';
    }

    public function end_admin_panel(): void
    {
        echo '</div></section>';
    }

    public function render_admin_fact_grid(array $facts): void
    {
        echo '<div class="profile-fact-grid">';
        foreach ($facts as $fact) {
            $variant = in_array(($fact['variant'] ?? ''), ['success', 'warning'], true) ? ' profile-fact-' . $fact['variant'] : '';
            echo '<article class="profile-fact' . $variant . '">';
            echo '<span>' . $this->escape($fact['label']) . '</span>';
            echo '<strong>' . $this->escape($fact['value']) . '</strong>';
            if (($fact['detail'] ?? '') !== '') {
                echo '<small>' . $this->escape($fact['detail']) . '</small>';
            }
            echo '</article>';
        }
        echo '</div>';
    }

    public function render_admin_panel_actions(array $actions): void
    {
        echo '<div class="admin-panel-actions">';
        foreach ($actions as $action) {
            $variant = $this->buttonVariant((string) ($action['variant'] ?? 'outline-light'));
            echo '<a class="btn btn-' . $variant . '" href="' . $this->escape($this->safeHref($action['href'])) . '">';
            echo $this->escape($action['label']) . '</a>';
        }
        echo '</div>';
    }

    private function renderAdminModuleActions(?array $actions): void
    {
        if ($actions === null || $actions === []) {
            return;
        }

        $normalized = isset($actions['label'], $actions['href']) ? [$actions] : $actions;
        echo '<div class="admin-module-actions" aria-label="Akcje modułu">';
        foreach ($normalized as $action) {
            if (!is_array($action) || ($action['label'] ?? '') === '' || ($action['href'] ?? '') === '') {
                continue;
            }
            $variant = $this->buttonVariant((string) ($action['variant'] ?? 'outline-light'));
            echo '<a class="btn btn-' . $variant . '" href="' . $this->escape($this->safeHref((string) $action['href'])) . '">';
            echo $this->escape((string) $action['label']) . '</a>';
        }
        echo '</div>';
    }

    public function render_admin_table(array $headers, array $rows): void
    {
        echo '<div class="table-responsive"><table class="table table-hover align-middle admin-data-table">';
        echo '<thead><tr>';
        foreach ($headers as $header) {
            echo '<th scope="col">' . $this->escape($header) . '</th>';
        }
        echo '</tr></thead><tbody>';
        foreach ($rows as $row) {
            echo '<tr>';
            foreach ($row as $cell) {
                echo '<td>' . $this->escape((string) $cell) . '</td>';
            }
            echo '</tr>';
        }
        echo '</tbody></table></div>';
    }

    public function render_admin_action_table(array $headers, array $rows, string $csrfToken): void
    {
        echo '<div class="table-responsive"><table class="table admin-table align-middle mb-0">';
        echo '<thead><tr>';
        foreach ($headers as $header) {
            echo '<th scope="col">' . $this->escape($header) . '</th>';
        }
        echo '<th scope="col" class="text-end">Akcje</th></tr></thead><tbody>';

        foreach ($rows as $row) {
            echo '<tr>';
            foreach ($row['cells'] as $cell) {
                echo '<td>' . $this->escape((string) $cell) . '</td>';
            }
            echo '<td class="text-end"><div class="admin-table-actions">';

            foreach ($row['actions'] as $action) {
                $variant = $this->buttonVariant($action['variant'] ?? 'outline-light');
                $label = $this->escape($action['label']);

                if (isset($action['href'])) {
                    echo '<a class="btn btn-sm btn-' . $variant . '" href="';
                    echo $this->escape($action['href']) . '">' . $label . '</a>';
                    continue;
                }

                if (!isset($action['action'])) {
                    continue;
                }

                echo '<form class="admin-table-action-form" action="' . $this->escape($action['action']) . '" method="post"';
                if (($action['confirm'] ?? '') !== '') {
                    echo ' data-confirm="' . $this->escape((string) $action['confirm']) . '"';
                }
                echo '>';
                $this->csrf_field($csrfToken);
                foreach ($action['fields'] ?? [] as $name => $value) {
                    echo '<input type="hidden" name="' . $this->escape((string) $name) . '" value="';
                    echo $this->escape((string) $value) . '">';
                }
                echo '<button class="btn btn-sm btn-' . $variant . '" type="submit">' . $label . '</button></form>';
            }

            echo '</div></td></tr>';
        }

        echo '</tbody></table></div>';
    }

    public function render_admin_login(
        string $action,
        array $identities,
        string $csrfToken,
        string $message = '',
        string $variant = 'info',
    ): void {
        echo '<!doctype html><html lang="pl" data-bs-theme="dark"><head>';
        echo '<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
        echo '<meta name="description" content="Logowanie do panelu miniPORTAL">';
        $this->renderBrandHead('', '', false);
        echo '<title>Logowanie - miniPORTAL Admin</title>';
        echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" ';
        echo 'integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">';
        echo '<link rel="stylesheet" href="' . $this->asset('css/stylebook.css') . '">';
        echo '<link rel="stylesheet" href="' . $this->asset('css/admin.css') . '">';
        echo '</head><body class="admin-stylebook"><div class="site-grid" aria-hidden="true"></div>';
        echo '<main class="min-vh-100 d-grid align-items-center py-4"><div class="container">';
        echo '<div class="login-stage border-0 bg-transparent shadow-none"><section class="login-panel">';
        echo '<a class="admin-brand text-decoration-none" href="index.php">';
        echo $this->brandLogo('admin-brand-logo', 'admin-logo.png') . '<span>miniPORTAL Admin</span></a>';
        echo '<p class="showcase-label mt-5 mb-2">Bezpieczny dostęp</p>';
        echo '<h1 class="h2 fw-bold">Zaloguj się do panelu</h1>';
        echo '<p class="text-secondary">Konto i uprawnienia pozostają lokalne, niezależnie od wybranego dostawcy tożsamości.</p>';

        if ($message !== '') {
            $allowedVariants = ['success', 'danger', 'warning', 'info'];
            $variant = in_array($variant, $allowedVariants, true) ? $variant : 'info';
            echo '<div class="alert alert-' . $variant . '" role="alert">' . $this->escape($message) . '</div>';
        }

        echo '<div class="provider-list">';
        foreach ($identities as $identity) {
            $icon = strtoupper(substr($identity['provider'], 0, 2));

            if (($identity['href'] ?? '') !== '') {
                echo '<a class="provider-button text-decoration-none" href="' . $this->escape($identity['href']) . '">';
                echo '<span class="provider-icon provider-icon-github" aria-hidden="true">' . $this->escape($icon) . '</span>';
                echo '<span><strong class="d-block">' . $this->escape($identity['label']) . '</strong>';
                echo '<small class="text-secondary">' . $this->escape($identity['description']) . '</small></span>';
                echo '<span class="provider-arrow" aria-hidden="true">-&gt;</span></a>';
                continue;
            }

            echo '<form action="' . $this->escape($action) . '" method="post">';
            $this->csrf_field($csrfToken);
            echo '<input type="hidden" name="provider" value="' . $this->escape($identity['provider']) . '">';
            echo '<input type="hidden" name="subject" value="' . $this->escape($identity['subject']) . '">';
            echo '<button class="provider-button" type="submit"><span class="provider-icon provider-icon-github" aria-hidden="true">';
            echo $this->escape($icon) . '</span>';
            echo '<span><strong class="d-block">' . $this->escape($identity['label']) . '</strong>';
            echo '<small class="text-secondary">' . $this->escape($identity['description']) . '</small></span>';
            echo '<span class="provider-arrow" aria-hidden="true">-&gt;</span></button></form>';
        }
        if ($identities === []) {
            echo '<div class="state-card py-4"><span class="state-icon" aria-hidden="true">OFF</span>';
            echo '<h2 class="h5">Brak aktywnych dostawców</h2>';
            echo '<p class="text-secondary mb-0">Skonfiguruj adapter OAuth albo świadomie włącz tryb demonstracyjny.</p></div>';
        }
        echo '</div></section></div></div></main></body></html>';
    }

    public function render_admin_access_state(
        int $status,
        string $title,
        string $message,
        string $actionHref,
        string $actionLabel,
    ): void {
        echo '<!doctype html><html lang="pl" data-bs-theme="dark"><head>';
        echo '<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
        $this->renderBrandHead('', '', false);
        echo '<title>' . $this->escape((string) $status) . ' - miniPORTAL Admin</title>';
        echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">';
        echo '<link rel="stylesheet" href="' . $this->asset('css/stylebook.css') . '">';
        echo '<link rel="stylesheet" href="' . $this->asset('css/admin.css') . '">';
        echo '</head><body class="admin-stylebook"><main class="container min-vh-100 d-grid align-items-center py-5">';
        echo '<section class="state-card access-state-card">';
        echo '<span class="state-code">' . $this->escape((string) $status) . '</span>';
        echo '<h1 class="h3">' . $this->escape($title) . '</h1>';
        echo '<p class="text-secondary">' . $this->escape($message) . '</p>';
        echo '<a class="btn btn-primary" href="' . $this->escape($actionHref) . '">' . $this->escape($actionLabel) . '</a>';
        echo '</section></main></body></html>';
    }

    public function render_admin_identities(
        array $user,
        array $providers,
        string $unlinkAction,
        string $csrfToken,
        string $message = '',
        string $variant = 'info',
    ): void {
        echo '<!doctype html><html lang="pl" data-bs-theme="dark"><head>';
        echo '<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
        $this->renderBrandHead('', '', false);
        echo '<title>Połączone konta - miniPORTAL Admin</title>';
        echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">';
        echo '<link rel="stylesheet" href="' . $this->asset('css/stylebook.css') . '">';
        echo '<link rel="stylesheet" href="' . $this->asset('css/admin.css') . '">';
        echo '</head><body class="admin-stylebook"><main class="container py-5">';
        echo '<a class="admin-brand text-decoration-none mb-4" href="index.php?route=/admin">';
        echo $this->brandLogo('admin-brand-logo', 'admin-logo.png') . '<span>miniPORTAL Admin</span></a>';
        echo '<section class="admin-panel mt-4"><div class="admin-panel-header">';
        echo '<div><p class="showcase-label mb-1">Profil</p><h1 class="h3 mb-1">Połączone tożsamości</h1>';
        echo '<p class="text-secondary mb-0">' . $this->escape($user['name']) . ' · ' . $this->escape($user['role']) . '</p></div>';
        echo '<a class="btn btn-outline-light" href="index.php?route=/admin/profile">Wróć do profilu</a></div>';

        if ($message !== '') {
            $allowedVariants = ['success', 'danger', 'warning', 'info'];
            $variant = in_array($variant, $allowedVariants, true) ? $variant : 'info';
            echo '<div class="alert alert-' . $variant . ' mx-4 mt-4" role="alert">' . $this->escape($message) . '</div>';
        }

        echo '<div class="table-responsive"><table class="table admin-table align-middle mb-0">';
        echo '<thead><tr><th>Dostawca</th><th>Status</th><th class="text-end">Akcja</th></tr></thead><tbody>';
        foreach ($providers as $provider) {
            echo '<tr><td><strong>' . $this->escape($provider['label']) . '</strong></td><td>';
            echo $provider['linked']
                ? '<span class="badge text-bg-success">Połączono</span>'
                : ($provider['configured']
                    ? '<span class="badge text-bg-secondary">Dostępny</span>'
                    : '<span class="badge text-bg-dark">Nieskonfigurowany</span>');
            echo '</td><td class="text-end">';

            if ($provider['linked']) {
                echo '<form class="d-inline" action="' . $this->escape($unlinkAction) . '" method="post">';
                $this->csrf_field($csrfToken);
                echo '<input type="hidden" name="provider" value="' . $this->escape($provider['name']) . '">';
                echo '<button class="btn btn-sm btn-outline-danger" type="submit">Odłącz</button></form>';
            } elseif ($provider['configured']) {
                echo '<a class="btn btn-sm btn-primary" href="index.php?route=/admin/identity/';
                echo $this->escape(rawurlencode($provider['name'])) . '/link">Połącz</a>';
            } else {
                echo '<span class="text-secondary">Brak konfiguracji</span>';
            }
            echo '</td></tr>';
        }
        echo '</tbody></table></div></section></main></body></html>';
    }

    public function render_public_page(
        string $title,
        string $content,
        string $publishedAt,
        string $description = '',
        string $pageType = 'standard',
        string $contentFormat = 'html',
        string $eyebrow = '',
    ): void {
        $labels = [
            'project' => 'Projekt',
            'legal' => 'Dokument prawny',
            'standard' => 'Informacje',
        ];
        $this->start_page($title . ' - ' . $this->publicName, $description !== '' ? $description : $title);
        $this->start_header(
            $title,
            ($labels[$pageType] ?? $labels['standard']) . ' · Opublikowano: ' . $publishedAt,
            $eyebrow
        );
        $this->end_header();
        $this->start_section();
        echo '<article class="showcase-card managed-home-content">';
        if ($content === '') {
            echo '<p>Ta strona nie ma jeszcze treści.</p>';
        } else {
            $this->render_rich_content($content, $contentFormat);
        }
        echo '<a class="btn btn-outline-light" href="/index.php">Wróć do strony głównej</a></article>';
        $this->end_section();
        $this->end_page();
    }

    public function render_rich_content(string $content, string $format = 'html'): void
    {
        $rendered = (new ContentRenderer())->render($content, $format);
        if ($rendered === '') {
            return;
        }

        echo '<div class="rich-content">';
        echo str_contains($rendered, '<')
            ? $rendered
            : '<p>' . nl2br($this->escape($rendered)) . '</p>';
        echo '</div>';
    }

    public function render_page_not_found(string $title, string $message): void
    {
        $this->render_public_error(404, $title, $message);
    }

    public function render_public_error(
        int $status,
        string $title,
        string $message,
        string $actionLabel = 'Wróć do strony głównej',
        string $actionHref = '/',
    ): void
    {
        $this->start_page($status . ' - ' . $this->publicName, $message, false);
        $this->start_header($title, $message, $status . ' / ' . $this->errorEyebrow($status));
        $this->end_header();
        $this->start_section();
        echo '<article class="showcase-card">';
        echo '<p class="eyebrow mb-2">Kod odpowiedzi ' . $this->escape((string) $status) . '</p>';
        echo '<h2 class="h4">' . $this->escape($this->errorSummary($status)) . '</h2>';
        echo '<p class="text-secondary">' . $this->escape($message) . '</p>';
        $this->render_button($actionLabel, $actionHref, 'outline-light');
        echo '</article>';
        $this->end_section();
        $this->end_page();
    }

    public function render_admin_resources(array $resources, array $menuItems, array $user): void
    {
        $this->start_admin_page('Wzorce UI', $menuItems, '/admin/design-system', $user);
        $this->start_admin_content(
            'Wzorce UI i diagnostyka',
            'Źródła wizualne Outside-In oraz punkty kontrolne działającego systemu.',
            [
                ['label' => 'Panel', 'href' => 'index.php?route=/admin'],
                ['label' => 'Wzorce UI', 'href' => ''],
            ]
        );
        $this->start_admin_panel('Materiały projektu', count($resources) . ' odnośniki');
        echo '<div class="row g-3">';
        foreach ($resources as $resource) {
            echo '<div class="col-md-6"><article class="showcase-card h-100">';
            echo '<h2 class="h5">' . $this->escape($resource['label']) . '</h2>';
            echo '<p class="text-secondary">' . $this->escape($resource['description']) . '</p>';
            echo '<a class="btn btn-sm btn-outline-light" href="' . $this->escape($resource['href']) . '">Otwórz</a>';
            echo '</article></div>';
        }
        echo '</div>';
        $this->end_admin_panel();
        $this->end_admin_content();
        $this->end_admin_page();
    }

    /**
     * @param array{
     *     key: string,
     *     type: string,
     *     eyebrow: string,
     *     acrostic_words: string,
     *     title: string,
     *     content_html: string,
     *     content_format: string,
     *     layout: string,
     *     button_label: string,
     *     button_url: string,
     *     items: list<array{
     *         label: string,
     *         title: string,
     *         content: string,
     *         content_format: string,
     *         item_kind: string,
     *         icon_key: string,
     *         button_label: string,
     *         button_url: string,
     *         variant: string,
     *         width: string,
     *         page_slug: string
     *     }>
     * } $section
     */
    private function renderHomepageHero(array $section, bool $authenticated): void
    {
        $content = (new ContentRenderer())->render($section['content_html'], $section['content_format']);
        $asideWidgets = is_array($section['widgets_aside'] ?? null) ? $section['widgets_aside'] : [];
        $hasAside = $asideWidgets !== [];

        echo '<header id="' . $this->escape($section['key']) . '" class="home-hero"><div class="container py-5">';
        echo '<div class="row align-items-center g-5"><div class="' . ($hasAside ? 'col-lg-7' : 'col-12') . ' reveal is-visible">';
        if ($section['eyebrow'] !== '') {
            echo '<p class="eyebrow">' . $this->escape($section['eyebrow']) . '</p>';
        }
        $acrosticWords = (string) ($section['acrostic_words'] ?? '');
        if ($section['layout'] === 'split' && trim($acrosticWords) !== '') {
            $this->renderHomepageAcrostic($acrosticWords);
        } else {
            echo '<h1 class="home-title fw-bold">' . $this->homepageHeading($section['title']) . '</h1>';
        }
        echo '<div class="home-lead managed-home-content mt-4">' . $content . '</div>';
        echo '<div class="hero-actions mt-4">';
        if ($section['button_label'] !== '' && $this->safeHref($section['button_url']) !== '') {
            echo '<a class="btn btn-primary btn-lg" href="' . $this->escape($this->safeHref($section['button_url'])) . '">';
            echo $this->escape($section['button_label']) . '</a>';
        }
        echo '</div></div>';
        if ($hasAside) {
            echo '<div class="col-lg-5 reveal is-visible">';
            $this->renderHomepageWidget($asideWidgets[0], $authenticated);
            echo '</div>';
        }
        echo '</div></div></header>';
    }

    /** @param list<array<string, mixed>> $widgets */
    private function renderHomepageWidgetArea(array $widgets, bool $authenticated): void
    {
        if ($widgets === []) {
            return;
        }
        echo '<section class="home-section homepage-widget-area"><div class="container"><div class="managed-card-grid">';
        foreach ($widgets as $widget) {
            $this->renderHomepageWidget($widget, $authenticated);
        }
        echo '</div></div></section>';
    }

    /** @param array<string, mixed> $widget */
    private function renderHomepageWidget(array $widget, bool $authenticated): void
    {
        if (($widget['type'] ?? '') === 'terminal') {
            $this->renderHomepageTerminalWidget($widget, $authenticated);
            return;
        }
        $title = trim((string) ($widget['title'] ?? '')) ?: (string) ($widget['name'] ?? 'Widget');
        $content = (new ContentRenderer())->render(
            (string) ($widget['content'] ?? ''),
            (string) ($widget['content_format'] ?? 'html')
        );
        echo '<article class="showcase-card managed-card reveal is-visible" data-widget="' . $this->escape((string) ($widget['key'] ?? 'card')) . '">';
        echo '<p class="managed-card-label">WIDGET</p><h3>' . $this->escape($title) . '</h3>';
        if ($content !== '') {
            echo '<div class="managed-home-content text-secondary">' . $content . '</div>';
        }
        $href = $this->safeHref((string) ($widget['button_url'] ?? ''));
        if ($href !== '' && trim((string) ($widget['button_label'] ?? '')) !== '') {
            echo '<a class="btn btn-outline-light" href="' . $this->escape($href) . '">';
            echo $this->escape((string) $widget['button_label']) . '</a>';
        }
        echo '</article>';
    }

    /** @param array<string, mixed> $widget */
    private function renderHomepageTerminalWidget(array $widget, bool $authenticated): void
    {
        $key = (preg_replace('/[^a-z0-9_-]/', '-', strtolower((string) ($widget['key'] ?? 'terminal'))) ?: 'terminal')
            . '-' . max(0, (int) ($widget['id'] ?? 0));
        $inputId = 'widget-terminal-' . $key . '-command';
        $hintId = 'widget-terminal-' . $key . '-hint';
        echo '<div class="terminal" data-home-terminal data-authenticated="' . ($authenticated ? 'true' : 'false') . '"';
        echo ' aria-label="Interaktywny terminal strony"><div class="terminal-bar">';
        echo '<i class="terminal-dot" aria-hidden="true"></i><i class="terminal-dot" aria-hidden="true"></i>';
        echo '<i class="terminal-dot" aria-hidden="true"></i><span>';
        echo $this->escape(trim((string) ($widget['title'] ?? '')) ?: 'syntaxdevteam.pl/build') . '</span></div>';
        echo '<div class="terminal-screen" data-terminal-output role="log" aria-live="polite" aria-label="Wynik terminala">';
        echo '</div>';
        echo '<form class="terminal-command" data-terminal-form action="#" autocomplete="off">';
        echo '<label class="visually-hidden" for="' . $inputId . '">Komenda terminala</label>';
        echo '<span aria-hidden="true">visitor@syntax:~$</span>';
        echo '<input id="' . $inputId . '" data-terminal-input name="command" type="text"';
        echo ' inputmode="text" autocapitalize="none" spellcheck="false" maxlength="80" aria-describedby="' . $hintId . '">';
        echo '<button type="submit" class="visually-hidden">Wykonaj</button></form>';
        echo '<p id="' . $hintId . '" class="visually-hidden">Wpisz help i naciśnij Enter. Historia komend jest dostępna strzałkami.</p>';
        echo '<template data-terminal-boot>' . $this->escape((string) ($widget['content'] ?? '')) . '</template>';
        echo '<template data-terminal-welcome>' . $this->escape((string) ($widget['content'] ?? '')) . '</template></div>';
    }

    private function renderHomepageAcrostic(string $value): void
    {
        $words = array_slice(preg_split('/\s+/u', trim($value), -1, PREG_SPLIT_NO_EMPTY) ?: [], 0, 12);
        echo '<h1 class="hero-acrostic" aria-label="' . $this->escape(implode(' ', $words)) . '">';
        foreach ($words as $word) {
            if (preg_match('/^./us', $word, $match) !== 1) {
                continue;
            }
            $initial = $match[0];
            $remainder = substr($word, strlen($initial));
            echo '<span class="hero-acrostic-word" aria-hidden="true"><strong class="hero-acrostic-initial">';
            echo $this->escape($initial) . '</strong><span>' . $this->escape($remainder) . '</span></span>';
        }
        echo '</h1>';
    }

    /**
     * @param array{
     *     key: string,
     *     type: string,
     *     eyebrow: string,
     *     acrostic_words: string,
     *     title: string,
     *     content_html: string,
     *     content_format: string,
     *     layout: string,
     *     button_label: string,
     *     button_url: string,
     *     items: list<array{
     *         label: string,
     *         title: string,
     *         content: string,
     *         content_format: string,
     *         item_kind: string,
     *         icon_key: string,
     *         button_label: string,
     *         button_url: string,
     *         variant: string,
     *         width: string,
     *         page_slug: string
     *     }>
     * } $section
     */
    private function renderHomepageSection(array $section): void
    {
        $layouts = ['full', 'split', 'columns', 'accent', 'contact'];
        $layout = in_array($section['layout'], $layouts, true) ? $section['layout'] : 'full';
        $content = (new ContentRenderer())->render($section['content_html'], $section['content_format']);
        $href = $this->safeHref($section['button_url']);

        echo '<section id="' . $this->escape($section['key']) . '" class="home-section managed-home-section">';
        echo '<div class="container">';

        if ($layout === 'contact') {
            $this->renderContactLayout($section, $content);
            echo '</div></section>';
            return;
        }

        if ($section['type'] === 'cta') {
            echo '<div class="contact-panel reveal"><div>';
            if ($section['eyebrow'] !== '') {
                echo '<p class="eyebrow mb-2">' . $this->escape($section['eyebrow']) . '</p>';
            }
            echo '<h2 class="h1 fw-bold">' . $this->homepageHeading($section['title']) . '</h2>';
            echo '<div class="managed-home-content text-secondary mb-0">' . $content . '</div></div>';
            if ($section['button_label'] !== '' && $href !== '') {
                echo '<a class="btn btn-primary btn-lg" href="' . $this->escape($href) . '">';
                echo $this->escape($section['button_label']) . '</a>';
            }
            echo '</div></div></section>';
            return;
        }

        if ($layout === 'columns' && $section['items'] !== []) {
            echo '<div class="home-heading reveal">';
            if ($section['eyebrow'] !== '') {
                echo '<p class="eyebrow">' . $this->escape($section['eyebrow']) . '</p>';
            }
            echo '<h2 class="fw-bold">' . $this->homepageHeading($section['title']) . '</h2>';
            if ($content !== '') {
                echo '<div class="managed-home-content mt-3">' . $content . '</div>';
            }
            echo '</div><div class="managed-card-grid">';
            foreach ($section['items'] as $index => $item) {
                $variant = in_array($item['variant'], ['primary', 'violet', 'neutral'], true)
                    ? $item['variant']
                    : 'neutral';
                $width = $item['width'] === 'wide' ? 'wide' : 'standard';
                $itemHref = $item['page_slug'] !== ''
                    ? '/p/' . rawurlencode($item['page_slug'])
                    : $this->safeHref($item['button_url']);
                echo '<article class="showcase-card managed-card managed-card-' . $width . ' reveal" ';
                echo 'data-variant="' . $variant . '" data-number="';
                echo str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) . '">';
                if ($item['label'] !== '') {
                    echo '<p class="managed-card-label">' . $this->escape($item['label']) . '</p>';
                }
                echo '<h3>' . $this->escape($item['title']) . '</h3>';
                $itemContent = (new ContentRenderer())->render($item['content'], $item['content_format']);
                if ($itemContent !== '' && !str_contains($itemContent, '<')) {
                    $itemContent = '<p>' . nl2br($this->escape($itemContent)) . '</p>';
                }
                echo '<div class="text-secondary rich-content">' . $itemContent . '</div>';
                if ($itemHref !== '') {
                    echo '<a class="btn btn-outline-light" href="' . $this->escape($itemHref) . '">';
                    echo $this->escape($item['button_label'] !== '' ? $item['button_label'] : 'Czytaj więcej') . '</a>';
                }
                echo '</article>';
            }
            echo '</div></div></section>';
            return;
        }

        echo '<div class="managed-home-layout managed-home-layout-' . $layout . ' reveal">';
        echo '<div class="home-heading mb-0">';
        if ($section['eyebrow'] !== '') {
            echo '<p class="eyebrow">' . $this->escape($section['eyebrow']) . '</p>';
        }
        echo '<h2 class="fw-bold">' . $this->homepageHeading($section['title']) . '</h2></div>';
        echo '<div class="managed-home-content">' . $content;
        if ($section['button_label'] !== '' && $href !== '') {
            echo '<p class="mt-4 mb-0"><a class="btn btn-outline-light" href="' . $this->escape($href) . '">';
            echo $this->escape($section['button_label']) . '</a></p>';
        }
        echo '</div></div></div></section>';
    }

    /**
     * @param array{
     *     eyebrow: string,
     *     title: string,
     *     items: list<array{
     *         label: string,
     *         title: string,
     *         content: string,
     *         content_format: string,
     *         item_kind: string,
     *         icon_key: string,
     *         button_label: string,
     *         button_url: string,
     *         page_slug: string
     *     }>
     * } $section
     */
    private function renderContactLayout(array $section, string $content): void
    {
        $channels = array_values(array_filter(
            $section['items'],
            static fn (array $item): bool => $item['item_kind'] !== 'person'
        ));
        $people = array_values(array_filter(
            $section['items'],
            static fn (array $item): bool => $item['item_kind'] === 'person'
        ));

        echo '<div class="contact-hub reveal"><div class="contact-hub-glow" aria-hidden="true"></div>';
        echo '<header class="contact-hub-heading">';
        if ($section['eyebrow'] !== '') {
            echo '<p class="contact-kicker">' . $this->escape($section['eyebrow']) . '</p>';
        }
        echo '<h2>' . $this->homepageHeading($section['title']) . '</h2>';
        if ($content !== '') {
            echo '<div class="contact-intro managed-home-content">' . $content . '</div>';
        }
        echo '</header><div class="contact-hub-grid">';
        echo '<section class="contact-group contact-group-channels"><div class="contact-group-heading">';
        echo '<p class="eyebrow">Kanały</p><h3>Wybierz najlepszą drogę</h3></div><div class="contact-list">';
        foreach ($channels as $item) {
            $this->renderContactItem($item, false);
        }
        echo '</div></section>';
        echo '<section class="contact-group contact-group-people"><div class="contact-group-heading">';
        echo '<p class="eyebrow">Zespół</p><h3>Bezpośredni kontakt</h3></div><div class="contact-list">';
        foreach ($people as $item) {
            $this->renderContactItem($item, true);
        }
        if ($people === []) {
            echo '<p class="contact-empty">Dodaj element typu „Osoba”, aby zbudować listę zespołu.</p>';
        }
        echo '</div></section></div></div>';
    }

    /**
     * @param array{
     *     label: string,
     *     title: string,
     *     content: string,
     *     content_format: string,
     *     icon_key: string,
     *     button_label: string,
     *     button_url: string,
     *     page_slug: string
     * } $item
     */
    private function renderContactItem(array $item, bool $person): void
    {
        $href = $item['page_slug'] !== ''
            ? '/p/' . rawurlencode($item['page_slug'])
            : $this->safeHref($item['button_url']);
        $description = (new ContentRenderer())->render($item['content'], $item['content_format']);
        $icon = $item['icon_key'] !== '' ? $item['icon_key'] : ($person ? 'person' : 'web');
        if (!$person && in_array($icon, ['', 'web'], true)) {
            $fingerprint = strtolower($item['label'] . ' ' . $item['title'] . ' ' . $item['button_url'] . ' ' . $item['page_slug']);
            if (str_contains($fingerprint, 'modrinth')) {
                $icon = 'modrinth';
            }
        }

        echo '<article class="contact-item' . ($person ? ' contact-item-person' : '') . '">';
        echo '<span class="contact-icon contact-icon-' . $this->escape($icon) . '" aria-hidden="true">';
        echo $this->contactIcon($icon) . '</span><div class="contact-item-copy">';
        if ($item['label'] !== '') {
            echo '<p class="contact-item-label">' . $this->escape($item['label']) . '</p>';
        }
        echo '<h4>' . $this->escape($item['title']) . '</h4>';
        if ($description !== '') {
            echo '<div class="contact-item-description">' . $description . '</div>';
        }
        echo '</div>';
        if ($href !== '') {
            echo '<a class="contact-item-action" href="' . $this->escape($href) . '">';
            echo $this->escape($item['button_label'] !== '' ? $item['button_label'] : 'Otwórz');
            echo '<span aria-hidden="true">↗</span></a>';
        }
        echo '</article>';
    }

    private function contactIcon(string $icon): string
    {
        return match ($icon) {
            'discord' => '<svg viewBox="0 0 24 24"><path d="M7.2 6.3A15 15 0 0 1 12 5.5a15 15 0 0 1 4.8.8c1.5 2.1 2.2 4.5 2 7a10 10 0 0 1-3 2.3l-.8-1.1c.7-.3 1.3-.7 1.9-1.2-3.1 1.5-6.7 1.5-9.8 0 .6.5 1.2.9 1.9 1.2l-.8 1.1a10 10 0 0 1-3-2.3c-.2-2.5.5-4.9 2-7Zm2.2 6.1c.7 0 1.2-.7 1.2-1.5s-.5-1.5-1.2-1.5-1.2.7-1.2 1.5.5 1.5 1.2 1.5Zm5.2 0c.7 0 1.2-.7 1.2-1.5s-.5-1.5-1.2-1.5-1.2.7-1.2 1.5.5 1.5 1.2 1.5Z"/></svg>',
            'github' => '<svg viewBox="0 0 24 24"><path d="M12 2a10 10 0 0 0-3.2 19.5v-2.2c-2.7.6-3.3-1.1-3.3-1.1-.4-1.1-1.1-1.4-1.1-1.4-.9-.6.1-.6.1-.6 1 0 1.5 1 1.5 1 .9 1.5 2.3 1.1 2.8.8.1-.6.4-1.1.7-1.3-2.1-.2-4.4-1.1-4.4-4.9 0-1.1.4-2 1-2.7-.1-.2-.4-1.3.1-2.7 0 0 .9-.3 2.8 1a9.7 9.7 0 0 1 5.1 0c2-1.3 2.8-1 2.8-1 .6 1.4.2 2.5.1 2.7.7.7 1.1 1.6 1.1 2.7 0 3.8-2.3 4.7-4.5 4.9.4.3.7.9.7 1.8v3A10 10 0 0 0 12 2Z"/></svg>',
            'mail' => '<svg viewBox="0 0 24 24"><path d="M3 5h18v14H3V5Zm9 7 7-5H5l7 5Zm0 2.4L5 9.5V17h14V9.5l-7 4.9Z"/></svg>',
            'hangar' => '<svg viewBox="0 0 24 24"><path d="M4 19V9l8-5 8 5v10h-3v-8.2L12 7.7l-5 3.1V19H4Zm5 0v-7h6v7H9Z"/></svg>',
            'modrinth' => '<svg viewBox="0 0 24 24"><path d="M12.252.004a11.78 11.768 0 0 0-8.92 3.73 11 10.999 0 0 0-2.17 3.11 11.37 11.359 0 0 0-1.16 5.169c0 1.42.17 2.5.6 3.77.24.759.77 1.899 1.17 2.529a12.3 12.298 0 0 0 8.85 5.639c.44.05 2.54.07 2.76.02.2-.04.22.1-.26-1.7l-.36-1.37-1.01-.06a8.5 8.489 0 0 1-5.18-1.8 5.34 5.34 0 0 1-1.3-1.26c0-.05.34-.28.74-.5a37.572 37.545 0 0 1 2.88-1.629c.03 0 .5.45 1.06.98l1 .97 2.07-.43 2.06-.43 1.47-1.47c.8-.8 1.48-1.5 1.48-1.52 0-.09-.42-1.63-.46-1.7-.04-.06-.2-.03-1.02.18-.53.13-1.2.3-1.45.4l-.48.15-.53.53-.53.53-.93.1-.93.07-.52-.5a2.7 2.7 0 0 1-.96-1.7l-.13-.6.43-.57c.68-.9.68-.9 1.46-1.1.4-.1.65-.2.83-.33.13-.099.65-.579 1.14-1.069l.9-.9-.7-.7-.7-.7-1.95.54c-1.07.3-1.96.53-1.97.53-.03 0-2.23 2.48-2.63 2.97l-.29.35.28 1.03c.16.56.3 1.16.31 1.34l.03.3-.34.23c-.37.23-2.22 1.3-2.84 1.63-.36.2-.37.2-.44.1-.08-.1-.23-.6-.32-1.03-.18-.86-.17-2.75.02-3.73a8.84 8.839 0 0 1 7.9-6.93c.43-.03.77-.08.78-.1.06-.17.5-2.999.47-3.039-.01-.02-.1-.02-.2-.03Zm3.68.67c-.2 0-.3.1-.37.38-.06.23-.46 2.42-.46 2.52 0 .04.1.11.22.16a8.51 8.499 0 0 1 2.99 2 8.38 8.379 0 0 1 2.16 3.449 6.9 6.9 0 0 1 .4 2.8c0 1.07 0 1.27-.1 1.73a9.37 9.369 0 0 1-1.76 3.769c-.32.4-.98 1.06-1.37 1.38-.38.32-1.54 1.1-1.7 1.14-.1.03-.1.06-.07.26.03.18.64 2.56.7 2.78l.06.06a12.07 12.058 0 0 0 7.27-9.4c.13-.77.13-2.58 0-3.4a11.96 11.948 0 0 0-5.73-8.578c-.7-.42-2.05-1.06-2.25-1.06Z"/></svg>',
            'person' => '<svg viewBox="0 0 24 24"><path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm-7 8a7 7 0 0 1 14 0H5Z"/></svg>',
            default => '<svg viewBox="0 0 24 24"><path d="M4 4h16v16H4V4Zm3 4v2h10V8H7Zm0 5v2h7v-2H7Z"/></svg>',
        };
    }

    /**
     * @param array{eyebrow: string, title: string} $section
     */
    private function navigationLabel(array $section): string
    {
        $label = preg_replace('/^\s*\d+\s*\/\s*/', '', $section['eyebrow']) ?? '';

        return $label !== '' ? $label : (preg_replace('/\s+/u', ' ', trim($section['title'])) ?? $section['title']);
    }

    private function homepageHeading(string $title): string
    {
        $lines = array_values(array_filter(array_map(
            static fn (string $line): string => trim($line),
            preg_split('/\R/u', trim($title)) ?: []
        ), static fn (string $line): bool => $line !== ''));

        return implode('<br>', array_map($this->escape(...), array_slice($lines, 0, 4)));
    }

    private function navigationHref(array $item): string
    {
        $href = trim((string) ($item['href'] ?? ''));
        if ($href !== '' && preg_match('~^(?:/|index\.php(?:\?|$)|https?://|#)~i', $href) === 1) {
            return $href;
        }

        return '/p/' . rawurlencode((string) ($item['slug'] ?? ''));
    }

    private function errorEyebrow(int $status): string
    {
        return match ($status) {
            401 => 'Wymagane logowanie',
            403 => 'Brak dostępu',
            404 => 'Nie znaleziono',
            405 => 'Niedozwolona metoda',
            default => 'Problem z żądaniem',
        };
    }

    private function errorSummary(int $status): string
    {
        return match ($status) {
            401 => 'Ta część serwisu wymaga zalogowania.',
            403 => 'Twoje konto nie ma dostępu do tego widoku.',
            404 => 'Ten adres nie prowadzi do aktywnej strony.',
            405 => 'Ten adres istnieje, ale oczekuje innego typu żądania.',
            default => 'Nie udało się poprawnie obsłużyć żądania.',
        };
    }

    private function safeHref(string $href): string
    {
        $href = trim($href);

        return preg_match('~^(?:https?://|mailto:|#|/(?!/)|index\.php(?:\?|$))~i', $href) === 1 ? $href : '';
    }

    private function renderAdminMenu(array $menuItems, string $activePath, bool $mobile = false): void
    {
        echo '<nav class="admin-nav">';
        $currentSection = null;

        foreach ($menuItems as $item) {
            if ($item['section'] !== $currentSection) {
                $currentSection = $item['section'];
                echo '<span class="admin-nav-label">' . $this->escape($currentSection) . '</span>';
            }

            $active = $item['path'] === $activePath;
            echo '<a class="admin-nav-link' . ($active ? ' active' : '') . '" href="index.php?route=';
            echo rawurlencode($item['path']) . '"';
            if ($active) {
                echo ' aria-current="page"';
            }
            if ($mobile) {
                echo ' data-admin-mobile-nav-link';
            }
            echo '>';
            echo '<span class="admin-nav-icon" aria-hidden="true">' . $this->escape($item['icon']) . '</span>';
            echo $this->escape($item['label']) . '</a>';
        }

        echo '</nav>';
    }

    /**
     * @param array{profile_links?: list<array{label: string, href: string}>} $user
     * @return list<array{label: string, href: string}>
     */
    private function adminProfileLinks(array $user): array
    {
        $links = $user['profile_links'] ?? [
            ['label' => 'Pokaż profil', 'href' => 'index.php?route=/admin/profile'],
            ['label' => 'Edytuj dane', 'href' => 'index.php?route=/admin/profile/edit'],
            ['label' => 'Połączone konta', 'href' => 'index.php?route=/admin/profile/identities'],
            ['label' => 'Ustawienia avatara', 'href' => 'index.php?route=/admin/profile/avatar'],
            ['label' => 'Bezpieczeństwo', 'href' => 'index.php?route=/admin/profile/security'],
        ];

        return array_values(array_filter(
            $links,
            fn (array $link): bool => ($link['label'] ?? '') !== '' && $this->safeHref((string) ($link['href'] ?? '')) !== ''
        ));
    }

    /**
     * @param array{initials: string, avatar_url?: string} $user
     */
    private function renderAdminAvatar(array $user): void
    {
        $avatarUrl = isset($user['avatar_url']) ? $this->safeHref((string) $user['avatar_url']) : '';
        echo '<span class="admin-avatar" aria-hidden="true">';
        if ($avatarUrl !== '' && preg_match('~^https?://~i', $avatarUrl) === 1) {
            echo '<img src="' . $this->escape($avatarUrl) . '" alt="" loading="lazy">';
        } else {
            echo $this->escape($user['initials']);
        }
        echo '</span>';
    }

    private function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $initials = '';
        foreach (array_slice($parts, 0, 2) as $part) {
            $initials .= function_exists('mb_substr')
                ? mb_strtoupper(mb_substr($part, 0, 1))
                : strtoupper(substr($part, 0, 1));
        }

        return $initials !== '' ? $initials : 'SD';
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function brandLogo(string $class, string $filename = 'syntaxdevteam-logo.png'): string
    {
        return '<img class="' . $class . '" src="' . $this->asset('img/brand/' . $filename)
            . '" width="512" height="512" alt="" aria-hidden="true">';
    }

    private function renderBrandHead(string $title, string $description, bool $indexable = true): void
    {
        echo '<meta name="color-scheme" content="dark">';
        echo '<meta name="theme-color" content="' . $this->escape($this->publicThemeColor) . '">';
        echo '<meta name="application-name" content="' . $this->escape($this->publicName) . '">';
        echo '<meta name="mobile-web-app-capable" content="yes">';
        echo '<meta name="apple-mobile-web-app-capable" content="yes">';
        echo '<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">';
        echo '<meta name="apple-mobile-web-app-title" content="' . $this->escape($this->publicName) . '">';
        echo '<link rel="icon" href="' . $this->faviconAsset('favicon.ico') . '" sizes="16x16 32x32 48x48 64x64 128x128 256x256">';
        echo '<link rel="icon" type="image/png" sizes="256x256" href="' . $this->faviconAsset('favicon-256x256.png') . '">';
        echo '<link rel="icon" type="image/png" sizes="96x96" href="' . $this->faviconAsset('favicon-96x96.png') . '">';
        echo '<link rel="icon" type="image/png" sizes="48x48" href="' . $this->faviconAsset('favicon-48x48.png') . '">';
        echo '<link rel="icon" type="image/png" sizes="32x32" href="' . $this->faviconAsset('favicon-32x32.png') . '">';
        echo '<link rel="icon" type="image/png" sizes="16x16" href="' . $this->faviconAsset('favicon-16x16.png') . '">';
        echo '<link rel="apple-touch-icon" sizes="180x180" href="' . $this->faviconAsset('apple-touch-icon.png') . '">';
        echo '<link rel="manifest" href="' . $this->faviconAsset('site.webmanifest') . '">';
        if (!$indexable) {
            echo '<meta name="robots" content="noindex, nofollow">';
            return;
        }

        echo '<meta name="robots" content="' . $this->escape($this->publicMetaRobots) . '">';
        if ($this->publicMetaAuthor !== '') {
            echo '<meta name="author" content="' . $this->escape($this->publicMetaAuthor) . '">';
        }
        if ($this->publicGoogleSiteVerification !== '') {
            echo '<meta name="google-site-verification" content="';
            echo $this->escape($this->publicGoogleSiteVerification) . '">';
        }
        if ($this->publicBingSiteVerification !== '') {
            echo '<meta name="msvalidate.01" content="' . $this->escape($this->publicBingSiteVerification) . '">';
        }

        $canonicalUrl = $this->publicUrl !== '' ? $this->publicUrl . ($this->publicPath === '/' ? '/' : $this->publicPath) : '';
        if ($canonicalUrl !== '') {
            echo '<link rel="canonical" href="' . $this->escape($canonicalUrl) . '">';
        }
        $logoPath = (string) parse_url($this->asset('img/brand/syntaxdevteam-logo.png'), PHP_URL_PATH);
        $logoUrl = $this->absolutePublicUrl($logoPath);
        $socialImageUrl = $this->publicSocialImageUrl !== ''
            ? $this->absolutePublicUrl($this->publicSocialImageUrl)
            : $logoUrl;
        echo '<meta property="og:type" content="website">';
        echo '<meta property="og:locale" content="' . $this->escape($this->publicLocale) . '">';
        echo '<meta property="og:site_name" content="' . $this->escape($this->publicName) . '">';
        echo '<meta property="og:title" content="' . $this->escape($title) . '">';
        echo '<meta property="og:description" content="' . $this->escape($description) . '">';
        if ($canonicalUrl !== '') {
            echo '<meta property="og:url" content="' . $this->escape($canonicalUrl) . '">';
        }
        echo '<meta property="og:image" content="' . $this->escape($socialImageUrl) . '">';
        if ($this->publicSocialImageAlt !== '') {
            echo '<meta property="og:image:alt" content="' . $this->escape($this->publicSocialImageAlt) . '">';
        }
        if ($this->publicSocialImageUrl === '') {
            echo '<meta property="og:image:type" content="image/png">';
            echo '<meta property="og:image:width" content="512"><meta property="og:image:height" content="512">';
        }
        echo '<meta name="twitter:card" content="';
        echo $this->publicSocialImageUrl !== '' ? 'summary_large_image' : 'summary';
        echo '"><meta name="twitter:title" content="' . $this->escape($title) . '">';
        echo '<meta name="twitter:description" content="' . $this->escape($description) . '">';
        echo '<meta name="twitter:image" content="' . $this->escape($socialImageUrl) . '">';
        if ($this->publicSocialImageAlt !== '') {
            echo '<meta name="twitter:image:alt" content="' . $this->escape($this->publicSocialImageAlt) . '">';
        }
        if ($this->publicTwitterSite !== '') {
            echo '<meta name="twitter:site" content="@' . $this->escape($this->publicTwitterSite) . '">';
        }

        if ($this->publicUrl !== '') {
            $structuredData = json_encode([
                '@context' => 'https://schema.org',
                '@graph' => [
                    [
                        '@type' => 'Organization',
                        '@id' => $this->publicUrl . '/#organization',
                        'name' => $this->publicName,
                        'url' => $this->publicUrl . '/',
                        'logo' => [
                            '@type' => 'ImageObject',
                            'url' => $logoUrl,
                            'width' => 512,
                            'height' => 512,
                        ],
                    ],
                    [
                        '@type' => 'WebSite',
                        '@id' => $this->publicUrl . '/#website',
                        'url' => $this->publicUrl . '/',
                        'name' => $this->publicName,
                        'inLanguage' => $this->publicLanguage,
                        'publisher' => ['@id' => $this->publicUrl . '/#organization'],
                    ],
                ],
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
            echo '<script type="application/ld+json">' . $structuredData . '</script>';
        }
    }

    private function faviconAsset(string $name): string
    {
        $url = $this->publicFaviconPath !== ''
            ? $this->publicFaviconPath . '/' . $name
            : ($name === 'site.webmanifest' ? $this->asset($name) : $this->asset('img/brand/' . $name));

        return $this->publicFaviconVersion !== ''
            ? $url . (str_contains($url, '?') ? '&amp;' : '?') . 'v=' . $this->publicFaviconVersion
            : $url;
    }

    private function isSafePublicAssetUrl(string $url): bool
    {
        if ($url === '') {
            return true;
        }
        if (str_starts_with($url, '/') && !str_starts_with($url, '//')) {
            return !str_contains($url, "\0") && !str_contains($url, '\\');
        }

        return filter_var($url, FILTER_VALIDATE_URL) !== false && str_starts_with($url, 'https://');
    }

    private function absolutePublicUrl(string $url): string
    {
        if (str_starts_with($url, 'https://') || $this->publicUrl === '') {
            return $url;
        }

        return $this->publicUrl . '/' . ltrim($url, '/');
    }

    private function verificationToken(string $token): string
    {
        $token = trim($token);

        return strlen($token) <= 255 && preg_match('/^[A-Za-z0-9._=+-]*$/', $token) === 1 ? $token : '';
    }

    private function isCurrentPublicHref(string $href): bool
    {
        $path = parse_url($href, PHP_URL_PATH);

        return is_string($path) && ($path === '/' ? '/' : rtrim($path, '/')) === $this->publicPath;
    }

    private function asset(string $relativePath): string
    {
        $relativePath = ltrim($relativePath, '/');
        $file = __DIR__ . '/assets/' . $relativePath;
        $version = is_file($file) ? (string) filemtime($file) : '1';

        return '/templates/future/assets/' . $relativePath . '?v=' . rawurlencode($version);
    }

    private function buttonVariant(string $variant): string
    {
        $allowed = [
            'primary',
            'secondary',
            'success',
            'danger',
            'warning',
            'info',
            'light',
            'dark',
            'outline-primary',
            'outline-secondary',
            'outline-success',
            'outline-danger',
            'outline-warning',
            'outline-info',
            'outline-light',
            'outline-dark',
        ];

        return in_array($variant, $allowed, true) ? $variant : 'outline-light';
    }
}
