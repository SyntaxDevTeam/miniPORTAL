<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Core;

use RuntimeException;

final class ApiScope
{
    private function __construct()
    {
    }

    public static function assertValid(string $scope): void
    {
        if (
            strlen($scope) > 128
            || preg_match('/^[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)+$/D', $scope) !== 1
        ) {
            throw new RuntimeException("Scope API {$scope} jest nieprawidłowy.");
        }
    }

    /**
     * @param list<string> $scopes
     * @return list<string>
     */
    public static function normalize(array $scopes): array
    {
        if (count($scopes) > 128) {
            throw new RuntimeException('Token API nie może posiadać więcej niż 128 scope\'ów.');
        }

        $normalized = [];
        foreach ($scopes as $scope) {
            if (!is_string($scope)) {
                throw new RuntimeException('Lista scope API zawiera nieprawidłową wartość.');
            }
            $scope = trim($scope);
            self::assertValid($scope);
            $normalized[$scope] = true;
        }

        $result = array_keys($normalized);
        sort($result, SORT_STRING);

        return $result;
    }
}
