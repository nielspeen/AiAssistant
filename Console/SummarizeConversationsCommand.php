<?php

namespace Modules\AiAssistant\Console;

use App\Conversation;
use Illuminate\Console\Command;
use Modules\AiAssistant\Services\HelperService;
use Modules\AiAssistant\Services\OpenAiService;

class SummarizeConversationsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ai-assistant:summarize-conversations';

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
                ->get();

            // Build conversation thread JSON structure
            $conversationThread = [
                'subject' => $conversation->subject,
                'threads' => $threads->map(function ($thread) {
                    if ($thread->created_by_user_id) {
                        $author = $thread->created_by_user->first_name;
                    } elseif ($thread->created_by_customer_id) {
                        $author = $thread->created_by_customer->first_name;
                    } else {
                        $author = 'Unknown';
                    }
                    return [
                        'body' => HelperService::normalizeWhitespace(strip_tags(HelperService::stripTagsWithContent($thread->body))),
                        'created_at' => $thread->created_at->toDateTimeString(),
                        'type' => $thread->type,
                        'author' => $author,
                    ];
                })->toArray()
            ];

            $this->info("Processing conversation #{$conversation->id}: {$conversation->subject}");

            // Send to OpenAI for summarization
            $response = $openAiService->sendResponseRequest(
                tap(clone config('aiassistant.prompts.summarize_conversation'), function ($prompt) use ($conversationThread) {
                    $prompt->conversation = $conversationThread;
                }),
                config('aiassistant.model'),
                config('aiassistant.max_tokens.summarize_conversation'),
                config('aiassistant.text_formats.summarize_conversation')
            );

            if ($response["status"] != "completed") {
                \Log::error("OpenAI response status is not completed for conversation #{$conversation->id}. Error: {$response['error']}");
                continue;
            }

            foreach ($response['output'] as $output) {
                if ($output["type"] == "message") {
                    $content = json_decode($output["content"][0]["text"], true);
                }
            }

            $aiData = [];
            if (!is_null($conversation->ai_assistant)) {
                $aiData = json_decode($conversation->ai_assistant, true);
            }

            if (!isset($content['one_liner']) || !isset($content['summary'])) {
                \Log::error("Invalid response from OpenAI for conversation #{$conversation->id}");
                continue;
            }

            $aiData['one_liner'] = $content['one_liner'];
            $aiData['summary'] = $content['summary'];
            $conversation->ai_assistant = json_encode($aiData);
            $conversation->ai_assistant_updated_at = now();
            $conversation->save();
        }
    }

    private function getConversations()
    {
        $summary_conversation_threshold = intval(\Option::get('aiassistant.summary_conversation_threshold', 3));

        return Conversation::withCount('threads')
            ->where('threads_count', '>', $summary_conversation_threshold)
            ->where('updated_at', '>', now()->subDays(1))
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
