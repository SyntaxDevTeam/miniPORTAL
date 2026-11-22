<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Templates\Shared;

/** Generic compact link collection for homepage providers. */
trait LinkStripTrait
{
    private function renderLinkStrip(array $section): void
    {
        if (($section['items'] ?? []) === []) { return; }
        $escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        $asset = '/templates/shared/assets/css/link-strip.css';
        $version = filemtime(dirname(__DIR__) . '/shared/assets/css/link-strip.css');
        echo '<link rel="stylesheet" href="' . $asset . '?v=' . $version . '">';
        echo '<section class="link-strip" id="' . $escape($section['key']) . '" aria-label="' . $escape($section['title']) . '"><div class="container link-strip-inner">';
        echo '<div class="link-strip-heading"><h2>' . $escape($section['title']) . '</h2>';
        echo '<button class="link-strip-toggle" type="button" hidden>Pause animation</button></div>';
        echo '<div class="link-strip-viewport"><div class="link-strip-track"><ul class="link-strip-list">';
        foreach ($section['items'] as $item) {
            $href = (string) ($item['href'] ?? '');
            $parts = parse_url($href);
            if (!is_array($parts) || !in_array(strtolower($parts['scheme'] ?? ''), ['https', 'http'], true)
                || isset($parts['user']) || isset($parts['pass']) || filter_var($href, FILTER_VALIDATE_URL) === false) { continue; }
            echo '<li><a href="' . $escape($href) . '" rel="external noopener"><span class="link-strip-name">' . $escape($item['title']) . '<span aria-hidden="true"> ↗</span></span>';
            echo '<span class="link-strip-description"><span class="link-strip-description-text">' . $escape($item['description']) . '</span></span></a></li>';
        }
        echo '</ul></div></div></div></section>';
        if (count($section['items']) >= 4) {
            $script = '/templates/shared/assets/js/link-strip.js';
            echo '<script src="' . $script . '?v=' . filemtime(dirname(__DIR__) . '/shared/assets/js/link-strip.js') . '" defer></script>';
        }
    }
}
