<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Core;

use DateTimeImmutable;
use DateTimeInterface;
use Throwable;

final class SeoIndex
{
    /** @var array<string, array{path:string,title:string,description:string,lastmod:string,priority:float,changefreq:string,type:string}> */
    private array $entries = [];

    public function __construct(private readonly string $publicUrl)
    {
    }

    public function add(
        string $path,
        string $title,
        string $description = '',
        ?string $lastModified = null,
        float $priority = 0.5,
        string $changeFrequency = 'weekly',
        string $type = 'WebPage',
    ): void {
        $path = $this->normalizePath($path);
        if ($path === '') {
            return;
        }

        $priority = max(0.0, min(1.0, $priority));
        $changeFrequency = in_array($changeFrequency, [
            'always', 'hourly', 'daily', 'weekly', 'monthly', 'yearly', 'never',
        ], true) ? $changeFrequency : 'weekly';

        $this->entries[$path] = [
            'path' => $path,
            'title' => trim($title),
            'description' => trim($description),
            'lastmod' => $this->normalizeDate($lastModified),
            'priority' => $priority,
            'changefreq' => $changeFrequency,
            'type' => $type !== '' ? $type : 'WebPage',
        ];
    }

    /**
     * @return list<array{path:string,url:string,title:string,description:string,lastmod:string,priority:float,changefreq:string,type:string}>
     */
    public function entries(): array
    {
        $entries = [];
        foreach ($this->entries as $entry) {
            $entries[] = [
                ...$entry,
                'url' => $this->absoluteUrl($entry['path']),
            ];
        }

        usort($entries, static fn (array $left, array $right): int => [$left['path']] <=> [$right['path']]);

        return $entries;
    }

    public function sitemapXml(): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($this->entries() as $entry) {
            $xml .= "  <url>\n";
            $xml .= '    <loc>' . $this->xml($entry['url']) . "</loc>\n";
            if ($entry['lastmod'] !== '') {
                $xml .= '    <lastmod>' . $this->xml($entry['lastmod']) . "</lastmod>\n";
            }
            $xml .= '    <changefreq>' . $entry['changefreq'] . "</changefreq>\n";
            $xml .= '    <priority>' . number_format($entry['priority'], 1, '.', '') . "</priority>\n";
            $xml .= "  </url>\n";
        }
        $xml .= "</urlset>\n";

        return $xml;
    }

    public function robotsTxt(string $robots = 'index, follow'): string
    {
        $lines = [
            'User-agent: *',
            'Disallow: /admin',
            'Disallow: /api',
        ];
        if ($this->publicUrl !== '') {
            $lines[] = 'Sitemap: ' . $this->absoluteUrl('/sitemap.xml');
        }

        return implode("\n", $lines) . "\n";
    }

    private function normalizePath(string $path): string
    {
        $path = trim($path);
        if ($path === '' || preg_match('/[\x00-\x1F\x7F]/', $path) === 1) {
            return '';
        }
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            $parts = parse_url($path);
            if (!is_array($parts) || !isset($parts['path'])) {
                return '';
            }
            $path = (string) $parts['path'] . (isset($parts['query']) ? '?' . (string) $parts['query'] : '');
        }
        if (!str_starts_with($path, '/')) {
            $path = '/' . $path;
        }

        return $path !== '/' ? rtrim($path, '/') : '/';
    }

    private function normalizeDate(?string $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        try {
            return (new DateTimeImmutable($value))->format(DateTimeInterface::ATOM);
        } catch (Throwable) {
            return '';
        }
    }

    private function absoluteUrl(string $path): string
    {
        if ($this->publicUrl === '') {
            return $path;
        }

        return rtrim($this->publicUrl, '/') . ($path === '/' ? '/' : $path);
    }

    private function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
