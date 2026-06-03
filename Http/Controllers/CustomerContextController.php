<?php

namespace Modules\AiAssistant\Http\Controllers;

use App\Mailbox;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Modules\AiAssistant\Services\CustomerContextService;

class CustomerContextController extends Controller
{
    private $customerContextService;

    public function __construct(CustomerContextService $customerContextService)
    {
        $this->customerContextService = $customerContextService;
    }

    public function test(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'mailbox_id' => 'required|integer|exists:mailboxes,id',
            'email' => 'required|email|max:191',
            'url' => 'required|url|max:2048',
            'secret_key' => 'nullable|string|max:255',
            'signature_header' => 'nullable|in:X-FREESCOUT-SIGNATURE,X-HELPSCOUT-SIGNATURE',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'msg' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $request->only(['mailbox_id', 'email', 'url', 'secret_key', 'signature_header']);
        $mailbox = Mailbox::findOrFail((int) $data['mailbox_id']);

        try {
            $result = $this->customerContextService->test($mailbox, $data['email'], [
                'url' => $data['url'],
                'secret_key' => $data['secret_key'] ?? '',
                'signature_header' => $data['signature_header'] ?? CustomerContextService::DEFAULT_SIGNATURE_HEADER,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'msg' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'status' => 'success',
            'http_status' => $result['http_status'],
            'raw_response' => $result['body'],
            'request_payload' => $result['payload'],
            'signature_header' => $result['signature_header'],
            'signature' => $result['signature'],
        ]);
    }
}
