<?php

namespace App\Services\Ai;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

final class YandexCompletionClient
{
    public function complete(string $systemPrompt, string $userPrompt, int $maxTokens = 4000, float $temperature = 0.2): string
    {
        $apiKey = config('services.yandex_ai.api_key');
        $folderId = config('services.yandex_ai.folder_id');

        if (! $apiKey || ! $folderId) {
            throw new \RuntimeException('Yandex AI credentials are not configured.');
        }

        $response = $this->client($apiKey)->post('https://llm.api.cloud.yandex.net/foundationModels/v1/completion', [
            'modelUri' => 'gpt://'.$folderId.'/'.config('services.yandex_ai.translate_model', 'yandexgpt'),
            'completionOptions' => [
                'stream' => false,
                'temperature' => $temperature,
                'maxTokens' => $maxTokens,
            ],
            'messages' => [
                ['role' => 'system', 'text' => $systemPrompt],
                ['role' => 'user', 'text' => $userPrompt],
            ],
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Yandex AI completion failed: '.$response->body());
        }

        $result = $response->json('result.alternatives.0.message.text');

        return is_string($result) ? trim($result) : '';
    }

    private function client(string $apiKey): PendingRequest
    {
        return Http::withHeaders([
            'Authorization' => 'Api-Key '.$apiKey,
            'Content-Type' => 'application/json',
        ])->timeout(120);
    }
}
