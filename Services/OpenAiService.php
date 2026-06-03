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

    public function createEmbeddings(array $inputs, string $model = '', string $timeoutProfile = 'embedding'): array
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
        ], $apiKey, $this->providerRequiresApiKey($provider), 'Embedding provider API key is not configured', $this->requestOptionsForProfile($timeoutProfile));

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

    private function makeRequest(string $url, array $data, $apiKey = null, $requiresApiKey = null, string $missingKeyMessage = 'AI provider API key is not configured', array $requestOptions = []): array
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

        $timeout = (int) ($requestOptions['timeout'] ?? config('aiassistant.provider_request.timeout', 45));
        $connectTimeout = (int) ($requestOptions['connect_timeout'] ?? config('aiassistant.provider_request.connect_timeout', 15));
        $maxAttempts = max(1, (int) ($requestOptions['max_attempts'] ?? 1));
        $retryDelayMs = max(0, (int) ($requestOptions['retry_delay_ms'] ?? 0));

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $ch = curl_init();

            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $jsonData,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_CONNECTTIMEOUT => $connectTimeout,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_USERAGENT => 'FreeScout-AI-Assistant/1.0'
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $totalTime = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
            $error = curl_error($ch);
            $errno = curl_errno($ch);

            if ($error) {
                $message = $this->formatCurlError($url, $errno, $error, $totalTime, $timeout);

                if ($attempt < $maxAttempts && $this->shouldRetryCurlError((int) $errno)) {
                    $this->logProviderRetry($url, $attempt, $maxAttempts, $message);
                    $this->sleepBeforeRetry($retryDelayMs, $attempt);
                    continue;
                }

                throw new \Exception($this->withAttemptSummary($message, $attempt, $maxAttempts));
            }

            if ($httpCode < 200 || $httpCode >= 300) {
                $message = $this->formatHttpError($url, (int) $httpCode, (string) $response, $totalTime);

                if ($attempt < $maxAttempts && $this->shouldRetryHttpCode((int) $httpCode)) {
                    $this->logProviderRetry($url, $attempt, $maxAttempts, $message);
                    $this->sleepBeforeRetry($retryDelayMs, $attempt);
                    continue;
                }

                throw new \Exception($this->withAttemptSummary($message, $attempt, $maxAttempts));
            }

            break;
        }

        $decodedResponse = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Invalid JSON response: ' . json_last_error_msg());
        }

        return $decodedResponse;
    }

    private function requestOptionsForProfile(string $profile): array
    {
        if ($profile === 'embedding_search') {
            return [
                'timeout' => (int) config('aiassistant.provider_request.embedding_search_timeout', 12),
                'connect_timeout' => (int) config('aiassistant.provider_request.embedding_search_connect_timeout', 5),
                'max_attempts' => (int) config('aiassistant.provider_request.embedding_search_attempts', 3),
                'retry_delay_ms' => (int) config('aiassistant.provider_request.embedding_search_retry_delay_ms', 750),
            ];
        }

        if ($profile === 'embedding') {
            return [
                'timeout' => (int) config('aiassistant.provider_request.embedding_timeout', 60),
                'connect_timeout' => (int) config('aiassistant.provider_request.embedding_connect_timeout', 10),
                'max_attempts' => (int) config('aiassistant.provider_request.embedding_attempts', 3),
                'retry_delay_ms' => (int) config('aiassistant.provider_request.embedding_retry_delay_ms', 1000),
            ];
        }

        return [];
    }

    private function formatCurlError(string $url, int $errno, string $error, $totalTime, int $timeout): string
    {
        $elapsed = $this->formatElapsedTime($totalTime);
        $provider = $this->providerLabelForUrl($url);

        if (in_array($errno, [CURLE_OPERATION_TIMEDOUT, CURLE_COULDNT_CONNECT])) {
            return "{$provider} request timed out after {$elapsed}. Configured timeout: {$timeout}s. cURL {$errno}: {$error}";
        }

        return "{$provider} request failed after {$elapsed}. cURL {$errno}: {$error}";
    }

    private function shouldRetryCurlError(int $errno): bool
    {
        return in_array($errno, [
            CURLE_OPERATION_TIMEDOUT,
            CURLE_COULDNT_CONNECT,
            CURLE_COULDNT_RESOLVE_HOST,
            CURLE_COULDNT_RESOLVE_PROXY,
            CURLE_GOT_NOTHING,
            CURLE_RECV_ERROR,
            CURLE_SEND_ERROR,
        ]);
    }

    private function shouldRetryHttpCode(int $httpCode): bool
    {
        return in_array($httpCode, [408, 425, 429, 500, 502, 503, 504]);
    }

    private function sleepBeforeRetry(int $retryDelayMs, int $attempt): void
    {
        if ($retryDelayMs <= 0) {
            return;
        }

        usleep(min(5000, $retryDelayMs * $attempt) * 1000);
    }

    private function logProviderRetry(string $url, int $attempt, int $maxAttempts, string $message): void
    {
        \Log::warning('AI Assistant provider request retrying', [
            'provider' => $this->providerLabelForUrl($url),
            'attempt' => $attempt,
            'max_attempts' => $maxAttempts,
            'message' => $message,
        ]);
    }

    private function withAttemptSummary(string $message, int $attempt, int $maxAttempts): string
    {
        if ($maxAttempts <= 1) {
            return $message;
        }

        return $message . " Attempts: {$attempt}/{$maxAttempts}.";
    }

    private function formatHttpError(string $url, int $httpCode, string $response, $totalTime): string
    {
        $provider = $this->providerLabelForUrl($url);
        $elapsed = $this->formatElapsedTime($totalTime);
        $reason = $this->httpReason($httpCode);
        $body = $this->responseExcerpt($response);
        $message = "{$provider} returned HTTP {$httpCode}";

        if ($reason) {
            $message .= " ({$reason})";
        }

        $message .= " after {$elapsed}.";

        if ($body !== '') {
            $message .= " Response: {$body}";
        }

        return $message;
    }

    private function providerLabelForUrl(string $url): string
    {
        if (strpos($url, '/embeddings') !== false && $this->getConfiguredEmbeddingProviderName() !== 'same') {
            return $this->providerName($this->getConfiguredEmbeddingProvider()) . ' embedding provider';
        }

        return $this->providerName($this->provider) . ' AI provider';
    }

    private function providerName(string $provider): string
    {
        $providers = config('aiassistant.providers', []);
        $name = $providers[$provider]['name'] ?? $provider;

        return $name ?: 'AI provider';
    }

    private function formatElapsedTime($seconds): string
    {
        return number_format((float) $seconds, 2) . 's';
    }

    private function responseExcerpt(string $response): string
    {
        $response = trim(strip_tags($response));
        $response = preg_replace('/\s+/', ' ', $response);
        $limit = (int) config('aiassistant.provider_request.max_error_body_chars', 2000);

        if ($limit > 0 && mb_strlen($response) > $limit) {
            return mb_substr($response, 0, $limit - 3) . '...';
        }

        return $response;
    }

    private function httpReason(int $httpCode): string
    {
        $reasons = [
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            408 => 'Request Timeout',
            409 => 'Conflict',
            413 => 'Payload Too Large',
            429 => 'Rate Limited',
            500 => 'Internal Server Error',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
            504 => 'Gateway Timeout',
        ];

        return $reasons[$httpCode] ?? '';
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
