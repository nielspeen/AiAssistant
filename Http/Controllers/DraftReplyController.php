<?php

namespace Modules\AiAssistant\Http\Controllers;

use App\Conversation;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Modules\AiAssistant\Jobs\DraftReplyJob;
use Modules\AiAssistant\Models\DraftJob;

class DraftReplyController extends Controller
{
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

        $draftJob = DraftJob::create([
            'conversation_id' => (int) $conversation->id,
            'user_id' => (int) auth()->user()->id,
            'status' => DraftJob::STATUS_PENDING,
            'locale' => (string) ($data['locale'] ?? ''),
            'document_limit' => (int) ($data['document_limit'] ?? 0),
        ]);

        DraftReplyJob::dispatch((int) $draftJob->id)
            ->onQueue(\Helper::QUEUE_DEFAULT);

        return response()->json([
            'status' => 'success',
            'draft_status' => $draftJob->status,
            'job_id' => $draftJob->id,
            'poll_url' => route('aiassistant.draft_jobs.show', ['id' => $draftJob->id]),
        ]);
    }

    public function show(Request $request, $id)
    {
        $draftJob = DraftJob::findOrFail((int) $id);
        $conversation = Conversation::findOrFail((int) $draftJob->conversation_id);

        if (!auth()->user()->can('view', $conversation)) {
            abort(403);
        }

        if ((int) $draftJob->user_id !== (int) auth()->user()->id && !auth()->user()->isAdmin()) {
            abort(403);
        }

        if ($draftJob->status === DraftJob::STATUS_COMPLETED) {
            $draft = $draftJob->result();

            return response()->json(array_merge([
                'status' => 'success',
                'draft_status' => $draftJob->status,
                'job_id' => $draftJob->id,
            ], [
                'draft' => $draft['draft'] ?? '',
                'english_translation' => $draft['english_translation'] ?? '',
                'language' => $draft['language'] ?? '',
                'confidence' => $draft['confidence'] ?? 'low',
                'documentation_urls' => $draft['documentation_urls'] ?? [],
                'staff_notes' => $draft['staff_notes'] ?? [],
                'retrieved_documents' => $draft['retrieved_documents'] ?? [],
                'documentation_status' => $draft['documentation_status'] ?? '',
                'customer_context_status' => $draft['customer_context_status'] ?? '',
            ]));
        }

        if ($draftJob->status === DraftJob::STATUS_FAILED) {
            return response()->json([
                'status' => 'error',
                'draft_status' => $draftJob->status,
                'job_id' => $draftJob->id,
                'msg' => $draftJob->error_message ?: 'Could not draft reply.',
                'error' => [
                    'type' => $draftJob->error_type ?: 'server_error',
                    'detail' => $draftJob->error_detail ?: '',
                ],
            ], 500);
        }

        return response()->json([
            'status' => 'success',
            'draft_status' => $draftJob->status,
            'job_id' => $draftJob->id,
            'message' => $draftJob->status === DraftJob::STATUS_RUNNING ? 'Drafting...' : 'Queued...',
        ]);
    }
}
