<?php

namespace Modules\AiAssistant\Console;

use App\Conversation;
use Illuminate\Console\Command;
use Modules\AiAssistant\Services\DraftReplyService;

class DraftReplyCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ai-assistant:draft-reply {conversation-id} {--locale=} {--document-limit=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Draft a reply for a conversation using indexed documentation';

    public function handle(DraftReplyService $draftReplyService)
    {
        $conversation = Conversation::with('customer')->find((int) $this->argument('conversation-id'));

        if (!$conversation) {
            $this->error('Conversation not found.');
            return;
        }

        $this->info("Drafting reply for conversation #{$conversation->id}: {$conversation->subject}");

        try {
            $result = $draftReplyService->draft(
                $conversation,
                (string) $this->option('locale'),
                intval($this->option('document-limit'))
            );
        } catch (\Exception $e) {
            $this->error($e->getMessage());
            return;
        }

        $this->line('');
        $this->line('Draft');
        $this->line('-----');
        $this->line($result['draft']);
        $this->line('');
        $this->line('Language: ' . $result['language']);
        $this->line('Confidence: ' . $result['confidence']);
        $this->line('Documentation: ' . $result['documentation_status']);
        $this->line('Customer Context: ' . $result['customer_context_status']);

        if (!empty($result['documentation_urls'])) {
            $this->line('');
            $this->line('URLs');
            $this->line('----');

            foreach ($result['documentation_urls'] as $url) {
                $this->line('- ' . $url);
            }
        }

        if (!empty($result['staff_notes'])) {
            $this->line('');
            $this->line('Staff Notes');
            $this->line('-----------');

            foreach ($result['staff_notes'] as $note) {
                $this->line('- ' . $note);
            }
        }

        if (!empty($result['retrieved_documents'])) {
            $this->line('');
            $this->line('Retrieved Documentation');
            $this->line('-----------------------');

            foreach ($result['retrieved_documents'] as $index => $document) {
                $this->line(sprintf(
                    '%d. %.4f %s',
                    $index + 1,
                    $document['score'],
                    $document['title']
                ));
                $this->line($document['url']);
            }
        }
    }
}
