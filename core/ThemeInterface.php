<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Core;

interface ThemeInterface
{
    /** @param list<array{id:string,label:string,description:string,href:string,section:string,keywords:string,order:int}> $items */
    public function set_admin_search_items(array $items): void;

    /**
     * @param array{role:string,label:string,stop_action:string,csrf_token:string}|null $preview
     */
    public function set_admin_role_preview(?array $preview): void;

    /**
     * @param list<array{
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
     *     widgets_before?: list<array<string, scalar>>,
     *     widgets_after?: list<array<string, scalar>>,
     *     widgets_aside?: list<array<string, scalar>>,
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
     * @param list<string> $activeModules
     */
    public function set_public_navigation(array $items, bool $authenticated, array $activeModules = []): void;

    /** Isolate a microsite or storefront from the platform's public navigation and auth links. */
    public function set_public_standalone(bool $standalone = true): void;

    /** @param list<array<string, mixed>> $items Navigation owned by an isolated microsite. */
    public function set_public_standalone_navigation(array $items): void;

    /** Override the validated HTTPS origin used by canonical, Open Graph and structured metadata. */
    public function set_public_origin(string $origin): void;

    /** @param list<string> $locations */
    public function set_auth_entry_locations(array $locations): void;

    public function start_page(string $title, string $description = '', bool $indexable = true): void;

    public function end_page(): void;

    public function start_header(string $title, string $lead = '', string $eyebrow = ''): void;

    public function end_header(): void;

    public function start_section(): void;

    public function end_section(): void;

    public function render_section_heading(string $title, string $lead = ''): void;

    public function start_grid(): void;

    public function end_grid(): void;

    public function start_column(string $size = '12'): void;

    public function end_column(): void;

    public function start_card(string $title = '', string $label = '', string $variant = 'default'): void;

    public function end_card(): void;

    public function render_text(string $text): void;

    public function render_button(string $label, string $href, string $variant = 'primary'): void;

    /**
     * @param list<array{label: string, href?: string}> $items
     */
    public function render_breadcrumb(array $items): void;

    /**
     * @param list<array{label: string, href: string, meta?: string}> $links
     */
    public function render_link_list(array $links, string $variant = 'stacked'): void;

    /**
     * @param list<array{label: string, href: string, active?: bool}> $tabs
     */
    public function render_tabs(array $tabs): void;

    public function render_avatar(string $name, ?string $avatarUrl = null, string $size = 'md'): void;

    public function render_image(string $url, string $alt, string $variant = 'content'): void;

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

    public function render_loading_state(string $id, string $label, string $variant = 'panel'): void;

    public function require_asset(string $asset): void;

    /**
     * @param list<string|array{label: string, href: string, direction?: string, reset?: bool}> $headers
     * @param list<list<scalar|null>> $rows
     */
    public function render_table(array $headers, array $rows): void;

    /**
     * @param list<array{label: string, value: scalar|null}> $facts
     * @param list<string> $headers
     * @param list<list<scalar|null>> $rows
     * @param list<array{label: string, href: string, variant?: string}> $actions
     */
    public function render_detail_card(
        string $title,
        string $label,
        array $facts,
        array $headers = [],
        array $rows = [],
        array $actions = [],
    ): void;

    /**
     * @param list<array{
     *     name:string,
     *     price:string,
     *     period:string,
     *     tagline:string,
     *     featured:bool,
     *     featured_label:string,
     *     action_label:string,
     *     action_href:string,
     *     resources:list<string>,
     *     benefits:list<string>
     * }> $plans
     */
    public function render_pricing_cards(array $plans): void;

    /**
     * @param array{
     *     name: string,
     *     role: string,
     *     headline: string,
     *     bio: string,
     *     avatar_url?: string,
     *     tags: list<string>,
     *     work: list<string>,
     *     focus: list<string>,
     *     skills: list<array{name: string, level: string, percent: int}>,
     *     projects: list<array{title: string, description: string}>,
     *     actions: list<array{label: string, href: string, variant?: string}>,
     *     contact_email?: string,
     *     contact_discord?: string
     * } $profile
     */
    public function render_team_member_profile(array $profile): void;

    /**
     * @param list<array{label: string, value: int|float, average?: int|float}> $points
     */
    public function render_line_chart(array $points, string $label): void;

    /**
     * @param list<array{label: string, value: int|float, detail?: string}> $items
     */
    public function render_bar_chart(array $items, string $label): void;

    /**
     * @param list<array{latitude: float, longitude: float, value: int|float, label: string, country_code?: string}> $points
     */
    public function render_geo_map(array $points, string $label): void;

    /**
     * @param list<string> $headers
     * @param list<array{cells: list<scalar|null>, actions: list<array{label: string, href: string, variant?: string}>}> $rows
     */
    public function render_action_table(array $headers, array $rows): void;

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
     *     cards?: list<array{key: string, label: string, description: string, preview: string}>,
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
     *     avatar_url?: string,
     *     logout_action?: string,
     *     logout_token?: string,
     *     profile_links?: list<array{label: string, href: string}>
     * } $user
     */
    public function start_admin_page(string $title, array $menuItems, string $activePath, array $user): void;

    public function end_admin_page(): void;

    /**
     * @param list<array{label: string, href: string}> $breadcrumbs
     * @param array{label?: string, href?: string, variant?: string}|list<array{label: string, href: string, variant?: string}>|null $action
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

    public function start_admin_panel_grid(string $variant = 'balanced'): void;

    public function end_admin_panel_grid(): void;

    public function start_admin_panel_column(): void;

    public function end_admin_panel_column(): void;

    public function start_admin_panel(string $title, string $meta = ''): void;

    public function end_admin_panel(): void;

    /**
     * @param list<array{label: string, value: string, detail?: string, variant?: string}> $facts
     */
    public function render_admin_fact_grid(array $facts): void;

    /**
     * @param list<array{
     *     label: string,
     *     action: string,
     *     checked: bool,
     *     fields?: array<string, string>
     * }> $actions
     */
    public function render_admin_switch_actions(array $actions, string $csrfToken = ''): void;

    /**
     * @param list<array{
     *     title: string,
     *     description?: string,
     *     options: list<array{key: string, label: string, description: string, preview: string}>
     * }> $groups
     */
    public function render_admin_option_gallery(array $groups): void;

    /**
     * @param list<array{label: string, href: string, variant?: string}> $actions
     */
    public function render_admin_panel_actions(array $actions): void;

    public function start_admin_catalog_toolbar(): void;

    public function end_admin_catalog_toolbar(): void;

    /**
     * @param list<array{key:string,label:string,href:string,active?:bool,disabled?:bool}> $sources
     * @param list<array{
     *     title:string,description:string,source:string,field:string,value?:string,
     *     options?:array<string,string>,meta?:string,icon?:string,group?:string
     * }> $items
     * @param list<array{key:string,label:string,href:string,active?:bool}> $sorts
     * @param array<string, scalar> $hiddenFields
     */
    public function render_admin_catalog(
        array $sources,
        array $items,
        string $action,
        string $csrfToken,
        array $hiddenFields = [],
        array $sorts = [],
    ): void;

    /**
     * @param list<array{
     *     title:string,
     *     items:list<array{
     *         label:string,filename:string,size:string,status?:string,id?:int,
     *         update_field?:string,update_value?:string,update_options?:array<string,string>,
     *         update_required?:bool,force_update?:bool
     *     }>
     * }> $groups
     */
    public function render_admin_file_collection_modal(
        string $id,
        string $title,
        string $description,
        array $groups,
        string $uploadAction,
        string $deleteAction,
        string $updateAction,
        string $csrfToken,
        int $profileId,
        string $uploadLimit,
        string $uploadField = 'file',
        string $uploadAccept = '',
        array $bulkUpdate = [],
    ): void;

    /**
     * @param list<string> $headers
     * @param list<list<scalar|null>> $rows
     */
    public function render_admin_table(array $headers, array $rows): void;

    /**
     * @param list<string> $headers
     * @param list<array{
     *     selection_value?: scalar,
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
     * @param array{action:string,field?:string,label?:string,confirm?:string}|null $bulkAction
     */
    public function render_admin_action_table(array $headers, array $rows, string $csrfToken, ?array $bulkAction = null): void;

    /** @param list<array<string, mixed>> $fields */
    public function render_admin_filter_modal(
        string $id,
        string $title,
        string $action,
        array $fields,
        string $clearHref,
    ): void;

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
        bool $indexable = true,
    ): void;

    public function render_rich_content(string $content, string $format = 'html'): void;

    /** @param array<string, mixed> $data */
    public function render_structured_data(array $data): void;

    public function render_public_error(
        int $status,
        string $title,
        string $message,
        string $actionLabel = 'Back to home',
        string $actionHref = '/',
    ): void;

    public function render_page_not_found(string $title, string $message): void;

    /**
     * @param list<array{label: string, href: string, description: string}> $resources
     * @param list<array{section: string, label: string, path: string, icon: string, permission: string, order: int}> $menuItems
     * @param array{name: string, role: string, initials: string, logout_action?: string, logout_token?: string} $user
     */
    public function render_admin_resources(array $resources, array $menuItems, array $user): void;
}
