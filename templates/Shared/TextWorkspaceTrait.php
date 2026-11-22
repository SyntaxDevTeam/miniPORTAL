<?php

declare(strict_types=1);
namespace SyntaxDevTeam\Cms\Templates\Shared;

trait TextWorkspaceTrait
{
    public function render_text_workspace(array $items, array $values): void
    {
        $escape = static fn (string $text): string => htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        $highlight = static function (string $text, array $tokens) use ($escape): string {
            if ($tokens === []) {
                return $escape($text);
            }
            $kinds = [];
            foreach ($tokens as $token) {
                $kinds[$token['text']] = in_array($token['kind'], ['placeholder', 'minimessage', 'legacy'], true) ? $token['kind'] : 'placeholder';
            }
            $parts = preg_split('~(' . implode('|', array_map(static fn (string $token): string => preg_quote($token, '~'), array_keys($kinds))) . ')~u', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
            $html = '';
            foreach ($parts ?: [$text] as $part) {
                $html .= isset($kinds[$part]) ? '<mark class="text-token text-token--' . $kinds[$part] . '">' . $escape($part) . '</mark>' : $escape($part);
            }
            return $html;
        };
        require __DIR__ . '/views/text-workspace.php';
    }
}
