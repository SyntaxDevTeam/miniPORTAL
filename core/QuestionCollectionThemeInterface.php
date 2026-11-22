<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Core;

/** Optional, data-only presentation capability for categorized question collections. */
interface QuestionCollectionThemeInterface
{
    public function render_question_collection(array $model): void;
}
