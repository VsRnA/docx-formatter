<?php

namespace App\Domain\Document\ValueObject;

final readonly class ParseCoverage
{
    /**
     * @param  list<string>  $missingFragments
     */
    public function __construct(
        public float $coverageRatio,
        public int $sourceCharCount,
        public int $blocksCharCount,
        public bool $passesThreshold,
        public array $missingFragments = [],
    ) {}

    /**
     * @return array{
     *     coverage_ratio: float,
     *     source_char_count: int,
     *     blocks_char_count: int,
     *     passes_threshold: bool,
     *     missing_fragments: list<string>,
     *     missing_fragments_count: int
     * }
     */
    public function toArray(): array
    {
        return [
            'coverage_ratio' => $this->coverageRatio,
            'source_char_count' => $this->sourceCharCount,
            'blocks_char_count' => $this->blocksCharCount,
            'passes_threshold' => $this->passesThreshold,
            'missing_fragments' => $this->missingFragments,
            'missing_fragments_count' => count($this->missingFragments),
        ];
    }
}
