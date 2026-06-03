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

    public function update(Request $request, $mailboxId)
    {
        $mailbox = Mailbox::findOrFail((int) $mailboxId);
        $errorPrefix = 'aiassistant_customer_context_' . $mailbox->id . '_';

        $validator = \Validator::make($request->all(), [
            'url' => [
                'max:2048',
                function ($attribute, $value, $fail) {
                    $value = trim((string) $value);

                    if ($value !== '' && filter_var($value, FILTER_VALIDATE_URL) === false) {
                        $fail(__('Enter a valid callback URL, or leave it blank.'));
                    }
                },
            ],
            'secret_key' => 'nullable|string|max:255',
            'signature_header' => 'required|in:X-FREESCOUT-SIGNATURE,X-HELPSCOUT-SIGNATURE',
            'guidance' => 'nullable|string|max:6000',
        ]);

        if ($validator->fails()) {
            $errors = [];

            foreach ($validator->errors()->toArray() as $field => $messages) {
                $errors[$errorPrefix . $field] = $messages;
            }

            return redirect()
                ->route('settings', ['section' => 'aiassistant'])
                ->withErrors($errors)
                ->withInput($request->all() + ['aiassistant_customer_context_mailbox_id' => $mailbox->id]);
        }

        CustomerContextService::setMailboxSettings($mailbox, [
            'url' => trim((string) $request->input('url', '')),
            'secret_key' => (string) $request->input('secret_key', ''),
            'signature_header' => (string) $request->input('signature_header', CustomerContextService::DEFAULT_SIGNATURE_HEADER),
            'guidance' => trim((string) $request->input('guidance', '')),
        ]);

        \Session::flash('flash_success_floating', __('Customer context settings saved for :mailbox.', ['mailbox' => htmlspecialchars($mailbox->name)]));

        return redirect()->route('settings', ['section' => 'aiassistant']);
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
