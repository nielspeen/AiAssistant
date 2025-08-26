<?php

namespace Modules\AiAssistant\Console;

use App\Thread;
use Illuminate\Console\Command;
use LanguageDetection\Language;
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

        $desiredLanguage = \Option::get('aiassistant.translation_language', 'en');
        $languageDetector  = new Language();

        foreach ($threads as $thread) {

            if ($thread->created_by_user_id) {
                $author = $thread->created_by_user->first_name;
            } elseif ($thread->created_by_customer_id) {
                $author = $thread->created_by_customer->first_name;
            } else {
                $author = 'Unknown';
            }

            if (empty($thread->body)) {
                $this->info("Thread #{$thread->id} has no body. Skipping.");
                continue;
            }

            // Build conversation thread JSON structure
            $threadData = [
                'body' => HelperService::normalizeWhitespace(strip_tags(HelperService::stripTagsWithContent($this->extractTextForLanguageTranslation($thread->body)))),
                'author' => $author,
            ];

            // Make sure it's not already in English
            $languages = (array)$languageDetector->detect($threadData['body'])->close();

            if (array_key_first($languages) === $desiredLanguage) {
                $this->info("Thread #{$thread->id} is already in desired language ({$desiredLanguage}). Skipping.");
                $thread->ai_assistant_updated_at = now();
                $thread->save();
                continue;
            }

            $this->info("Translating thread #{$thread->id}, language: " . array_key_first($languages));

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

            if (!isset($content['translation']) || trim($content["translation"]) == "") {
                \Log::error("Invalid response from OpenAI for thread #{$thread->id}: " . json_encode($content));
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

    private function extractTextForLanguageTranslation(string $text): string
    {
        // Split into lines
        $lines = explode("\n", $text);
        $extractedLines = [];

        foreach ($lines as $line) {
            $line = trim($line);

            // Skip empty lines
            if (empty($line)) {
                continue;
            }

            // Stop if we hit a quote mark (common email quote indicators)
            if (preg_match('/^[>|\|]/', $line) ||
                preg_match('/^On .+ wrote:$/', $line) ||
                preg_match('/^From: .+$/', $line) ||
                preg_match('/^Sent: .+$/', $line) ||
                preg_match('/^To: .+$/', $line) ||
                preg_match('/^Subject: .+$/', $line) ||
                preg_match('/^Date: .+$/', $line) ||
                preg_match('/^-{3,}$/', $line) || // Separator lines
                preg_match('/^_{3,}$/', $line) || // Separator lines
                preg_match('/<div id="ymail_android_signature">/', $line) || // Yahoo Mail signature
                preg_match('/<a id="ymail_android_signature_link"/', $line)) { // Yahoo Mail signature link
                break;
            }

            $extractedLines[] = $line;
        }

        $result = implode(' ', $extractedLines);

        // If we still have no content, just return the first few lines without filtering
        if (empty($result) && count($lines) > 0) {
            $firstLines = array_slice($lines, 0, min(3, count($lines)));
            $result = implode(' ', array_filter(array_map('trim', $firstLines)));
        }

        return $result;
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
