<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Core;

final class RichTextSanitizer
{
    private const ALLOWED_TAGS = '<p><br><strong><em><u><s><ul><ol><li><blockquote><h2><h3><table><thead><tbody><tr><th><td>';
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
        $images = [];
        $html = preg_replace_callback('/<img\b[^>]*>/i', function (array $match) use (&$images): string {
            $image = $this->sanitizeImage($match[0]);
            if ($image === '') {
                return '';
            }
            $token = "\x1AIMG" . count($images) . "\x1A";
            $images[$token] = $image;

            return $token;
        }, $html) ?? '';
        $html = str_ireplace(
            ['<div>', '</div>', '<div><br></div>'],
            ['<p>', '</p>', '<p><br></p>'],
            trim($html)
        );
        $html = strip_tags($html, self::ALLOWED_TAGS);
        $html = preg_replace('/<([a-z0-9]+)\b[^>]*>/i', '<$1>', $html) ?? '';
        $html = preg_replace('/<br\s*\/?>/i', '<br>', $html) ?? '';

        return trim(strtr($html, $images));
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
