<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Core;

interface ThemeInterface
{
    /**
     * @param list<array{
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
     * }> $sections
     * @param list<array{
     *     title: string,
     *     slug: string,
     *     href?: string,
     *     summary: string,
     *     type: string,
     *     navigation_area: string,
     *     navigation_label: string,
     *     sort_order?: int
     * }> $pages
     */
    public function render_homepage(array $sections, array $pages, bool $authenticated): void;

    /**
     * @param list<array{
     *     title: string,
     *     slug: string,
     *     href?: string,
     *     summary: string,
     *     type: string,
     *     navigation_area: string,
     *     navigation_label: string,
     *     sort_order?: int
     * }> $items
     */
    public function set_public_navigation(array $items, bool $authenticated): void;

    public function start_page(string $title, string $description = ''): void;

    public function end_page(): void;

    public function start_header(string $title, string $lead = '', string $eyebrow = ''): void;

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

    /**
     * @param list<array{
     *     label: string,
     *     title: string,
     *     href: string,
     *     direction?: string,
     *     description?: string,
     *     disabled?: bool
     * }> $items
     */
    public function render_content_navigation(array $items): void;

    public function render_alert(string $message, string $variant = 'info'): void;

    /**
     * @param list<string> $headers
     * @param list<list<scalar|null>> $rows
     */
    public function render_table(array $headers, array $rows): void;

    /**
     * @param list<array{
     *     name: string,
     *     label: string,
     *     type?: string,
     *     value?: string,
     *     values?: list<string>,
     *     options?: array<string, string>,
     *     groups?: array<string, array<string, string>>,
     *     checked?: bool,
     *     rows?: int,
     *     help?: string,
     *     format_name?: string,
     *     format_value?: string
     * }> $fields
     */
    public function render_form(
        string $action,
        array $fields,
        string $submitLabel,
        string $csrfToken = '',
        string $method = 'post',
    ): void;

    public function csrf_field(string $token): void;

    /**
     * @param list<array{
     *     section: string,
     *     label: string,
     *     path: string,
     *     icon: string,
     *     permission: string,
     *     order: int
     * }> $menuItems
     * @param array{
     *     name: string,
     *     role: string,
     *     initials: string,
     *     logout_action?: string,
     *     logout_token?: string
     * } $user
     */
    public function start_admin_page(string $title, array $menuItems, string $activePath, array $user): void;

    public function end_admin_page(): void;

    /**
     * @param list<array{label: string, href: string}> $breadcrumbs
     * @param array{label: string, href: string}|null $action
     */
    public function start_admin_content(
        string $title,
        string $lead = '',
        array $breadcrumbs = [],
        ?array $action = null,
    ): void;

    public function end_admin_content(): void;

    public function start_admin_metrics(): void;

    public function render_admin_metric(string $label, string $value, string $symbol, string $detail = ''): void;

    public function end_admin_metrics(): void;

    public function start_admin_panel(string $title, string $meta = ''): void;

    public function end_admin_panel(): void;

    /**
     * @param list<string> $headers
     * @param list<list<scalar|null>> $rows
     */
    public function render_admin_table(array $headers, array $rows): void;

    /**
     * @param list<string> $headers
     * @param list<array{
     *     cells: list<scalar|null>,
     *     actions: list<array{
     *         label: string,
     *         variant?: string,
     *         href?: string,
     *         action?: string,
     *         fields?: array<string, scalar>,
     *         confirm?: string
     *     }>
     * }> $rows
     */
    public function render_admin_action_table(array $headers, array $rows, string $csrfToken): void;

    /**
     * @param list<array{
     *     provider: string,
     *     subject: string,
     *     label: string,
     *     description: string,
     *     href?: string
     * }> $identities
     */
    public function render_admin_login(
        string $action,
        array $identities,
        string $csrfToken,
        string $message = '',
        string $variant = 'info',
    ): void;

    public function render_admin_access_state(
        int $status,
        string $title,
        string $message,
        string $actionHref,
        string $actionLabel,
    ): void;

    /**
     * @param array{name: string, role: string} $user
     * @param list<array{name: string, label: string, configured: bool, linked: bool}> $providers
     */
    public function render_admin_identities(
        array $user,
        array $providers,
        string $unlinkAction,
        string $csrfToken,
        string $message = '',
        string $variant = 'info',
    ): void;

    public function render_public_page(
        string $title,
        string $content,
        string $publishedAt,
        string $description = '',
        string $pageType = 'standard',
        string $contentFormat = 'html',
        string $eyebrow = '',
    ): void;

    public function render_rich_content(string $content, string $format = 'html'): void;

    public function render_page_not_found(string $title, string $message): void;

    /**
     * @param list<array{label: string, href: string, description: string}> $resources
     * @param list<array{section: string, label: string, path: string, icon: string, permission: string, order: int}> $menuItems
     * @param array{name: string, role: string, initials: string, logout_action?: string, logout_token?: string} $user
     */
    public function render_admin_resources(array $resources, array $menuItems, array $user): void;
}
