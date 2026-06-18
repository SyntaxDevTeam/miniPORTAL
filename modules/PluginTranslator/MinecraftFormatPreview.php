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
        $text = str_replace('§', '&', $text);
        $segments = [];
        $style = $this->emptyStyle();
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
            }

            if ($char === '<') {
                $end = strpos($text, '>', $i);
                if ($end !== false) {
                    $tag = strtolower(substr($text, $i + 1, $end - $i - 1));
                    $newStyle = $this->applyMiniMessage($style, $tag);
                    if ($newStyle !== null) {
                        $this->push($segments, $buffer, $style);
                        $style = $newStyle;
                        $i = $end;
                        continue;
                    }
                }
            }

            $buffer .= $char;
        }

        $this->push($segments, $buffer, $style);

        return $segments !== [] ? $segments : [['text' => '', ...$this->emptyStyle()]];
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
        $tag = ltrim($tag, '/');
        if ($tag === 'reset') {
            return $this->emptyStyle();
        }
        if (preg_match('/^#[0-9a-f]{6}$/', $tag) === 1) {
            return $this->colorStyle($style, strtoupper($tag));
        }
        if (isset(self::MINI_COLORS[$tag])) {
            return $this->colorStyle($style, self::MINI_COLORS[$tag]);
        }
        if (in_array($tag, ['bold', 'b'], true)) {
            $style['bold'] = true;
            return $style;
        }
        if (in_array($tag, ['italic', 'i'], true)) {
            $style['italic'] = true;
            return $style;
        }
        if (in_array($tag, ['underlined', 'underline', 'u'], true)) {
            $style['underline'] = true;
            return $style;
        }
        if (in_array($tag, ['strikethrough', 'st'], true)) {
            $style['strikethrough'] = true;
            return $style;
        }

        return null;
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
