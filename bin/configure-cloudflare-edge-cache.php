<?php

declare(strict_types=1);

use SyntaxDevTeam\Cms\Core\Autoloader;
use SyntaxDevTeam\Cms\Core\EdgeCachePolicy;

require_once dirname(__DIR__) . '/core/Autoloader.php';
Autoloader::register();

$options = getopt('', ['dry-run', 'host:', 'zone-id:', 'session-name:', 'token-file:', 'help']);
if (isset($options['help'])) {
    echo <<<TXT
Użycie:
  php bin/configure-cloudflare-edge-cache.php --dry-run
  php bin/configure-cloudflare-edge-cache.php --token-file=/chroniony/plik [--zone-id=...] [--host=...]
  CLOUDFLARE_API_TOKEN=... php bin/configure-cloudflare-edge-cache.php [--zone-id=...] [--host=...]

Token musi mieć co najmniej Zone > Cache Rules > Edit oraz dostęp do wskazanej strefy.
Po poprawnym wdrożeniu ustaw EDGE_HTML_CACHE_ENABLED=true w środowisku aplikacji.

TXT;
    exit(0);
}

$config = require dirname(__DIR__) . '/config/config.php';
$configuredUrl = (string) (($config['app'] ?? [])['public_url'] ?? '');
$configuredHost = (string) (parse_url($configuredUrl, PHP_URL_HOST) ?? '');
$host = strtolower(trim((string) ($options['host'] ?? $configuredHost)));
$sessionName = trim((string) (
    $options['session-name']
    ?? (($config['session'] ?? [])['name'] ?? 'MINIPORTALSESSID')
));
$zoneId = trim((string) ($options['zone-id'] ?? getenv('CLOUDFLARE_ZONE_ID') ?: ''));
$tokenFile = trim((string) ($options['token-file'] ?? ''));
$token = trim((string) (getenv('CLOUDFLARE_API_TOKEN') ?: ''));
if ($tokenFile !== '') {
    if (!is_file($tokenFile) || !is_readable($tokenFile)) {
        fwrite(STDERR, "Plik tokenu nie istnieje albo nie jest czytelny.\n");
        exit(1);
    }
    $token = trim((string) file_get_contents($tokenFile));
}
$dryRun = isset($options['dry-run']);

if (preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $host) !== 1) {
    fwrite(STDERR, "Nieprawidłowy host publiczny. Użyj --host=example.com.\n");
    exit(1);
}
if (preg_match('/^[A-Za-z0-9_-]{1,64}$/', $sessionName) !== 1) {
    fwrite(STDERR, "Nieprawidłowa nazwa cookie sesyjnego.\n");
    exit(1);
}

$quote = static fn (string $value): string => '"' . addcslashes($value, "\\\"") . '"';
$hostMatch = 'http.host eq ' . $quote($host);
$cookieMatch = 'http.cookie contains ' . $quote($sessionName . '=');
$publicPaths = EdgeCachePolicy::cloudflarePublicPathExpression();
$privatePaths = array_map(
    static fn (string $prefix): string => 'starts_with(http.request.uri.path, ' . $quote($prefix) . ')',
    EdgeCachePolicy::cloudflarePrivatePrefixes(),
);
$eligibleExpression = '(' . $hostMatch
    . ' and http.request.method in {"GET" "HEAD"}'
    . ' and http.request.uri.query eq ""'
    . ' and not (' . $cookieMatch . ')'
    . ' and ' . $publicPaths . ')';
$bypassExpression = '(' . $hostMatch . ' and ('
    . $cookieMatch
    . ' or not (http.request.method in {"GET" "HEAD"})'
    . ' or ' . implode(' or ', $privatePaths)
    . '))';

$eligibleRule = [
    'ref' => 'miniportal_anonymous_html_edge_cache_v1',
    'description' => 'miniPORTAL: anonymous public HTML only',
    'expression' => $eligibleExpression,
    'action' => 'set_cache_settings',
    'action_parameters' => [
        'cache' => true,
        'edge_ttl' => ['mode' => 'bypass_by_default'],
        'browser_ttl' => ['mode' => 'respect_origin'],
        'serve_stale' => ['disable_stale_while_updating' => false],
    ],
    'enabled' => true,
];
$bypassRule = [
    'ref' => 'miniportal_session_cache_bypass_v1',
    'description' => 'miniPORTAL: absolute session and private route bypass',
    'expression' => $bypassExpression,
    'action' => 'set_cache_settings',
    'action_parameters' => ['cache' => false],
    'enabled' => true,
];

if ($dryRun) {
    echo json_encode(
        ['host' => $host, 'session_cookie' => $sessionName, 'rules' => [$eligibleRule, $bypassRule]],
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
    ) . "\n";
    exit(0);
}
if ($token === '') {
    fwrite(STDERR, "Brak tokenu Cloudflare. Użyj --token-file albo CLOUDFLARE_API_TOKEN.\n");
    exit(1);
}
if (preg_match('/^[A-Za-z0-9_-]{20,256}$/', $token) !== 1) {
    fwrite(STDERR, "Token Cloudflare ma nieprawidłowy format.\n");
    exit(1);
}

/** @return array<string, mixed> */
$api = static function (string $method, string $path, ?array $body = null) use ($token): array {
    $headers = [
        'Authorization: Bearer ' . $token,
        'Accept: application/json',
    ];
    $content = '';
    if ($body !== null) {
        $content = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $headers[] = 'Content-Type: application/json';
    }
    $context = stream_context_create(['http' => [
        'method' => $method,
        'header' => implode("\r\n", $headers),
        'content' => $content,
        'ignore_errors' => true,
        'timeout' => 20,
    ]]);
    $response = @file_get_contents('https://api.cloudflare.com/client/v4' . $path, false, $context);
    $responseHeaders = $http_response_header ?? [];
    $status = 0;
    if (isset($responseHeaders[0]) && preg_match('/\s(\d{3})\s/', $responseHeaders[0], $match) === 1) {
        $status = (int) $match[1];
    }
    $decoded = is_string($response) ? json_decode($response, true) : null;
    if (
        $status < 200
        || $status >= 300
        || !is_array($decoded)
        || ($decoded['success'] ?? false) !== true
    ) {
        $messages = [];
        foreach ((array) ($decoded['errors'] ?? []) as $error) {
            if (is_array($error) && isset($error['message'])) {
                $messages[] = (string) $error['message'];
            }
        }
        throw new RuntimeException(sprintf(
            'Cloudflare API %s %s zwróciło HTTP %d: %s',
            $method,
            $path,
            $status,
            $messages !== [] ? implode('; ', $messages) : 'brak poprawnej odpowiedzi',
        ));
    }

    return $decoded;
};

try {
    if ($zoneId === '') {
        $zones = $api('GET', '/zones?name=' . rawurlencode($host) . '&status=active&per_page=50');
        $matches = is_array($zones['result'] ?? null) ? $zones['result'] : [];
        if (count($matches) !== 1 || !is_array($matches[0] ?? null)) {
            throw new RuntimeException('Nie znaleziono dokładnie jednej aktywnej strefy dla hosta ' . $host . '.');
        }
        $zoneId = (string) ($matches[0]['id'] ?? '');
    }
    if (preg_match('/^[a-f0-9]{32}$/i', $zoneId) !== 1) {
        throw new RuntimeException('Cloudflare zwrócił nieprawidłowy Zone ID.');
    }

    $rulesetsResponse = $api('GET', '/zones/' . $zoneId . '/rulesets');
    $ruleset = null;
    foreach ((array) ($rulesetsResponse['result'] ?? []) as $candidate) {
        if (
            is_array($candidate)
            && ($candidate['phase'] ?? null) === 'http_request_cache_settings'
            && ($candidate['kind'] ?? null) === 'zone'
        ) {
            $ruleset = $candidate;
            break;
        }
    }
    if ($ruleset === null) {
        $created = $api('POST', '/zones/' . $zoneId . '/rulesets', [
            'name' => 'miniPORTAL cache rules',
            'description' => 'Managed cache rules for miniPORTAL',
            'kind' => 'zone',
            'phase' => 'http_request_cache_settings',
        ]);
        $ruleset = is_array($created['result'] ?? null) ? $created['result'] : null;
    }
    $rulesetId = is_array($ruleset) ? (string) ($ruleset['id'] ?? '') : '';
    if (preg_match('/^[a-f0-9]{32}$/i', $rulesetId) !== 1) {
        throw new RuntimeException('Nie udało się ustalić Ruleset ID fazy cache.');
    }

    $details = $api('GET', '/zones/' . $zoneId . '/rulesets/' . $rulesetId);
    $existingRules = is_array(($details['result'] ?? [])['rules'] ?? null)
        ? $details['result']['rules']
        : [];
    $upsert = static function (array $rule) use ($api, $zoneId, $rulesetId, &$existingRules): void {
        $existing = null;
        foreach ($existingRules as $candidate) {
            if (is_array($candidate) && ($candidate['ref'] ?? null) === $rule['ref']) {
                $existing = $candidate;
                break;
            }
        }
        $rule['position'] = ['after' => ''];
        if (is_array($existing) && isset($existing['id'])) {
            $api(
                'PATCH',
                '/zones/' . $zoneId . '/rulesets/' . $rulesetId . '/rules/' . rawurlencode((string) $existing['id']),
                $rule,
            );
        } else {
            $api('POST', '/zones/' . $zoneId . '/rulesets/' . $rulesetId . '/rules', $rule);
        }
        $refreshed = $api('GET', '/zones/' . $zoneId . '/rulesets/' . $rulesetId);
        $existingRules = is_array(($refreshed['result'] ?? [])['rules'] ?? null)
            ? $refreshed['result']['rules']
            : [];
    };

    // Cloudflare stosuje ostatnią pasującą wartość. Bypass musi pozostać ostatni.
    $upsert($eligibleRule);
    $upsert($bypassRule);

    $refs = array_values(array_map(
        static fn (array $rule): string => (string) ($rule['ref'] ?? ''),
        array_filter($existingRules, 'is_array'),
    ));
    $eligiblePosition = array_search($eligibleRule['ref'], $refs, true);
    $bypassPosition = array_search($bypassRule['ref'], $refs, true);
    if (
        $eligiblePosition === false
        || $bypassPosition === false
        || $bypassPosition <= $eligiblePosition
        || $bypassPosition !== array_key_last($refs)
    ) {
        throw new RuntimeException('Weryfikacja kolejności reguł nie powiodła się; origin pozostaje wyłączony.');
    }

    echo "Wdrożono reguły Cloudflare dla {$host}.\n";
    echo "Bypass sesji jest ostatnią regułą fazy cache. Ustaw teraz EDGE_HTML_CACHE_ENABLED=true.\n";
} catch (Throwable $exception) {
    fwrite(STDERR, 'Błąd: ' . $exception->getMessage() . "\n");
    exit(1);
}
