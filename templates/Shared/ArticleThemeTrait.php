<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Templates\Shared;

trait ArticleThemeTrait
{
    private array $articlePageMetadata = [];

    public function start_article_page(string $title, string $description, array $metadata, bool $indexable = true): void
    {
        $this->articlePageMetadata = $metadata;
        try {
            $this->start_page($title, $description, $indexable);
        } finally {
            $this->articlePageMetadata = [];
        }
    }

    private function publicPageLanguage(): string
    {
        $language = $this->articlePageMetadata['language'] ?? 'und';

        return $language === 'und' ? $this->publicLanguage : $language;
    }

    private function publicCanonicalUrl(): string
    {
        return $this->publicUrl === '' ? '' : $this->publicUrl . ($this->articlePageMetadata['path'] ?? $this->publicPath);
    }

    private function renderPublicSocialType(): void
    {
        $article = isset($this->articlePageMetadata['published']);
        $locale = match ($this->articlePageMetadata['language'] ?? '') {
            'en' => str_starts_with($this->publicLocale, 'en_') ? $this->publicLocale : 'en_US',
            'pl' => 'pl_PL',
            default => $this->publicLocale,
        };
        echo '<meta property="og:type" content="' . ($article ? 'article' : 'website') . '">';
        echo '<meta property="og:locale" content="' . $this->escape($locale) . '">';
        if ($article) {
            echo '<meta property="article:published_time" content="' . $this->escape($this->articlePageMetadata['published']) . '">';
            echo '<meta property="article:modified_time" content="' . $this->escape($this->articlePageMetadata['modified']) . '">';
        }
    }

    public function render_article_catalog(array $model): void
    {
        $escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
        foreach (['documentation.css', 'article-catalog.css'] as $stylesheet) {
            $path = '/templates/shared/assets/css/' . $stylesheet;
            echo '<link rel="stylesheet" href="' . $path . '?v=' . filemtime(dirname(__DIR__, 2) . $path) . '">';
        }
        require __DIR__ . '/views/article-catalog.php';
    }
}
