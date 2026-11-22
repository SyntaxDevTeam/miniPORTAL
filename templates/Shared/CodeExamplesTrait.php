<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Templates\Shared;

use SyntaxDevTeam\Cms\Core\ContentRenderer;

trait CodeExamplesTrait
{
    public function render_code_examples(string $content, string $format): void
    {
        $html = (new ContentRenderer())->render($content, $format);
        $python = str_contains($html, 'class="language-python-run"');
        $kotlin = str_contains($html, 'class="language-kotlin-run"');
        if ($python || $kotlin) {
            echo '<link rel="stylesheet" href="/templates/shared/assets/vendor/codemirror/5.65.20/codemirror.min.css">';
            echo '<link rel="stylesheet" href="/templates/shared/assets/css/code-examples.css?v='
                . filemtime(__DIR__ . '/../shared/assets/css/code-examples.css') . '">';
            echo '<script defer src="/templates/shared/assets/vendor/codemirror/5.65.20/codemirror.min.js"></script>';
            if ($python) {
                echo '<script defer src="/templates/shared/assets/vendor/codemirror/5.65.20/python.min.js"></script>';
            }
            if ($kotlin) {
                echo '<script defer src="/templates/shared/assets/vendor/codemirror/5.65.20/clike.js"></script>';
            }
            echo '<div data-code-examples>';
            $this->render_rich_content($content, $format);
            echo '</div>';
            if ($python) {
                echo '<script defer src="/templates/shared/assets/js/code-examples.js?v='
                    . filemtime(__DIR__ . '/../shared/assets/js/code-examples.js') . '"></script>';
            }
            if ($kotlin) {
                echo '<script defer src="/templates/shared/assets/js/kotlin-examples.js?v='
                    . filemtime(__DIR__ . '/../shared/assets/js/kotlin-examples.js') . '"></script>';
            }
            return;
        }
        $this->render_rich_content($content, $format);
    }

    public function render_code_runner(string $runtimeBase, string $nonce): void
    {
        require __DIR__ . '/views/code-runner.php';
    }
}
