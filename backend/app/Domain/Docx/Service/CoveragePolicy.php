<?php

namespace App\Domain\Docx\Service;

use App\Domain\Document\ValueObject\ParseCoverage;

final class CoveragePolicy
{
    /**
     * @param  list<string>  $missingFragments
     */
    public function evaluate(
        string $sourceText,
        string $blocksText,
        float $threshold,
        array $missingFragments = [],
    ): ParseCoverage {
        $sourceLen = mb_strlen($sourceText);
        $blocksLen = mb_strlen($blocksText);

        if ($sourceLen === 0) {
            return new ParseCoverage(1.0, 0, $blocksLen, true, $missingFragments);
        }

        $ratio = min(1.0, $blocksLen / $sourceLen);

        return new ParseCoverage(
            round($ratio, 4),
            $sourceLen,
            $blocksLen,
            $ratio >= $threshold,
            $missingFragments,
        );
    }
}
