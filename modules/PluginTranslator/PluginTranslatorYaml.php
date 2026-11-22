<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\PluginTranslator;

use core\lib\Spyc;

require_once __DIR__ . '/../../core/libs/Spyc.php';

final class PluginTranslatorYaml
{
    private const MAX_BYTES = 262144;

    /**
     * @return array<string, mixed>
     */
    public function parse(string $yaml): array
    {
        if (strlen($yaml) > self::MAX_BYTES) {
            throw new \InvalidArgumentException('The YAML file is too large. The translator limit is 256 KB.');
        }

        $yaml = trim($yaml);
        if ($yaml === '') {
            throw new \InvalidArgumentException('Choose a .yml/.yaml file to open the editor.');
        }

        set_error_handler(static function (int $severity, string $message): bool {
            throw new \RuntimeException($message, $severity);
        });

        try {
            $parsed = Spyc::YAMLLoadString($yaml);
        } catch (\Throwable $exception) {
            throw new \InvalidArgumentException('The YAML file could not be parsed. Check indentation, keys and special characters.', 0, $exception);
        } finally {
            restore_error_handler();
        }

        if (!is_array($parsed)) {
            throw new \InvalidArgumentException('The YAML file must contain a map of keys and messages.');
        }

        return $parsed;
    }

    /**
     * @param array<string, mixed> $data
     * @return list<array{token: string, path: list<string>, label: string, value: string}>
     */
    public function flatten(array $data): array
    {
        $items = [];
        $this->flattenNode($data, [], $items);

        return $items;
    }

    /**
     * @param array<string, mixed> $source
     * @param array<string, mixed> $translations
     * @return array<string, mixed>
     */
    public function translated(array $source, array $translations): array
    {
        $result = $source;
        foreach ($this->flatten($source) as $item) {
            $value = $translations[$item['token']] ?? $item['value'];
            if (is_string($value) && trim($value) !== '') {
                $this->setPath($result, $item['path'], $value);
            }
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function dump(array $data): string
    {
        try {
            $yaml = Spyc::YAMLDump($data, 2, false, true);
        } catch (\Throwable $exception) {
            throw new \RuntimeException('The translated YAML could not be exported.', 0, $exception);
        }

        return rtrim($yaml) . "\n";
    }

    /**
     * @param list<array{token: string, path: list<string>, label: string, value: string}> $items
     * @param array<string, mixed> $translations
     */
    public function translatedCount(array $items, array $translations): int
    {
        $count = 0;
        foreach ($items as $item) {
            $value = $translations[$item['token']] ?? '';
            if (is_scalar($value) && trim((string) $value) !== '') {
                $count++;
            }
        }

        return $count;
    }

    /** Technical tokens are compared as multisets; named tokens may be reordered. */
    public function protectedTokens(string $text, bool $sorted = true): array
    {
        preg_match_all('/%[A-Za-z_][A-Za-z0-9_]*%|%(?:[0-9]+\\$)?[-+0-9.]*[sdif]|\\{\\{[^{}\\r\\n]+\\}\\}|\\{[A-Za-z0-9_.-]+\\}|<[^<>\\r\\n]+>|(?:&|§)#[0-9a-fA-F]{6}|(?:&|§)[0-9a-fA-Fk-oK-OrRxX]/u', $text, $matches);
        $tokens = $matches[0];
        if ($sorted) {
            sort($tokens, SORT_STRING);
        }
        return $tokens;
    }

    /** @return list<array{text:string,kind:string}> Tokens in source order for presentation. */
    public function tokenDetails(string $text): array
    {
        $tokens = array_values(array_unique($this->protectedTokens($text, false)));
        $result = [];
        $lookup = array_fill_keys(array_map('strtolower', $tokens), true);
        foreach ($tokens as $token) {
            $kind = 'placeholder';
            if (str_starts_with($token, '&') || str_starts_with($token, '§')) {
                $kind = 'legacy';
            } elseif (str_starts_with($token, '<')) {
                $tag = strtolower(trim($token, '<>/'));
                $name = explode(':', $tag, 2)[0];
                $known = ['black', 'dark_blue', 'dark_green', 'dark_aqua', 'dark_red',
                    'dark_purple', 'gold', 'gray', 'grey', 'dark_gray', 'dark_grey',
                    'blue', 'green', 'aqua', 'red', 'light_purple', 'yellow', 'white',
                    'bold', 'b', 'italic', 'i', 'em', 'underlined', 'underline', 'u',
                    'strikethrough', 'st', 'obfuscated', 'obf', 'reset', 'color', 'colour',
                    'c', 'gradient', 'rainbow', 'transition', 'hover', 'click', 'font',
                    'insertion', 'insert', 'newline', 'br', 'keybind', 'translatable',
                    'translate', 'tr', 'lang', 'lang_or', 'selector', 'score', 'nbt',
                    'pride', 'shadow', 'sprite', 'head'];
                $paired = isset($lookup['</' . $name . '>']);
                if (str_starts_with($token, '</') || str_starts_with($name, '#') || in_array($name, $known, true) || $paired) {
                    $kind = 'minimessage';
                }
            }
            $result[] = ['text' => $token, 'kind' => $kind];
        }
        return $result;
    }

    /** @return array<string, string> */
    public function qualityIssues(array $items, array $translations): array
    {
        $issues = [];
        foreach ($items as $item) {
            $value = $translations[$item['token']] ?? '';
            if (is_string($value) && trim($value) !== ''
                && $this->protectedTokens($item['value']) !== $this->protectedTokens($value)) {
                $issues[$item['token']] = 'Keep the original placeholders, tags and colour codes (including repeated tokens).';
            }
        }
        return $issues;
    }

    public function assertQuality(array $items, array $translations): void
    {
        $issues = $this->qualityIssues($items, $translations);
        if ($issues !== []) {
            $labels = array_column($items, 'label', 'token');
            $token = array_key_first($issues);
            throw new \InvalidArgumentException($labels[$token] . ': ' . $issues[$token]);
        }
    }

    /**
     * @param array<mixed> $node
     * @param list<string> $path
     * @param list<array{token: string, path: list<string>, label: string, value: string}> $items
     */
    private function flattenNode(array $node, array $path, array &$items): void
    {
        foreach ($node as $key => $value) {
            $segment = (string) $key;
            $currentPath = [...$path, $segment];
            if (is_array($value)) {
                $this->flattenNode($value, $currentPath, $items);
                continue;
            }

            if (!is_string($value) || trim($value) === '') {
                continue;
            }

            $label = implode('.', $currentPath);
            $items[] = [
                'token' => $this->token($currentPath),
                'path' => $currentPath,
                'label' => $label,
                'value' => $value === null ? '' : (string) $value,
            ];
        }
    }

    /**
     * @param list<string> $path
     */
    private function token(array $path): string
    {
        return rtrim(strtr(base64_encode(json_encode($path, JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
    }

    /**
     * @param array<string, mixed> $data
     * @param list<string> $path
     */
    private function setPath(array &$data, array $path, string $value): void
    {
        $cursor = &$data;
        $last = array_pop($path);
        if ($last === null) {
            return;
        }

        foreach ($path as $segment) {
            if (!isset($cursor[$segment]) || !is_array($cursor[$segment])) {
                $cursor[$segment] = [];
            }
            $cursor = &$cursor[$segment];
        }

        $cursor[$last] = $value;
    }
}
