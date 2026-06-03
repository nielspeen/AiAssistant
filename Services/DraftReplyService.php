<?php

namespace Modules\AiAssistant\Services;

use App\Conversation;
use App\Thread;
use LanguageDetection\Language;
use Modules\AiAssistant\Models\Document;

class DraftReplyService
{
    private $customerContextService;
    private $documentSearchService;
    private $openAiService;

    public function __construct(
        CustomerContextService $customerContextService,
        DocumentSearchService $documentSearchService,
        OpenAiService $openAiService
    ) {
        $this->customerContextService = $customerContextService;
        $this->documentSearchService = $documentSearchService;
        $this->openAiService = $openAiService;
    }

    public function draft(Conversation $conversation, string $locale = '', int $documentLimit = 0): array
    {
        $threads = $conversation->threads()
            ->with(['created_by_user', 'created_by_customer'])
            ->orderBy('created_at', 'asc')
            ->get();

        $conversationContext = $this->conversationContext($conversation, $threads);
        $latestCustomerText = $this->latestCustomerText($threads);
        $locale = $this->normalizeLocale($locale ?: $this->detectLocale($latestCustomerText));
        $searchText = $this->searchText($conversation, $conversationContext, $latestCustomerText);
        $documentation = $this->documentationContext($conversation, $searchText, $locale, $documentLimit);
        $customerContext = $this->customerContextService->contextForConversation($conversation);

        $promptConfig = config('aiassistant.prompts.draft_reply');
        $textFormat = config('aiassistant.text_formats.draft_reply');

        if (!$promptConfig || !$textFormat) {
            throw new \Exception('AI Assistant draft_reply prompt or response schema is not configured');
        }

        $prompt = clone $promptConfig;
        $prompt->reply_locale = $locale;
        $prompt->reply_language = $this->languageName($locale);
        $prompt->conversation = $conversationContext;
        $prompt->documentation = $documentation['chunks'];
        $prompt->documentation_status = $documentation['status'];
        $prompt->mailbox_guidance = $customerContext['guidance'];
        $prompt->customer_context = $customerContext['data'];
        $prompt->customer_context_status = $customerContext['status'];

        $response = $this->openAiService->sendResponseRequest(
            $prompt,
            $this->openAiService->getConfiguredModel(),
            config('aiassistant.max_tokens.draft_reply', 10000),
            $textFormat
        );

        $content = $this->decodeStructuredResponse($response);

        return [
            'draft' => trim($content['draft'] ?? ''),
            'english_translation' => trim($content['english_translation'] ?? ''),
            'language' => $content['language'] ?? $locale,
            'confidence' => $content['confidence'] ?? 'low',
            'documentation_urls' => $content['documentation_urls'] ?? [],
            'staff_notes' => $content['staff_notes'] ?? [],
            'retrieved_documents' => $documentation['chunks'],
            'documentation_status' => $documentation['status'],
            'customer_context_status' => $customerContext['status'],
            'search_text' => $searchText,
        ];
    }

    private function conversationContext(Conversation $conversation, $threads): array
    {
        $customer = $conversation->customer;

        return [
            'id' => $conversation->id,
            'subject' => $conversation->subject,
            'mailbox_id' => $conversation->mailbox_id,
            'customer' => [
                'name' => $customer ? $customer->getFullName(true, true) : '',
                'email' => $conversation->customer_email,
            ],
            'threads' => $threads
                ->filter(function ($thread) {
                    return $thread->body !== null && trim($thread->body) !== '';
                })
                ->slice(-12)
                ->map(function ($thread) {
                    return [
                        'created_at' => $thread->created_at ? $thread->created_at->toDateTimeString() : '',
                        'type' => $this->threadType($thread),
                        'author' => $this->threadAuthor($thread),
                        'body' => $this->plainText($thread->body, 3000),
                    ];
                })
                ->values()
                ->toArray(),
        ];
    }

    private function documentationContext(Conversation $conversation, string $searchText, string $locale, int $limit): array
    {
        if (!$this->documentSearchService->canSearch()) {
            return [
                'status' => 'disabled',
                'chunks' => [],
            ];
        }

        try {
            $results = $this->documentSearchService->search(
                (int) $conversation->mailbox_id,
                $searchText,
                $locale,
                $limit
            );
        } catch (\Exception $e) {
            return [
                'status' => 'failed: ' . $e->getMessage(),
                'chunks' => [],
            ];
        }

        return [
            'status' => $results ? 'available' : 'no_matches',
            'chunks' => array_map(function ($result) {
                return [
                    'title' => $result['title'],
                    'url' => $result['url'],
                    'score' => round($result['score'], 4),
                    'content' => $this->plainText($result['content'], 1800),
                ];
            }, $results),
        ];
    }

    private function latestCustomerText($threads): string
    {
        $latest = $threads
            ->filter(function ($thread) {
                return (int) $thread->type === Thread::TYPE_CUSTOMER && $thread->body;
            })
            ->last();

        return $latest ? $this->plainText($latest->body, 3000) : '';
    }

    private function searchText(Conversation $conversation, array $conversationContext, string $latestCustomerText): string
    {
        $parts = [
            $conversation->subject,
            $latestCustomerText,
        ];

        if (!$latestCustomerText && !empty($conversationContext['threads'])) {
            $lastThread = end($conversationContext['threads']);
            $parts[] = $lastThread['body'] ?? '';
        }

        return $this->plainText(implode("\n", array_filter($parts)), 3000);
    }

    private function decodeStructuredResponse(array $response): array
    {
        if (($response['status'] ?? '') !== 'completed') {
            throw new \Exception('AI provider response status is not completed');
        }

        foreach (($response['output'] ?? []) as $output) {
            if (($output['type'] ?? '') === 'message') {
                $decoded = json_decode($output['content'][0]['text'] ?? '', true);

                if (is_array($decoded)) {
                    return $decoded;
                }
            }
        }

        throw new \Exception('Invalid draft reply response from AI provider');
    }

    private function detectLocale(string $text): string
    {
        if (!$text) {
            return Document::CANONICAL_LOCALE;
        }

        $scriptLocale = $this->detectLocaleByScript($text);

        if ($scriptLocale) {
            return $scriptLocale;
        }

        try {
            $languages = (array) (new Language())->detect($text)->close();
            $language = strtolower((string) array_key_first($languages));
        } catch (\Exception $e) {
            return Document::CANONICAL_LOCALE;
        }

        if (strpos($language, 'zh') === 0) {
            return 'zh';
        }

        if (strpos($language, 'ja') === 0 || strpos($language, 'jp') === 0) {
            return 'ja';
        }

        if (strpos($language, 'ko') === 0) {
            return 'ko';
        }

        return Document::CANONICAL_LOCALE;
    }

    private function detectLocaleByScript(string $text): string
    {
        if (preg_match('/[\x{1100}-\x{11ff}\x{3130}-\x{318f}\x{ac00}-\x{d7af}]/u', $text)) {
            return 'ko';
        }

        if (preg_match('/[\x{3040}-\x{30ff}]/u', $text)) {
            return 'ja';
        }

        if (preg_match('/\p{Han}/u', $text)) {
            return 'zh';
        }

        return '';
    }

    private function normalizeLocale(string $locale): string
    {
        $locale = strtolower(trim($locale));

        if ($locale === 'jp') {
            $locale = 'ja';
        }

        if (!in_array($locale, Document::SUPPORTED_LOCALES)) {
            return Document::CANONICAL_LOCALE;
        }

        return $locale;
    }

    private function languageName(string $locale): string
    {
        switch ($locale) {
            case 'ja':
                return 'Japanese';
            case 'zh':
                return 'Chinese';
            case 'ko':
                return 'Korean';
            case 'en':
            default:
                return 'English';
        }
    }

    private function threadType(Thread $thread): string
    {
        if ((int) $thread->type === Thread::TYPE_CUSTOMER) {
            return 'customer';
        }

        if ((int) $thread->type === Thread::TYPE_NOTE) {
            return 'internal_note';
        }

        if ((int) $thread->type === Thread::TYPE_MESSAGE) {
            return 'staff_reply';
        }

        return Thread::$types[$thread->type] ?? 'unknown';
    }

    private function threadAuthor(Thread $thread): string
    {
        if ($thread->created_by_user) {
            return trim($thread->created_by_user->getFullName());
        }

        if ($thread->created_by_customer) {
            return $thread->created_by_customer->getFullName(true, true);
        }

        return 'Unknown';
    }

    private function plainText(string $html, int $limit = 0): string
    {
        $text = HelperService::normalizeWhitespace(strip_tags(HelperService::stripTagsWithContent($html)));

        if ($limit > 0 && mb_strlen($text) > $limit) {
            return mb_substr($text, 0, $limit - 3) . '...';
        }

        return $text;
    }
}
