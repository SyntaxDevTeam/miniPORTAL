<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Core;

interface ThemeInterface
{
    public function start_page(string $title, string $description = ''): void;

    public function end_page(): void;

    public function start_header(string $title, string $lead = ''): void;

    public function end_header(): void;

    public function start_section(): void;

    public function end_section(): void;

    public function start_grid(): void;

    public function end_grid(): void;

    public function start_column(string $size = '12'): void;

    public function end_column(): void;

    public function start_card(string $title = '', string $label = ''): void;

    public function end_card(): void;

    public function render_text(string $text): void;

    public function render_button(string $label, string $href, string $variant = 'primary'): void;

    public function render_alert(string $message, string $variant = 'info'): void;

    /**
     * @param list<string> $headers
     * @param list<list<scalar|null>> $rows
     */
    public function render_table(array $headers, array $rows): void;

    /**
     * @param list<array{name: string, label: string, type?: string, value?: string}> $fields
     */
    public function render_form(string $action, array $fields, string $submitLabel): void;

    public function csrf_field(string $token): void;
}
