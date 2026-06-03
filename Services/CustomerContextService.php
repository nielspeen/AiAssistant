<?php

namespace Modules\AiAssistant\Services;

use App\Conversation;
use App\Customer;
use App\Email;
use App\Mailbox;

class CustomerContextService
{
    const DEFAULT_SIGNATURE_HEADER = 'X-FREESCOUT-SIGNATURE';
    const OPTION_GUIDANCE = 'aiassistant.customer_context_guidance';
    const OPTION_SIGNATURE_HEADER = 'aiassistant.customer_context_signature_header';
    const OPTION_SECRET_KEY = 'aiassistant.customer_context_secret_key';
    const OPTION_URL = 'aiassistant.customer_context_url';

    public function contextForConversation(Conversation $conversation): array
    {
        $mailbox = $conversation->mailbox ?: Mailbox::find((int) $conversation->mailbox_id);
        $settings = $mailbox ? self::getMailboxSettings($mailbox) : [];
        $url = trim((string) ($settings['url'] ?? ''));
        $guidance = $this->boundedGuidance($settings['guidance'] ?? '');

        if (!$url) {
            return [
                'status' => 'disabled',
                'data' => null,
                'guidance' => $guidance,
            ];
        }

        try {
            $response = $this->postJson($url, $this->payload($conversation, $mailbox), $settings);
        } catch (\Exception $e) {
            return [
                'status' => 'failed: ' . $e->getMessage(),
                'data' => null,
                'guidance' => $guidance,
            ];
        }

        return [
            'status' => 'available',
            'data' => $this->boundedData($response),
            'guidance' => $guidance,
        ];
    }

    public function test(Mailbox $mailbox, string $email, array $settings): array
    {
        $settings = [
            'url' => trim((string) ($settings['url'] ?? '')),
            'secret_key' => (string) ($settings['secret_key'] ?? ''),
            'signature_header' => self::normalizeSignatureHeader($settings['signature_header'] ?? self::DEFAULT_SIGNATURE_HEADER),
        ];

        if (!$settings['url']) {
            throw new \Exception('Customer context URL is not configured');
        }

        return $this->rawPostJson($settings['url'], $this->testPayload($mailbox, $email), $settings);
    }

    public static function getMailboxUrl(Mailbox $mailbox): string
    {
        return self::getMailboxSettings($mailbox)['url'];
    }

    public static function getMailboxSettings(Mailbox $mailbox): array
    {
        $mailboxId = (string) $mailbox->id;
        $urls = \Option::get(self::OPTION_URL) ?: [];
        $secretKeys = \Option::get(self::OPTION_SECRET_KEY) ?: [];
        $signatureHeaders = \Option::get(self::OPTION_SIGNATURE_HEADER) ?: [];
        $guidance = \Option::get(self::OPTION_GUIDANCE) ?: [];

        return [
            'url' => trim((string) ($urls[$mailboxId] ?? '')),
            'secret_key' => (string) ($secretKeys[$mailboxId] ?? ''),
            'signature_header' => self::normalizeSignatureHeader($signatureHeaders[$mailboxId] ?? self::DEFAULT_SIGNATURE_HEADER),
            'guidance' => trim((string) ($guidance[$mailboxId] ?? '')),
        ];
    }

    public static function setMailboxSettings(Mailbox $mailbox, array $settings): void
    {
        $mailboxId = (string) $mailbox->id;
        $urls = \Option::get(self::OPTION_URL) ?: [];
        $secretKeys = \Option::get(self::OPTION_SECRET_KEY) ?: [];
        $signatureHeaders = \Option::get(self::OPTION_SIGNATURE_HEADER) ?: [];
        $guidance = \Option::get(self::OPTION_GUIDANCE) ?: [];

        $urls[$mailboxId] = trim((string) ($settings['url'] ?? ''));
        $secretKeys[$mailboxId] = (string) ($settings['secret_key'] ?? '');
        $signatureHeaders[$mailboxId] = self::normalizeSignatureHeader($settings['signature_header'] ?? self::DEFAULT_SIGNATURE_HEADER);
        $guidance[$mailboxId] = trim((string) ($settings['guidance'] ?? ''));

        \Option::set(self::OPTION_URL, $urls);
        \Option::set(self::OPTION_SECRET_KEY, $secretKeys);
        \Option::set(self::OPTION_SIGNATURE_HEADER, $signatureHeaders);
        \Option::set(self::OPTION_GUIDANCE, $guidance);
    }

    private function payload(Conversation $conversation, ?Mailbox $mailbox): array
    {
        $customer = $conversation->customer;
        $emails = $this->customerEmails($conversation);

        return [
            'event' => 'draft_reply_context',
            'mailbox' => [
                'id' => $mailbox ? (int) $mailbox->id : (int) $conversation->mailbox_id,
                'name' => $mailbox ? $mailbox->name : '',
                'email' => $mailbox ? $mailbox->email : '',
            ],
            'conversation' => [
                'id' => (int) $conversation->id,
                'number' => (int) $conversation->number,
                'subject' => $conversation->subject,
                'customer_email' => $conversation->customer_email,
            ],
            'customer' => [
                'id' => $customer ? (int) $customer->id : null,
                'name' => $customer ? $customer->getFullName(true, true) : '',
                'emails' => $emails,
            ],
            'emails' => $emails,
        ];
    }

    private function testPayload(Mailbox $mailbox, string $email): array
    {
        $customer = Customer::getByEmail($email);
        $emails = $customer ? $this->customerEmailsForCustomer($customer, $email) : [$email];

        return [
            'event' => 'draft_reply_context',
            'test' => true,
            'mailbox' => [
                'id' => (int) $mailbox->id,
                'name' => $mailbox->name,
                'email' => $mailbox->email,
            ],
            'conversation' => [
                'id' => null,
                'number' => null,
                'subject' => 'Customer context test',
                'customer_email' => $email,
            ],
            'customer' => [
                'id' => $customer ? (int) $customer->id : null,
                'name' => $customer ? $customer->getFullName(true, true) : '',
                'emails' => $emails,
            ],
            'emails' => $emails,
        ];
    }

    private function customerEmails(Conversation $conversation): array
    {
        $emails = [];

        if ($conversation->customer) {
            foreach ($conversation->customer->emails as $email) {
                if (!empty($email->email)) {
                    $emails[] = $email->email;
                }
            }
        }

        foreach (explode(',', (string) $conversation->customer_email) as $email) {
            $email = Email::sanitizeEmail(trim($email));

            if ($email) {
                $emails[] = $email;
            }
        }

        return array_values(array_unique($emails));
    }

    private function customerEmailsForCustomer(Customer $customer, string $fallbackEmail): array
    {
        $emails = [];

        foreach ($customer->emails as $email) {
            if (!empty($email->email)) {
                $emails[] = $email->email;
            }
        }

        $fallbackEmail = Email::sanitizeEmail($fallbackEmail);

        if ($fallbackEmail) {
            $emails[] = $fallbackEmail;
        }

        return array_values(array_unique($emails));
    }

    public function generateSignature(string $data, string $secret): string
    {
        return base64_encode(hash_hmac('sha1', $data, $secret, true));
    }

    private function postJson(string $url, array $payload, array $settings): array
    {
        $result = $this->rawPostJson($url, $payload, $settings);
        $response = $result['body'];

        if ($result['http_status'] < 200 || $result['http_status'] >= 300) {
            throw new \Exception('HTTP error: ' . $result['http_status']);
        }

        $maxBytes = (int) config('aiassistant.customer_context.max_response_bytes', 131072);

        if ($maxBytes > 0 && strlen((string) $response) > $maxBytes) {
            throw new \Exception('JSON response is too large');
        }

        $decoded = json_decode((string) $response, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            throw new \Exception('Invalid JSON response: ' . json_last_error_msg());
        }

        return $decoded;
    }

    private function rawPostJson(string $url, array $payload, array $settings): array
    {
        $jsonData = json_encode($payload);
        $signatureHeader = self::normalizeSignatureHeader($settings['signature_header'] ?? self::DEFAULT_SIGNATURE_HEADER);
        $signature = $this->generateSignature($jsonData, (string) ($settings['secret_key'] ?? ''));

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $jsonData,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
                'Content-Length: ' . strlen($jsonData),
                'User-Agent: FreeScout-AI-Assistant/1.0',
                $signatureHeader . ': ' . $signature,
            ],
            CURLOPT_TIMEOUT => (int) config('aiassistant.customer_context.timeout', 15),
            CURLOPT_CONNECTTIMEOUT => (int) config('aiassistant.customer_context.connect_timeout', 5),
            CURLOPT_PROXY => config('app.proxy'),
            CURLOPT_SSL_VERIFYPEER => config('app.curl_ssl_verifypeer'),
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        if ($error) {
            throw new \Exception('cURL error: ' . $error);
        }

        return [
            'body' => (string) $response,
            'http_status' => (int) $httpCode,
            'payload' => $jsonData,
            'signature_header' => $signatureHeader,
            'signature' => $signature,
        ];
    }

    private function boundedData(array $data)
    {
        $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $limit = (int) config('aiassistant.customer_context.max_prompt_chars', 12000);

        if ($limit > 0 && mb_strlen($json) > $limit) {
            return [
                'truncated' => true,
                'json_excerpt' => mb_substr($json, 0, $limit - 3) . '...',
            ];
        }

        return $data;
    }

    private function boundedGuidance(string $guidance): string
    {
        $limit = (int) config('aiassistant.customer_context.max_guidance_chars', 6000);

        if ($limit > 0 && mb_strlen($guidance) > $limit) {
            return mb_substr($guidance, 0, $limit - 3) . '...';
        }

        return $guidance;
    }

    public static function normalizeSignatureHeader($header): string
    {
        $header = trim((string) $header);

        if (!in_array($header, ['X-FREESCOUT-SIGNATURE', 'X-HELPSCOUT-SIGNATURE'])) {
            return self::DEFAULT_SIGNATURE_HEADER;
        }

        return $header;
    }
}
