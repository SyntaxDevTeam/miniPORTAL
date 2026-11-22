<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\Cooperation;

use InvalidArgumentException;
use SyntaxDevTeam\Cms\Core\Request;

final class PartnerInput
{
    public static function fromRequest(Request $request): array
    {
        $data = [
            'name' => trim($request->postString('name')),
            'website_url' => trim($request->postString('website_url')),
            'description' => trim($request->postString('description')),
            'sort_order' => $request->postInt('sort_order', 100),
            'is_visible' => $request->postBool('is_visible') ? 1 : 0,
        ];
        foreach (['name' => 120, 'website_url' => 500, 'description' => 5000] as $key => $limit) {
            if ($data[$key] === '' || mb_strlen($data[$key]) > $limit || preg_match($key === 'description' ? '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u' : '/[\x00-\x1F\x7F]/u', $data[$key])) {
                throw new InvalidArgumentException('Uzupełnij nazwę (do 120 znaków), adres (do 500 znaków) i opis (do 5000 znaków).');
            }
        }
        if (!self::validUrl($data['website_url'])) {
            throw new InvalidArgumentException('Podaj pełny adres HTTP lub HTTPS bez danych logowania, np. https://partner.pl.');
        }
        if ($data['sort_order'] === null || $data['sort_order'] < 0 || $data['sort_order'] > 65535) {
            throw new InvalidArgumentException('Kolejność musi być liczbą od 0 do 65535.');
        }
        return $data;
    }

    public static function validUrl(string $url): bool
    {
        $parts = parse_url($url);
        return filter_var($url, FILTER_VALIDATE_URL) !== false && is_array($parts)
            && in_array(strtolower($parts['scheme'] ?? ''), ['https', 'http'], true)
            && !isset($parts['user']) && !isset($parts['pass'])
            && preg_match('/[\x00-\x20\x7F]/', $url) !== 1;
    }
}
