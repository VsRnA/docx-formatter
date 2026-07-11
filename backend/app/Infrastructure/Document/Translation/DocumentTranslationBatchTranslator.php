<?php

namespace App\Infrastructure\Document\Translation;

use App\Domain\Docx\Port\TranslatorPort;
use App\Infrastructure\External\Ai\Support\LanguageDetector;
use App\Models\Document;

final class DocumentTranslationBatchTranslator
{
    public function __construct(
        private readonly TranslatorPort $translator,
        private readonly TranslationCacheStore $cache,
        private readonly LanguageDetector $languageDetector,
    ) {}

    /**
     * @param  list<array{blockIndex: int, segmentId: int, text: string, translatable: bool}>  $units
     * @return array<string, string>
     */
    public function translateAll(Document $document, array $units): array
    {
        $from = $document->language_from;
        $to = $document->language_to;

        $uniqueTexts = $this->collectUniqueTexts($units, $to);
        if ($uniqueTexts === []) {
            return [];
        }

        $results = [];
        $misses = [];

        foreach ($uniqueTexts as $text) {
            $cached = $this->cache->get($text, $from, $to);
            if ($cached !== null) {
                $results[$text] = $cached;

                continue;
            }

            $misses[] = $text;
        }

        if ($misses !== []) {
            $chunks = $this->chunkTexts($misses);
            $translatedChunks = $this->translator->translateManyChunks($chunks, $from, $to);

            foreach ($misses as $index => $text) {
                [$chunkIndex, $positionInChunk] = $this->locateInChunks($index, $chunks);
                $translated = trim((string) ($translatedChunks[$chunkIndex][$positionInChunk] ?? ''));
                if ($translated === '') {
                    $translated = $text;
                }

                $results[$text] = $translated;
                $this->cache->put($text, $from, $to, $translated);
            }
        }

        return $results;
    }

    /**
     * @param  list<array{blockIndex: int, segmentId: int, text: string, translatable: bool}>  $units
     * @return list<string>
     */
    private function collectUniqueTexts(array $units, string $targetLang): array
    {
        $seen = [];
        $unique = [];

        foreach ($units as $unit) {
            if (! ($unit['translatable'] ?? true)) {
                continue;
            }

            $text = TranslationCacheStore::normalizeTextKey((string) ($unit['text'] ?? ''));
            if ($text === '' || isset($seen[$text])) {
                continue;
            }

            if ($this->languageDetector->shouldSkipTranslation($text, $targetLang)) {
                continue;
            }

            $seen[$text] = true;
            $unique[] = $text;
        }

        return $unique;
    }

    /**
     * @param  list<string>  $texts
     * @return list<list<string>>
     */
    private function chunkTexts(array $texts): array
    {
        $batchSize = max(1, (int) config('services.translation.batch_size', 30));
        $charBudget = max(1, (int) config('services.translation.batch_char_budget', 6000));

        $chunks = [];
        $current = [];
        $currentChars = 0;

        foreach ($texts as $text) {
            $length = mb_strlen($text);

            if ($current !== [] && (count($current) >= $batchSize || $currentChars + $length > $charBudget)) {
                $chunks[] = $current;
                $current = [];
                $currentChars = 0;
            }

            $current[] = $text;
            $currentChars += $length;
        }

        if ($current !== []) {
            $chunks[] = $current;
        }

        return $chunks;
    }

    /**
     * @param  list<list<string>>  $chunks
     * @return array{0: int, 1: int}
     */
    private function locateInChunks(int $flatIndex, array $chunks): array
    {
        $offset = 0;

        foreach ($chunks as $chunkIndex => $chunk) {
            $count = count($chunk);
            if ($flatIndex < $offset + $count) {
                return [$chunkIndex, $flatIndex - $offset];
            }

            $offset += $count;
        }

        throw new \RuntimeException(sprintf(
            'Unable to locate translated text index %d in %d chunk(s)',
            $flatIndex,
            count($chunks),
        ));
    }
}
