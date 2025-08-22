<?php

namespace Modules\AiAssistant\Console;

use App\Thread;
use Illuminate\Console\Command;
use Modules\AiAssistant\Services\HelperService;
use Modules\AiAssistant\Services\OpenAiService;

class TranslateThreadsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ai-assistant:translate-threads';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Translate threads to the selected language';

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
        $threads = $this->getThreads();

        if ($threads->count() === 0) {
            return;
        }

        foreach ($threads as $thread) {

            if ($thread->created_by_user_id) {
                $author = $thread->created_by_user->first_name;
            } elseif ($thread->created_by_customer_id) {
                $author = $thread->created_by_customer->first_name;
            } else {
                $author = 'Unknown';
            }

            // Build conversation thread JSON structure
            $threadData = [
                'body' => HelperService::normalizeWhitespace(strip_tags(HelperService::stripTagsWithContent($thread->body))),
                'author' => $author,
            ];


            $this->info("Processing thread #{$thread->id}.");

            // Send to OpenAI for summarization
            $response = $openAiService->sendResponseRequest(
                tap(clone config('aiassistant.prompts.translate_thread'), function ($prompt) use ($threadData) {
                    $prompt->thread = $threadData;
                }),
                config('aiassistant.model'),
                config('aiassistant.max_tokens.translate_thread'),
                config('aiassistant.text_formats.translate_thread')
            );

            if ($response["status"] != "completed") {
                \Log::error("OpenAI response status is not completed for thread #{$thread->id}. Error: {$response['error']}");
                continue;
            }

            foreach ($response['output'] as $output) {
                if ($output["type"] == "message") {
                    $content = json_decode($output["content"][0]["text"], true);
                }
            }

            $aiData = [];
            if (!is_null($thread->ai_assistant)) {
                $aiData = json_decode($thread->ai_assistant, true);
            }

            if (!isset($content['translation'])) {
                \Log::error("Invalid response from OpenAI for thread #{$thread->id}");
                continue;
            }

            if ($content['same_language']) {
                $thread->ai_assistant_updated_at = now();
                $thread->save();
                continue;
            }

            $aiData['translation'] = $content['translation'];
            $thread->ai_assistant = json_encode($aiData);
            $thread->ai_assistant_updated_at = now();
            $thread->save();
            return;
        }
    }

    private function getThreads()
    {
        return Thread::where('updated_at', '>', now()->subDays(1))
            ->where(function ($query) {
                $query->whereNull('ai_assistant_updated_at')
                      ->orWhereColumn('updated_at', '>', 'ai_assistant_updated_at');
            })
            ->where('status', '!=', Thread::STATUS_CLOSED)
            ->where('status', '!=', Thread::STATUS_SPAM)
            ->where('state', '!=', Thread::STATE_HIDDEN)
            ->get();
    }

}
