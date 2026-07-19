<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Core;

use RuntimeException;

final class AdminMenuRegistry
{
    /** @var array<string, int> */
    private array $sections = [
        'Przestrzeń robocza' => 10,
        'Core' => 20,
        'Treść' => 30,
        'Narzędzia' => 40,
        'Dedykowane' => 50,
        'System' => 60,
    ];

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

    public function defineSection(string $name, int $order): void
    {
        $name = trim($name);
        if ($name === '' || $order < 0) {
            throw new RuntimeException('Sekcja menu wymaga nazwy i nieujemnej kolejności.');
        }
        $this->sections[$name] = $order;
    }

    /** @return list<array{section:string,label:string,path:string,icon:string,permission:string,order:int}> */
    public function items(): array
    {
        return $this->items;
    }

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

        $sectionPosition = [];
        foreach ($items as $position => $item) {
            $section = $item['section'];
            $sectionPosition[$section] ??= $position;
        }

        usort(
            $items,
            fn (array $left, array $right): int => [
                $this->sections[$left['section']] ?? 500,
                $sectionPosition[$left['section']],
                $left['order'],
                $left['label'],
            ] <=> [
                $this->sections[$right['section']] ?? 500,
                $sectionPosition[$right['section']],
                $right['order'],
                $right['label'],
            ]
        );

        return $items;
    }
}
