<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\CoreAuth;

/** Presentation data only. The originating module retains ownership of every write route. */
final readonly class LinkedAccountSection
{
    /**
     * @param list<array{label:string,value:string,detail?:string}> $facts
     * @param list<array{label:string,href:string,variant?:string}> $links
     * @param list<array{action:string,fields:array,label:string}> $forms
     * @param list<array{label:string,action:string,fields?:array,confirm?:string,variant?:string}> $commands
     */
    public function __construct(
        public string $source,
        public string $title,
        public string $description,
        public array $facts = [],
        public array $links = [],
        public array $forms = [],
        public array $commands = [],
    ) {
    }

    public static function localRoute(string $value): bool
    {
        return preg_match('~^(?:/(?!/)|index\.php\?route=/)[a-zA-Z0-9/_?=&%:.+-]*$~D', $value) === 1;
    }
}
