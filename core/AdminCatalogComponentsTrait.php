<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Core;

trait AdminCatalogComponentsTrait
{
    public function start_admin_catalog_toolbar(): void
    {
        echo '<div class="admin-catalog-toolbar">';
    }

    public function end_admin_catalog_toolbar(): void
    {
        echo '</div>';
    }

    public function render_admin_catalog(
        array $sources,
        array $items,
        string $action,
        string $csrfToken,
        array $hiddenFields = [],
        array $sorts = [],
    ): void {
        echo '<div class="admin-catalog" data-admin-catalog>';
        echo '<nav class="admin-catalog-sources" aria-label="Źródło pluginów">';
        foreach ($sources as $source) {
            $disabled = (bool) ($source['disabled'] ?? false);
            $class = 'admin-catalog-source' . (($source['active'] ?? false) ? ' is-active' : '');
            if ($disabled) {
                echo '<span class="' . $class . ' is-disabled" aria-disabled="true">';
            } else {
                echo '<a class="' . $class . '" href="' . $this->catalogEscape((string) $source['href']) . '">';
            }
            echo $this->catalogEscape((string) $source['label']);
            echo $disabled ? '</span>' : '</a>';
        }
        echo '</nav>';

        if ($sorts !== []) {
            echo '<nav class="admin-catalog-sorts" aria-label="Sortowanie katalogu"><span>Sortuj:</span>';
            foreach ($sorts as $sort) {
                $class = 'admin-catalog-sort' . (($sort['active'] ?? false) ? ' is-active' : '');
                echo '<a class="' . $class . '" href="' . $this->catalogEscape((string) $sort['href']) . '">';
                echo $this->catalogEscape((string) $sort['label']) . '</a>';
            }
            echo '</nav>';
        }

        if ($items === []) {
            echo '<div class="admin-catalog-empty"><strong>Brak pluginów do pokazania</strong>';
            echo '<span>Zmień źródło albo wyszukiwane hasło.</span></div></div>';
            return;
        }

        echo '<form action="' . $this->catalogEscape($action) . '" method="post">';
        if ($csrfToken !== '') {
            $this->csrf_field($csrfToken);
        }
        foreach ($hiddenFields as $name => $value) {
            echo '<input type="hidden" name="' . $this->catalogEscape((string) $name) . '" value="';
            echo $this->catalogEscape((string) $value) . '">';
        }
        echo '<div class="admin-catalog-grid">';
        $currentGroup = '';
        foreach ($items as $index => $item) {
            $group = (string) ($item['group'] ?? 'compatible');
            if ($group !== $currentGroup) {
                if ($currentGroup !== '') echo '</div>';
                if ($group === 'unverified') {
                    echo '<section class="admin-catalog-compatibility-warning"><h2>Może działać — zgodność niepotwierdzona</h2>';
                    echo '<p>Autor nie oznaczył najnowszego wydania jako zgodnego z bieżącą wersją i silnikiem serwera. Instalujesz je świadomie i na własne ryzyko.</p>';
                    echo '<div class="admin-catalog-grid">';
                }
                $currentGroup = $group;
            }
            $options = is_array($item['options'] ?? null) ? $item['options'] : [];
            $inputId = 'catalog-item-' . $index;
            $field = preg_replace('/[^a-z0-9_\[\]-]/i', '', (string) $item['field']) ?: 'plugins[]';
            $value = (string) ($item['value'] ?? array_key_first($options) ?? '');
            echo '<article class="admin-catalog-card" data-admin-catalog-card tabindex="0">';
            echo '<span class="admin-catalog-selected-indicator" aria-hidden="true">✓ Wybrano</span>';
            echo '<div class="admin-catalog-card-head"><span class="admin-catalog-icon" aria-hidden="true">';
            $iconUrl = $this->catalogImageUrl((string) ($item['icon_url'] ?? ''));
            if ($iconUrl !== '') {
                echo '<img src="' . $this->catalogEscape($iconUrl) . '" alt="" loading="lazy" decoding="async">';
            } else {
                echo $this->catalogIcon((string) ($item['icon'] ?? $item['source']));
            }
            echo '</span><div><h3>' . $this->catalogEscape((string) $item['title']) . '</h3>';
            echo '<span>' . $this->catalogEscape((string) $item['source']) . '</span></div></div>';
            echo '<p>' . $this->catalogEscape((string) $item['description']) . '</p>';
            if (($item['meta'] ?? '') !== '') {
                echo '<small>' . $this->catalogEscape((string) $item['meta']) . '</small>';
            }
            echo '<div class="admin-catalog-card-action">';
            echo '<input class="admin-catalog-choice visually-hidden" id="' . $inputId . '" type="checkbox" aria-label="Wybierz ';
            echo $this->catalogEscape((string) $item['title']) . '" ';
            if ($options === []) {
                echo 'name="' . $this->catalogEscape($field) . '" value="' . $this->catalogEscape($value) . '">';
            } else {
                echo 'data-admin-catalog-toggle aria-controls="' . $inputId . '-select">';
                echo '<select class="form-select form-select-sm" id="' . $inputId . '-select" name="';
                echo $this->catalogEscape($field) . '" disabled>';
                foreach ($options as $optionValue => $label) {
                    echo '<option value="' . $this->catalogEscape((string) $optionValue) . '">';
                    echo $this->catalogEscape((string) $label) . '</option>';
                }
                echo '</select>';
            }
            echo '<label class="admin-catalog-choice-label" for="' . $inputId . '">Kliknij kafelek, aby wybrać</label>';
            echo '</div></article>';
        }
        echo $currentGroup === 'unverified' ? '</div></section>' : '</div>';
        echo '<div class="admin-catalog-submit"><button class="btn btn-primary" type="submit">';
        echo 'Zainstaluj wybrane</button></div></form></div>';
    }

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
    ): void {
        $safeId = preg_replace('/[^a-z0-9_-]/i', '', $id) ?: 'adminPluginFiles';
        $count = array_sum(array_map(static fn (array $group): int => count($group['items'] ?? []), $groups));
        echo '<button class="btn btn-primary admin-plugin-files-trigger" type="button" data-bs-toggle="modal" data-bs-target="#';
        echo $safeId . '"><span class="admin-plugin-files-trigger-icon" aria-hidden="true">≡</span><span>Zainstalowane pluginy</span><span class="badge text-bg-dark">';
        echo $count . '</span></button>';
        echo '<div class="modal fade admin-plugin-files-modal" id="' . $safeId . '" tabindex="-1" aria-labelledby="';
        if (($bulkUpdate['auto_open'] ?? false) === true) {
            echo $safeId . '-label" data-auto-open="true" aria-hidden="true"><div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">';
        } else {
            echo $safeId . '-label" aria-hidden="true"><div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">';
        }
        echo '<div class="modal-content"><div class="modal-header"><div><h2 class="modal-title fs-5" id="';
        echo $safeId . '-label">' . $this->catalogEscape($title) . ' <small>' . $count . '</small></h2>';
        echo '<p>' . $this->catalogEscape($description) . '</p></div><button class="btn-close" type="button" ';
        echo 'data-bs-dismiss="modal" aria-label="Zamknij"></button></div><div class="modal-body">';
        echo '<form class="admin-plugin-upload" action="' . $this->catalogEscape($uploadAction) . '" method="post" enctype="multipart/form-data">';
        $this->csrf_field($csrfToken);
        echo '<input type="hidden" name="profile_id" value="' . $profileId . '">';
        $safeUploadField = preg_replace('/[^a-z0-9_-]/i', '', $uploadField) ?: 'file';
        echo '<label class="btn btn-primary" for="admin-file-upload-' . $profileId . '">Wybierz plik</label>';
        echo '<input id="admin-file-upload-' . $profileId . '" name="' . $safeUploadField . '" type="file"';
        if ($uploadAccept !== '') {
            echo ' accept="' . $this->catalogEscape($uploadAccept) . '"';
        }
        echo ' required>';
        echo '<button class="btn btn-outline-light" type="submit">Wyślij</button><small>Limit: ';
        echo $this->catalogEscape($uploadLimit) . '</small></form>';
        if (($bulkUpdate['available'] ?? 0) > 0) {
            echo '<form class="admin-plugin-upload" action="' . $this->catalogEscape($updateAction) . '" method="post">';
            $this->csrf_field($csrfToken);
            echo '<input type="hidden" name="profile_id" value="' . $profileId . '">';
            echo '<input type="hidden" name="update_all" value="1">';
            echo '<button class="btn btn-outline-primary" type="submit">Aktualizuj wszystkie (';
            echo (int) $bulkUpdate['available'] . ')</button></form>';
        }

        foreach ($groups as $group) {
            echo '<section class="admin-plugin-file-group"><h3>' . $this->catalogEscape((string) $group['title']);
            echo ' <span>' . count($group['items']) . '</span></h3>';
            if ($group['items'] === []) {
                echo '<p class="admin-plugin-file-empty">Brak pluginów w tej grupie.</p></section>';
                continue;
            }
            foreach ($group['items'] as $item) {
                echo '<article class="admin-plugin-file"><span class="admin-plugin-file-icon" aria-hidden="true">◆</span>';
                echo '<div class="admin-plugin-file-copy"><strong>' . $this->catalogEscape((string) $item['label']) . '</strong>';
                echo '<span>' . $this->catalogEscape((string) $item['filename']) . '</span>';
                echo '<small>' . $this->catalogEscape((string) $item['size']);
                if (($item['status'] ?? '') !== '') {
                    echo ' · ' . $this->catalogEscape((string) $item['status']);
                }
                echo '</small></div>';
                if (($item['update_field'] ?? '') !== '' && (($item['update_required'] ?? true) || ($item['force_update'] ?? false))) {
                    echo '<form class="admin-plugin-file-update" action="' . $this->catalogEscape($updateAction) . '" method="post">';
                    $this->csrf_field($csrfToken);
                    echo '<input type="hidden" name="profile_id" value="' . $profileId . '">';
                    $options = is_array($item['update_options'] ?? null) ? $item['update_options'] : [];
                    if ($options !== []) {
                        echo '<select class="form-select form-select-sm" name="' . $this->catalogEscape((string) $item['update_field']) . '[]">';
                        foreach ($options as $optionValue => $label) {
                            echo '<option value="' . $this->catalogEscape((string) $optionValue) . '">';
                            echo $this->catalogEscape((string) $label) . '</option>';
                        }
                        echo '</select>';
                    } else {
                        echo '<input type="hidden" name="' . $this->catalogEscape((string) $item['update_field']) . '[]" value="';
                        echo $this->catalogEscape((string) ($item['update_value'] ?? '')) . '">';
                    }
                    if ($item['update_required'] ?? true) {
                        echo '<button class="btn btn-sm btn-outline-primary" type="submit">Aktualizuj</button>';
                    }
                    if ($item['force_update'] ?? false) {
                        echo '<button class="btn btn-sm btn-outline-warning" type="submit" name="force" value="1"';
                        echo ' data-confirm="Wymusić ponowną instalację najnowszego buildu tego pluginu?">Wymuś</button>';
                    }
                    echo '</form>';
                }
                echo '<form action="' . $this->catalogEscape($deleteAction) . '" method="post" data-confirm="Usunąć ten plik JAR? Zmiana zadziała po restarcie serwera.">';
                $this->csrf_field($csrfToken);
                echo '<input type="hidden" name="profile_id" value="' . $profileId . '">';
                echo '<input type="hidden" name="filename" value="' . $this->catalogEscape((string) $item['filename']) . '">';
                echo '<input type="hidden" name="snapshot_id" value="' . (int) ($item['id'] ?? 0) . '">';
                echo '<button class="btn btn-sm btn-outline-danger" type="submit" aria-label="Usuń ';
                echo $this->catalogEscape((string) $item['filename']) . '">Usuń</button></form></article>';
            }
            echo '</section>';
        }
        echo '</div></div></div></div>';
    }

    private function catalogEscape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    private function catalogIcon(string $source): string
    {
        return match (strtolower($source)) {
            'syntaxdevteam', 'build_explorer', 'build_explorer_v2' => 'SD',
            'modrinth', 'modrinth_search' => 'M',
            'spigotmc' => 'S',
            'hangar' => 'H',
            default => '◆',
        };
    }

    private function catalogImageUrl(string $url): string
    {
        if (
            str_starts_with($url, '/')
            && !str_starts_with($url, '//')
            && !str_contains($url, '..')
            && preg_match('#^/[A-Za-z0-9_./-]+(?:\?[A-Za-z0-9_.=&%-]+)?$#', $url) === 1
        ) {
            return $url;
        }
        $parts = parse_url($url);
        return filter_var($url, FILTER_VALIDATE_URL) !== false
            && ($parts['scheme'] ?? '') === 'https'
            && ($parts['user'] ?? '') === ''
            && ($parts['pass'] ?? '') === ''
            ? $url : '';
    }
}
