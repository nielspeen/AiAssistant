<?php

namespace Modules\AiAssistant\Console;

use Illuminate\Console\Command;
use Modules\AiAssistant\Models\Document;
use Modules\AiAssistant\Services\DocumentSearchService;

class SearchDocumentsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ai-assistant:search-documents {query} {--mailbox-id=} {--locale=en} {--limit=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Search indexed AI Assistant documentation';

    public function handle(DocumentSearchService $searchService)
    {
        $mailboxId = (int) $this->option('mailbox-id');

        if ($mailboxId <= 0) {
            $mailboxId = (int) Document::where('enabled', true)
                ->where('status', Document::STATUS_INDEXED)
                ->orderBy('mailbox_id')
                ->value('mailbox_id');
        }

        if ($mailboxId <= 0) {
            $this->warn('No indexed documentation found.');
            return;
        }

        if (!$searchService->canSearch()) {
            $this->warn('Selected documentation embedding provider does not support embeddings. Documentation search is disabled.');
            return;
        }

        $results = $searchService->search(
            $mailboxId,
            $this->argument('query'),
            (string) $this->option('locale'),
            intval($this->option('limit'))
        );

        if (!$results) {
            $this->info('No matching documentation found.');
            return;
        }

        foreach ($results as $index => $result) {
            $this->line(sprintf(
                '%d. %.4f %s',
                $index + 1,
                $result['score'],
                $result['title']
            ));
            $this->line($result['url']);
            $this->line($this->snippet($result['content']));
            $this->line('');
        }
    }

    private function snippet(string $content): string
    {
        $content = trim(preg_replace('/\s+/', ' ', $content));

        if (mb_strlen($content) <= 240) {
            return $content;
        }

        return mb_substr($content, 0, 237) . '...';
    }
}
