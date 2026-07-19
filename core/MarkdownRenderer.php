<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Core;

final class MarkdownRenderer
{
    public function render(string $markdown): string
    {
        $lines = preg_split('/\R/u', str_replace("\t", '    ', trim($markdown))) ?: [];

        return $this->renderBlocks($lines);
    }

    /**
     * @param list<string> $lines
     */
    private function renderBlocks(array $lines): string
    {
        $html = [];
        $count = count($lines);

        for ($index = 0; $index < $count;) {
            $line = $lines[$index];

            if (trim($line) === '') {
                $index++;
                continue;
            }

            if (preg_match('/^ {0,3}```([a-zA-Z0-9_-]*)\s*$/', $line, $match) === 1) {
                $language = $match[1];
                $code = [];
                $index++;
                while ($index < $count && preg_match('/^ {0,3}```\s*$/', $lines[$index]) !== 1) {
                    $code[] = $lines[$index];
                    $index++;
                }
                $index += $index < $count ? 1 : 0;
                $class = $language !== ''
                    ? ' class="language-' . $this->escape($language) . '"'
                    : '';
                $html[] = '<pre><code' . $class . '>'
                    . $this->escape(implode("\n", $code))
                    . '</code></pre>';
                continue;
            }

            if (preg_match('/^ {0,3}(#{1,6})\s+(.+?)\s*#*\s*$/', $line, $match) === 1) {
                $level = strlen($match[1]);
                $html[] = "<h{$level}>" . $this->renderInline($match[2]) . "</h{$level}>";
                $index++;
                continue;
            }

            if (preg_match('/^ {0,3}((\*\s*){3,}|(-\s*){3,}|(_\s*){3,})$/', $line) === 1) {
                $html[] = '<hr>';
                $index++;
                continue;
            }

            if (str_starts_with(ltrim($line), '>')) {
                $quote = [];
                while ($index < $count && preg_match('/^ {0,3}>\s?(.*)$/', $lines[$index], $match) === 1) {
                    $quote[] = $match[1];
                    $index++;
                }
                $html[] = '<blockquote>' . $this->renderBlocks($quote) . '</blockquote>';
                continue;
            }

            if ($this->isTableHeader($lines, $index)) {
                $headers = $this->tableCells($line);
                $index += 2;
                $rows = [];
                while ($index < $count && str_contains($lines[$index], '|') && trim($lines[$index]) !== '') {
                    $rows[] = $this->tableCells($lines[$index]);
                    $index++;
                }
                $html[] = $this->renderTable($headers, $rows);
                continue;
            }

            if ($this->listMatch($line) !== null) {
                $first = $this->listMatch($line);
                $ordered = $first['ordered'];
                $tag = $ordered ? 'ol' : 'ul';
                $items = [];
                while ($index < $count) {
                    $item = $this->listMatch($lines[$index]);
                    if ($item === null || $item['ordered'] !== $ordered) {
                        break;
                    }
                    $items[] = $item;
                    $index++;
                }
                $listClass = array_any($items, static fn (array $item): bool => $item['task'])
                    ? ' class="task-list"'
                    : '';
                $list = "<{$tag}{$listClass}>";
                foreach ($items as $item) {
                    $class = $item['task'] ? ' class="task-list-item"' : '';
                    $checkbox = $item['task']
                        ? '<input type="checkbox" disabled' . ($item['checked'] ? ' checked' : '') . '> '
                        : '';
                    $list .= '<li' . $class . '>' . $checkbox . $this->renderInline($item['text']) . '</li>';
                }
                $html[] = $list . "</{$tag}>";
                continue;
            }

            $paragraph = [$line];
            $index++;
            while (
                $index < $count
                && trim($lines[$index]) !== ''
                && !$this->startsBlock($lines, $index)
            ) {
                $paragraph[] = $lines[$index];
                $index++;
            }
            $html[] = '<p>' . implode(
                "<br>\n",
                array_map(fn (string $line): string => $this->renderInline(rtrim($line)), $paragraph)
            ) . '</p>';
        }

        return implode("\n", $html);
    }

    /**
     * @param list<string> $lines
     */
    private function startsBlock(array $lines, int $index): bool
    {
        $line = $lines[$index];

        return preg_match('/^ {0,3}(#{1,6})\s+/', $line) === 1
            || preg_match('/^ {0,3}```/', $line) === 1
            || preg_match('/^ {0,3}>/', $line) === 1
            || preg_match('/^ {0,3}((\*\s*){3,}|(-\s*){3,}|(_\s*){3,})$/', $line) === 1
            || $this->listMatch($line) !== null
            || $this->isTableHeader($lines, $index);
    }

    /**
     * @return array{ordered: bool, task: bool, checked: bool, text: string}|null
     */
    private function listMatch(string $line): ?array
    {
        if (preg_match('/^ {0,3}([-+*]|\d+[.)])\s+(.+)$/', $line, $match) !== 1) {
            return null;
        }

        $text = $match[2];
        $task = preg_match('/^\[([ xX])]\s+(.+)$/', $text, $taskMatch) === 1;

        return [
            'ordered' => ctype_digit($match[1][0]),
            'task' => $task,
            'checked' => $task && strtolower($taskMatch[1]) === 'x',
            'text' => $task ? $taskMatch[2] : $text,
        ];
    }

    /**
     * @param list<string> $lines
     */
    private function isTableHeader(array $lines, int $index): bool
    {
        if (!isset($lines[$index + 1]) || !str_contains($lines[$index], '|')) {
            return false;
        }

        $separator = trim($lines[$index + 1], " \t|");
        if ($separator === '') {
            return false;
        }

        foreach (explode('|', $separator) as $cell) {
            if (preg_match('/^\s*:?-{3,}:?\s*$/', $cell) !== 1) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<string>
     */
    private function tableCells(string $line): array
    {
        return array_map('trim', explode('|', trim($line, " \t|")));
    }

    /**
     * @param list<string> $headers
     * @param list<list<string>> $rows
     */
    private function renderTable(array $headers, array $rows): string
    {
        $html = '<div class="table-responsive"><table><thead><tr>';
        foreach ($headers as $header) {
            $html .= '<th>' . $this->renderInline($header) . '</th>';
        }
        $html .= '</tr></thead><tbody>';
        foreach ($rows as $row) {
            $html .= '<tr>';
            foreach (array_keys($headers) as $column) {
                $html .= '<td>' . $this->renderInline($row[$column] ?? '') . '</td>';
            }
            $html .= '</tr>';
        }

        return $html . '</tbody></table></div>';
    }

    private function renderInline(string $text): string
    {
        $tokens = [];
        $token = static function (string $html) use (&$tokens): string {
            $key = "\x1A" . count($tokens) . "\x1A";
            $tokens[$key] = $html;
            return $key;
        };

        $text = preg_replace_callback(
            '/\\\\([\\\\`*{}\[\]()#+\-.!_>~|])/',
            fn (array $match): string => $token($this->escape($match[1])),
            $text
        ) ?? $text;
        $text = preg_replace_callback(
            '/`([^`\n]+)`/',
            fn (array $match): string => $token('<code>' . $this->escape($match[1]) . '</code>'),
            $text
        ) ?? $text;
        $text = preg_replace_callback(
            '/\[!\[([^\]]*)]\((\S+?)(?:\s+"[^"]*")?\)]\((\S+?)(?:\s+"[^"]*")?\)/',
            fn (array $match): string => $this->linkedImageToken(
                $token,
                $match[1],
                $match[2],
                $match[3]
            ),
            $text
        ) ?? $text;
        $text = preg_replace_callback(
            '/!\[([^\]]*)]\((\S+?)(?:\s+"[^"]*")?\)\{([^}]*)}/',
            fn (array $match): string => $this->safeLinkToken($token, $match[1], $match[2], true, $match[3]),
            $text
        ) ?? $text;
        $text = preg_replace_callback(
            '/!\[([^\]]*)]\((\S+?)(?:\s+"[^"]*")?\)/',
            fn (array $match): string => $this->safeLinkToken($token, $match[1], $match[2], true),
            $text
        ) ?? $text;
        $text = preg_replace_callback(
            '/\[([^\]]+)]\((\S+?)(?:\s+"[^"]*")?\)/',
            fn (array $match): string => $this->safeLinkToken($token, $match[1], $match[2], false),
            $text
        ) ?? $text;
        $text = preg_replace_callback(
            '~<(https?://[^ >]+|mailto:[^ >]+)>~i',
            fn (array $match): string => $token($this->link($match[1], $match[1])),
            $text
        ) ?? $text;
        $text = preg_replace_callback(
            '~(?<!["\'(])(https?://[^\s<]+[^<\s.,;:!?])~i',
            fn (array $match): string => $token($this->link($match[1], $match[1])),
            $text
        ) ?? $text;

        $text = $this->escape($text);
        $text = preg_replace('/\*\*(.+?)\*\*|__(.+?)__/s', '<strong>$1$2</strong>', $text) ?? $text;
        $text = preg_replace('/~~(.+?)~~/s', '<s>$1</s>', $text) ?? $text;
        $text = preg_replace('/(?<!\*)\*([^*\n]+)\*(?!\*)|(?<!_)_([^_\n]+)_(?!_)/', '<em>$1$2</em>', $text) ?? $text;

        return strtr($text, $tokens);
    }

    private function safeLinkToken(
        callable $token,
        string $label,
        string $url,
        bool $image,
        string $imageAttributes = ''
    ): string
    {
        if (!$this->isSafeUrl($url)) {
            return $label;
        }
        if ($image) {
            return $token($this->image($url, $label, $imageAttributes));
        }

        return $token($this->link($label, $url));
    }

    private function linkedImageToken(
        callable $token,
        string $label,
        string $imageUrl,
        string $linkUrl
    ): string {
        if (!$this->isSafeUrl($imageUrl) || !$this->isSafeUrl($linkUrl)) {
            return $label;
        }

        $external = preg_match('~^https?://~i', $linkUrl) === 1;

        return $token(
            '<a href="' . $this->escape($linkUrl) . '"'
            . ($external ? ' rel="nofollow noopener noreferrer"' : '')
            . '>' . $this->image($imageUrl, $label) . '</a>'
        );
    }

    private function image(string $url, string $label, string $attributes = ''): string
    {
        $classes = ['content-image'];
        $width = null;
        if ($attributes !== '') {
            foreach (preg_split('/\s+/', trim($attributes), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $attribute) {
                $class = ltrim($attribute, '.');
                if (in_array($class, [
                    'content-image-left',
                    'content-image-right',
                    'content-image-center',
                    'content-image-wide',
                    'content-image-original',
                    'content-image-small',
                    'content-image-medium',
                    'content-image-large',
                    'content-image-custom',
                ], true)) {
                    $classes[] = $class;
                    continue;
                }
                if (preg_match('/^width=([0-9]+(?:\.[0-9]+)?)(px|rem|em|%|vw)$/i', $attribute, $match) === 1) {
                    $candidate = $this->safeImageWidth((float) $match[1], strtolower($match[2]));
                    if ($candidate !== null) {
                        $width = $candidate;
                        $classes[] = 'content-image-custom';
                    }
                }
            }
        }
        if (count($classes) === 1) {
            $classes[] = 'content-image-original';
        }

        return '<img src="' . $this->escape($url) . '" alt="' . $this->escape($label)
            . '" class="' . $this->escape(implode(' ', array_unique($classes)))
            . ($width !== null ? '" style="--content-image-width:' . $width . ';' : '')
            . '" loading="lazy">';
    }

    private function safeImageWidth(float $number, string $unit): ?string
    {
        $limits = [
            'px' => [1, 2400],
            'rem' => [0.1, 160],
            'em' => [0.1, 160],
            '%' => [1, 100],
            'vw' => [1, 100],
        ];
        if (!isset($limits[$unit])) {
            return null;
        }
        [$min, $max] = $limits[$unit];
        if ($number < $min || $number > $max) {
            return null;
        }

        return rtrim(rtrim(number_format($number, 3, '.', ''), '0'), '.') . $unit;
    }

    private function link(string $label, string $url): string
    {
        $external = preg_match('~^https?://~i', $url) === 1;

        return '<a href="' . $this->escape($url) . '"'
            . ($external ? ' rel="nofollow noopener noreferrer"' : '')
            . '>' . $this->escape($label) . '</a>';
    }

    private function isSafeUrl(string $url): bool
    {
        if (preg_match('~^(?:https?://|mailto:|/|#|\./|\.\./|index\.php(?:\?|$))~i', $url) === 1) {
            return !str_starts_with($url, '//');
        }

        return !str_contains($url, ':')
            && !str_starts_with($url, '//')
            && preg_match('~^[a-zA-Z0-9][a-zA-Z0-9._~/?#%&=+\-]*$~', $url) === 1;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
