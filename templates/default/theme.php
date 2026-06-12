<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Templates\DefaultTheme;

use SyntaxDevTeam\Cms\Core\ThemeInterface;

final class Theme implements ThemeInterface
{
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
        echo '<a class="btn btn-sm btn-outline-light" href="templates/default/stylebook.html">Stylebook</a>';
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
        echo '<p class="eyebrow mb-2">Etap 1 / punkt integracji</p>';
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
        $allowedVariants = ['primary', 'secondary', 'success', 'danger', 'warning', 'info', 'light', 'dark'];
        $variant = in_array($variant, $allowedVariants, true) ? $variant : 'primary';

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

    public function render_form(string $action, array $fields, string $submitLabel): void
    {
        echo '<form class="showcase-card" action="' . $this->escape($action) . '" method="post">';

        foreach ($fields as $field) {
            $name = $this->escape($field['name']);
            $type = $this->escape($field['type'] ?? 'text');
            echo '<div class="mb-3"><label class="form-label" for="' . $name . '">';
            echo $this->escape($field['label']) . '</label>';
            echo '<input class="form-control" id="' . $name . '" name="' . $name . '" type="' . $type . '" value="';
            echo $this->escape($field['value'] ?? '') . '"></div>';
        }

        echo '<button class="btn btn-primary" type="submit">' . $this->escape($submitLabel) . '</button></form>';
    }

    public function csrf_field(string $token): void
    {
        echo '<input type="hidden" name="_token" value="' . $this->escape($token) . '">';
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
