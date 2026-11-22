<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Templates\Shared;

/** Shared, accessible editor for public module navigation in all admin themes. */
trait PublicNavigationEditorTrait
{
    /**
     * @param list<array{
     *     id:string,field_key:string,label:string,default_label:string,href:string,
     *     order:int,show_main:bool,show_footer:bool
     * }> $items
     */
    public function render_admin_public_navigation_editor(array $items, string $csrfToken): void
    {
        if ($items === []) {
            return;
        }

        usort($items, static fn (array $a, array $b): int => [$a['order'], $a['label']] <=> [$b['order'], $b['label']]);

        $escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        $assetRoot = dirname(__DIR__) . '/shared/assets/';
        $cssVersion = (int) filemtime($assetRoot . 'css/admin-public-navigation.css');
        $jsVersion = (int) filemtime($assetRoot . 'js/admin-public-navigation.js');
        $total = count($items);
        $mainCount = count(array_filter($items, static fn (array $item): bool => $item['show_main']));
        $footerCount = count(array_filter($items, static fn (array $item): bool => $item['show_footer']));

        echo '<link rel="stylesheet" href="/templates/shared/assets/css/admin-public-navigation.css?v=' . $cssVersion . '">';
        echo '<div class="admin-public-nav" data-public-nav-editor>';
        echo '<div class="admin-public-nav__summary"><span><strong>Edytuj linki modułów</strong>';
        echo '<small>' . $total . ' linków · menu: ' . $mainCount . ' · stopka: ' . $footerCount . '</small></span>';
        echo '</div>';
        echo '<div class="admin-public-nav__body">';
        echo '<p class="admin-public-nav__hint">Ustaw kolejność strzałkami ↑ i ↓. Przy aktywnym filtrze przesuwasz link względem widocznych pozycji. ';
        echo 'Wyłącz oba przełączniki, aby ukryć link. Każda zmiana zapisuje się automatycznie.</p>';
        echo '<div class="admin-public-nav__toolbar">';
        echo '<label for="admin-public-nav-search">Szukaj modułu';
        echo '<input class="form-control form-control-sm" id="admin-public-nav-search" type="search"';
        echo ' placeholder="Nazwa lub adres..." autocomplete="off" data-public-nav-search></label>';
        echo '<label for="admin-public-nav-filter">Pokaż';
        echo '<select class="form-select form-select-sm" id="admin-public-nav-filter" data-public-nav-filter>';
        echo '<option value="all">Wszystkie</option><option value="visible">Widoczne</option>';
        echo '<option value="hidden">Ukryte</option><option value="main">W menu</option>';
        echo '<option value="footer">W stopce</option></select></label>';
        echo '<span class="admin-public-nav__count" data-public-nav-count aria-live="polite">' . $total . ' z ' . $total . '</span>';
        echo '</div>';

        echo '<form class="admin-public-nav__form" action="index.php?route=/admin/settings/navigation/update" method="post">';
        $this->csrf_field($csrfToken);
        echo '<div class="admin-public-nav__scroll" tabindex="0" role="region" aria-label="Edycja linków modułów">';
        echo '<table class="admin-public-nav__table"><thead><tr>';
        echo '<th scope="col">Moduł / adres</th><th scope="col">Etykieta linku</th>';
        echo '<th scope="col">Menu główne</th><th scope="col">Stopka</th><th scope="col">Kolejność</th>';
        echo '</tr></thead><tbody>';

        foreach ($items as $item) {
            $key = (string) $item['field_key'];
            $fieldPrefix = 'public_nav_' . $key;
            $idPrefix = 'admin-public-nav-' . str_replace('_', '-', $key);
            $default = (string) $item['default_label'];
            $itemId = (string) $item['id'];
            $main = (bool) $item['show_main'];
            $footer = (bool) $item['show_footer'];
            echo '<tr id="' . $escape($idPrefix . '-row') . '" data-public-nav-row data-public-nav-id="' . $escape($itemId) . '" data-public-nav-default="' . $escape($default . ' ' . $item['href']) . '">';
            echo '<th scope="row"><span class="admin-public-nav__name">' . $escape($default) . '</span>';
            echo '<small class="admin-public-nav__href">' . $escape((string) $item['href']) . '</small></th>';
            echo '<td data-title="Etykieta"><label class="visually-hidden" for="' . $escape($idPrefix . '-label') . '">';
            echo $escape('Etykieta linku ' . $default) . '</label>';
            echo '<input class="form-control form-control-sm" type="text" maxlength="80" ';
            echo 'id="' . $escape($idPrefix . '-label') . '" name="' . $escape($fieldPrefix . '_label') . '" ';
            echo 'value="' . $escape((string) $item['label']) . '" data-public-nav-field="label" data-public-nav-label></td>';

            foreach (['main' => ['Menu główne', $main], 'footer' => ['Stopka', $footer]] as $area => [$areaLabel, $checked]) {
                $fieldId = $idPrefix . '-' . $area;
                echo '<td class="admin-public-nav__check" data-title="' . $escape($areaLabel) . '">';
                echo '<button class="admin-public-nav__toggle' . ($checked ? ' is-on' : ' is-off') . '" type="button" ';
                echo 'id="' . $escape($fieldId) . '" role="switch" aria-checked="' . ($checked ? 'true' : 'false') . '" ';
                echo 'aria-label="' . $escape($areaLabel . ': ' . $default) . '" data-public-nav-field="' . $escape($area) . '">';
                echo '<span class="admin-public-nav__toggle-track" aria-hidden="true"><span class="admin-public-nav__toggle-knob"></span></span>';
                echo '<span class="admin-public-nav__toggle-state" data-public-nav-toggle-state aria-hidden="true">' . ($checked ? 'ON' : 'OFF') . '</span></button></td>';
            }

            echo '<td data-title="Kolejność"><div class="admin-public-nav__move">';
            foreach (['up' => ['↑', 'Przesuń w górę'], 'down' => ['↓', 'Przesuń w dół']] as $direction => [$symbol, $description]) {
                echo '<button type="button" class="admin-public-nav__move-button" data-public-nav-move="' . $direction . '" aria-label="' . $escape($description . ': ' . $default) . '" title="' . $escape($description) . '">' . $symbol . '</button>';
            }
            echo '</div><label class="visually-hidden" for="' . $escape($idPrefix . '-order') . '">';
            echo $escape('Kolejność linku ' . $default) . '</label>';
            echo '<input class="form-control form-control-sm admin-public-nav__order" type="hidden" ';
            echo 'id="' . $escape($idPrefix . '-order') . '" name="' . $escape($fieldPrefix . '_order') . '" ';
            echo 'value="' . max(0, min(65535, (int) $item['order'])) . '" data-public-nav-field="order"></td>';
            echo '</tr>';
        }

        echo '</tbody></table></div>';
        echo '<p class="admin-public-nav__empty" data-public-nav-empty hidden>Brak linków spełniających wybrane kryteria.</p>';
        echo '<div class="admin-public-nav__actions"><span data-public-nav-status role="status" aria-live="polite">Zmiany zapisują się automatycznie.</span></div></form>';
        echo '<noscript><p class="admin-public-nav__error">Edycja nawigacji wymaga JavaScript.</p></noscript>';
        echo '</div></div>';
        echo '<script src="/templates/shared/assets/js/admin-public-navigation.js?v=' . $jsVersion . '" defer></script>';
    }
}
