<?php

namespace Modules\AiAssistant\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Modules\AiAssistant\Models\Document;
use Modules\AiAssistant\Models\MailboxApiKey;
use Modules\AiAssistant\Services\DocumentIndexingService;
use Modules\AiAssistant\Services\DocumentationMarkdownService;

class ApiDocumentController extends Controller
{
    private $indexingService;
    private $markdownService;

    public function __construct(
        DocumentIndexingService $indexingService,
        DocumentationMarkdownService $markdownService
    ) {
        $this->indexingService = $indexingService;
        $this->markdownService = $markdownService;
    }

    public function store(Request $request)
    {
        if (!\Schema::hasTable('aiassistant_mailbox_api_keys')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Documentation API key storage is not ready. Run module migrations and try again.',
            ], 503);
        }

        $apiKey = $this->apiKeyFromRequest($request);

        if (!$apiKey) {
            return response()->json([
                'status' => 'error',
                'message' => 'Missing Authorization bearer token.',
            ], 401);
        }

        $mailboxApiKey = MailboxApiKey::findEnabledByToken($apiKey);

        if (!$mailboxApiKey) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid Authorization bearer token.',
            ], 401);
        }

        $data = $request->all();
        $errors = $this->validatePayload($data);

        if ($errors) {
            return response()->json([
                'status' => 'error',
                'message' => 'Documentation payload is invalid.',
                'errors' => $errors,
            ], 422);
        }

        $identifier = trim($data['identifier']);
        $content = trim($data['content']);
        $sourceIdentifier = Document::apiSourceIdentifier($identifier);
        $document = Document::where('mailbox_id', (int) $mailboxApiKey->mailbox_id)
            ->where('source_identifier', $sourceIdentifier)
            ->first();
        $created = !$document;

        if (!$document) {
            $document = new Document();
        }

        $publicUrl = $this->publicUrl($data);
        $title = $this->title($data, $content, $identifier);
        $localizedUrls = $this->localizedUrls($data, $publicUrl);

        $document->mailbox_id = (int) $mailboxApiKey->mailbox_id;
        $document->title = mb_substr($title, 0, 191);
        $document->source_type = Document::SOURCE_TYPE_API;
        $document->source_url = $publicUrl ?: $this->privateSourceUrl($identifier);
        $document->source_identifier = $sourceIdentifier;
        $document->canonical_locale = $this->canonicalLocale($data);
        $document->localized_urls = $localizedUrls;
        $document->enabled = $this->enabled($data);
        $document->content = $content;
        $document->content_hash = hash('sha256', $content);
        $document->metadata = [
            'api_identifier' => $identifier,
            'submitted_at' => now()->toDateTimeString(),
            'has_public_url' => (bool) $publicUrl,
        ];
        $sourceMetadataChanged = !$created && (
            $document->isDirty('source_url') ||
            $document->isDirty('localized_urls') ||
            $document->isDirty('canonical_locale')
        );

        if ($created || $document->isDirty('content_hash')) {
            $document->status = Document::STATUS_PENDING;
            $document->last_indexed_at = null;
            $document->last_error = null;
        }

        $document->save();

        $mailboxApiKey->last_used_at = now();
        $mailboxApiKey->save();

        try {
            $indexing = $this->indexingService->indexSubmittedContent($document, $content, $title, $sourceMetadataChanged);
        } catch (\Throwable $e) {
            \Log::error('AI Assistant documentation API indexing failed', [
                'mailbox_id' => $document->mailbox_id,
                'document_id' => $document->id,
                'identifier' => $identifier,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Documentation was saved, but indexing failed.',
                'document' => $this->documentResponse($document),
                'error' => [
                    'type' => 'indexing_failed',
                    'detail' => $e->getMessage(),
                ],
            ], 500);
        }

        $statusCode = $created ? 201 : 200;

        if (($indexing['status'] ?? '') === 'skipped') {
            $statusCode = $created ? 202 : 200;
        }

        return response()->json([
            'status' => 'success',
            'message' => $created ? 'Documentation created.' : 'Documentation updated.',
            'document' => $this->documentResponse($document->fresh()),
            'indexing' => $indexing,
        ], $statusCode);
    }

    private function apiKeyFromRequest(Request $request): string
    {
        $authorization = trim((string) $request->headers->get('Authorization'));

        if (stripos($authorization, 'Bearer ') === 0) {
            return trim(substr($authorization, 7));
        }

        return $authorization;
    }

    private function validatePayload(array $data): array
    {
        $errors = [];

        if (empty($data['identifier']) || !is_string($data['identifier']) || mb_strlen(trim($data['identifier'])) > 191) {
            $errors['identifier'] = ['Identifier is required and must be 191 characters or fewer.'];
        }

        if (empty($data['content']) || !is_string($data['content'])) {
            $errors['content'] = ['Content is required.'];
        }

        foreach (['title' => 191, 'public_url' => 2048, 'canonical_locale' => 10] as $field => $maxLength) {
            if (isset($data[$field]) && $data[$field] !== null && (!is_string($data[$field]) || mb_strlen(trim($data[$field])) > $maxLength)) {
                $errors[$field] = [ucfirst(str_replace('_', ' ', $field)) . ' must be a string of ' . $maxLength . ' characters or fewer.'];
            }
        }

        if (!empty($data['public_url']) && !filter_var(trim($data['public_url']), FILTER_VALIDATE_URL)) {
            $errors['public_url'] = ['Public URL must be a valid URL.'];
        }

        if (!empty($data['canonical_locale']) && !in_array(trim($data['canonical_locale']), Document::SUPPORTED_LOCALES)) {
            $errors['canonical_locale'] = ['Canonical locale must be one of: ' . implode(', ', Document::SUPPORTED_LOCALES) . '.'];
        }

        if (isset($data['enabled']) && filter_var($data['enabled'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === null) {
            $errors['enabled'] = ['Enabled must be true or false.'];
        }

        if (isset($data['localized_urls']) && $data['localized_urls'] !== null) {
            if (!is_array($data['localized_urls'])) {
                $errors['localized_urls'] = ['Localized URLs must be an object keyed by locale.'];
            } else {
                foreach ($data['localized_urls'] as $locale => $url) {
                    if (!in_array($locale, Document::SUPPORTED_LOCALES)) {
                        $errors['localized_urls.' . $locale] = ['Locale must be one of: ' . implode(', ', Document::SUPPORTED_LOCALES) . '.'];
                        continue;
                    }

                    if ($url !== null && $url !== '' && (!is_string($url) || mb_strlen(trim($url)) > 2048 || !filter_var(trim($url), FILTER_VALIDATE_URL))) {
                        $errors['localized_urls.' . $locale] = ['Localized URL must be a valid URL of 2048 characters or fewer.'];
                    }
                }
            }
        }

        return $errors;
    }

    private function publicUrl(array $data): string
    {
        if (empty($data['public_url'])) {
            return '';
        }

        return Document::normalizeSourceUrl($data['public_url']);
    }

    private function title(array $data, string $content, string $identifier): string
    {
        if (!empty($data['title'])) {
            return trim($data['title']);
        }

        $title = trim($this->markdownService->extractTitle($content));

        return $title ?: $identifier;
    }

    private function canonicalLocale(array $data): string
    {
        if (!empty($data['canonical_locale'])) {
            return trim($data['canonical_locale']);
        }

        return Document::CANONICAL_LOCALE;
    }

    private function localizedUrls(array $data, string $publicUrl): array
    {
        $urls = [];

        if (!empty($data['localized_urls']) && is_array($data['localized_urls'])) {
            foreach ($data['localized_urls'] as $locale => $url) {
                $url = trim((string) $url);

                if (in_array($locale, Document::SUPPORTED_LOCALES) && $url) {
                    $urls[$locale] = Document::normalizeSourceUrl($url);
                }
            }

            return $urls;
        }

        if ($publicUrl && preg_match('#/en(/|$)#', $publicUrl)) {
            return Document::localizedUrlsFor($publicUrl);
        }

        return $urls;
    }

    private function enabled(array $data): bool
    {
        if (!array_key_exists('enabled', $data)) {
            return true;
        }

        return filter_var($data['enabled'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) !== false;
    }

    private function privateSourceUrl(string $identifier): string
    {
        $slug = preg_replace('/[^A-Za-z0-9._:-]+/', '-', trim($identifier));
        $slug = trim($slug, '-');

        return 'api://' . mb_substr($slug ?: hash('sha256', $identifier), 0, 190);
    }

    private function documentResponse(Document $document): array
    {
        return [
            'id' => $document->id,
            'mailbox_id' => $document->mailbox_id,
            'title' => $document->title,
            'identifier' => $document->metadata()['api_identifier'] ?? null,
            'source_type' => $document->source_type,
            'source_url' => strpos($document->source_url, 'api://') === 0 ? null : $document->source_url,
            'status' => $document->status,
            'chunks_count' => $document->chunks()->count(),
            'content_hash' => $document->content_hash,
            'last_indexed_at' => $document->last_indexed_at ? $document->last_indexed_at->toDateTimeString() : null,
            'last_error' => $document->last_error,
        ];
    }
}
