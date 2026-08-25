<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Core;

use DateTimeImmutable;
use DateTimeZone;
use JsonException;
use RuntimeException;

final class ApiTokenService
{
    public function __construct(private readonly ?ApiTokenStoreInterface $store)
    {
    }

    /**
     * Raw token jest zwracany wyłącznie raz, podczas utworzenia.
     * TenantContext jest wymagany jawnie, aby token platformowy nigdy nie powstał przez pominięcie argumentu.
     *
     * @param list<string> $scopes
     */
    public function issue(
        string $label,
        array $scopes,
        TenantContext $tenant,
        ?int $subjectUserId = null,
        ?DateTimeImmutable $expiresAt = null,
        ?int $createdBy = null,
    ): IssuedApiToken {
        if ($this->store === null) {
            throw new RuntimeException('Magazyn tokenów API jest niedostępny.');
        }

        $label = trim($label);
        if ($label === '' || strlen($label) > 120) {
            throw new RuntimeException('Etykieta tokenu API musi mieć od 1 do 120 znaków.');
        }
        if ($subjectUserId !== null && $subjectUserId <= 0) {
            throw new RuntimeException('Identyfikator użytkownika tokenu API jest nieprawidłowy.');
        }
        if ($createdBy !== null && $createdBy <= 0) {
            throw new RuntimeException('Identyfikator twórcy tokenu API jest nieprawidłowy.');
        }

        $normalizedScopes = ApiScope::normalize($scopes);
        if ($normalizedScopes === []) {
            throw new RuntimeException('Token API musi posiadać co najmniej jeden scope.');
        }

        $now = $this->now();
        $normalizedExpiry = null;
        if ($expiresAt !== null) {
            $expiresAt = $expiresAt->setTimezone(new DateTimeZone('UTC'));
            if ($expiresAt <= $now) {
                throw new RuntimeException('Data wygaśnięcia tokenu API musi być w przyszłości.');
            }
            $normalizedExpiry = $expiresAt->format('Y-m-d H:i:s');
        }

        $publicId = bin2hex(random_bytes(8));
        $secret = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $token = 'mpat_' . $publicId . '_' . $secret;
        $tokenHash = hash('sha256', $token);
        $scopesJson = json_encode(
            $normalizedScopes,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES
        );

        $id = $this->store->create([
            'public_id' => $publicId,
            'label' => $label,
            'token_hash' => $tokenHash,
            'scopes_json' => $scopesJson,
            'subject_user_id' => $subjectUserId,
            'tenant_type' => $tenant->type,
            'tenant_id' => $tenant->id,
            'created_by' => $createdBy,
            'expires_at' => $normalizedExpiry,
        ]);

        return new IssuedApiToken(
            $id,
            $publicId,
            $token,
            $label,
            $normalizedScopes,
            $subjectUserId,
            $tenant,
            $normalizedExpiry,
        );
    }

    public function authenticate(Request $request): ?ApiTokenPrincipal
    {
        return $this->authenticateBearer($request->header('Authorization'));
    }

    public function authenticateBearer(string $authorization): ?ApiTokenPrincipal
    {
        if ($this->store === null) {
            return null;
        }

        if (
            preg_match(
                '/^Bearer\s+(mpat_([a-f0-9]{16})_([A-Za-z0-9_-]{43}))$/D',
                trim($authorization),
                $matches
            ) !== 1
        ) {
            return null;
        }

        $row = $this->store->findByPublicId($matches[2]);
        if (!is_array($row) || ($row['revoked_at'] ?? null) !== null) {
            return null;
        }

        $storedHash = (string) ($row['token_hash'] ?? '');
        if (
            preg_match('/^[a-f0-9]{64}$/D', $storedHash) !== 1
            || !hash_equals($storedHash, hash('sha256', $matches[1]))
        ) {
            return null;
        }

        $now = $this->now();
        $expiresAt = trim((string) ($row['expires_at'] ?? ''));
        if ($expiresAt !== '') {
            try {
                $expiry = new DateTimeImmutable($expiresAt, new DateTimeZone('UTC'));
            } catch (\Throwable) {
                return null;
            }
            if ($expiry <= $now) {
                return null;
            }
        }

        try {
            $decodedScopes = json_decode(
                (string) ($row['scopes_json'] ?? ''),
                true,
                64,
                JSON_THROW_ON_ERROR
            );
        } catch (JsonException) {
            return null;
        }
        if (!is_array($decodedScopes)) {
            return null;
        }

        try {
            $scopes = ApiScope::normalize(array_values($decodedScopes));
            $tenant = $this->hydrateTenant($row);
        } catch (RuntimeException) {
            return null;
        }
        if ($scopes === []) {
            return null;
        }

        $id = filter_var($row['id'] ?? null, FILTER_VALIDATE_INT);
        $subjectUserId = ($row['subject_user_id'] ?? null) !== null
            ? filter_var($row['subject_user_id'], FILTER_VALIDATE_INT)
            : null;
        $label = trim((string) ($row['label'] ?? ''));
        if (
            $id === false
            || (int) $id <= 0
            || ($subjectUserId !== null && ($subjectUserId === false || (int) $subjectUserId <= 0))
            || $label === ''
            || strlen($label) > 120
        ) {
            return null;
        }

        $principal = new ApiTokenPrincipal(
            (int) $id,
            $matches[2],
            $label,
            $scopes,
            $subjectUserId !== null ? (int) $subjectUserId : null,
            $tenant,
        );

        $this->store->touch($principal->id, $now->format('Y-m-d H:i:s'));

        return $principal;
    }

    public function revoke(int $id): void
    {
        if ($id <= 0) {
            throw new RuntimeException('Identyfikator tokenu API jest nieprawidłowy.');
        }
        if ($this->store === null) {
            throw new RuntimeException('Magazyn tokenów API jest niedostępny.');
        }

        $this->store->revoke($id, $this->now()->format('Y-m-d H:i:s'));
    }

    /** @param array<string, mixed> $row */
    private function hydrateTenant(array $row): TenantContext
    {
        $type = $row['tenant_type'] ?? null;
        $id = $row['tenant_id'] ?? null;

        if ($type === null && $id === null) {
            return TenantContext::platform();
        }
        if (!is_string($type) || !is_string($id)) {
            throw new RuntimeException('Token API posiada niespójny tenant context.');
        }

        return TenantContext::scoped($type, $id);
    }

    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }
}
