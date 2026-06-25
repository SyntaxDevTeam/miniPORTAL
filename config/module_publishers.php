<?php

declare(strict_types=1);

$legacyPublicKeyFile = __DIR__ . '/keys/syntaxdevteam-learning-public.pem';
$activePublicKeyFile = __DIR__ . '/keys/syntaxdevteam-learning-2026-rotated-public.pem';
$modulesPublicKeyFile = __DIR__ . '/keys/syntaxdevteam-modules-2026-public.pem';

$publishers = [];
if (is_readable($legacyPublicKeyFile)) {
    $publishers['syntaxdevteam-learning-2026'] = [
        'name' => 'SyntaxDevTeam Learning Publisher (legacy)',
        'public_key' => (string) file_get_contents($legacyPublicKeyFile),
        'status' => 'retired',
        'valid_from' => '2026-06-14T00:00:00+00:00',
        'valid_until' => '2026-06-15T23:59:59+00:00',
        'replacement_key_id' => 'syntaxdevteam-learning-2026-rotated',
    ];
}
if (is_readable($activePublicKeyFile)) {
    $publishers['syntaxdevteam-learning-2026-rotated'] = [
        'name' => 'SyntaxDevTeam Learning Publisher',
        'public_key' => (string) file_get_contents($activePublicKeyFile),
        'status' => 'active',
        'valid_from' => '2026-06-15T00:00:00+00:00',
        'valid_until' => null,
        'replacement_key_id' => null,
    ];
}
if (is_readable($modulesPublicKeyFile)) {
    $publishers['syntaxdevteam-modules-2026'] = [
        'name' => 'SyntaxDevTeam miniPORTAL Modules',
        'public_key' => (string) file_get_contents($modulesPublicKeyFile),
        'status' => 'active',
        'valid_from' => '2026-06-24T00:00:00+00:00',
        'valid_until' => null,
        'replacement_key_id' => null,
    ];
}

return $publishers;
