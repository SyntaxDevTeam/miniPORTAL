<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\Widgets;

final class WidgetLayout
{
    /**
     * @param list<array<string, mixed>> $sections
     * @param list<Widget> $widgets
     * @return list<array<string, mixed>>
     */
    public function attach(array $sections, array $widgets): array
    {
        if ($sections === []) {
            return [];
        }

        foreach ($sections as &$section) {
            $section['widgets_before'] = [];
            $section['widgets_after'] = [];
            $section['widgets_aside'] = [];
        }
        unset($section);

        $lastIndex = array_key_last($sections);
        foreach ($widgets as $widget) {
            $data = $widget->toThemeData();
            if ($widget->placement === 'homepage_start') {
                $sections[0]['widgets_before'][] = $data;
                continue;
            }
            if ($widget->placement === 'before_footer' && $lastIndex !== null) {
                $sections[$lastIndex]['widgets_after'][] = $data;
                continue;
            }

            foreach ($sections as &$section) {
                if ($widget->placement === 'hero_aside' && ($section['type'] ?? '') === 'hero') {
                    $section['widgets_aside'][] = $data;
                    break;
                }
                if ($widget->placement === 'after_hero' && ($section['type'] ?? '') === 'hero') {
                    $section['widgets_after'][] = $data;
                    break;
                }
                if ($widget->targetSectionKey !== '' && ($section['key'] ?? '') === $widget->targetSectionKey) {
                    $area = $widget->placement === 'before_section' ? 'widgets_before' : 'widgets_after';
                    $section[$area][] = $data;
                    break;
                }
            }
            unset($section);
        }

        return $sections;
    }
}
