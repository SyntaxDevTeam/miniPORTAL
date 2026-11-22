<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Core;

final class RichTextSanitizer
{
    private const ALLOWED_ELEMENTS = [
        'p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'del', 'ul', 'ol', 'li',
        'blockquote', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'table', 'thead',
        'tbody', 'tfoot', 'tr', 'th', 'td', 'div', 'span', 'hr', 'pre', 'code',
        'a', 'img', 'details', 'summary', 'sub', 'sup', 'kbd', 'mark',
    ];

    private const IMAGE_CLASSES = [
        'content-image',
        'content-image-left',
        'content-image-right',
        'content-image-center',
        'content-image-wide',
        'content-image-original',
        'content-image-small',
        'content-image-medium',
        'content-image-large',
        'content-image-custom',
    ];

    public function sanitize(string $html): string
    {
        $html = preg_replace(
            '#<(script|style|iframe|object|embed|svg|math)\b[^>]*>.*?</\1\s*>#is',
            '',
            $html
        ) ?? '';
        // Use the same allowlist for visual HTML and HTML embedded in Markdown.
        $html = strip_tags(trim($html), self::ALLOWED_ELEMENTS);

        return trim(preg_replace_callback(
            '~</?[a-z][a-z0-9]*\b(?:[^\x22\x27<>]|\x22[^\x22]*\x22|\x27[^\x27]*\x27)*>~i',
            fn (array $match): string => $this->sanitizeMarkdownTag($match[0]),
            $html
        ) ?? '');
    }

    /** Sanitize one raw HTML tag embedded in Markdown, without filtering generated Markdown. */
    public function sanitizeMarkdownTag(string $tag): string
    {
        if (preg_match('~^<(/?)([a-z][a-z0-9]*)\b~i', $tag, $match) !== 1) {
            return '';
        }
        $name = strtolower($match[2]);
        if (!in_array($name, self::ALLOWED_ELEMENTS, true)) {
            return '';
        }
        if ($match[1] === '/') {
            return in_array($name, ['img', 'br', 'hr'], true) ? '' : '</' . $name . '>';
        }
        if ($name === 'img') {
            return $this->sanitizeImage($tag);
        }
        if ($name === 'a') {
            $attributes = $this->attributes($tag);
            $anchor = html_entity_decode(trim($attributes['id'] ?? $attributes['name'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $anchorAttribute = preg_match('/^[a-zA-Z][a-zA-Z0-9_-]{0,99}$/D', $anchor) === 1
                ? ' id="' . $anchor . '"' : '';
            $href = html_entity_decode(trim($attributes['href'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $safe = $href !== ''
                && preg_match('/[\x00-\x20\x7F\\\\]/', $href) !== 1
                && !str_starts_with($href, '//')
                && (preg_match('~^(?:https?://|mailto:|/|#|\./|\.\./)~i', $href) === 1
                    || (preg_match('~^[a-zA-Z0-9][a-zA-Z0-9._\~/?#%&=+\-]*$~', $href) === 1));

            return '<a' . $anchorAttribute . ($safe ? ' href="' . $this->escape($href) . '" rel="nofollow noopener noreferrer"' : '') . '>';
        }

        if ($name === 'code') {
            $attributes = $this->attributes($tag);
            $class = html_entity_decode($attributes['class'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
            return preg_match('/^language-[a-zA-Z0-9_-]{1,40}$/D', $class) === 1
                ? '<code class="' . $this->escape($class) . '">' : '<code>';
        }

        $alignment = '';
        if (in_array($name, ['div', 'p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote', 'th', 'td'], true)) {
            $attributes = $this->attributes($tag);
            $align = strtolower(trim(html_entity_decode($attributes['align'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8')));
            if (in_array($align, ['left', 'center', 'right', 'justify'], true)) {
                $alignment = ' align="' . $align . '"';
            }
        }

        return '<' . $name . $alignment . '>';
    }

    private function sanitizeImage(string $tag): string
    {
        $attributes = $this->attributes($tag);
        $src = html_entity_decode(trim((string) ($attributes['src'] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (!$this->isSafeImageUrl($src)) {
            return '';
        }

        $classes = array_values(array_intersect(
            preg_split('/\s+/', (string) ($attributes['class'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: [],
            self::IMAGE_CLASSES
        ));
        if (!in_array('content-image', $classes, true)) {
            array_unshift($classes, 'content-image');
        }
        $sizeClasses = [
            'content-image-original',
            'content-image-small',
            'content-image-medium',
            'content-image-large',
            'content-image-wide',
            'content-image-custom',
        ];
        if (!array_any($classes, static fn (string $class): bool => in_array($class, $sizeClasses, true))) {
            $classes[] = 'content-image-original';
        }

        $alt = html_entity_decode(trim((string) ($attributes['alt'] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $width = $this->customWidth((string) ($attributes['style'] ?? ''));
        if ($width === null && preg_match('/^[0-9]{1,4}$/D', $attributes['width'] ?? '') === 1) {
            $width = $this->customWidth('--content-image-width:' . (int) $attributes['width'] . 'px');
        }
        if ($width !== null && !in_array('content-image-custom', $classes, true)) {
            $classes[] = 'content-image-custom';
        }

        return '<img src="' . $this->escape($src) . '" alt="'
            . $this->escape(substr($alt, 0, 255))
            . '" class="' . $this->escape(implode(' ', array_unique($classes)))
            . ($width !== null ? '" style="--content-image-width:' . $width . ';' : '')
            . '" loading="lazy">';
    }

    /** @return array<string, string> */
    private function attributes(string $tag): array
    {
        $attributes = [];
        preg_match_all(
            '/([a-zA-Z0-9:-]+)\s*=\s*("([^"]*)"|\'([^\']*)\'|([^\s"\'>]+))/',
            $tag,
            $matches,
            PREG_SET_ORDER
        );
        foreach ($matches as $match) {
            $attributes[strtolower($match[1])] = $match[3] !== ''
                ? $match[3]
                : ($match[4] !== '' ? $match[4] : $match[5]);
        }

        return $attributes;
    }

    private function isSafeImageUrl(string $url): bool
    {
        if ($url === '' || preg_match('/[\x00-\x1F\x7F]/', $url) === 1 || str_starts_with($url, '//')) {
            return false;
        }

        return preg_match('~^https?://~i', $url) === 1
            || preg_match('/^\/[A-Za-z0-9\/_.,%+\-]+(?:\?[A-Za-z0-9._~%&=+\-]*)?$/', $url) === 1;
    }

    private function customWidth(string $style): ?string
    {
        if (preg_match('/--content-image-width\s*:\s*([0-9]+(?:\.[0-9]+)?)(px|rem|em|%|vw)\s*;?/i', $style, $match) !== 1) {
            return null;
        }
        $number = (float) $match[1];
        $unit = strtolower($match[2]);
        $limits = [
            'px' => [1, 2400],
            'rem' => [0.1, 160],
            'em' => [0.1, 160],
            '%' => [1, 100],
            'vw' => [1, 100],
        ];
        [$min, $max] = $limits[$unit];
        if ($number < $min || $number > $max) {
            return null;
        }
        $formatted = rtrim(rtrim(number_format($number, 3, '.', ''), '0'), '.');

        return $formatted . $unit;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
