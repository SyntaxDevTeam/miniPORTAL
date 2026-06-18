<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\PluginTranslator;

final class MinecraftFormatPreview
{
    private const LEGACY_COLORS = [
        '0' => '#000000',
        '1' => '#0000AA',
        '2' => '#00AA00',
        '3' => '#00AAAA',
        '4' => '#AA0000',
        '5' => '#AA00AA',
        '6' => '#FFAA00',
        '7' => '#AAAAAA',
        '8' => '#555555',
        '9' => '#5555FF',
        'a' => '#55FF55',
        'b' => '#55FFFF',
        'c' => '#FF5555',
        'd' => '#FF55FF',
        'e' => '#FFFF55',
        'f' => '#FFFFFF',
    ];

    private const MINI_COLORS = [
        'black' => '#000000',
        'dark_blue' => '#0000AA',
        'dark_green' => '#00AA00',
        'dark_aqua' => '#00AAAA',
        'dark_red' => '#AA0000',
        'dark_purple' => '#AA00AA',
        'gold' => '#FFAA00',
        'gray' => '#AAAAAA',
        'dark_gray' => '#555555',
        'blue' => '#5555FF',
        'green' => '#55FF55',
        'aqua' => '#55FFFF',
        'red' => '#FF5555',
        'light_purple' => '#FF55FF',
        'yellow' => '#FFFF55',
        'white' => '#FFFFFF',
    ];

    /**
     * @return list<array{text: string, color: string, bold: bool, italic: bool, underline: bool, strikethrough: bool}>
     */
    public function segments(string $text): array
    {
        return $this->preview($text)['segments'];
    }

    /**
     * @return array{
     *     segments: list<array{text: string, color: string, bold: bool, italic: bool, underline: bool, strikethrough: bool}>,
     *     issues: list<string>,
     *     variables: list<string>
     * }
     */
    public function preview(string $text): array
    {
        $text = str_replace('§', '&', $text);
        $segments = [];
        $issues = [];
        $variables = [];
        $style = $this->emptyStyle();
        $miniStack = [];
        $buffer = '';
        $length = strlen($text);

        for ($i = 0; $i < $length; $i++) {
            $char = $text[$i];
            if (($char === '&' || $char === '§') && $i + 1 < $length) {
                $rgb = $this->readLegacyRgb($text, $i);
                if ($rgb !== null) {
                    $this->push($segments, $buffer, $style);
                    $style = $this->colorStyle($style, $rgb['color']);
                    $i += $rgb['length'] - 1;
                    continue;
                }

                $code = strtolower($text[$i + 1]);
                if (isset(self::LEGACY_COLORS[$code]) || in_array($code, ['k', 'l', 'm', 'n', 'o', 'r'], true)) {
                    $this->push($segments, $buffer, $style);
                    $style = $this->applyLegacy($style, $code);
                    $i++;
                    continue;
                }

                if ($code === '#' || $code === 'x') {
                    $issues[] = 'Niepoprawny albo niepełny kod koloru RGB legacy przy znaku ' . ($i + 1) . '.';
                }
            }

            if ($char === '<') {
                $end = strpos($text, '>', $i);
                if ($end !== false) {
                    $rawTag = substr($text, $i + 1, $end - $i - 1);
                    $tag = strtolower($rawTag);
                    $mini = $this->miniMessageStyle($style, $tag);
                    if ($mini !== null) {
                        $this->push($segments, $buffer, $style);
                        if ($mini['mode'] === 'open') {
                            $miniStack[] = ['tag' => $mini['tag'], 'style' => $style];
                            $style = $mini['style'];
                        } elseif ($mini['mode'] === 'close') {
                            $last = array_pop($miniStack);
                            if (!is_array($last) || $last['tag'] !== $mini['tag']) {
                                $issues[] = 'Nieprawidłowe zamknięcie tagu </' . $mini['tag'] . '> przy znaku ' . ($i + 1) . '.';
                                if (is_array($last)) {
                                    $miniStack[] = $last;
                                }
                            } else {
                                $style = $last['style'];
                            }
                        } else {
                            $miniStack = [];
                            $style = $this->emptyStyle();
                        }
                        $i = $end;
                        continue;
                    }

                    if (preg_match('/^#[0-9a-fA-F]*$/', $rawTag) === 1) {
                        $issues[] = 'Niepoprawny kolor MiniMessage <' . $rawTag . '> przy znaku ' . ($i + 1) . '.';
                    } elseif ($this->looksLikeVariable($rawTag)) {
                        $variables[] = '<' . $rawTag . '>';
                    }
                }
            }

            $buffer .= $char;
        }

        $this->push($segments, $buffer, $style);
        foreach (array_reverse($miniStack) as $entry) {
            if (is_array($entry) && isset($entry['tag'])) {
                $issues[] = 'Brak zamknięcia tagu <' . $entry['tag'] . '>.';
            }
        }

        return [
            'segments' => $segments !== [] ? $segments : [['text' => '', ...$this->emptyStyle()]],
            'issues' => array_values(array_unique($issues)),
            'variables' => array_values(array_unique($variables)),
        ];
    }

    /**
     * @return array{color: string, bold: bool, italic: bool, underline: bool, strikethrough: bool}
     */
    private function emptyStyle(): array
    {
        return [
            'color' => '',
            'bold' => false,
            'italic' => false,
            'underline' => false,
            'strikethrough' => false,
        ];
    }

    /**
     * @param array{color: string, bold: bool, italic: bool, underline: bool, strikethrough: bool} $style
     * @return array{color: string, bold: bool, italic: bool, underline: bool, strikethrough: bool}
     */
    private function colorStyle(array $style, string $color): array
    {
        $style['color'] = $color;

        return $style;
    }

    /**
     * @param array{color: string, bold: bool, italic: bool, underline: bool, strikethrough: bool} $style
     * @return array{color: string, bold: bool, italic: bool, underline: bool, strikethrough: bool}
     */
    private function applyLegacy(array $style, string $code): array
    {
        if (isset(self::LEGACY_COLORS[$code])) {
            return $this->colorStyle($this->emptyStyle(), self::LEGACY_COLORS[$code]);
        }
        if ($code === 'r') {
            return $this->emptyStyle();
        }
        if ($code === 'l') {
            $style['bold'] = true;
        }
        if ($code === 'o') {
            $style['italic'] = true;
        }
        if ($code === 'n') {
            $style['underline'] = true;
        }
        if ($code === 'm') {
            $style['strikethrough'] = true;
        }

        return $style;
    }

    /**
     * @param array{color: string, bold: bool, italic: bool, underline: bool, strikethrough: bool} $style
     * @return array{color: string, bold: bool, italic: bool, underline: bool, strikethrough: bool}|null
     */
    private function applyMiniMessage(array $style, string $tag): ?array
    {
        $mini = $this->miniMessageStyle($style, $tag);
        if ($mini === null) {
            return null;
        }
        if ($mini['mode'] === 'reset') {
            return $this->emptyStyle();
        }

        return $mini['style'];
    }

    /**
     * @param array{color: string, bold: bool, italic: bool, underline: bool, strikethrough: bool} $style
     * @return array{mode: 'open'|'close'|'reset', tag: string, style: array{color: string, bold: bool, italic: bool, underline: bool, strikethrough: bool}}|null
     */
    private function miniMessageStyle(array $style, string $tag): ?array
    {
        $closing = str_starts_with($tag, '/');
        $tag = ltrim($tag, '/');
        $canonical = $this->canonicalMiniTag($tag);
        if ($canonical === null) {
            return null;
        }
        if ($closing) {
            return ['mode' => 'close', 'tag' => $canonical, 'style' => $style];
        }
        if ($tag === 'reset') {
            return ['mode' => 'reset', 'tag' => 'reset', 'style' => $this->emptyStyle()];
        }
        if (preg_match('/^#[0-9a-f]{6}$/', $tag) === 1) {
            return ['mode' => 'open', 'tag' => $canonical, 'style' => $this->colorStyle($style, strtoupper($tag))];
        }
        if (isset(self::MINI_COLORS[$tag])) {
            return ['mode' => 'open', 'tag' => $canonical, 'style' => $this->colorStyle($style, self::MINI_COLORS[$tag])];
        }
        if (in_array($tag, ['bold', 'b'], true)) {
            $style['bold'] = true;
            return ['mode' => 'open', 'tag' => $canonical, 'style' => $style];
        }
        if (in_array($tag, ['italic', 'i'], true)) {
            $style['italic'] = true;
            return ['mode' => 'open', 'tag' => $canonical, 'style' => $style];
        }
        if (in_array($tag, ['underlined', 'underline', 'u'], true)) {
            $style['underline'] = true;
            return ['mode' => 'open', 'tag' => $canonical, 'style' => $style];
        }
        if (in_array($tag, ['strikethrough', 'st'], true)) {
            $style['strikethrough'] = true;
            return ['mode' => 'open', 'tag' => $canonical, 'style' => $style];
        }

        return null;
    }

    private function canonicalMiniTag(string $tag): ?string
    {
        if ($tag === 'reset') {
            return 'reset';
        }
        if (preg_match('/^#[0-9a-f]{6}$/', $tag) === 1) {
            return $tag;
        }
        if (isset(self::MINI_COLORS[$tag])) {
            return $tag;
        }

        return [
            'b' => 'bold',
            'bold' => 'bold',
            'i' => 'italic',
            'italic' => 'italic',
            'u' => 'underlined',
            'underline' => 'underlined',
            'underlined' => 'underlined',
            'st' => 'strikethrough',
            'strikethrough' => 'strikethrough',
        ][$tag] ?? null;
    }

    private function looksLikeVariable(string $tag): bool
    {
        return preg_match('/^\/?[a-zA-Z][a-zA-Z0-9_.:-]*$/', $tag) === 1;
    }

    /**
     * @return array{color: string, length: int}|null
     */
    private function readLegacyRgb(string $text, int $offset): ?array
    {
        $prefix = $text[$offset];
        $candidate = substr($text, $offset, 8);
        if (preg_match('/^[' . preg_quote($prefix, '/') . ']#[0-9a-fA-F]{6}$/', $candidate) === 1) {
            return ['color' => strtoupper(substr($candidate, 1)), 'length' => 8];
        }

        $candidate = substr($text, $offset, 14);
        if (preg_match('/^[' . preg_quote($prefix, '/') . ']x([' . preg_quote($prefix, '/') . '][0-9a-fA-F]){6}$/', $candidate) !== 1) {
            return null;
        }

        $hex = '';
        for ($i = 2; $i < 14; $i += 2) {
            $hex .= $candidate[$i + 1];
        }

        return ['color' => '#' . strtoupper($hex), 'length' => 14];
    }

    /**
     * @param list<array{text: string, color: string, bold: bool, italic: bool, underline: bool, strikethrough: bool}> $segments
     * @param array{color: string, bold: bool, italic: bool, underline: bool, strikethrough: bool} $style
     */
    private function push(array &$segments, string &$buffer, array $style): void
    {
        if ($buffer === '') {
            return;
        }

        $segments[] = ['text' => $buffer, ...$style];
        $buffer = '';
    }
}
