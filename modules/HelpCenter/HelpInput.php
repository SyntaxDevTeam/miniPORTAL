<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\HelpCenter;

use InvalidArgumentException;
use SyntaxDevTeam\Cms\Core\{ContentRenderer, Request};

final class HelpInput
{
    public static function category(Request $request): array
    {
        $icon = self::text($request, 'icon', 16, false);
        if ($icon !== '' && (mb_strlen($icon) > 4 || preg_match('/[<>\s]/u', $icon))) {
            throw new InvalidArgumentException('Ikona powinna być pojedynczym symbolem lub emoji.');
        }
        return ['name' => self::text($request, 'name', 100), 'description' => self::text($request, 'description', 240, false),
            'icon' => $icon !== '' ? $icon : '✦', 'sort_order' => self::order($request), 'is_visible' => (int)$request->postBool('is_visible'), 'visibility' => self::visibility($request)];
    }

    public static function question(Request $request): array
    {
        $format = $request->postString('content_format');
        if (!in_array($format, ['html', 'markdown'], true)) { throw new InvalidArgumentException('Wybierz format HTML lub Markdown.'); }
        $answer = trim($request->postString('answer'));
        if ($answer === '' || strlen($answer) > 100000 || str_contains($answer, "\0")) {
            throw new InvalidArgumentException('Odpowiedź jest wymagana i może zajmować maksymalnie 100 KB.');
        }
        $renderer = new ContentRenderer();
        $answer = $renderer->prepareForStorage($answer, $format);
        if (trim(html_entity_decode(strip_tags($renderer->render($answer, $format)), ENT_QUOTES, 'UTF-8')) === '') {
            throw new InvalidArgumentException('Dodaj tekst odpowiedzi.');
        }
        $category = $request->postInt('category_id');
        if ($category === null || $category < 1) { throw new InvalidArgumentException('Wybierz kategorię pytania.'); }
        return ['category_id' => $category, 'question' => self::text($request, 'question', 240), 'answer' => $answer,
            'content_format' => $format, 'keywords' => self::text($request, 'keywords', 500, false),
            'sort_order' => self::order($request), 'is_published' => (int)$request->postBool('is_published'), 'is_featured' => (int)$request->postBool('is_featured'), 'visibility' => self::visibility($request)];
    }

    public static function settings(Request $request): array
    {
        $url = self::text($request, 'contact_url', 500, false);
        if ($url !== '' && !self::contactUrl($url)) { throw new InvalidArgumentException('Kontakt musi być lokalną ścieżką od / lub adresem HTTPS bez danych logowania.'); }
        return ['title' => self::text($request, 'title', 100), 'intro' => self::text($request, 'intro', 700),
            'contact_url' => $url, 'contact_label' => self::text($request, 'contact_label', 80)];
    }

    public static function contactUrl(string $url): bool
    {
        if (preg_match('/[\x00-\x20\x7f\\\\]/', $url)) { return false; }
        if (str_starts_with($url, '/') && !str_starts_with($url, '//')) { return true; }
        $parts = parse_url($url);
        return filter_var($url, FILTER_VALIDATE_URL) !== false && ($parts['scheme'] ?? '') === 'https'
            && !isset($parts['user']) && !isset($parts['pass']);
    }

    private static function text(Request $request, string $key, int $limit, bool $required = true): string
    {
        $value = trim($request->postString($key));
        if (($required && $value === '') || mb_strlen($value) > $limit || preg_match('/[\x00-\x1f\x7f]/u', $value)) {
            $label = ['name' => 'Nazwa kategorii', 'description' => 'Opis', 'icon' => 'Symbol', 'question' => 'Pytanie', 'keywords' => 'Słowa kluczowe', 'title' => 'Nagłówek', 'intro' => 'Krótki opis', 'contact_url' => 'Adres kontaktu', 'contact_label' => 'Tekst przycisku kontaktu'][$key] ?? $key;
            throw new InvalidArgumentException('Sprawdź pole „' . $label . '” (maksymalnie ' . $limit . ' znaków, bez znaków sterujących).');
        }
        return $value;
    }

    private static function visibility(Request $request): string
    {
        $visibility = $request->postString('visibility', 'public');
        if (!in_array($visibility, ['public', 'staff'], true)) {
            throw new InvalidArgumentException('Wybierz widoczność: publiczne lub tylko dla administracji serwera.');
        }
        return $visibility;
    }

    private static function order(Request $request): int
    {
        $order = $request->postInt('sort_order', 100);
        if ($order === null || $order < 0 || $order > 65535) { throw new InvalidArgumentException('Kolejność musi być liczbą od 0 do 65535.'); }
        return $order;
    }
}
