<?php

namespace Modules\AiAssistant\Services;

class DocumentChunker
{
    public function chunks(string $markdown, int $chunkSize, int $chunkOverlap): array
    {
        $markdown = $this->stripFrontMatter($markdown);
        $markdown = trim(preg_replace("/\r\n?/", "\n", $markdown));

        if ($markdown === '') {
            return [];
        }

        $chunkSize = max(500, $chunkSize);
        $chunkOverlap = max(0, min($chunkOverlap, (int) floor($chunkSize / 2)));
        $paragraphs = preg_split("/\n{2,}/", $markdown);
        $chunks = [];
        $current = '';

        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);

            if ($paragraph === '') {
                continue;
            }

            if (mb_strlen($paragraph) > $chunkSize) {
                if ($current !== '') {
                    $chunks[] = $current;
                    $current = '';
                }

                foreach ($this->splitLongText($paragraph, $chunkSize, $chunkOverlap) as $part) {
                    $chunks[] = $part;
                }

                continue;
            }

            $candidate = $current === '' ? $paragraph : $current . "\n\n" . $paragraph;

            if (mb_strlen($candidate) <= $chunkSize) {
                $current = $candidate;
                continue;
            }

            $chunks[] = $current;
            $current = $this->withOverlap($current, $chunkOverlap, $paragraph);
        }

        if ($current !== '') {
            $chunks[] = $current;
        }

        return array_values(array_filter(array_map('trim', $chunks)));
    }

    private function stripFrontMatter(string $markdown): string
    {
        return preg_replace('/\A---\s*\R.*?\R---\s*/s', '', $markdown, 1);
    }

    private function splitLongText(string $text, int $chunkSize, int $chunkOverlap): array
    {
        $chunks = [];
        $offset = 0;
        $length = mb_strlen($text);
        $step = max(1, $chunkSize - $chunkOverlap);

        while ($offset < $length) {
            $chunks[] = trim(mb_substr($text, $offset, $chunkSize));
            $offset += $step;
        }

        return $chunks;
    }

    private function withOverlap(string $previous, int $chunkOverlap, string $next): string
    {
        if (!$chunkOverlap) {
            return $next;
        }

        $overlap = trim(mb_substr($previous, -$chunkOverlap));

        return $overlap ? $overlap . "\n\n" . $next : $next;
    }
}
