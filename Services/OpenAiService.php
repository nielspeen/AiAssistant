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

    public function getConfiguredEmbeddingModel(): string
    {
        $model = trim(\Option::get('aiassistant.documentation.embedding_model', ''));

        if ($model) {
            return $this->normalizeEmbeddingModel($model);
        }

        $providers = config('aiassistant.providers', []);
        $provider = $this->getConfiguredEmbeddingProvider();

        return $this->normalizeEmbeddingModel($providers[$provider]['embedding_model'] ?? '');
    }

    public function providerSupportsEmbeddings(): bool
    {
        $providers = config('aiassistant.providers', []);
        $provider = $this->getConfiguredEmbeddingProvider();

        return !empty($providers[$provider]['supports_embeddings']);
    }

    public function createEmbeddings(array $inputs, string $model = ''): array
    {
        if (!$this->providerSupportsEmbeddings()) {
            throw new \Exception('The selected documentation embedding provider does not support embeddings');
        }

        $model = $this->normalizeEmbeddingModel($model ?: $this->getConfiguredEmbeddingModel());

        if (!$model) {
            throw new \Exception('Embedding model is not configured');
        }

        $provider = $this->getConfiguredEmbeddingProvider();
        $apiKey = $this->getConfiguredEmbeddingApiKey();

        $response = $this->makeRequest($this->getConfiguredEmbeddingBaseUrl() . '/embeddings', [
            'input' => array_values($inputs),
            'model' => $model,
        ], $apiKey, $this->providerRequiresApiKey($provider), 'Embedding provider API key is not configured');

        if (empty($response['data']) || !is_array($response['data'])) {
            throw new \Exception('Invalid embeddings response');
        }

        $embeddings = [];

        foreach ($response['data'] as $item) {
            if (!isset($item['index']) || !isset($item['embedding']) || !is_array($item['embedding'])) {
                throw new \Exception('Invalid embeddings response item');
            }

            $embeddings[(int) $item['index']] = $item['embedding'];
        }

        ksort($embeddings);

        return array_values($embeddings);
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

    private function makeRequest(string $url, array $data, $apiKey = null, $requiresApiKey = null, string $missingKeyMessage = 'AI provider API key is not configured'): array
    {
        $apiKey = $apiKey === null ? $this->apiKey : $apiKey;
        $requiresApiKey = $requiresApiKey === null ? $this->providerRequiresApiKey($this->provider) : $requiresApiKey;

        if (!$apiKey && $requiresApiKey) {
            throw new \Exception($missingKeyMessage);
        }

        $jsonData = json_encode($data);
        $headers = [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($jsonData)
        ];

        if ($apiKey) {
            $headers[] = 'Authorization: Bearer ' . $apiKey;
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

    private function getConfiguredEmbeddingApiKey(): string
    {
        $apiKey = \Helper::decrypt(\Option::get('aiassistant.documentation.embedding_api_key', '')) ?: '';

        if ($apiKey || $this->getConfiguredEmbeddingProviderName() !== 'same') {
            return $apiKey;
        }

        return $this->apiKey;
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

    private function getConfiguredEmbeddingBaseUrl(): string
    {
        $baseUrl = trim(\Option::get('aiassistant.documentation.embedding_base_url', ''));

        if ($baseUrl) {
            return rtrim($baseUrl, '/');
        }

        if ($this->getConfiguredEmbeddingProviderName() === 'same') {
            return $this->baseUrl;
        }

        $providers = config('aiassistant.providers', []);
        $provider = $this->getConfiguredEmbeddingProvider();
        $baseUrl = $providers[$provider]['base_url'] ?? $providers['openai']['base_url'] ?? 'https://api.openai.com/v1';

        return rtrim($baseUrl, '/');
    }

    private function getConfiguredProvider(): string
    {
        $provider = strtolower(trim((string) \Option::get('aiassistant.provider', config('aiassistant.provider', 'openai'))));
        $providers = config('aiassistant.providers', []);

        if (!$provider || !array_key_exists($provider, $providers)) {
            return config('aiassistant.provider', 'openai');
        }

        return $provider;
    }

    private function getConfiguredEmbeddingProvider(): string
    {
        $embeddingProvider = $this->getConfiguredEmbeddingProviderName();

        if ($embeddingProvider === 'same') {
            return $this->provider;
        }

        return $embeddingProvider;
    }

    private function getConfiguredEmbeddingProviderName(): string
    {
        $provider = strtolower(trim((string) \Option::get(
            'aiassistant.documentation.embedding_provider',
            config('aiassistant.documentation.embedding_provider', 'same')
        )));

        if ($provider === 'same') {
            return 'same';
        }

        $providers = config('aiassistant.providers', []);

        if (!$provider || !array_key_exists($provider, $providers)) {
            return 'same';
        }

        return $provider;
    }

    private function normalizeEmbeddingModel(string $model): string
    {
        return trim($model);
    }

    private function providerRequiresApiKey(string $provider): bool
    {
        $providers = config('aiassistant.providers', []);

        return $providers[$provider]['requires_api_key'] ?? true;
    }

}
