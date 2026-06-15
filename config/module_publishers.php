<?php

declare(strict_types=1);

$publicKeyFile = __DIR__ . '/keys/syntaxdevteam-learning-public.pem';

return is_readable($publicKeyFile) ? [
    'syntaxdevteam-learning-2026' => [
        'name' => 'SyntaxDevTeam Learning Publisher',
        'public_key' => (string) file_get_contents($publicKeyFile),
    ],
] : [];
