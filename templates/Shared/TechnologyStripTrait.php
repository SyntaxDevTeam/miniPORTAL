<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Templates\Shared;

use SyntaxDevTeam\Cms\Core\ContentRenderer;
use SyntaxDevTeam\Cms\Core\PublicImageUrl;

trait TechnologyStripTrait
{
    private function renderTechnologyStrip(array $section): void
    {
        if (($section['items'] ?? []) === []) { return; }
        $escape = static fn (string $text): string => htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        $version = filemtime(dirname(__DIR__) . '/shared/assets/css/technologies.css');
        echo '<link rel="stylesheet" href="/templates/shared/assets/css/technologies.css?v=' . $version . '">';
        echo '<section class="technology-strip" id="' . $escape($section['key']) . '" aria-label="' . $escape($section['title']) . '"><div class="container">';
        if (($section['eyebrow'] ?? '') !== '') { echo '<p class="technology-eyebrow">' . $escape($section['eyebrow']) . '</p>'; }
        echo '<h2>' . $escape($section['title']) . '</h2>';
        if (($section['content_html'] ?? '') !== '') {
            echo '<div class="technology-intro">' . (new ContentRenderer())->render($section['content_html'], $section['content_format'] ?? 'html') . '</div>';
        }
        echo '<div class="technology-marquee" data-technology-marquee><div class="technology-track"><ul class="technology-list">';
        foreach ($section['items'] as $item) {
            $href = '';
            if (($item['page_slug'] ?? '') !== '') {
                $href = '/p/' . rawurlencode($item['page_slug']);
            } else {
                $candidate = trim(explode("\n", $item['button_url'] ?? '')[0]);
                if ($candidate !== '' && PublicImageUrl::valid($candidate)) { $href = $candidate; }
            }
            $tag = $href !== '' ? 'a' : 'div';
            echo '<li><' . $tag . ' class="technology-tile"' . ($href !== '' ? ' href="' . $escape($href) . '"' : '') . '>';
            $logo = (string) ($item['logo_url'] ?? '');
            echo '<span class="technology-logo">';
            if ($logo !== '' && PublicImageUrl::valid($logo)) {
                echo '<img src="' . $escape($logo) . '" alt="" width="64" height="64" loading="lazy" decoding="async" referrerpolicy="no-referrer">';
            } else {
                echo '<span aria-hidden="true">' . $escape(mb_substr($item['title'], 0, 2)) . '</span>';
            }
            echo '</span><span class="technology-name">' . $escape($item['title']) . '</span></' . $tag . '></li>';
        }
        echo '</ul></div></div>';
        $scriptVersion = filemtime(dirname(__DIR__) . '/shared/assets/js/technologies.js');
        echo '</div></section><script src="/templates/shared/assets/js/technologies.js?v=' . $scriptVersion . '" defer></script>';
    }
}
