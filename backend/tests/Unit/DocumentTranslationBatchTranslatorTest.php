<?php

namespace Tests\Unit;

use App\Domain\Docx\Port\TranslatorPort;
use App\Infrastructure\Document\Translation\DocumentTranslationBatchTranslator;
use App\Infrastructure\Document\Translation\TranslationCacheStore;
use App\Infrastructure\External\Ai\Support\LanguageDetector;
use App\Models\Document;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class DocumentTranslationBatchTranslatorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config([
            'services.translation.batch_size' => 2,
            'services.translation.batch_char_budget' => 20,
            'services.translation.cache_enabled' => true,
            'services.translation.cache_ttl_days' => 1,
            'services.yandex_ai.translate_model' => 'yandexgpt',
        ]);
    }

    public function test_deduplicates_identical_text_into_one_translation_call(): void
    {
        $translator = new FakeChunkTranslator;
        $service = new DocumentTranslationBatchTranslator(
            $translator,
            new TranslationCacheStore,
            new LanguageDetector,
        );

        $document = new Document;
        $document->language_from = 'en';
        $document->language_to = 'ru';

        $units = [
            ['blockIndex' => 0, 'segmentId' => 0, 'text' => 'Hello', 'translatable' => true],
            ['blockIndex' => 1, 'segmentId' => 0, 'text' => 'Hello', 'translatable' => true],
            ['blockIndex' => 2, 'segmentId' => 0, 'text' => 'World', 'translatable' => true],
        ];

        $result = $service->translateAll($document, $units);

        $this->assertSame(['Hello' => 'RU:Hello', 'World' => 'RU:World'], $result);
        $this->assertCount(1, $translator->calls);
        $this->assertSame(['Hello', 'World'], $translator->calls[0]);
    }

    public function test_splits_texts_into_configured_chunks(): void
    {
        $translator = new FakeChunkTranslator;
        $service = new DocumentTranslationBatchTranslator(
            $translator,
            new TranslationCacheStore,
            new LanguageDetector,
        );

        $document = new Document;
        $document->language_from = 'en';
        $document->language_to = 'ru';

        $units = [
            ['blockIndex' => 0, 'segmentId' => 0, 'text' => 'One', 'translatable' => true],
            ['blockIndex' => 1, 'segmentId' => 0, 'text' => 'Two', 'translatable' => true],
            ['blockIndex' => 2, 'segmentId' => 0, 'text' => 'Three', 'translatable' => true],
        ];

        $service->translateAll($document, $units);

        $this->assertCount(2, $translator->calls);
        $this->assertSame(['One', 'Two'], $translator->calls[0]);
        $this->assertSame(['Three'], $translator->calls[1]);
    }

    public function test_uses_cache_for_repeated_text(): void
    {
        $cache = new TranslationCacheStore;
        $cache->put('Cached phrase', 'en', 'ru', 'RU:Cached phrase');

        $translator = new FakeChunkTranslator;
        $service = new DocumentTranslationBatchTranslator(
            $translator,
            $cache,
            new LanguageDetector,
        );

        $document = new Document;
        $document->language_from = 'en';
        $document->language_to = 'ru';

        $units = [
            ['blockIndex' => 0, 'segmentId' => 0, 'text' => 'Cached phrase', 'translatable' => true],
            ['blockIndex' => 1, 'segmentId' => 0, 'text' => 'Fresh phrase', 'translatable' => true],
        ];

        $result = $service->translateAll($document, $units);

        $this->assertSame('RU:Cached phrase', $result['Cached phrase']);
        $this->assertSame('RU:Fresh phrase', $result['Fresh phrase']);
        $this->assertCount(1, $translator->calls);
        $this->assertSame(['Fresh phrase'], $translator->calls[0]);
    }

    public function test_skips_non_translatable_and_target_language_text(): void
    {
        $translator = new FakeChunkTranslator;
        $service = new DocumentTranslationBatchTranslator(
            $translator,
            new TranslationCacheStore,
            new LanguageDetector,
        );

        $document = new Document;
        $document->language_from = 'en';
        $document->language_to = 'ru';

        $units = [
            ['blockIndex' => 0, 'segmentId' => 0, 'text' => '12345', 'translatable' => true],
            ['blockIndex' => 1, 'segmentId' => 0, 'text' => 'Привет мир', 'translatable' => true],
            ['blockIndex' => 2, 'segmentId' => 0, 'text' => 'Skip me', 'translatable' => false],
        ];

        $result = $service->translateAll($document, $units);

        $this->assertSame([], $result);
        $this->assertSame([], $translator->calls);
    }
}

final class FakeChunkTranslator implements TranslatorPort
{
    /** @var list<list<string>> */
    public array $calls = [];

    public function translate(string $text, string $from = 'en', string $to = 'ru'): string
    {
        return 'RU:'.$text;
    }

    public function translateMany(array $texts, string $from = 'en', string $to = 'ru'): array
    {
        return array_map(fn (string $text): string => $this->translate($text, $from, $to), $texts);
    }

    public function translateManyChunks(array $chunks, string $from = 'en', string $to = 'ru'): array
    {
        $this->calls = $chunks;

        return array_map(
            fn (array $chunk): array => array_map(
                fn (string $text): string => $this->translate($text, $from, $to),
                $chunk,
            ),
            $chunks,
        );
    }
}
