<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\CoreAuth;

final readonly class HttpResponse
{
    public function __construct(
        public int $status,
        public string $body,
    ) {
    }

    public function json(): array
    {
        $data = json_decode($this->body, true, 32, JSON_THROW_ON_ERROR);

        return is_array($data) ? $data : [];
    }
}
