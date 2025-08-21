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
}
