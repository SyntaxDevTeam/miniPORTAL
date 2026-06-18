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
            throw new \InvalidArgumentException('Plik YAML jest za duży. Limit translatora to 256 KB.');
        }

        $yaml = trim($yaml);
        if ($yaml === '') {
            throw new \InvalidArgumentException('Wklej treść YAML albo wybierz plik .yml.');
        }

        set_error_handler(static function (int $severity, string $message): bool {
            throw new \RuntimeException($message, $severity);
        });

        try {
            $parsed = Spyc::YAMLLoadString($yaml);
        } finally {
            restore_error_handler();
        }

        if (!is_array($parsed)) {
            throw new \InvalidArgumentException('YAML musi zawierać mapę kategorii i wiadomości.');
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
            $this->setPath($result, $item['path'], is_scalar($value) ? (string) $value : '');
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function dump(array $data): string
    {
        $yaml = Spyc::YAMLDump($data, 2, false, true);

        return rtrim($yaml) . "\n";
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

            if (!is_scalar($value) && $value !== null) {
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
