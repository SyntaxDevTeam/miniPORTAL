<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Core;

use RuntimeException;

/** Presentation-independent settings for the public footer. */
final readonly class PublicFooterSettings
{
    public const KEYS = ['public_footer_credit', 'public_footer_credit_visible', 'public_footer_links_enabled', 'public_footer_links'];

    public function __construct(private array $config = [])
    {
    }

    /** @return list<array{label: string, href: string}> */
    public static function parseLinks(string $text): array
    {
        if (!mb_check_encoding($text, 'UTF-8') || strlen($text) > 12000) {
            throw new RuntimeException('Lista linków stopki jest zbyt długa.');
        }
        $links = [];
        foreach (preg_split('/\R/u', trim($text)) ?: [] as $line) {
            if (trim($line) === '') {
                continue;
            }
            $parts = explode('|', $line, 2);
            $label = trim($parts[0]);
            $href = trim($parts[1] ?? '');
            if ($label === '' || mb_strlen($label, 'UTF-8') > 80 || strlen($href) > 1000 || !self::safeHref($href)) {
                throw new RuntimeException('Każdy link stopki wymaga etykiety do 80 znaków i adresu HTTPS, HTTP lub lokalnej ścieżki /…; oddziel je znakiem |.');
            }
            $links[] = ['label' => $label, 'href' => $href];
            if (count($links) > 12) {
                throw new RuntimeException('Stopka może zawierać maksymalnie 12 własnych linków.');
            }
        }
        return $links;
    }

    /** @return array<string, string> */
    public static function validate(array $input): array
    {
        $text = trim((string) ($input['public_footer_text'] ?? ''));
        $credit = trim((string) ($input['public_footer_credit'] ?? ''));
        if (mb_strlen($text, 'UTF-8') > 160 || mb_strlen($credit, 'UTF-8') > 240) {
            throw new RuntimeException('Hasło stopki może mieć do 160 znaków, a podpis do 240 znaków.');
        }
        $links = self::parseLinks((string) ($input['public_footer_links'] ?? ''));
        return [
            'public_footer_text' => $text,
            'public_footer_credit' => $credit,
            'public_footer_credit_visible' => ($input['public_footer_credit_visible'] ?? '1') === '1' ? '1' : '0',
            'public_footer_links_enabled' => ($input['public_footer_links_enabled'] ?? '0') === '1' ? '1' : '0',
            'public_footer_links' => implode("\n", array_map(static fn (array $link): string => $link['label'] . ' | ' . $link['href'], $links)),
        ];
    }

    /** Null preserves the theme's linked default credit; an empty string hides it. */
    public function credit(string $version): ?string
    {
        if (($this->config['public_footer_credit_visible'] ?? '1') !== '1') {
            return '';
        }
        $credit = trim((string) ($this->config['public_footer_credit'] ?? ''));
        return $credit === '' ? null : strtr($credit, ['{year}' => date('Y'), '{version}' => $version]);
    }

    /** Keep main navigation intact, replacing only public footer entries when enabled. */
    public function navigationPages(array $pages): array
    {
        if (($this->config['public_footer_links_enabled'] ?? '0') !== '1') {
            return $pages;
        }
        try {
            $links = self::parseLinks((string) ($this->config['public_footer_links'] ?? ''));
        } catch (RuntimeException) {
            return $pages;
        }
        $pages = array_values(array_filter($pages, static fn (array $page): bool => ($page['navigation_area'] ?? '') !== 'footer'));
        foreach ($links as $link) {
            $pages[] = ['navigation_area' => 'footer', 'navigation_label' => $link['label'], 'title' => $link['label'], 'href' => $link['href'], 'slug' => ''];
        }
        return $pages;
    }

    private static function safeHref(string $href): bool
    {
        if ($href === '' || preg_match('/[\x00-\x20\x7f\\\\]/', $href) === 1) {
            return false;
        }
        if (str_starts_with($href, '/') && !str_starts_with($href, '//')) {
            return true;
        }
        $parts = parse_url($href);
        return is_array($parts) && in_array(strtolower((string) ($parts['scheme'] ?? '')), ['https', 'http'], true)
            && !empty($parts['host']) && !isset($parts['user']) && !isset($parts['pass'])
            && filter_var($href, FILTER_VALIDATE_URL) !== false;
    }
}
