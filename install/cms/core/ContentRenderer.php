<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Core;

final class ContentRenderer
{
    public const HTML = 'html';
    public const MARKDOWN = 'markdown';

    public function render(string $content, string $format): string
    {
        return $this->normalizeFormat($format) === self::MARKDOWN
            ? (new MarkdownRenderer())->render($content)
            : (new RichTextSanitizer())->sanitize($content);
    }

    public function prepareForStorage(string $content, string $format): string
    {
        return $this->normalizeFormat($format) === self::MARKDOWN
            ? trim($content)
            : (new RichTextSanitizer())->sanitize($content);
    }

    public function normalizeFormat(string $format): string
    {
        return $format === self::MARKDOWN ? self::MARKDOWN : self::HTML;
    }
}
