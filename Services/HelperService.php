<?php

namespace Modules\AiAssistant\Services;

class HelperService
{
    /**
     * Remove specified tags *and their content* from HTML.
     *
     * @param string $html
     * @param array<string> $tags Tags to remove completely (case-insensitive).
     * @return string
     */
    public static function stripTagsWithContent(string $html, array $tags = ['style', 'script']): string
    {
        foreach ($tags as $tag) {
            $pattern = sprintf('#<%1$s\b[^>]*>.*?</%1$s>#is', preg_quote($tag, '#'));
            $html = preg_replace($pattern, '', $html) ?? $html;
        }

        // Now strip remaining tags normally.
        return strip_tags($html);
    }

    /**
     * Normalize whitespace and newlines after strip_tags().
     *
     * @param string $text
     * @return string
     */
    public static function normalizeWhitespace(string $text): string
    {
        // Convert all line breaks (\r\n, \r) into a single \n
        $text = preg_replace('/\R/u', "\n", $text) ?? $text;

        // Trim spaces/tabs from start and end of each line
        $text = preg_replace('/^[ \t]+|[ \t]+$/m', '', $text) ?? $text;

        // Collapse multiple blank lines into a single newline
        $text = preg_replace("/\n{2,}/", "\n", $text) ?? $text;

        // Trim leading/trailing whitespace overall
        return trim($text);
    }

    public static function plainTranslation($translation): string
    {
        if (is_array($translation) || is_object($translation)) {
            $translation = (array) $translation;

            foreach (['translation', 'body', 'text', 'message', 'content', 'english_translation'] as $key) {
                if (isset($translation[$key])) {
                    return self::plainTranslation($translation[$key]);
                }
            }

            return self::normalizeTranslationWhitespace(implode("\n", array_filter(array_map(function ($value) {
                return self::plainTranslation($value);
            }, $translation))));
        }

        $translation = trim((string) $translation);

        if ($translation === '') {
            return '';
        }

        $decoded = json_decode($translation, true);

        if (json_last_error() === JSON_ERROR_NONE && (is_array($decoded) || is_object($decoded))) {
            return self::plainTranslation($decoded);
        }

        $translation = html_entity_decode($translation, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $translation = preg_replace('#<br\s*/?>#i', "\n", $translation) ?? $translation;
        $translation = strip_tags($translation);

        return self::normalizeTranslationWhitespace($translation);
    }

    private static function normalizeTranslationWhitespace(string $text): string
    {
        $text = preg_replace('/\R/u', "\n", $text) ?? $text;
        $text = preg_replace('/^[ \t]+|[ \t]+$/m', '', $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return trim($text);
    }
}
