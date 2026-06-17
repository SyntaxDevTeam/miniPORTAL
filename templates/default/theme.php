<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Templates\DefaultTheme;

use SyntaxDevTeam\Cms\Core\ContentRenderer;
use SyntaxDevTeam\Cms\Core\RichTextSanitizer;
use SyntaxDevTeam\Cms\Core\ThemeInterface;

final class Theme implements ThemeInterface
{
    private readonly string $publicName;

    private readonly string $publicEyebrow;

    private readonly string $publicMetaDescription;

    private readonly string $publicMetaKeywords;

    private readonly string $publicFooterText;

    private array $publicNavigation = [];

    private bool $publicAuthenticated = false;

    public function __construct(array $config = [])
    {
        $this->publicName = trim((string) ($config['public_name'] ?? 'SyntaxDevTeam')) ?: 'SyntaxDevTeam';
        $this->publicEyebrow = trim((string) ($config['public_eyebrow'] ?? 'Software dla społeczności'));
        $this->publicMetaDescription = trim((string) (
            $config['public_meta_description']
            ?? 'SyntaxDevTeam tworzy pluginy Minecraft, boty Discord, aplikacje Android i narzędzia backendowe.'
        ));
        $this->publicMetaKeywords = trim((string) ($config['public_meta_keywords'] ?? ''));
        $this->publicFooterText = trim((string) (
            $config['public_footer_text']
            ?? 'Projektowane modułowo. Rozwijane świadomie.'
        )) ?: 'Projektowane modułowo. Rozwijane świadomie.';
    }

    public function set_public_navigation(array $items, bool $authenticated): void
    {
        $this->publicNavigation = $items;
        $this->publicAuthenticated = $authenticated;
    }

    public function render_homepage(array $sections, array $pages, bool $authenticated): void
    {
        echo '<!doctype html><html lang="pl" data-bs-theme="dark"><head>';
        echo '<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
        echo '<meta name="description" content="' . $this->escape($this->publicMetaDescription) . '">';
        if ($this->publicMetaKeywords !== '') {
            echo '<meta name="keywords" content="' . $this->escape($this->publicMetaKeywords) . '">';
        }
        echo '<meta name="theme-color" content="#080c12"><title>' . $this->escape($this->publicName);
        echo ' - ' . $this->escape($this->publicEyebrow) . '</title>';
        echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" ';
        echo 'integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">';
        echo '<link rel="stylesheet" href="' . $this->asset('css/stylebook.css') . '">';
        echo '<link rel="stylesheet" href="' . $this->asset('css/homepage.css') . '"></head><body>';
        echo '<div class="site-grid" aria-hidden="true"></div><a class="visually-hidden-focusable skip-link" href="#content">Przejdź do treści</a>';
        $this->renderPublicNavbar($pages, $authenticated, $sections, true);
        echo '<main id="content">';
        $heroRendered = false;
        foreach ($sections as $section) {
            if ($section['type'] === 'hero' && !$heroRendered) {
                $this->renderHomepageHero($section, $authenticated);
                $heroRendered = true;
                continue;
            }

            $this->renderHomepageSection($section);
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
        echo '<a class="navbar-brand fw-bold" href="/"><span aria-hidden="true">&lt;/&gt;</span> ';
        echo $this->escape($this->publicName) . '</a>';
        echo '<button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Przełącz nawigację"><span class="navbar-toggler-icon"></span></button>';
        echo '<div class="collapse navbar-collapse" id="mainNav"><ul class="navbar-nav ms-auto align-items-lg-center">';
        if (!$fixed) {
            echo '<li class="nav-item"><a class="nav-link" href="/">Home</a></li>';
        }
        $hasContactLink = false;
        foreach ($sections as $section) {
            if ($section['type'] === 'hero') {
                continue;
            }
            $hasContactLink = $hasContactLink
                || (string) ($section['key'] ?? '') === 'contact'
                || (string) ($section['layout'] ?? '') === 'contact';
            echo '<li class="nav-item"><a class="nav-link" href="#' . $this->escape($section['key']) . '">';
            echo $this->escape($this->navigationLabel($section)) . '</a></li>';
        }
        foreach ($pages as $page) {
            if ($page['navigation_area'] !== 'main') {
                continue;
            }
            echo '<li class="nav-item"><a class="nav-link" href="';
            echo $this->escape($this->navigationHref($page)) . '">';
            echo $this->escape($page['navigation_label'] !== '' ? $page['navigation_label'] : $page['title']);
            echo '</a></li>';
        }
        if ($pages !== [] && array_filter($pages, static fn (array $page): bool => $page['navigation_area'] === 'main') === []) {
            if ($authenticated) {
                echo '<li class="nav-item"><a class="nav-link" href="index.php?route=/pages">Podstrony</a></li>';
            }
        }
        if (!$hasContactLink) {
            echo '<li class="nav-item"><a class="nav-link" href="/#contact">Kontakt</a></li>';
        }
        echo '<li class="nav-item ms-lg-2"><a class="btn btn-sm btn-outline-light" href="';
        echo $authenticated ? '/admin' : '/admin/login';
        echo '">' . ($authenticated ? 'Otwórz panel' : 'Zaloguj się') . '</a></li></ul></div></div></nav>';
    }

    private function renderPublicFooter(array $pages): void
    {
        echo '<footer class="border-top py-4"><div class="container d-flex flex-column flex-md-row justify-content-between gap-2 text-secondary small">';
        echo '<span>&copy; 2026 ' . $this->escape($this->publicName) . '</span><span class="d-flex flex-wrap gap-3">';
        foreach ($pages as $page) {
            if ($page['navigation_area'] !== 'footer') {
                continue;
            }
            echo '<a class="text-secondary" href="' . $this->escape($this->navigationHref($page)) . '">';
            echo $this->escape($page['navigation_label'] !== '' ? $page['navigation_label'] : $page['title']);
            echo '</a>';
        }
        echo '<span>' . $this->escape($this->publicFooterText) . '</span></span></div></footer>';
    }

    public function start_page(string $title, string $description = ''): void
    {
        $title = $this->escape($title);
        $description = $this->escape($description !== '' ? $description : $this->publicMetaDescription);

        echo '<!doctype html><html lang="pl" data-bs-theme="dark"><head>';
        echo '<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
        echo '<meta name="description" content="' . $description . '">';
        if ($this->publicMetaKeywords !== '') {
            echo '<meta name="keywords" content="' . $this->escape($this->publicMetaKeywords) . '">';
        }
        echo '<title>' . $title . '</title>';
        echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" ';
        echo 'integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">';
        echo '<link rel="stylesheet" href="' . $this->asset('css/stylebook.css') . '">';
        echo '</head><body>';
        $this->renderPublicNavbar($this->publicNavigation, $this->publicAuthenticated);
        echo '<main>';
    }

    public function end_page(): void
    {
        echo '</main>';
        $this->renderPublicFooter($this->publicNavigation);
        echo '</body></html>';
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
        $allowedSizes = ['12', 'lg-5', 'lg-6', 'lg-7', 'md-6', 'md-8'];
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

    public function render_form(
        string $action,
        array $fields,
        string $submitLabel,
        string $csrfToken = '',
        string $method = 'post',
    ): void {
        $method = strtolower($method) === 'get' ? 'get' : 'post';
        $hasFile = array_any($fields, static fn (array $field): bool => ($field['type'] ?? '') === 'file');
        echo '<form class="showcase-card" action="' . $this->escape($action) . '" method="' . $method . '"';
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
                echo '<textarea class="form-control" id="' . $name . '" name="' . $name . '" rows="' . $rows . '">';
                echo $value . '</textarea>';
            } elseif ($type === 'select') {
                echo '<select class="form-select" id="' . $name . '" name="' . $name . '">';
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
                echo '<input class="form-control" id="' . $name . '" name="' . $name . '" type="file"' . $accept . '>';
            } else {
                $allowedTypes = ['text', 'email', 'password', 'number', 'url', 'date'];
                $type = in_array($type, $allowedTypes, true) ? $type : 'text';
                echo '<input class="form-control" id="' . $name . '" name="' . $name . '" type="' . $type . '" value="' . $value . '">';
            }

            if (($field['help'] ?? '') !== '') {
                echo '<div class="form-text">' . $this->escape((string) $field['help']) . '</div>';
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
        echo '<span class="admin-brand-mark" aria-hidden="true">&lt;/&gt;</span><span>miniPORTAL</span></a></div>';
        $this->renderAdminMenu($menuItems, $activePath);
        echo '<div class="admin-sidebar-footer"><div class="admin-user">';
        echo '<span class="admin-avatar" aria-hidden="true">' . $this->escape($user['initials']) . '</span>';
        echo '<span class="admin-user-copy"><strong>' . $this->escape($user['name']) . '</strong>';
        echo '<span>' . $this->escape($user['role']) . '</span></span></div></div></aside>';
        echo '<div class="admin-workspace"><header class="admin-topbar">';
        echo '<button class="admin-icon-button d-lg-none" type="button" data-admin-sidebar-toggle aria-label="Otwórz nawigację panelu">MN</button>';
        echo '<div class="admin-search"><span class="admin-search-mark" aria-hidden="true">SZ</span>';
        echo '<label class="visually-hidden" for="admin-global-search">Szukaj w panelu</label>';
        echo '<input class="form-control" id="admin-global-search" type="search" placeholder="Szukaj w panelu..."></div>';
        echo '<div class="ms-auto d-flex align-items-center gap-2">';
        echo '<a class="admin-icon-button text-decoration-none" href="index.php" aria-label="Wróć do strony głównej">HM</a>';
        echo '<div class="dropdown admin-user-menu">';
        echo '<button class="admin-user-menu-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">';
        echo '<span class="admin-avatar" aria-hidden="true">' . $this->escape($user['initials']) . '</span>';
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
        echo '</div>';
        if ($action !== null) {
            echo '<a class="btn btn-primary" href="' . $this->escape($action['href']) . '">';
            echo $this->escape($action['label']) . '</a>';
        }
        echo '</div>';
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
            echo '<td class="text-end">';

            foreach ($row['actions'] as $action) {
                $variant = $this->buttonVariant($action['variant'] ?? 'outline-light');
                $label = $this->escape($action['label']);

                if (isset($action['href'])) {
                    echo '<a class="btn btn-sm btn-' . $variant . ' me-1" href="';
                    echo $this->escape($action['href']) . '">' . $label . '</a>';
                    continue;
                }

                if (!isset($action['action'])) {
                    continue;
                }

                echo '<form class="d-inline" action="' . $this->escape($action['action']) . '" method="post"';
                if (($action['confirm'] ?? '') !== '') {
                    echo ' data-confirm="' . $this->escape((string) $action['confirm']) . '"';
                }
                echo '>';
                $this->csrf_field($csrfToken);
                foreach ($action['fields'] ?? [] as $name => $value) {
                    echo '<input type="hidden" name="' . $this->escape((string) $name) . '" value="';
                    echo $this->escape((string) $value) . '">';
                }
                echo '<button class="btn btn-sm btn-' . $variant . ' me-1" type="submit">' . $label . '</button></form>';
            }

            echo '</td></tr>';
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
        echo '<title>Logowanie - miniPORTAL Admin</title>';
        echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" ';
        echo 'integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">';
        echo '<link rel="stylesheet" href="' . $this->asset('css/stylebook.css') . '">';
        echo '<link rel="stylesheet" href="' . $this->asset('css/admin.css') . '">';
        echo '</head><body class="admin-stylebook"><div class="site-grid" aria-hidden="true"></div>';
        echo '<main class="min-vh-100 d-grid align-items-center py-4"><div class="container">';
        echo '<div class="login-stage border-0 bg-transparent shadow-none"><section class="login-panel">';
        echo '<a class="admin-brand text-decoration-none" href="index.php">';
        echo '<span class="admin-brand-mark" aria-hidden="true">&lt;/&gt;</span><span>miniPORTAL Admin</span></a>';
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
        echo '</div><div class="security-note mt-4"><span aria-hidden="true">[SEC]</span>';
        echo '<span>Przepływy zewnętrzne używają state i PKCE, a sesja jest rotowana po zalogowaniu.</span></div>';
        echo '</section></div></div></main></body></html>';
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
        echo '<title>Połączone konta - miniPORTAL Admin</title>';
        echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">';
        echo '<link rel="stylesheet" href="' . $this->asset('css/stylebook.css') . '">';
        echo '<link rel="stylesheet" href="' . $this->asset('css/admin.css') . '">';
        echo '</head><body class="admin-stylebook"><main class="container py-5">';
        echo '<a class="admin-brand text-decoration-none mb-4" href="index.php?route=/admin">';
        echo '<span class="admin-brand-mark" aria-hidden="true">&lt;/&gt;</span><span>miniPORTAL Admin</span></a>';
        echo '<section class="admin-panel mt-4"><div class="admin-panel-header">';
        echo '<div><p class="showcase-label mb-1">Profil</p><h1 class="h3 mb-1">Połączone tożsamości</h1>';
        echo '<p class="text-secondary mb-0">' . $this->escape($user['name']) . ' · ' . $this->escape($user['role']) . '</p></div>';
        echo '<a class="btn btn-outline-light" href="index.php?route=/admin">Wróć do panelu</a></div>';

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
        $this->start_page($status . ' - ' . $this->publicName, $message);
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

        echo '<header id="' . $this->escape($section['key']) . '" class="home-hero"><div class="container py-5">';
        echo '<div class="row align-items-center g-5"><div class="col-lg-7 reveal is-visible">';
        if ($section['eyebrow'] !== '') {
            echo '<p class="eyebrow">' . $this->escape($section['eyebrow']) . '</p>';
        }
        echo '<h1 class="home-title fw-bold">' . $this->escape($section['title']) . '</h1>';
        echo '<div class="home-lead managed-home-content mt-4">' . $content . '</div>';
        echo '<div class="hero-actions mt-4">';
        if ($section['button_label'] !== '' && $this->safeHref($section['button_url']) !== '') {
            echo '<a class="btn btn-primary btn-lg" href="' . $this->escape($this->safeHref($section['button_url'])) . '">';
            echo $this->escape($section['button_label']) . '</a>';
        }
        echo '<a class="btn btn-outline-light btn-lg" href="index.php?route=' . ($authenticated ? '/admin' : '/admin/login') . '">';
        echo $authenticated ? 'Przejdź do panelu' : 'Panel administracyjny';
        echo '</a></div></div><div class="col-lg-5 reveal is-visible">';
        echo '<div class="terminal" aria-label="Status systemu"><div class="terminal-bar">';
        echo '<i class="terminal-dot" aria-hidden="true"></i><i class="terminal-dot" aria-hidden="true"></i>';
        echo '<i class="terminal-dot" aria-hidden="true"></i><span>syntaxdevteam.pl/build</span></div>';
        echo '<pre><code>$ ./workspace status' . "\n\n";
        echo 'CoreAuth     READY' . "\n" . 'CorePages    EDITABLE' . "\n" . 'ThemeEngine  ONLINE' . "\n";
        echo 'CrudApp      CONNECTED' . "\n\n" . 'architecture: MODULAR' . "\n";
        echo 'security:     ENABLED' . "\n" . 'status:       READY_TO_BUILD</code></pre></div>';
        echo '</div></div></div></header>';
    }

    /**
     * @param array{
     *     key: string,
     *     type: string,
     *     eyebrow: string,
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
            echo '<h2 class="h1 fw-bold">' . $this->escape($section['title']) . '</h2>';
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
            echo '<h2 class="fw-bold">' . $this->escape($section['title']) . '</h2>';
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
        echo '<h2 class="fw-bold">' . $this->escape($section['title']) . '</h2></div>';
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
        echo '<h2>' . $this->escape($section['title']) . '</h2>';
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

        return $label !== '' ? $label : $section['title'];
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

        return preg_match('~^(?:https?://|mailto:|#|index\.php(?:\?|$))~i', $href) === 1 ? $href : '';
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
                echo ' data-bs-dismiss="offcanvas"';
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
            ['label' => 'Edytuj dane', 'href' => 'index.php?route=/admin/profile'],
            ['label' => 'Połączone konta', 'href' => 'index.php?route=/admin/identities'],
            ['label' => 'Ustawienia avatara', 'href' => 'index.php?route=/admin/profile'],
            ['label' => 'Bezpieczeństwo', 'href' => 'index.php?route=/admin/profile'],
        ];

        return array_values(array_filter(
            $links,
            fn (array $link): bool => ($link['label'] ?? '') !== '' && $this->safeHref((string) ($link['href'] ?? '')) !== ''
        ));
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function asset(string $relativePath): string
    {
        $relativePath = ltrim($relativePath, '/');
        $file = __DIR__ . '/assets/' . $relativePath;
        $version = is_file($file) ? (string) filemtime($file) : '1';

        return '/templates/default/assets/' . $relativePath . '?v=' . rawurlencode($version);
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
