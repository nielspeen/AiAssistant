<?php

namespace Modules\AiAssistant\Console;

use Illuminate\Console\Command;
use Modules\AiAssistant\Models\Document;
use Modules\AiAssistant\Services\DocumentIndexingService;

class IndexDocumentsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ai-assistant:index-documents {--document-id=} {--force} {--limit=25}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Index AI Assistant documentation';

    public function handle(DocumentIndexingService $indexingService)
    {
        if (!$indexingService->canIndex()) {
            $this->warn('Selected documentation embedding provider does not support embeddings. Documentation indexing is disabled.');
            return;
        }

        $query = Document::where('enabled', true)->orderBy('id');

        if ($this->option('document-id')) {
            $query->where('id', (int) $this->option('document-id'));
        } elseif (!$this->option('force')) {
            $query->where(function ($query) {
                $query->where('status', '!=', Document::STATUS_INDEXED)
                    ->orWhereNull('content_hash')
                    ->orWhereDoesntHave('chunks');
            });
        }

        $limit = max(1, intval($this->option('limit')));
        $documents = $query->limit($limit)->get();

        if (!$documents->count()) {
            $this->info('No documents to index.');
            return;
        }

        foreach ($documents as $document) {
            try {
                $result = $indexingService->index($document, (bool) $this->option('force'));
                $this->info("#{$document->id} {$document->title}: {$result['status']} ({$result['message']})");
            } catch (\Exception $e) {
                $this->error("#{$document->id} {$document->title}: failed ({$e->getMessage()})");
            }
        }
    }
}
