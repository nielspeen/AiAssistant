<?php

namespace Modules\AiAssistant\Http\Controllers;

use App\Conversation;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Modules\AiAssistant\Services\DraftReplyService;

class DraftReplyController extends Controller
{
    private $draftReplyService;

    public function __construct(DraftReplyService $draftReplyService)
    {
        $this->draftReplyService = $draftReplyService;
    }

    public function store(Request $request, $id)
    {
        $conversation = Conversation::with('customer')->findOrFail((int) $id);

        if (!auth()->user()->can('view', $conversation)) {
            abort(403);
        }

        $data = $this->validate($request, [
            'locale' => 'nullable|string|max:10',
            'document_limit' => 'nullable|integer|min:1|max:10',
        ]);

        try {
            $draft = $this->draftReplyService->draft(
                $conversation,
                $data['locale'] ?? '',
                (int) ($data['document_limit'] ?? 0)
            );
        } catch (\Throwable $e) {
            \Log::error('AI Assistant draft reply failed', [
                'conversation_id' => $conversation->id,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'msg' => $this->errorMessage($e),
                'error' => [
                    'type' => $this->errorType($e),
                    'detail' => $e->getMessage(),
                ],
            ], 500);
        }

        return response()->json([
            'status' => 'success',
            'draft' => $draft['draft'],
            'language' => $draft['language'],
            'confidence' => $draft['confidence'],
            'documentation_urls' => $draft['documentation_urls'],
            'staff_notes' => $draft['staff_notes'],
            'retrieved_documents' => $draft['retrieved_documents'],
            'documentation_status' => $draft['documentation_status'],
            'customer_context_status' => $draft['customer_context_status'],
        ]);
    }

    private function errorMessage(\Throwable $e): string
    {
        $message = $e->getMessage();

        if (strpos($message, 'draft_reply') !== false || strpos($message, 'clone()') !== false) {
            return 'Draft reply prompt configuration is missing or stale. Refresh the module config/cache and try again.';
        }

        if (strpos($message, 'API key') !== false) {
            return $message;
        }

        if (strpos($message, 'HTTP error') !== false) {
            return 'AI provider request failed: ' . $message;
        }

        if (strpos($message, 'documentation embedding provider') !== false) {
            return $message;
        }

        return 'Could not draft reply: ' . $message;
    }

    private function errorType(\Throwable $e): string
    {
        $message = $e->getMessage();

        if (strpos($message, 'draft_reply') !== false || strpos($message, 'clone()') !== false) {
            return 'configuration';
        }

        if (strpos($message, 'API key') !== false) {
            return 'missing_api_key';
        }

        if (strpos($message, 'HTTP error') !== false) {
            return 'provider_http_error';
        }

        if (strpos($message, 'documentation embedding provider') !== false) {
            return 'embedding_provider';
        }

        return 'server_error';
    }
}
