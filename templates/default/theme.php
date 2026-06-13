<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Templates\DefaultTheme;

use SyntaxDevTeam\Cms\Core\ThemeInterface;

final class Theme implements ThemeInterface
{
    public function render_homepage(array $pages, bool $authenticated): void
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
        echo '<li class="nav-item"><a class="nav-link" href="#projects">Projekty</a></li>';
        echo '<li class="nav-item"><a class="nav-link" href="#stack">Technologie</a></li>';
        if ($pages !== []) {
            echo '<li class="nav-item"><a class="nav-link" href="#pages">Strony</a></li>';
        }
        echo '<li class="nav-item"><a class="nav-link" href="#contact">Kontakt</a></li>';
        echo '<li class="nav-item ms-lg-2"><a class="btn btn-sm btn-outline-light" href="index.php?route=';
        echo $authenticated ? '/admin' : '/admin/login';
        echo '">' . ($authenticated ? 'Otwórz panel' : 'Zaloguj się') . '</a></li></ul></div></div></nav>';

        echo '<header id="top" class="home-hero"><div class="container py-5"><div class="row align-items-center g-5">';
        echo '<div class="col-lg-7 reveal is-visible"><p class="eyebrow">Minecraft / Discord / Android / Backend</p>';
        echo '<h1 class="home-title fw-bold">Kod, który zasila <span>społeczności.</span></h1>';
        echo '<p class="home-lead mt-4">Projektujemy pluginy serwerowe, automatyzacje Discord, aplikacje mobilne i modułowe systemy WWW, które można rozwijać bez przepisywania wszystkiego od początku.</p>';
        echo '<div class="hero-actions mt-4"><a class="btn btn-primary btn-lg" href="#projects">Poznaj projekty</a>';
        echo '<a class="btn btn-outline-light btn-lg" href="index.php?route=' . ($authenticated ? '/admin' : '/admin/login') . '">';
        echo $authenticated ? 'Przejdź do panelu' : 'Panel administracyjny';
        echo '</a></div><div class="hero-metrics mt-5">';
        echo '<div class="hero-metric"><strong>Paper</strong><span>pluginy serwerowe</span></div>';
        echo '<div class="hero-metric"><strong>Discord</strong><span>boty i automatyzacje</span></div>';
        echo '<div class="hero-metric"><strong>PHP 8.5</strong><span>modułowy miniPORTAL</span></div></div></div>';
        echo '<div class="col-lg-5 reveal is-visible"><div class="terminal" aria-label="Status systemu"><div class="terminal-bar">';
        echo '<i class="terminal-dot" aria-hidden="true"></i><i class="terminal-dot" aria-hidden="true"></i><i class="terminal-dot" aria-hidden="true"></i>';
        echo '<span>syntaxdevteam.pl/build</span></div><pre><code>$ ./workspace status' . "\n\n";
        echo 'CoreAuth     READY' . "\n" . 'CorePages    PUBLISHED' . "\n" . 'ThemeEngine  ONLINE' . "\n" . 'CrudApp      CONNECTED' . "\n\n";
        echo 'architecture: MODULAR' . "\n" . 'security:     ENABLED' . "\n" . 'status:       READY_TO_BUILD</code></pre></div></div></div></div></header>';

        echo '<main id="content"><section id="projects" class="home-section"><div class="container">';
        echo '<div class="home-heading reveal"><p class="eyebrow">01 / Wybrane realizacje</p>';
        echo '<h2 class="fw-bold">Niezależne projekty. Wspólny standard jakości.</h2>';
        echo '<p class="lead text-secondary">Każdy produkt jest osobnym modułem, ale korzysta ze sprawdzonych fundamentów.</p></div>';
        echo '<div class="project-grid">';
        $projects = [
            ['PunisherX', 'System moderacji dla Paper i Folia: kary, historia działań, uprawnienia oraz API.', 'cyan', true],
            ['SyntaxCore', 'Wspólna biblioteka komunikatów, konfiguracji, logowania i integracji.', 'violet', false],
            ['Econify', 'Bot Discord łączący ekonomię społeczności, zadania, sklep i panel WWW.', 'violet', false],
            ['miniPORTAL', 'Czysty PHP, wymienne motywy, lokalne ACL i niezależne moduły treści.', 'cyan', true],
        ];
        foreach ($projects as $index => [$title, $description, $accent, $wide]) {
            echo '<article class="showcase-card project-card ' . ($wide ? 'project-card-wide ' : '') . 'reveal" data-accent="' . $accent . '">';
            echo '<span class="project-number">PROJECT / ' . str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT) . '</span>';
            echo '<h3>' . $this->escape($title) . '</h3><p class="text-secondary">' . $this->escape($description) . '</p>';
            echo '<a class="btn btn-outline-light" href="#contact">Dowiedz się więcej</a></article>';
        }
        echo '</div></div></section>';

        echo '<section id="stack" class="home-section"><div class="container"><div class="home-heading reveal">';
        echo '<p class="eyebrow">02 / Technologie</p><h2 class="fw-bold">Dobieramy narzędzia do problemu.</h2>';
        echo '<p class="lead text-secondary">Bez zbędnej warstwy abstrakcji, z naciskiem na bezpieczeństwo i utrzymanie.</p></div>';
        echo '<div class="row g-4">';
        foreach ([
            ['Serwery', 'Paper & Folia', 'Kotlin, Adventure i nowoczesne środowiska serwerowe.'],
            ['Automatyzacja', 'Discord & OAuth', 'Boty, logowanie federacyjne, ACL i integracje API.'],
            ['Web', 'PHP & CrudApp', 'PHP 8.5, Medoo, MySQL i wymienna warstwa Theme.'],
        ] as $index => [$label, $title, $description]) {
            echo '<div class="col-lg-4 reveal"><article class="showcase-card stack-card h-100" data-number="0' . ($index + 1) . '">';
            echo '<p class="showcase-label">' . $this->escape($label) . '</p><h3>' . $this->escape($title) . '</h3>';
            echo '<p class="text-secondary">' . $this->escape($description) . '</p></article></div>';
        }
        echo '</div></div></section>';

        if ($pages !== []) {
            echo '<section id="pages" class="home-section"><div class="container"><div class="home-heading reveal">';
            echo '<p class="eyebrow">03 / Opublikowane strony</p><h2 class="fw-bold">Treści zarządzane przez miniPORTAL.</h2>';
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

        echo '<section id="contact" class="home-section"><div class="container"><div class="contact-panel reveal"><div>';
        echo '<p class="eyebrow mb-2">04 / Kontakt</p><h2 class="h1 fw-bold">Zbudujmy coś użytecznego.</h2>';
        echo '<p class="text-secondary mb-0">Plugin, bot, aplikacja czy system WWW - zacznijmy od konkretnego problemu.</p></div>';
        echo '<a class="btn btn-primary btn-lg" href="mailto:contact@syntaxdevteam.pl">contact@syntaxdevteam.pl</a>';
        echo '</div></div></section></main><footer class="border-top py-4"><div class="container d-flex flex-column flex-md-row justify-content-between gap-2 text-secondary small">';
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
        echo '<a class="btn btn-sm btn-outline-light" href="templates/default/admin-stylebook.html">Panel admin</a>';
        echo '<a class="btn btn-sm btn-outline-light" href="templates/default/homepage.html">Strona główna</a>';
        echo '<a class="btn btn-sm btn-outline-light" href="templates/default/stylebook.html">Stylebook</a>';
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
            $value = $this->escape($field['value'] ?? '');

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

            echo '<div class="mb-3"><label class="form-label" for="' . $name . '">' . $label . '</label>';

            if ($type === 'textarea') {
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
        $this->start_page($title . ' - miniPORTAL', $title);
        $this->start_header($title, 'Opublikowano: ' . $publishedAt);
        $this->end_header();
        $this->start_section();
        echo '<article class="showcase-card">';

        foreach (preg_split('/\R{2,}/', trim($content)) ?: [] as $paragraph) {
            echo '<p>' . nl2br($this->escape($paragraph)) . '</p>';
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
