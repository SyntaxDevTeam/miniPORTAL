<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Core;

use JsonException;

final class FileTemplateCache implements TemplateCacheInterface
{
    public function __construct(
        private readonly string $directory,
        private readonly bool $enabled = true,
        private readonly int $ttl = 300,
    ) {
        if ($this->enabled && !is_dir($this->directory)) {
            @mkdir($this->directory, 0770, true);
        }
    }

    public function remember(string $namespace, string $key, callable $renderer, array $tags = []): string
    {
        if (!$this->usable()) {
            return $renderer();
        }

        $hash = hash('sha256', $namespace . "\0" . $key);
        $contentFile = $this->directory . '/' . $hash . '.cache';
        $metadataFile = $this->directory . '/' . $hash . '.json';
        $metadata = $this->metadata($metadataFile);
        if (
            $metadata !== null
            && is_file($contentFile)
            && (int) ($metadata['expires_at'] ?? 0) >= time()
        ) {
            $content = file_get_contents($contentFile);
            if (is_string($content)) {
                return $content;
            }
        }

        $content = $renderer();
        $metadata = [
            'namespace' => $this->normalize($namespace),
            'tags' => $this->normalizeTags($tags),
            'created_at' => time(),
            'expires_at' => time() + max(1, $this->ttl),
        ];
        try {
            $encoded = json_encode($metadata, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
            $this->atomicWrite($contentFile, $content);
            $this->atomicWrite($metadataFile, $encoded);
        } catch (\Throwable) {
            @unlink($contentFile);
            @unlink($metadataFile);
        }

        return $content;
    }

    public function invalidateTags(array $tags): int
    {
        if (!$this->usable()) {
            return 0;
        }
        $tags = $this->normalizeTags($tags);
        if ($tags === []) {
            return 0;
        }

        $removed = 0;
        foreach (glob($this->directory . '/*.json') ?: [] as $metadataFile) {
            $metadata = $this->metadata($metadataFile);
            $entryTags = is_array($metadata['tags'] ?? null) ? $metadata['tags'] : [];
            if (array_intersect($tags, $entryTags) === []) {
                continue;
            }
            $hash = basename($metadataFile, '.json');
            @unlink($this->directory . '/' . $hash . '.cache');
            if (@unlink($metadataFile)) {
                $removed++;
            }
        }

        return $removed;
    }

    public function clear(): int
    {
        if (!is_dir($this->directory)) {
            return 0;
        }

        $removed = 0;
        foreach (glob($this->directory . '/*.{cache,json}', GLOB_BRACE) ?: [] as $file) {
            if (is_file($file) && !is_link($file) && @unlink($file)) {
                $removed++;
            }
        }

        return $removed;
    }

    public function stats(): array
    {
        $entries = 0;
        $bytes = 0;
        foreach (glob($this->directory . '/*.cache') ?: [] as $file) {
            if (!is_file($file) || is_link($file)) {
                continue;
            }
            $entries++;
            $bytes += (int) filesize($file);
        }

        return [
            'enabled' => $this->usable(),
            'entries' => $entries,
            'bytes' => $bytes,
            'directory' => $this->directory,
        ];
    }

    private function usable(): bool
    {
        return $this->enabled && is_dir($this->directory) && is_writable($this->directory);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function metadata(string $file): ?array
    {
        if (!is_file($file) || is_link($file)) {
            return null;
        }
        try {
            $data = json_decode((string) file_get_contents($file), true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return is_array($data) ? $data : null;
    }

    /**
     * @param list<string> $tags
     * @return list<string>
     */
    private function normalizeTags(array $tags): array
    {
        $normalized = [];
        foreach ($tags as $tag) {
            $tag = $this->normalize($tag);
            if ($tag !== '') {
                $normalized[] = $tag;
            }
        }

        return array_values(array_unique($normalized));
    }

    private function normalize(string $value): string
    {
        $value = strtolower(trim($value));

        return preg_match('/^[a-z0-9][a-z0-9._:-]{0,80}$/', $value) === 1 ? $value : '';
    }

    private function atomicWrite(string $file, string $content): void
    {
        $temporary = tempnam($this->directory, 'tmp-');
        if ($temporary === false) {
            throw new \RuntimeException('Nie można utworzyć pliku tymczasowego cache.');
        }
        try {
            if (file_put_contents($temporary, $content, LOCK_EX) === false || !rename($temporary, $file)) {
                throw new \RuntimeException('Nie można zapisać wpisu cache.');
            }
            @chmod($file, 0660);
        } finally {
            if (is_file($temporary)) {
                @unlink($temporary);
            }
        }
    }
}
