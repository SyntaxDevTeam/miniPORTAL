<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\CorePages;

/** Resolves stable references using public data owned by optional modules. */
final class ContactPerson
{
    public static function resolve(array $sections, array $people): array
    {
        foreach ($sections as &$section) {
            if (!isset($section['items'])) continue;
            $items = [];
            foreach ($section['items'] as $item) {
                $ref = $item['contact_ref'] ?? '';
                if ($ref !== '') {
                    $person = $people[$ref] ?? null;
                    if ($person === null) continue;
                    $buttons = [];
                    $email = trim((string) ($person['email'] ?? ''));
                    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $buttons[] = ['label' => $email, 'url' => 'mailto:' . $email];
                    }
                    $buttons[] = ['label' => 'Profil', 'url' => $person['url']];
                    $item = array_replace($item, [
                        'title' => $person['name'], 'item_kind' => 'person', 'icon_key' => 'person',
                        'page_slug' => '', 'buttons' => $buttons,
                        'button_label' => implode("\n", array_column($buttons, 'label')),
                        'button_url' => implode("\n", array_column($buttons, 'url')),
                    ]);
                }
                $items[] = $item;
            }
            $section['items'] = $items;
        }
        unset($section);
        return $sections;
    }
}
