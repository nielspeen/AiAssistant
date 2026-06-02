<?php

namespace Modules\AiAssistant\Http\Controllers;

use App\Mailbox;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Modules\AiAssistant\Models\Document;
use Modules\AiAssistant\Services\DocumentIndexingService;
use Modules\AiAssistant\Services\DocumentationMarkdownService;

class DocumentController extends Controller
{
    private $markdownService;

    public function __construct(DocumentationMarkdownService $markdownService)
    {
        $this->markdownService = $markdownService;
    }

    public function index()
    {
        $documents = Document::with('mailbox')
            ->withCount('chunks')
            ->orderBy('mailbox_id')
            ->orderBy('title')
            ->get();

        return view('aiassistant::documents.index', [
            'documents' => $documents,
            'mailboxes' => $this->mailboxes(),
        ]);
    }

    public function create()
    {
        return view('aiassistant::documents.form', [
            'document' => new Document([
                'source_type' => Document::SOURCE_TYPE_URL,
                'canonical_locale' => Document::CANONICAL_LOCALE,
                'enabled' => true,
            ]),
            'mailboxes' => $this->mailboxes(),
            'mode' => 'create',
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validatedData($request);
        $document = new Document();

        try {
            $this->fillDocument($document, $data);
        } catch (\Exception $e) {
            return back()->withErrors(['source_url' => $e->getMessage()])->withInput();
        }

        $document->save();

        \Session::flash('flash_success_floating', __('Documentation URL added'));

        return redirect()->route('aiassistant.documents');
    }

    public function bulkStore(Request $request)
    {
        $data = $this->validate($request, [
            'mailbox_id' => 'required|integer|exists:mailboxes,id',
            'urls' => 'required|string',
        ]);

        $urls = $this->parseBulkUrls($data['urls']);

        if (!$urls) {
            return back()->withErrors(['urls' => __('Enter at least one URL.')])->withInput();
        }

        $created = 0;
        $updated = 0;
        $unchanged = 0;
        $errors = [];

        foreach ($urls as $url) {
            if (!preg_match('#/en(/|$)#', $url)) {
                $errors[] = $url . ': ' . __('URL must contain /en/.');
                continue;
            }

            try {
                $sourceUrl = Document::normalizeSourceUrl($url);
                $sourceIdentifier = hash('sha256', $sourceUrl);
                $document = Document::where('mailbox_id', (int) $data['mailbox_id'])
                    ->where('source_identifier', $sourceIdentifier)
                    ->first();

                $isNew = !$document;

                if ($isNew) {
                    $document = new Document();
                }

                $oldSourceUrl = $document->source_url;
                $oldContentHash = $document->content_hash;

                $this->fillDocument($document, [
                    'mailbox_id' => $data['mailbox_id'],
                    'source_url' => $sourceUrl,
                    'enabled' => true,
                ]);

                if (!$isNew && $oldSourceUrl === $document->source_url && $oldContentHash === $document->content_hash) {
                    $unchanged++;
                } elseif ($isNew) {
                    $created++;
                } else {
                    $updated++;
                }

                $document->save();

                if ($oldSourceUrl !== $document->source_url || $oldContentHash !== $document->content_hash) {
                    $document->chunks()->delete();
                }
            } catch (\Exception $e) {
                $errors[] = $url . ': ' . $e->getMessage();
            }
        }

        $message = __('Documentation URLs processed') . ': ' . __('created') . ' ' . $created . ', ' . __('updated') . ' ' . $updated . ', ' . __('unchanged') . ' ' . $unchanged;

        if ($errors) {
            \Session::flash('flash_error_floating', __('Some URLs could not be added') . ':<br>' . implode('<br>', array_map('htmlspecialchars', $errors)));
        }

        \Session::flash('flash_success_floating', $message);

        return redirect()->route('aiassistant.documents');
    }

    public function edit($id)
    {
        return view('aiassistant::documents.form', [
            'document' => Document::findOrFail($id),
            'mailboxes' => $this->mailboxes(),
            'mode' => 'edit',
        ]);
    }

    public function update(Request $request, $id)
    {
        $document = Document::findOrFail($id);
        $oldSourceUrl = $document->source_url;
        $oldContentHash = $document->content_hash;
        $data = $this->validatedData($request);

        try {
            $this->fillDocument($document, $data);
        } catch (\Exception $e) {
            return back()->withErrors(['source_url' => $e->getMessage()])->withInput();
        }

        $document->save();

        if ($oldSourceUrl !== $document->source_url || $oldContentHash !== $document->content_hash) {
            $document->chunks()->delete();
        }

        \Session::flash('flash_success_floating', __('Documentation URL updated'));

        return redirect()->route('aiassistant.documents');
    }

    public function destroy($id)
    {
        $document = Document::findOrFail($id);
        $document->chunks()->delete();
        $document->delete();

        \Session::flash('flash_success_floating', __('Documentation URL deleted'));

        return redirect()->route('aiassistant.documents');
    }

    public function indexOne(Request $request, DocumentIndexingService $indexingService, $id)
    {
        $document = Document::findOrFail($id);
        $force = (bool) $request->get('force');
        $result = $this->indexDocument($indexingService, $document, $force);

        $this->flashIndexingResult([$result], $force ? __('Force reindex complete') : __('Reindex complete'));

        return redirect()->route('aiassistant.documents');
    }

    public function indexAll(Request $request, DocumentIndexingService $indexingService)
    {
        $force = (bool) $request->get('force');
        $limit = max(1, min(200, intval($request->get('limit', 50))));

        if (!$indexingService->canIndex()) {
            \Session::flash('flash_error_floating', __('Selected documentation embedding provider does not support embeddings. Documentation indexing is disabled.'));

            return redirect()->route('aiassistant.documents');
        }

        $query = Document::where('enabled', true)->orderBy('mailbox_id')->orderBy('title');

        if (!$force) {
            $embeddingModel = app(\Modules\AiAssistant\Services\OpenAiService::class)->getConfiguredEmbeddingModel();

            $query->where(function ($query) use ($embeddingModel) {
                $query->where('status', '!=', Document::STATUS_INDEXED)
                    ->orWhereNull('content_hash')
                    ->orWhereDoesntHave('chunks');

                if ($embeddingModel) {
                    $query->orWhereHas('chunks', function ($query) use ($embeddingModel) {
                        $query->where('embedding_model', '!=', $embeddingModel)
                            ->orWhereNull('embedding_model');
                    });
                }
            });
        }

        $documents = $query->limit($limit)->get();
        $results = [];

        foreach ($documents as $document) {
            $results[] = $this->indexDocument($indexingService, $document, $force);
        }

        $this->flashIndexingResult($results, $force ? __('Force reindex all complete') : __('Index pending/changed complete'));

        return redirect()->route('aiassistant.documents');
    }

    private function validatedData(Request $request): array
    {
        return $this->validate($request, [
            'mailbox_id' => 'required|integer|exists:mailboxes,id',
            'source_url' => ['required', 'url', 'max:2048', 'regex:#/en(/|$)#'],
            'enabled' => 'nullable|boolean',
        ]);
    }

    private function fillDocument(Document $document, array $data)
    {
        $sourceUrl = Document::normalizeSourceUrl($data['source_url']);
        $markdown = $this->markdownService->fetch($sourceUrl);
        $sourceChanged = $document->source_url !== $sourceUrl;
        $contentChanged = $document->content_hash !== $markdown['hash'];

        $document->mailbox_id = (int) $data['mailbox_id'];
        $document->title = mb_substr($markdown['title'], 0, 191);
        $document->source_type = Document::SOURCE_TYPE_URL;
        $document->source_url = $sourceUrl;
        $document->source_identifier = hash('sha256', $sourceUrl);
        $document->canonical_locale = Document::CANONICAL_LOCALE;
        $document->localized_urls = Document::localizedUrlsFor($sourceUrl);
        $document->enabled = !empty($data['enabled']);
        $document->content = $markdown['content'];
        $document->content_hash = $markdown['hash'];

        if (!$document->exists || $sourceChanged || $contentChanged) {
            $document->status = Document::STATUS_PENDING;
            $document->last_indexed_at = null;
            $document->last_error = null;
        }
    }

    private function mailboxes()
    {
        return Mailbox::orderBy('name')->get();
    }

    private function parseBulkUrls(string $urls): array
    {
        $lines = preg_split('/\R+/', $urls);
        $parsed = [];

        foreach ($lines as $line) {
            $url = trim($line);

            if (!$url) {
                continue;
            }

            $parsed[Document::normalizeSourceUrl($url)] = Document::normalizeSourceUrl($url);
        }

        return array_values($parsed);
    }

    private function indexDocument(DocumentIndexingService $indexingService, Document $document, bool $force): array
    {
        try {
            $result = $indexingService->index($document, $force);

            return [
                'title' => $document->title,
                'status' => $result['status'],
                'message' => $result['message'],
                'error' => false,
            ];
        } catch (\Exception $e) {
            return [
                'title' => $document->title,
                'status' => 'failed',
                'message' => $e->getMessage(),
                'error' => true,
            ];
        }
    }

    private function flashIndexingResult(array $results, string $title)
    {
        if (!$results) {
            \Session::flash('flash_success_floating', $title . ': ' . __('No documents to index.'));
            return;
        }

        $counts = [
            'indexed' => 0,
            'skipped' => 0,
            'failed' => 0,
        ];

        $lines = [];

        foreach ($results as $result) {
            if (!isset($counts[$result['status']])) {
                $counts[$result['status']] = 0;
            }

            $counts[$result['status']]++;
            $lines[] = htmlspecialchars($result['title']) . ': ' . htmlspecialchars($result['status']) . ' (' . htmlspecialchars($result['message']) . ')';
        }

        $message = $title . ': ' .
            __('indexed') . ' ' . ($counts['indexed'] ?? 0) . ', ' .
            __('skipped') . ' ' . ($counts['skipped'] ?? 0) . ', ' .
            __('failed') . ' ' . ($counts['failed'] ?? 0);

        if (count($lines) <= 10) {
            $message .= '<br>' . implode('<br>', $lines);
        }

        if (($counts['failed'] ?? 0) > 0) {
            \Session::flash('flash_error_floating', $message);
        } else {
            \Session::flash('flash_success_floating', $message);
        }
    }
}
