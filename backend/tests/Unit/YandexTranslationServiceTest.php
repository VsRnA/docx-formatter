<?php

namespace Tests\Unit;

use App\Infrastructure\External\Ai\Support\LanguageDetector;
use App\Infrastructure\External\Ai\Support\TranslationGlossary;
use App\Infrastructure\External\Ai\Support\TranslationResponseSanitizer;
use App\Infrastructure\External\Ai\YandexCompletionClient;
use App\Infrastructure\External\Ai\YandexTranslationService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class YandexTranslationServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.yandex_ai.api_key' => 'test-key',
            'services.yandex_ai.folder_id' => 'test-folder',
            'services.yandex_ai.translate_model' => 'yandexgpt',
            'services.translation.concurrency' => 2,
        ]);
    }

    public function test_translate_many_chunks_uses_concurrent_batch_requests(): void
    {
        Http::fake([
            'https://llm.api.cloud.yandex.net/foundationModels/v1/completion' => Http::sequence()
                ->push([
                    'result' => [
                        'alternatives' => [
                            ['message' => ['text' => '["Первый","Второй"]']],
                        ],
                    ],
                ])
                ->push([
                    'result' => [
                        'alternatives' => [
                            ['message' => ['text' => '["Третий"]']],
                        ],
                    ],
                ]),
        ]);

        $service = new YandexTranslationService(
            new YandexCompletionClient,
            new LanguageDetector,
            new TranslationGlossary,
            new TranslationResponseSanitizer,
        );

        $result = $service->translateManyChunks([
            ['First', 'Second'],
            ['Third'],
        ], 'en', 'ru');

        $this->assertSame([
            ['Первый', 'Второй'],
            ['Третий'],
        ], $result);
        Http::assertSentCount(2);
    }

    public function test_translate_many_chunks_retries_failed_batch_once(): void
    {
        Http::fake([
            'https://llm.api.cloud.yandex.net/foundationModels/v1/completion' => Http::sequence()
                ->push(['result' => ['alternatives' => [['message' => ['text' => 'not json']]]]])
                ->push([
                    'result' => [
                        'alternatives' => [
                            ['message' => ['text' => '["Перевод"]']],
                        ],
                    ],
                ]),
        ]);

        $service = new YandexTranslationService(
            new YandexCompletionClient,
            new LanguageDetector,
            new TranslationGlossary,
            new TranslationResponseSanitizer,
        );

        $result = $service->translateManyChunks([
            ['Translate me'],
        ], 'en', 'ru');

        $this->assertSame([['Перевод']], $result);
        Http::assertSentCount(2);
    }
}
