<?php

namespace Modules\AiAssistant\Jobs;

use App\Conversation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\AiAssistant\Models\DraftJob;
use Modules\AiAssistant\Services\DraftReplyService;

class DraftReplyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $draftJobId;
    public $timeout = 240;
    public $tries = 1;

    public function __construct(int $draftJobId)
    {
        $this->draftJobId = $draftJobId;
    }

    public function handle(DraftReplyService $draftReplyService)
    {
        $draftJob = DraftJob::find($this->draftJobId);

        if (!$draftJob) {
            return;
        }

        $draftJob->status = DraftJob::STATUS_RUNNING;
        $draftJob->started_at = now();
        $draftJob->save();

        try {
            $conversation = Conversation::with('customer')->findOrFail((int) $draftJob->conversation_id);
            $result = $draftReplyService->draft(
                $conversation,
                (string) $draftJob->locale,
                (int) $draftJob->document_limit
            );

            $draftJob->result = $result;
            $draftJob->status = DraftJob::STATUS_COMPLETED;
            $draftJob->completed_at = now();
            $draftJob->save();
        } catch (\Throwable $e) {
            $this->markFailed($draftJob, $e);
        }
    }

    public function failed(\Throwable $e)
    {
        $draftJob = DraftJob::find($this->draftJobId);

        if ($draftJob) {
            $this->markFailed($draftJob, $e);
        }
    }

    private function markFailed(DraftJob $draftJob, \Throwable $e): void
    {
        $draftJob->status = DraftJob::STATUS_FAILED;
        $draftJob->error_type = get_class($e);
        $draftJob->error_message = $this->friendlyErrorMessage($e);
        $draftJob->error_detail = $e->getMessage();
        $draftJob->completed_at = now();
        $draftJob->save();

        \Log::error('AI Assistant async draft reply failed', [
            'draft_job_id' => $draftJob->id,
            'conversation_id' => $draftJob->conversation_id,
            'exception' => get_class($e),
            'message' => $e->getMessage(),
        ]);
    }

    private function friendlyErrorMessage(\Throwable $e): string
    {
        $message = $e->getMessage();

        if (strpos($message, 'draft_reply') !== false || strpos($message, 'clone()') !== false) {
            return 'Draft reply prompt configuration is missing or stale. Refresh the module config/cache and try again.';
        }

        if (strpos($message, 'API key') !== false) {
            return $message;
        }

        if ($this->isProviderRequestError($message)) {
            return 'AI provider request failed. See details below.';
        }

        if (strpos($message, 'documentation embedding provider') !== false) {
            return $message;
        }

        return 'Could not draft reply: ' . $message;
    }

    private function isProviderRequestError(string $message): bool
    {
        return strpos($message, 'AI provider') !== false
            || strpos($message, 'embedding provider') !== false
            || strpos($message, 'HTTP ') !== false
            || strpos($message, 'cURL ') !== false
            || strpos($message, 'timed out') !== false;
    }
}
