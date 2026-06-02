<?php

namespace Modules\AiAssistant\Services;

class OpenAiService
{
    private $apiKey;
    private $baseUrl;
    private $provider;

    public function __construct()
    {
        $this->provider = $this->getConfiguredProvider();
        $this->apiKey = $this->getConfiguredApiKey();
        $this->baseUrl = $this->getConfiguredBaseUrl();
    }

    public function getConfiguredModel(): string
    {
        return trim(\Option::get('aiassistant.model', '')) ?: config('aiassistant.model');
    }

    public function sendResponseRequest(object $content, string $model, int $maxTokens, object $textFormat): array
    {
        $url = $this->baseUrl . '/chat/completions';

        $data = [
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are a helpful assistant part of a support ticketing system. Return only valid JSON matching the requested schema.',
                ],
                [
                    'role' => 'user',
                    'content' => json_encode($content, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                ],
            ],
            'max_tokens' => $maxTokens,
            'model' => $model,
            'response_format' => $this->chatCompletionResponseFormat($textFormat),
        ];

        $response = $this->makeRequest($url, $data);

        return [
            'status' => 'completed',
            'output' => [
                [
                    'type' => 'message',
                    'content' => [
                        [
                            'text' => $this->extractContent($response),
                        ],
                    ],
                ],
            ],
        ];
    }

    private function makeRequest(string $url, array $data): array
    {
        if (!$this->apiKey && $this->providerRequiresApiKey()) {
            throw new \Exception('AI provider API key is not configured');
        }

        $jsonData = json_encode($data);
        $headers = [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($jsonData)
        ];

        if ($this->apiKey) {
            $headers[] = 'Authorization: Bearer ' . $this->apiKey;
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $jsonData,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_CONNECTTIMEOUT => 120,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT => 'FreeScout-AI-Assistant/1.0'
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);

        if ($error) {
            throw new \Exception('cURL error: ' . $error);
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            throw new \Exception('HTTP error: ' . $httpCode . ' - ' . $response);
        }

        $decodedResponse = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Invalid JSON response: ' . json_last_error_msg());
        }

        return $decodedResponse;
    }

    public function extractContent(array $response): string
    {
        if (isset($response['choices'][0]['message']['content'])) {
            return $response['choices'][0]['message']['content'];
        }

        return '';
    }

    private function chatCompletionResponseFormat(object $textFormat): array
    {
        return [
            'type' => 'json_schema',
            'json_schema' => [
                'name' => $textFormat->name,
                'strict' => $textFormat->strict,
                'schema' => $textFormat->schema,
            ],
        ];
    }

    private function getConfiguredApiKey(): string
    {
        return \Helper::decrypt(\Option::get('aiassistant.api_key', '')) ?: '';
    }

    private function getConfiguredBaseUrl(): string
    {
        $baseUrl = trim(\Option::get('aiassistant.base_url', ''));

        if (!$baseUrl) {
            $providers = config('aiassistant.providers', []);
            $baseUrl = $providers[$this->provider]['base_url'] ?? $providers['openai']['base_url'] ?? 'https://api.openai.com/v1';
        }

        return rtrim($baseUrl, '/');
    }

    private function getConfiguredProvider(): string
    {
        return \Option::get('aiassistant.provider', config('aiassistant.provider', 'openai'));
    }

    private function providerRequiresApiKey(): bool
    {
        $providers = config('aiassistant.providers', []);

        return $providers[$this->provider]['requires_api_key'] ?? true;
    }

}
