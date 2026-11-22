<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Core;

/** Public display-only images; never fetched by the server. */
final class PublicImageUrl
{
    public static function valid(string $url): bool
    {
        if ($url === '') { return true; }
        if (strlen($url) > 500 || preg_match('/[\x00-\x20\x7F\\\\]/', $url)) { return false; }
        if (str_starts_with($url, '/') && !str_starts_with($url, '//')) { return true; }
        $parts = parse_url($url);
        return filter_var($url, FILTER_VALIDATE_URL) !== false && is_array($parts)
            && strtolower($parts['scheme'] ?? '') === 'https' && !isset($parts['user']) && !isset($parts['pass']);
    }
}
