<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Core;

use RuntimeException;

final class AdminMenuRegistry
{
    /**
     * @var list<array{
     *     section: string,
     *     label: string,
     *     path: string,
     *     icon: string,
     *     permission: string,
     *     order: int
     * }>
     */
    private array $items = [];

    public function add(
        string $section,
        string $label,
        string $path,
        string $icon,
        string $permission,
        int $order = 100,
    ): void {
        if ($label === '' || $path === '' || $permission === '') {
            throw new RuntimeException('Pozycja menu wymaga etykiety, ścieżki i uprawnienia.');
        }

        foreach ($this->items as $item) {
            if ($item['path'] === $path) {
                throw new RuntimeException("Pozycja menu dla ścieżki {$path} jest już zarejestrowana.");
            }
        }

        $this->items[] = [
            'section' => $section,
            'label' => $label,
            'path' => $path,
            'icon' => strtoupper(substr($icon, 0, 2)),
            'permission' => $permission,
            'order' => $order,
        ];
    }

    /**
     * @param list<string> $permissions
     * @return list<array{
     *     section: string,
     *     label: string,
     *     path: string,
     *     icon: string,
     *     permission: string,
     *     order: int
     * }>
     */
    public function visibleFor(array $permissions): array
    {
        $items = array_values(array_filter(
            $this->items,
            static fn (array $item): bool => in_array('*', $permissions, true)
                || in_array($item['permission'], $permissions, true)
        ));

        usort(
            $items,
            static fn (array $left, array $right): int => [$left['order'], $left['label']]
                <=> [$right['order'], $right['label']]
        );

        return $items;
    }
}
