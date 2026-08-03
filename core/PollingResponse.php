<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Core;

/**
 * Wspólny, bezstanowy kontrakt dla częstych odczytów przyrostowych.
 *
 * Klient odsyła otrzymany ETag w X-Poll-ETag. Gdy stan źródła się nie zmienił,
 * odpowiedź kończy się kodem 204 bez serializacji i transferu pustego JSON-a.
 */
final class PollingResponse
{
    /** @param array<string, bool|int|string> $state */
    public static function etag(string $scope, array $state): string
    {
        ksort($state);
        $payload = json_encode([$scope, $state], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        return '"mp-poll-' . substr(hash('sha256', $payload), 0, 32) . '"';
    }

    public static function isUnchanged(Request $request, string $etag): bool
    {
        $candidate = trim($request->header('X-Poll-ETag'));

        return $candidate !== ''
            && strlen($candidate) <= 80
            && hash_equals($etag, $candidate);
    }

    /**
     * Wysyła prywatne nagłówki odpowiedzi i zwraca false po zakończeniu 204.
     */
    public static function begin(Request $request, string $scope, array $state): bool
    {
        $etag = self::etag($scope, $state);
        header('Cache-Control: no-store, private');
        header('ETag: ' . $etag);
        header('Vary: Cookie');

        if (!self::isUnchanged($request, $etag)) {
            return true;
        }

        http_response_code(204);
        return false;
    }
}
