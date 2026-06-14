<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Templates\DefaultTheme;

use SyntaxDevTeam\Cms\Core\RichTextSanitizer;
use SyntaxDevTeam\Cms\Core\ThemeInterface;

final class Theme implements ThemeInterface
{
    public function render_homepage(array $sections, array $pages, bool $authenticated): void
    {
        echo '<!doctype html><html lang="pl" data-bs-theme="dark"><head>';
        echo '<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
        echo '<meta name="description" content="SyntaxDevTeam tworzy pluginy Minecraft, boty Discord, aplikacje Android i narzędzia backendowe.">';
        echo '<meta name="theme-color" content="#080c12"><title>SyntaxDevTeam - software dla społeczności</title>';
        echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" ';
        echo 'integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">';
        echo '<link rel="stylesheet" href="templates/default/assets/css/stylebook.css">';
        echo '<link rel="stylesheet" href="templates/default/assets/css/homepage.css"></head><body>';
        echo '<div class="site-grid" aria-hidden="true"></div><a class="visually-hidden-focusable skip-link" href="#content">Przejdź do treści</a>';
        echo '<nav class="navbar navbar-expand-lg border-bottom fixed-top" data-site-nav aria-label="Główna nawigacja"><div class="container">';
        echo '<a class="navbar-brand fw-bold" href="#top"><span aria-hidden="true">&lt;/&gt;</span> SyntaxDevTeam</a>';
        echo '<button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Przełącz nawigację"><span class="navbar-toggler-icon"></span></button>';
        echo '<div class="collapse navbar-collapse" id="mainNav"><ul class="navbar-nav ms-auto align-items-lg-center">';
        foreach ($sections as $section) {
            if ($section['type'] === 'hero') {
                continue;
            }
            echo '<li class="nav-item"><a class="nav-link" href="#' . $this->escape($section['key']) . '">';
            echo $this->escape($this->navigationLabel($section)) . '</a></li>';
        }
        if ($pages !== []) {
            echo '<li class="nav-item"><a class="nav-link" href="#pages">Strony</a></li>';
        }
        echo '<li class="nav-item ms-lg-2"><a class="btn btn-sm btn-outline-light" href="index.php?route=';
        echo $authenticated ? '/admin' : '/admin/login';
        echo '">' . ($authenticated ? 'Otwórz panel' : 'Zaloguj się') . '</a></li></ul></div></div></nav>';

        $heroRendered = false;
        echo '<main id="content">';
        foreach ($sections as $section) {
            if ($section['type'] === 'hero' && !$heroRendered) {
                $this->renderHomepageHero($section, $authenticated);
                $heroRendered = true;
                continue;
            }

            $this->renderHomepageSection($section);
        }

        if ($pages !== []) {
            echo '<section id="pages" class="home-section"><div class="container"><div class="home-heading reveal">';
            echo '<p class="eyebrow">Opublikowane strony</p><h2 class="fw-bold">Treści zarządzane przez miniPORTAL.</h2>';
            echo '<p class="lead text-secondary">Poniższe pozycje pochodzą dynamicznie z modułu core_pages.</p></div><div class="row g-4">';
            foreach ($pages as $page) {
                echo '<div class="col-md-6 col-lg-4 reveal"><article class="showcase-card h-100">';
                echo '<p class="showcase-label">PAGE / ' . $this->escape($page['slug']) . '</p>';
                echo '<h3 class="h4">' . $this->escape($page['title']) . '</h3>';
                echo '<a class="btn btn-outline-light mt-3" href="index.php?route=/page&amp;slug=' . $this->escape(rawurlencode($page['slug'])) . '">Czytaj</a>';
                echo '</article></div>';
            }
            echo '</div></div></section>';
        }

        echo '</main><footer class="border-top py-4"><div class="container d-flex flex-column flex-md-row justify-content-between gap-2 text-secondary small">';
        echo '<span>&copy; 2026 SyntaxDevTeam</span><span>Projektowane modułowo. Rozwijane świadomie.</span></div></footer>';
        echo '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" ';
        echo 'integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>';
        echo '<script src="templates/default/assets/js/site.js"></script></body></html>';
    }

    public function start_page(string $title, string $description = ''): void
    {
        $title = $this->escape($title);
        $description = $this->escape($description);

        echo '<!doctype html><html lang="pl" data-bs-theme="dark"><head>';
        echo '<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
        echo '<meta name="description" content="' . $description . '">';
        echo '<title>' . $title . '</title>';
        echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" ';
        echo 'integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">';
        echo '<link rel="stylesheet" href="templates/default/assets/css/stylebook.css">';
        echo '</head><body>';
        echo '<nav class="navbar border-bottom"><div class="container">';
        echo '<a class="navbar-brand fw-bold" href="index.php">&lt;/&gt; miniPORTAL</a>';
        echo '<div class="d-flex gap-2">';
        echo '<a class="btn btn-sm btn-outline-light" href="index.php">Strona główna</a>';
        echo '<a class="btn btn-sm btn-outline-light" href="index.php?route=/articles">Artykuły</a>';
        echo '<a class="btn btn-sm btn-outline-light" href="index.php?route=/admin">Panel</a>';
        echo '</div>';
        echo '</div></nav><main>';
    }

    public function end_page(): void
    {
        echo '</main><footer class="border-top py-4"><div class="container text-secondary small">';
        echo 'miniPORTAL · Core → Modules → Templates';
        echo '</div></footer></body></html>';
    }

    public function start_header(string $title, string $lead = ''): void
    {
        echo '<header class="stylebook-hero border-bottom"><div class="container py-5">';
        echo '<p class="eyebrow mb-2">miniPORTAL / punkt integracji</p>';
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

    public function render_form(string $action, array $fields, string $submitLabel, string $csrfToken = ''): void
    {
        echo '<form class="showcase-card" action="' . $this->escape($action) . '" method="post">';

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
            if ($type === 'richtext') {
                echo '<span class="form-label" id="' . $name . '-label">' . $label . '</span>';
            } else {
                echo '<label class="form-label" for="' . $name . '">' . $label . '</label>';
            }

            if ($type === 'richtext') {
                $safeValue = (new RichTextSanitizer())->sanitize($rawValue);
                echo '<div class="richtext-editor" data-richtext>';
                echo '<div class="editor-toolbar" role="toolbar" aria-label="Formatowanie treści">';
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
                echo 'data-richtext-surface aria-labelledby="' . $name . '-label">' . $safeValue . '</div>';
                echo '<textarea class="visually-hidden" id="' . $name . '" name="' . $name . '" data-richtext-input>';
                echo $value . '</textarea></div>';
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
        echo '<link rel="stylesheet" href="templates/default/assets/css/stylebook.css">';
        echo '<link rel="stylesheet" href="templates/default/assets/css/admin.css">';
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
        if (($user['logout_action'] ?? '') !== '' && ($user['logout_token'] ?? '') !== '') {
            echo '<form action="' . $this->escape($user['logout_action']) . '" method="post" class="m-0">';
            $this->csrf_field($user['logout_token']);
            echo '<button class="admin-icon-button" type="submit" aria-label="Wyloguj">EX</button></form>';
        }
        echo '<span class="admin-avatar d-none d-sm-grid" aria-hidden="true">' . $this->escape($user['initials']) . '</span>';
        echo '</div></header><main id="admin-main" class="admin-content">';
    }

    public function end_admin_page(): void
    {
        echo '</main></div></div></div>';
        echo '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" ';
        echo 'integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>';
        echo '<script src="templates/default/assets/js/admin.js"></script></body></html>';
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

                echo '<form class="d-inline" action="' . $this->escape($action['action']) . '" method="post">';
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
        echo '<link rel="stylesheet" href="templates/default/assets/css/stylebook.css">';
        echo '<link rel="stylesheet" href="templates/default/assets/css/admin.css">';
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
        echo '<link rel="stylesheet" href="templates/default/assets/css/stylebook.css">';
        echo '<link rel="stylesheet" href="templates/default/assets/css/admin.css">';
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
        echo '<link rel="stylesheet" href="templates/default/assets/css/stylebook.css">';
        echo '<link rel="stylesheet" href="templates/default/assets/css/admin.css">';
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

    public function render_public_page(string $title, string $content, string $publishedAt): void
    {
        $content = (new RichTextSanitizer())->sanitize($content);
        $this->start_page($title . ' - miniPORTAL', $title);
        $this->start_header($title, 'Opublikowano: ' . $publishedAt);
        $this->end_header();
        $this->start_section();
        echo '<article class="showcase-card managed-home-content">';
        if ($content === '') {
            echo '<p>Ta strona nie ma jeszcze treści.</p>';
        } elseif (str_contains($content, '<')) {
            echo $content;
        } else {
            echo '<p>' . nl2br($this->escape($content)) . '</p>';
        }
        echo '<a class="btn btn-outline-light" href="index.php">Wróć do strony głównej</a></article>';
        $this->end_section();
        $this->end_page();
    }

    public function render_page_not_found(string $title, string $message): void
    {
        $this->start_page('404 - miniPORTAL', $message);
        $this->start_header($title, $message);
        $this->end_header();
        $this->start_section();
        $this->render_alert($message, 'warning');
        $this->render_button('Wróć do strony głównej', 'index.php', 'outline-light');
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
     *     layout: string,
     *     button_label: string,
     *     button_url: string,
     *     items: list<array{
     *         label: string,
     *         title: string,
     *         content: string,
     *         button_label: string,
     *         button_url: string,
     *         variant: string,
     *         width: string
     *     }>
     * } $section
     */
    private function renderHomepageHero(array $section, bool $authenticated): void
    {
        $content = (new RichTextSanitizer())->sanitize($section['content_html']);

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
     *     layout: string,
     *     button_label: string,
     *     button_url: string,
     *     items: list<array{
     *         label: string,
     *         title: string,
     *         content: string,
     *         button_label: string,
     *         button_url: string,
     *         variant: string,
     *         width: string
     *     }>
     * } $section
     */
    private function renderHomepageSection(array $section): void
    {
        $layouts = ['full', 'split', 'columns', 'accent'];
        $layout = in_array($section['layout'], $layouts, true) ? $section['layout'] : 'full';
        $content = (new RichTextSanitizer())->sanitize($section['content_html']);
        $href = $this->safeHref($section['button_url']);

        echo '<section id="' . $this->escape($section['key']) . '" class="home-section managed-home-section">';
        echo '<div class="container">';

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
                $itemHref = $this->safeHref($item['button_url']);
                echo '<article class="showcase-card managed-card managed-card-' . $width . ' reveal" ';
                echo 'data-variant="' . $variant . '" data-number="';
                echo str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) . '">';
                if ($item['label'] !== '') {
                    echo '<p class="managed-card-label">' . $this->escape($item['label']) . '</p>';
                }
                echo '<h3>' . $this->escape($item['title']) . '</h3>';
                echo '<p class="text-secondary">' . nl2br($this->escape($item['content'])) . '</p>';
                if ($item['button_label'] !== '' && $itemHref !== '') {
                    echo '<a class="btn btn-outline-light" href="' . $this->escape($itemHref) . '">';
                    echo $this->escape($item['button_label']) . '</a>';
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
     * @param array{eyebrow: string, title: string} $section
     */
    private function navigationLabel(array $section): string
    {
        $label = preg_replace('/^\s*\d+\s*\/\s*/', '', $section['eyebrow']) ?? '';

        return $label !== '' ? $label : $section['title'];
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

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
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
