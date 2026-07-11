<?php

namespace App\Infrastructure\External\Ai;

use App\Domain\Shared\Port\CompletionPort;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class YandexCompletionClient implements CompletionPort
{
    /**
     * @param  list<array{systemPrompt: string, userPrompt: string, maxTokens?: int, temperature?: float}>  $requests
     * @return list<string>
     */
    public function completeBatch(array $requests, int $concurrency = 3): array
    {
        if ($requests === []) {
            return [];
        }

        $apiKey = config('services.yandex_ai.api_key');
        $folderId = config('services.yandex_ai.folder_id');

        if (! $apiKey || ! $folderId) {
            throw new \RuntimeException('Yandex AI credentials are not configured.');
        }

        $results = array_fill(0, count($requests), '');
        $concurrency = max(1, $concurrency);

        foreach (array_chunk($requests, $concurrency, true) as $batch) {
            $responses = Http::pool(function ($pool) use ($batch, $apiKey, $folderId) {
                foreach ($batch as $index => $request) {
                    $pool->as((string) $index)
                        ->withHeaders([
                            'Authorization' => 'Api-Key '.$apiKey,
                            'Content-Type' => 'application/json',
                        ])
                        ->timeout(120)
                        ->post('https://llm.api.cloud.yandex.net/foundationModels/v1/completion', [
                            'modelUri' => 'gpt://'.$folderId.'/'.config('services.yandex_ai.translate_model', 'yandexgpt'),
                            'completionOptions' => [
                                'stream' => false,
                                'temperature' => $request['temperature'] ?? 0.2,
                                'maxTokens' => $request['maxTokens'] ?? 4000,
                            ],
                            'messages' => [
                                ['role' => 'system', 'text' => $request['systemPrompt']],
                                ['role' => 'user', 'text' => $request['userPrompt']],
                            ],
                        ]);
                }
            });

            foreach ($batch as $index => $request) {
                $response = $responses[(string) $index] ?? null;
                if ($response === null || ! $response->successful()) {
                    Log::warning('Yandex AI batch completion failed', [
                        'index' => $index,
                        'status' => $response?->status(),
                        'body' => $response?->body(),
                    ]);

                    continue;
                }

                $result = $response->json('result.alternatives.0.message.text');
                $results[$index] = is_string($result) ? trim($result) : '';
            }
        }

        return $results;
    }

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
