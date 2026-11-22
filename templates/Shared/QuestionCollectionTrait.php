<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Templates\Shared;

trait QuestionCollectionTrait
{
    public function render_question_collection(array $model): void
    {
        $escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
        $assets = '/templates/shared/assets/';
        echo '<link rel="stylesheet" href="' . $assets . 'css/question-collection.css?v=' . filemtime(__DIR__ . '/../shared/assets/css/question-collection.css') . '">';
        require __DIR__ . '/views/question-collection.php';
        echo '<script defer src="' . $assets . 'js/question-collection.js?v=' . filemtime(__DIR__ . '/../shared/assets/js/question-collection.js') . '"></script>';
    }
}
