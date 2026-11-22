<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\Wikipedia;

/** Data-only navigation, built exclusively from the repository's access-filtered pages. */
final class WikiNavigation
{
    public static function language(WikiPage $page): string
    {
        return str_starts_with($page->slug, 'pl-') ? 'pl' : 'en';
    }

    public static function key(WikiPage $page): string
    {
        return self::language($page) === 'pl' ? substr($page->slug, 3) : $page->slug;
    }

    /** @param list<WikiPage> $pages */
    public static function model(string $name, string $slug, string $summary, array $pages, ?WikiPage $current = null, string $language = 'en'): array
    {
        $available = [];
        foreach ($pages as $page) {
            $available[self::language($page)] = true;
        }
        $language = $current !== null ? self::language($current) : (isset($available[$language]) ? $language : (array_key_first($available) ?? 'en'));
        $polish = $language === 'pl';
        $projectHref = '/wiki/project/' . rawurlencode($slug);
        $items = [];
        $languages = [];
        foreach (['en' => 'English', 'pl' => 'Polski'] as $code => $label) {
            if (!isset($available[$code])) continue;
            $target = null;
            if ($current !== null) {
                foreach ($pages as $candidate) {
                    if (self::language($candidate) === $code && self::key($candidate) === self::key($current)) {
                        $target = $candidate;
                        break;
                    }
                }
            }
            // Older imports can explicitly map a historical URL to a differently named translation.
            // Read only the importer-owned first line and match against accessible local candidates.
            if ($current !== null && $target === null) {
                $firstLine = explode("\n", ltrim($current->content), 2)[0];
                if (str_starts_with($firstLine, '> **Language / Język:** ')) {
                    foreach ($pages as $candidate) {
                        if (self::language($candidate) === $code
                            && preg_match('~\]\(' . preg_quote(self::href($candidate), '~') . '\)~', $firstLine) === 1) {
                            $target = $candidate;
                            break;
                        }
                    }
                }
            }
            $languages[] = ['code' => $code, 'label' => $label, 'active' => $code === $language,
                'href' => $target !== null ? self::href($target) : $projectHref . '?lang=' . $code,
                'overview' => $current !== null && $target === null];
        }
        foreach ($pages as $page) {
            if (self::language($page) !== $language) continue;
            $items[] = ['title' => preg_replace('/^\[PL\]\s*|\s*\[PL\]$/u', '', $page->title) ?? $page->title,
                'summary' => $page->summary, 'href' => self::href($page), 'active' => $page->id === $current?->id];
        }
        $position = array_search(true, array_column($items, 'active'), true);
        return ['mode' => $current === null ? 'project' : 'page', 'project' => $name,
            'project_href' => $projectHref . '?lang=' . $language, 'summary' => $summary,
            'language' => $language, 'languages' => $languages, 'pages' => $items,
            'title' => $current !== null ? ($items[$position]['title'] ?? $current->title) : $name,
            'description' => $current?->summary ?? $summary, 'content' => $current?->content ?? '',
            'format' => $current?->contentFormat ?? 'markdown', 'updated' => substr($current?->updatedAt ?? '', 0, 10),
            'minutes' => max(1, (int) ceil(str_word_count(strip_tags($current?->content ?? '')) / 220)),
            'previous' => $position !== false ? ($items[$position - 1] ?? null) : null,
            'next' => $position !== false ? ($items[$position + 1] ?? null) : null,
            'labels' => $polish ? ['search' => 'Szukaj w dokumentacji', 'contents' => 'Dokumenty', 'overview' => 'Przegląd projektu', 'on_page' => 'Na tej stronie', 'start' => 'Zacznij czytać', 'empty' => 'Brak dokumentów.', 'no_results' => 'Brak wyników. Spróbuj innej frazy.', 'previous' => 'Poprzedni dokument', 'next' => 'Następny dokument', 'updated' => 'Aktualizacja', 'read' => 'min czytania', 'copy' => 'Kopiuj', 'copied' => 'Skopiowano', 'copy_error' => 'Zaznacz i skopiuj kod', 'docs' => 'Dokumentacja', 'intro' => 'Wszystko, czego potrzebujesz, w jednym miejscu.', 'translation' => 'Brak tłumaczenia — przegląd projektu']
            : ['search' => 'Search documentation', 'contents' => 'Documents', 'overview' => 'Project overview', 'on_page' => 'On this page', 'start' => 'Start reading', 'empty' => 'No documents yet.', 'no_results' => 'No results. Try another search.', 'previous' => 'Previous document', 'next' => 'Next document', 'updated' => 'Updated', 'read' => 'min read', 'copy' => 'Copy', 'copied' => 'Copied', 'copy_error' => 'Select and copy the code', 'docs' => 'Documentation', 'intro' => 'Everything you need, in one place.', 'translation' => 'Translation unavailable — project overview']];
    }

    private static function href(WikiPage $page): string
    {
        return '/wiki/page/' . rawurlencode($page->projectSlug) . '/' . rawurlencode($page->slug);
    }
}
