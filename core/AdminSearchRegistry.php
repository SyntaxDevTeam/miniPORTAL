<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Core;

use RuntimeException;

final class AdminSearchRegistry
{
    /** @var array<string, array{id:string,label:string,description:string,href:string,section:string,keywords:string,permission:string,order:int}> */
    private array $items = [];

    /** @param list<string> $keywords */
    public function add(
        string $id,
        string $label,
        string $description,
        string $href,
        array $keywords,
        string $permission,
        string $section = 'Panel',
        int $order = 100,
    ): void {
        $id = trim($id);
        $label = trim($label);
        $href = trim($href);
        if (preg_match('/^[a-z][a-z0-9_.-]{1,95}$/', $id) !== 1 || $label === '' || $href === '' || $permission === '') {
            throw new RuntimeException('Nieprawidłowy wpis indeksu wyszukiwania panelu.');
        }
        if (isset($this->items[$id])) {
            throw new RuntimeException("Wpis wyszukiwania {$id} został już zarejestrowany.");
        }
        $terms = array_values(array_unique(array_filter(array_map(
            static fn (mixed $keyword): string => trim((string) $keyword),
            [...$keywords, $label, $description, $section]
        ))));
        $this->items[$id] = [
            'id' => $id,
            'label' => substr($label, 0, 120),
            'description' => substr(trim($description), 0, 240),
            'href' => substr($href, 0, 500),
            'section' => substr(trim($section) ?: 'Panel', 0, 80),
            'keywords' => implode(' ', $terms),
            'permission' => $permission,
            'order' => max(0, min(65535, $order)),
        ];
    }

    /** @param list<array{section:string,label:string,path:string,icon:string,permission:string,order:int}> $items */
    public function importMenu(array $items): void
    {
        foreach ($items as $item) {
            $id = 'menu.' . substr(hash('sha256', $item['path']), 0, 20);
            if (isset($this->items[$id])) {
                continue;
            }
            $this->add(
                $id,
                $item['label'],
                'Otwórz sekcję ' . $item['label'],
                'index.php?route=' . rawurlencode($item['path']),
                [$item['path'], $item['icon']],
                $item['permission'],
                $item['section'],
                $item['order'],
            );
        }
    }

    /** @param list<string> $permissions @return list<array{id:string,label:string,description:string,href:string,section:string,keywords:string,order:int}> */
    public function visibleFor(array $permissions): array
    {
        $items = array_values(array_filter(
            $this->items,
            static fn (array $item): bool => in_array('*', $permissions, true)
                || in_array($item['permission'], $permissions, true)
        ));
        usort($items, static fn (array $a, array $b): int => [$a['order'], $a['section'], $a['label']] <=> [$b['order'], $b['section'], $b['label']]);

        return array_map(static function (array $item): array {
            unset($item['permission']);
            return $item;
        }, $items);
    }
}
