<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Core;

final class RichTextSanitizer
{
    private const ALLOWED_TAGS = '<p><br><strong><em><u><s><ul><ol><li><blockquote><h2><h3>';

    public function sanitize(string $html): string
    {
        $html = preg_replace(
            '#<(script|style|iframe|object|embed|svg|math)\b[^>]*>.*?</\1\s*>#is',
            '',
            $html
        ) ?? '';
        $html = str_ireplace(
            ['<div>', '</div>', '<div><br></div>'],
            ['<p>', '</p>', '<p><br></p>'],
            trim($html)
        );
        $html = strip_tags($html, self::ALLOWED_TAGS);
        $html = preg_replace('/<([a-z0-9]+)\b[^>]*>/i', '<$1>', $html) ?? '';
        $html = preg_replace('/<br\s*\/?>/i', '<br>', $html) ?? '';

        return trim($html);
    }
}
