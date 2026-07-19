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
        if ($this->enabled && is_dir($this->directory) && !is_dir($this->tagDirectory())) {
            @mkdir($this->tagDirectory(), 0770, true);
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
        $cached = $this->readFresh($metadataFile, $contentFile);
        if ($cached !== null) {
            return $cached;
        }

        $lock = $this->lock($hash);
        try {
            $cached = $this->readFresh($metadataFile, $contentFile);
            if ($cached !== null) {
                return $cached;
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
                $this->writeTagIndex($hash, $metadata['tags']);
            } catch (\Throwable) {
                @unlink($contentFile);
                @unlink($metadataFile);
                $this->removeHashFromAllTags($hash);
            }

            return $content;
        } finally {
            $this->unlock($lock);
        }
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

        $hashes = $this->hashesForTags($tags);
        $removed = 0;
        foreach ($hashes as $hash) {
            if ($this->removeEntry($hash)) {
                $removed++;
            }
        }

        if ($hashes !== []) {
            return $removed;
        }

        foreach (glob($this->directory . '/*.json') ?: [] as $metadataFile) {
            $metadata = $this->metadata($metadataFile);
            $entryTags = is_array($metadata['tags'] ?? null) ? $metadata['tags'] : [];
            if (array_intersect($tags, $entryTags) === []) {
                continue;
            }
            $hash = basename($metadataFile, '.json');
            if ($this->removeEntry($hash)) {
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
        foreach (glob($this->directory . '/*.{cache,json,lock}', GLOB_BRACE) ?: [] as $file) {
            if (is_file($file) && !is_link($file) && @unlink($file)) {
                $removed++;
            }
        }
        foreach (glob($this->tagDirectory() . '/*.tag') ?: [] as $file) {
            if (is_file($file) && !is_link($file) && @unlink($file)) {
                $removed++;
            }
        }

        return $removed;
    }

    public function stats(): array
    {
        $entries = 0;
        $expired = 0;
        $bytes = 0;
        foreach (glob($this->directory . '/*.json') ?: [] as $metadataFile) {
            if (!is_file($metadataFile) || is_link($metadataFile)) {
                continue;
            }
            $metadata = $this->metadata($metadataFile);
            $contentFile = $this->directory . '/' . basename($metadataFile, '.json') . '.cache';
            if ($metadata === null || !is_file($contentFile) || is_link($contentFile)) {
                continue;
            }
            $bytes += (int) filesize($contentFile);
            if ((int) ($metadata['expires_at'] ?? 0) < time()) {
                $expired++;
            } else {
                $entries++;
            }
        }

        return [
            'enabled' => $this->usable(),
            'entries' => $entries,
            'expired' => $expired,
            'bytes' => $bytes,
            'ttl' => max(1, $this->ttl),
            'writable' => is_dir($this->directory) && is_writable($this->directory),
            'directory' => $this->directory,
        ];
    }

    private function usable(): bool
    {
        return $this->enabled && is_dir($this->directory) && is_writable($this->directory);
    }

    private function readFresh(string $metadataFile, string $contentFile): ?string
    {
        $metadata = $this->metadata($metadataFile);
        if (
            $metadata === null
            || !is_file($contentFile)
            || (int) ($metadata['expires_at'] ?? 0) < time()
        ) {
            return null;
        }
        $content = file_get_contents($contentFile);

        return is_string($content) ? $content : null;
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

    private function tagDirectory(): string
    {
        return rtrim($this->directory, '/') . '/tags';
    }

    /**
     * @param list<string> $tags
     */
    private function writeTagIndex(string $hash, array $tags): void
    {
        if (!is_dir($this->tagDirectory())) {
            @mkdir($this->tagDirectory(), 0770, true);
        }
        $this->removeHashFromAllTags($hash);
        foreach ($tags as $tag) {
            $file = $this->tagFile($tag);
            $handle = fopen($file, 'c+');
            if ($handle === false) {
                continue;
            }
            try {
                if (!flock($handle, LOCK_EX)) {
                    continue;
                }
                $contents = stream_get_contents($handle);
                $hashes = $contents !== '' ? preg_split('/\R/', trim($contents)) ?: [] : [];
                $hashes = array_values(array_unique(array_filter(
                    [...$hashes, $hash],
                    static fn (string $value): bool => preg_match('/^[a-f0-9]{64}$/', $value) === 1
                )));
                ftruncate($handle, 0);
                rewind($handle);
                fwrite($handle, implode("\n", $hashes) . ($hashes !== [] ? "\n" : ''));
            } finally {
                flock($handle, LOCK_UN);
                fclose($handle);
            }
            @chmod($file, 0660);
        }
    }

    /**
     * @param list<string> $tags
     * @return list<string>
     */
    private function hashesForTags(array $tags): array
    {
        $hashes = [];
        foreach ($tags as $tag) {
            $file = $this->tagFile($tag);
            if (!is_file($file) || is_link($file)) {
                continue;
            }
            foreach (preg_split('/\R/', trim((string) file_get_contents($file))) ?: [] as $hash) {
                if (preg_match('/^[a-f0-9]{64}$/', $hash) === 1) {
                    $hashes[] = $hash;
                }
            }
        }

        return array_values(array_unique($hashes));
    }

    private function removeEntry(string $hash): bool
    {
        $metadataFile = $this->directory . '/' . $hash . '.json';
        $existed = is_file($metadataFile) || is_file($this->directory . '/' . $hash . '.cache');
        @unlink($this->directory . '/' . $hash . '.cache');
        @unlink($metadataFile);
        $this->removeHashFromAllTags($hash);

        return $existed;
    }

    private function removeHashFromAllTags(string $hash): void
    {
        foreach (glob($this->tagDirectory() . '/*.tag') ?: [] as $file) {
            if (!is_file($file) || is_link($file)) {
                continue;
            }
            $handle = fopen($file, 'c+');
            if ($handle === false) {
                continue;
            }
            try {
                if (!flock($handle, LOCK_EX)) {
                    continue;
                }
                $hashes = preg_split('/\R/', trim((string) stream_get_contents($handle))) ?: [];
                $hashes = array_values(array_filter($hashes, static fn (string $value): bool => $value !== $hash));
                ftruncate($handle, 0);
                rewind($handle);
                if ($hashes !== []) {
                    fwrite($handle, implode("\n", $hashes) . "\n");
                }
            } finally {
                flock($handle, LOCK_UN);
                fclose($handle);
            }
        }
    }

    private function tagFile(string $tag): string
    {
        return $this->tagDirectory() . '/' . hash('sha256', $tag) . '.tag';
    }

    /** @return resource|null */
    private function lock(string $hash): mixed
    {
        $lockFile = $this->directory . '/' . $hash . '.lock';
        $handle = fopen($lockFile, 'c');
        if ($handle === false) {
            return null;
        }
        if (!flock($handle, LOCK_EX)) {
            fclose($handle);
            return null;
        }

        return $handle;
    }

    /** @param resource|null $handle */
    private function unlock(mixed $handle): void
    {
        if (is_resource($handle)) {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
