<?php

namespace Modules\AiAssistant\Services;

use Modules\AiAssistant\Models\Document;

class DocumentationMarkdownService
{
    public function fetch(string $sourceUrl): array
    {
        $markdownUrl = Document::markdownUrlFor($sourceUrl);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $markdownUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT => 'FreeScout-AI-Assistant/1.0',
        ]);

        $content = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        if ($error) {
            throw new \Exception('Unable to fetch Markdown: ' . $error);
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            throw new \Exception('Unable to fetch Markdown: HTTP ' . $httpCode);
        }

        if (!trim($content)) {
            throw new \Exception('Unable to fetch Markdown: empty response');
        }

        return [
            'content' => $content,
            'title' => $this->extractTitle($content) ?: $this->titleFromUrl($sourceUrl),
            'hash' => hash('sha256', $content),
        ];
    }

    public function extractTitle(string $markdown): string
    {
        if (preg_match('/\A---\s*\R(.*?)\R---\s*/s', $markdown, $matches)) {
            if (preg_match('/^title:\s*(.+?)\s*$/mi', $matches[1], $titleMatch)) {
                return trim($titleMatch[1], " \t\n\r\0\x0B\"'");
            }
        }

        if (preg_match('/^\s*#\s+(.+?)\s*$/m', $markdown, $matches)) {
            return trim($matches[1]);
        }

        return '';
    }

    private function titleFromUrl(string $sourceUrl): string
    {
        $path = parse_url(Document::normalizeSourceUrl($sourceUrl), PHP_URL_PATH);
        $basename = basename($path ?: '');

        return $basename ? ucwords(str_replace(['-', '_'], ' ', $basename)) : 'Documentation';
    }
}
