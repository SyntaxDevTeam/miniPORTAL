<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Core;

use RuntimeException;

final class FileTranslator implements TranslatorInterface
{
    /** @var array<string, string> */
    private array $messages;

    /** @var array<string, string> */
    private array $fallbackMessages;

    /**
     * @param list<string> $supportedLocales
     */
    public function __construct(
        private readonly string $catalogDirectory,
        private readonly string $currentLocale,
        private readonly string $fallbackLocale,
        private readonly array $supportedLocales,
    ) {
        if (!in_array($currentLocale, $supportedLocales, true)) {
            throw new RuntimeException('Bieżący język nie znajduje się na liście obsługiwanych języków.');
        }
        if (!in_array($fallbackLocale, $supportedLocales, true)) {
            throw new RuntimeException('Język domyślny nie znajduje się na liście obsługiwanych języków.');
        }

        $this->fallbackMessages = $this->load($fallbackLocale);
        $this->messages = $currentLocale === $fallbackLocale
            ? $this->fallbackMessages
            : $this->load($currentLocale);
    }

    public function locale(): string
    {
        return $this->currentLocale;
    }

    public function defaultLocale(): string
    {
        return $this->fallbackLocale;
    }

    public function supportedLocales(): array
    {
        return $this->supportedLocales;
    }

    public function translate(string $key, array $parameters = [], string $fallback = ''): string
    {
        $message = $this->messages[$key] ?? $this->fallbackMessages[$key] ?? $fallback ?: $key;
        foreach ($parameters as $name => $value) {
            $message = str_replace('{' . $name . '}', (string) ($value ?? ''), $message);
        }

        return $message;
    }

    /** @return array<string, string> */
    private function load(string $locale): array
    {
        if (preg_match('/^[a-z]{2}$/', $locale) !== 1) {
            throw new RuntimeException('Kod języka ma nieprawidłowy format.');
        }
        $file = rtrim($this->catalogDirectory, '/') . '/' . $locale . '.php';
        if (!is_file($file)) {
            throw new RuntimeException("Brak katalogu tłumaczeń dla języka {$locale}.");
        }
        $messages = require $file;
        if (!is_array($messages)) {
            throw new RuntimeException("Katalog tłumaczeń {$locale} nie zwrócił tablicy.");
        }

        $normalized = [];
        foreach ($messages as $key => $message) {
            if (is_string($key) && is_string($message)) {
                $normalized[$key] = $message;
            }
        }

        return $normalized;
    }
}
