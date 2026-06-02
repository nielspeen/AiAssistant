<?php

namespace Modules\AiAssistant\Services;

use Modules\AiAssistant\Models\Document;
use Modules\AiAssistant\Models\DocumentChunk;

class DocumentSearchService
{
    private $openAiService;

    public function __construct(OpenAiService $openAiService)
    {
        $this->openAiService = $openAiService;
    }

    public function canSearch(): bool
    {
        return $this->openAiService->providerSupportsEmbeddings()
            && $this->openAiService->getConfiguredEmbeddingModel();
    }

    public function search(int $mailboxId, string $query, string $locale = Document::CANONICAL_LOCALE, int $limit = 0): array
    {
        $query = trim($query);

        if (!$query) {
            return [];
        }

        if (!$this->canSearch()) {
            throw new \Exception('Selected documentation embedding provider does not support embeddings');
        }

        $embeddingModel = $this->openAiService->getConfiguredEmbeddingModel();
        $queryEmbedding = $this->openAiService->createEmbeddings([$query], $embeddingModel)[0] ?? [];

        if (!$queryEmbedding) {
            throw new \Exception('Query embedding is empty');
        }

        $limit = $limit > 0 ? $limit : $this->retrievalLimit();
        $locale = $this->normalizeLocale($locale);
        $results = [];

        DocumentChunk::with('document')
            ->where('embedding_model', $embeddingModel)
            ->whereHas('document', function ($query) use ($mailboxId) {
                $query->where('mailbox_id', $mailboxId)
                    ->where('enabled', true)
                    ->where('status', Document::STATUS_INDEXED);
            })
            ->orderBy('document_id')
            ->orderBy('chunk_index')
            ->chunk(200, function ($chunks) use (&$results, $queryEmbedding, $locale) {
                foreach ($chunks as $chunk) {
                    $embedding = $chunk->embedding();

                    if (count($embedding) !== count($queryEmbedding)) {
                        continue;
                    }

                    $score = $this->cosineSimilarity($queryEmbedding, $embedding);

                    if ($score <= 0) {
                        continue;
                    }

                    $document = $chunk->document;

                    if (!$document) {
                        continue;
                    }

                    $results[] = [
                        'score' => $score,
                        'document_id' => $document->id,
                        'chunk_id' => $chunk->id,
                        'chunk_index' => $chunk->chunk_index,
                        'title' => $document->title,
                        'url' => $document->localizedUrl($locale),
                        'content' => $chunk->content,
                    ];
                }
            });

        usort($results, function ($a, $b) {
            return $b['score'] <=> $a['score'];
        });

        return array_slice($results, 0, $limit);
    }

    private function cosineSimilarity(array $a, array $b): float
    {
        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        foreach ($a as $index => $value) {
            $aValue = (float) $value;
            $bValue = (float) $b[$index];

            $dot += $aValue * $bValue;
            $normA += $aValue * $aValue;
            $normB += $bValue * $bValue;
        }

        if ($normA <= 0 || $normB <= 0) {
            return 0.0;
        }

        return $dot / (sqrt($normA) * sqrt($normB));
    }

    private function retrievalLimit(): int
    {
        return max(1, intval(\Option::get(
            'aiassistant.documentation.retrieval_limit',
            config('aiassistant.documentation.retrieval_limit', 5)
        )));
    }

    private function normalizeLocale(string $locale): string
    {
        $locale = strtolower(trim($locale));

        if (!in_array($locale, Document::SUPPORTED_LOCALES)) {
            return Document::CANONICAL_LOCALE;
        }

        return $locale;
    }
}
