<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\Widgets;

final class WidgetLayout
{
    /**
     * @param list<array<string, mixed>> $sections
     * @param list<Widget> $widgets
     * @param array<string, WidgetDefinition> $definitions
     * @return list<array<string, mixed>>
     */
    public function attach(array $sections, array $widgets, array $definitions = []): array
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
            $data = $this->themeData($widget, $definitions);
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

    /**
     * @param array<string, WidgetDefinition> $definitions
     * @return array<string, scalar>
     */
    private function themeData(Widget $widget, array $definitions): array
    {
        $data = $widget->toThemeData();
        $definition = $definitions[$widget->definitionId] ?? null;
        if (!$definition instanceof WidgetDefinition) {
            return $data;
        }

        return array_merge($data, $definition->runtimeData($widget));
    }
}
