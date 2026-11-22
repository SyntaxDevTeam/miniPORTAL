<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Core;

/** Optional presentation for editable article examples; never executes code in PHP. */
interface CodeExamplesThemeInterface
{
    public function render_code_examples(string $content, string $format): void;
    public function render_code_runner(string $runtimeBase, string $nonce): void;
}
