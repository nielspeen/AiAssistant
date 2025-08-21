<?php

namespace Modules\AiAssistant\Services;

class OpenAiService
{
    private $apiKey;
    private $baseUrl = 'https://api.openai.com/v1';

    public function __construct()
    {
        $this->apiKey = config('aiassistant.api_key');
    }

    public function sendResponseRequest(object $content, string $model, int $maxTokens, object $textFormat): array
    {
        $url = $this->baseUrl . '/responses';

        $data = [
            'input' => json_encode($content, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'instructions' => 'You are a helpful assistant part of a support ticketing system.',
            'max_output_tokens' => $maxTokens,
            'model' => $model,
            'text' => (object) [
                'format' => $textFormat,
            ],
        ];

        return $this->makeRequest($url, $data);
    }

    private function makeRequest(string $url, array $data): array
    {
        if (!$this->apiKey) {
            throw new \Exception('OpenAI API key is not configured');
        }

        $jsonData = json_encode($data);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $jsonData,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Length: ' . strlen($jsonData)
            ],
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

        if ($httpCode !== 200) {
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

}
