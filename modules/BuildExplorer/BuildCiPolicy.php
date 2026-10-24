<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\BuildExplorer;

use SyntaxDevTeam\Cms\Core\Request;

/** Project credentials never grant access to retention or legacy CI endpoints. */
final class BuildCiPolicy
{
    public const NATIVE_EXTENSIONS = ['exe', 'msi', 'dmg', 'appimage', 'zip', 'tar.gz'];
    public const NATIVE_MAX_BYTES = 94371840; // 90 MiB; fits the public transport ceiling.

    public function __construct(private readonly array $projects = []) {}

    public static function fromFile(string $path): self
    {
        if (!is_file($path)) { return new self(); }
        $data = json_decode((string) file_get_contents($path), true, 16, JSON_THROW_ON_ERROR);
        if (!is_array($data) || ($data !== [] && array_is_list($data))) {
            throw new \RuntimeException('Invalid project CI policy configuration.');
        }
        return new self($data);
    }

    /** @return array{extensions:list<string>,max_bytes:int}|null */
    public function authorize(Request $request, string $project): ?array
    {
        $policy = $this->projects[$project] ?? null;
        $provided = $request->header('X-Build-Token');
        if ($provided === '' && preg_match('/^Bearer\s+(.+)$/i', $request->header('Authorization'), $matches) === 1) {
            $provided = trim($matches[1]);
        }
        if (!is_array($policy) || strlen($provided) < 32
            || !is_string($policy['token_sha256'] ?? null)
            || !hash_equals($policy['token_sha256'], hash('sha256', $provided))) {
            return null;
        }
        $extensions = $policy['extensions'] ?? [];
        $maxBytes = $policy['max_bytes'] ?? 0;
        if (!is_array($extensions) || $extensions === []
            || array_diff($extensions, self::NATIVE_EXTENSIONS) !== []
            || !is_int($maxBytes) || $maxBytes < 1 || $maxBytes > self::NATIVE_MAX_BYTES) {
            return null;
        }
        return ['extensions' => array_values($extensions), 'max_bytes' => $maxBytes];
    }

    public static function extension(string $filename): string
    {
        return str_ends_with(strtolower($filename), '.tar.gz') ? 'tar.gz' : strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    }
}
