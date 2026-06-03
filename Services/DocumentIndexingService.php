<?php

namespace Modules\AiAssistant\Services;

use Modules\AiAssistant\Models\Document;

class DocumentIndexingService
{
    private $chunker;
    private $markdownService;
    private $openAiService;

    public function __construct(
        DocumentChunker $chunker,
        DocumentationMarkdownService $markdownService,
        OpenAiService $openAiService
    ) {
        $this->chunker = $chunker;
        $this->markdownService = $markdownService;
        $this->openAiService = $openAiService;
    }

    public function canIndex(): bool
    {
        return $this->openAiService->providerSupportsEmbeddings()
            && $this->openAiService->getConfiguredEmbeddingModel();
    }

    public function index(Document $document, bool $force = false): array
    {
        if (!$document->enabled) {
            return ['status' => 'skipped', 'message' => 'Document is disabled'];
        }

        if (!$this->canIndex()) {
            return ['status' => 'skipped', 'message' => 'Selected documentation embedding provider does not support embeddings'];
        }

        try {
            if ($document->source_type === Document::SOURCE_TYPE_API) {
                return $this->indexContent($document, (string) $document->content, (string) $document->title, $force);
            }

            $markdown = $this->markdownService->fetch($document->source_url);

            return $this->indexContent($document, $markdown['content'], $markdown['title'], $force);
        } catch (\Exception $e) {
            $document->status = Document::STATUS_FAILED;
            $document->last_error = $e->getMessage();
            $document->save();

            throw $e;
        }
    }

    public function indexSubmittedContent(Document $document, string $content, string $title = '', bool $force = false): array
    {
        if (!$document->enabled) {
            return ['status' => 'skipped', 'message' => 'Document is disabled'];
        }

        if (!$this->canIndex()) {
            return ['status' => 'skipped', 'message' => 'Selected documentation embedding provider does not support embeddings'];
        }

        return $this->indexContent($document, $content, $title, $force);
    }

    private function indexContent(Document $document, string $content, string $title = '', bool $force = false): array
    {
        $content = trim($content);

        if (!$content) {
            throw new \Exception('Document has no indexable content');
        }

        try {
            $markdown = [
                'content' => $content,
                'title' => $title ?: $this->markdownService->extractTitle($content) ?: $document->title,
                'hash' => hash('sha256', $content),
            ];
            $contentChanged = $document->content_hash !== $markdown['hash'];
            $hasChunks = $document->chunks()->count() > 0;
            $embeddingModel = $this->openAiService->getConfiguredEmbeddingModel();
            $hasCurrentEmbeddingChunks = $embeddingModel
                ? $document->chunks()->where('embedding_model', $embeddingModel)->exists()
                : false;

            $document->title = mb_substr($markdown['title'], 0, 191);
            $document->content = $markdown['content'];
            $document->content_hash = $markdown['hash'];

            if (!$force && !$contentChanged && $hasChunks && $hasCurrentEmbeddingChunks && $document->status === Document::STATUS_INDEXED) {
                $document->last_error = null;
                $document->save();

                return ['status' => 'skipped', 'message' => 'Document is unchanged'];
            }

            $chunks = $this->chunker->chunks(
                $document->content,
                $this->chunkSize(),
                $this->chunkOverlap()
            );

            if (!$chunks) {
                throw new \Exception('Document has no indexable content');
            }

            $embeddings = $this->openAiService->createEmbeddings($chunks, $embeddingModel);

            if (count($embeddings) !== count($chunks)) {
                throw new \Exception('Embeddings count does not match chunk count');
            }

            $document->chunks()->delete();

            foreach ($chunks as $index => $chunk) {
                $document->chunks()->create([
                    'chunk_index' => $index,
                    'content' => $chunk,
                    'content_hash' => hash('sha256', $chunk),
                    'token_count' => $this->estimateTokens($chunk),
                    'embedding' => $embeddings[$index],
                    'embedding_model' => $embeddingModel,
                    'metadata' => [
                        'source_url' => $document->source_url,
                        'localized_urls' => $document->localizedUrls(),
                    ],
                ]);
            }

            $document->status = Document::STATUS_INDEXED;
            $document->last_indexed_at = now();
            $document->last_error = null;
            $document->save();

            return ['status' => 'indexed', 'message' => count($chunks) . ' chunks'];
        } catch (\Exception $e) {
            $document->status = Document::STATUS_FAILED;
            $document->last_error = $e->getMessage();
            $document->save();

            throw $e;
        }
    }

    private function chunkSize(): int
    {
        return intval(\Option::get('aiassistant.documentation.chunk_size', config('aiassistant.documentation.chunk_size', 3000)));
    }

    private function chunkOverlap(): int
    {
        return intval(\Option::get('aiassistant.documentation.chunk_overlap', config('aiassistant.documentation.chunk_overlap', 400)));
    }

    private function estimateTokens(string $text): int
    {
        return (int) ceil(mb_strlen($text) / 4);
    }
}
