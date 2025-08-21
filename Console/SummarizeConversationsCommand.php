<?php

namespace Modules\AiAssistant\Console;

use App\Conversation;
use Illuminate\Console\Command;
use Modules\AiAssistant\Services\OpenAiService;

class SummarizeConversationsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'aiassistant:summarize-conversations';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Summarize conversations';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $apiKey = config('aiassistant.api_key');
        if (!$apiKey) {
            $this->error('API key is not set');
            return;
        }

        $openAiService = new OpenAiService();
        $conversations = $this->getConversations();

        if ($conversations->count() === 0) {
            return;
        }

        foreach ($conversations as $conversation) {
            // Get threads ordered by creation time (oldest first for chronological order)
            $threads = $conversation->threads()
                ->orderBy('created_at', 'asc')
                ->get(['body', 'created_at', 'type']);

            // Build conversation thread JSON structure
            $conversationThread = [
                'conversation_id' => $conversation->id,
                'subject' => $conversation->subject,
                'status' => $conversation->status,
                'threads' => $threads->map(function ($thread) {
                    return [
                        'body' => $thread->body,
                        'created_at' => $thread->created_at->toDateTimeString(),
                        'type' => $thread->type,
                        'source_via' => $thread->source_via,
                    ];
                })->toArray()
            ];

            // Convert to JSON for OpenAI
            $threadJson = json_encode($conversationThread, JSON_PRETTY_PRINT);

            $this->info("Processing conversation #{$conversation->id}: {$conversation->subject}");

            // Send to OpenAI for summarization
            $response = $openAiService->sendChatCompletion(
                config('aiassistant.prompts.summarize_conversation') . $threadJson,
                config('aiassistant.model')
            );

            $content = $response['choices'][0]['message']['content'];
            $aiData = [];
            if (!is_null($conversation->ai_assistant)) {
                $aiData = json_decode($conversation->ai_assistant, true);
            }
            $aiData['summary'] = $content;
            $conversation->ai_assistant = json_encode($aiData);
            $conversation->ai_assistant_updated_at = now();
            $conversation->save();
        }
    }

    private function getConversations()
    {
        return Conversation::where('updated_at', '>', now()->subDays(1))
            ->where(function ($query) {
                $query->whereNull('ai_assistant_updated_at')
                      ->orWhereColumn('updated_at', '>', 'ai_assistant_updated_at');
            })
            ->where('status', '!=', Conversation::STATUS_CLOSED)
            ->where('status', '!=', Conversation::STATUS_SPAM)
            ->where('state', '!=', Conversation::STATE_DELETED)
            ->get();
    }
}
