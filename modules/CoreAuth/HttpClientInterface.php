<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\CoreAuth;

interface HttpClientInterface
{
    /**
     * @param array<string, string> $headers
     * @param array<string, scalar> $form
     */
    public function request(string $method, string $url, array $headers = [], array $form = []): HttpResponse;
}
