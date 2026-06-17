<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Core;

use RuntimeException;

final class PublicNavigationRegistry
{
    /** @var array<string, array{id: string, label: string, href: string, area: string, order: int}> */
    private array $items = [];

    public function add(string $id, string $label, string $href, string $area = 'none', int $order = 100): void
    {
        $id = trim($id);
        $label = trim($label);
        $href = trim($href);
        $area = in_array($area, ['none', 'main', 'footer'], true) ? $area : 'none';
        $order = max(0, min(65535, $order));

        if (preg_match('/^[a-z][a-z0-9_.-]{1,95}$/', $id) !== 1) {
            throw new RuntimeException("Identyfikator linku publicznego {$id} jest nieprawidłowy.");
        }
        if ($label === '' || strlen($label) > 80) {
            throw new RuntimeException("Etykieta linku publicznego {$id} jest nieprawidłowa.");
        }
        if ($href === '' || strlen($href) > 255) {
            throw new RuntimeException("Adres linku publicznego {$id} jest nieprawidłowy.");
        }
        if (isset($this->items[$id])) {
            throw new RuntimeException("Link publiczny {$id} został już zarejestrowany.");
        }

        $this->items[$id] = [
            'id' => $id,
            'label' => $label,
            'href' => $href,
            'area' => $area,
            'order' => $order,
        ];
    }

    /**
     * @param array<string, string|array{label?: string, main?: bool, footer?: bool, area?: string}> $settings
     * @return list<array{
     *     id: string,
     *     label: string,
     *     default_label: string,
     *     href: string,
     *     area: string,
     *     order: int,
     *     show_main: bool,
     *     show_footer: bool
     * }>
     */
    public function items(array $settings = []): array
    {
        $items = [];
        foreach ($this->items as $id => $item) {
            $defaultArea = in_array($item['area'], ['main', 'footer'], true) ? $item['area'] : 'none';
            $showMain = $defaultArea === 'main';
            $showFooter = $defaultArea === 'footer';
            $label = $item['label'];
            $setting = $settings[$id] ?? null;

            if (is_string($setting)) {
                $area = in_array($setting, ['none', 'main', 'footer'], true) ? $setting : 'none';
                $showMain = $area === 'main';
                $showFooter = $area === 'footer';
            } elseif (is_array($setting)) {
                $customLabel = trim((string) ($setting['label'] ?? ''));
                if ($customLabel !== '' && strlen($customLabel) <= 80) {
                    $label = $customLabel;
                }
                $showMain = (bool) ($setting['main'] ?? false);
                $showFooter = (bool) ($setting['footer'] ?? false);
                if (!$showMain && !$showFooter && isset($setting['area']) && is_string($setting['area'])) {
                    $area = in_array($setting['area'], ['none', 'main', 'footer'], true) ? $setting['area'] : 'none';
                    $showMain = $area === 'main';
                    $showFooter = $area === 'footer';
                }
            }

            $item['default_label'] = $item['label'];
            $item['label'] = $label;
            $item['show_main'] = $showMain;
            $item['show_footer'] = $showFooter;
            $item['area'] = $showMain ? 'main' : ($showFooter ? 'footer' : 'none');
            $items[] = $item;
        }

        usort(
            $items,
            static fn (array $left, array $right): int => [$left['area'], $left['order'], $left['label']]
                <=> [$right['area'], $right['order'], $right['label']]
        );

        return $items;
    }
}
